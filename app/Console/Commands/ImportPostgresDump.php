<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ImportPostgresDump extends Command
{
    use ConfirmableTrait;

    protected $signature = 'db:import-pg
                            {path : Path to the plain SQL dump file to import}
                            {--connection= : Database connection name from config/database.php}
                            {--psql= : Full path to the psql binary if not in PATH}
                            {--no-output : Don\'t stream psql output to console}
                            {--strip-set=* : Strip specific SET directives by name (repeatable, e.g. --strip-set=transaction_timeout)}
                            {--auto-strip-unrecognized : Retry once by stripping any unrecognized configuration parameters found in psql errors}
                            {--maintenance-db= : Initial database to connect to when dump contains DROP/CREATE DATABASE (e.g., postgres or template1)}
                            {--strip-db-ddl : Strip DROP/CREATE DATABASE and \\connect directives from the dump}
                            {--force : Force the operation to run when in production}
                            {--terminate-connections : Before running the dump, block new connections to the target DB and terminate existing ones}
                            {--no-unblock : If set with --terminate-connections, don’t re-enable ALLOW_CONNECTIONS after import}';

    protected $description = 'Import a PostgreSQL plain SQL dump (schema + data) into the configured database';

    protected array $tempFiles = [];

    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        $path = $this->argument('path');
        if (! is_string($path) || $path === '') {
            $this->error('You must provide the path to a SQL file.');

            return 1;
        }

        $path = trim($path, "\"'");
        $originalPath = realpath($path) ?: $path;

        if (! file_exists($originalPath)) {
            $this->error("File not found: {$originalPath}");

            return 1;
        }
        if (! is_readable($originalPath)) {
            $this->error("File is not readable: {$originalPath}");

            return 1;
        }

        $lower = strtolower($originalPath);
        if (! Str::endsWith($lower, ['.sql'])) {
            $this->warn('This does not look like a plain .sql dump. If it is a custom-format dump (.dump/.backup), use pg_restore instead of psql.');
        }

        $connectionName = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$connectionName}");

        if (! $config) {
            $this->error("Database connection [{$connectionName}] is not defined.");

            return 1;
        }
        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error("Connection [{$connectionName}] is not a PostgreSQL connection.");

            return 1;
        }

        $database = $config['database'] ?? null;
        $host = $config['host'] ?? null;
        $port = (string) ($config['port'] ?? 5432);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        if (! $database) {
            $this->error('No database name found in the selected connection.');

            return 1;
        }

        $psql = $this->option('psql') ?: 'psql';

        // Check psql availability
        try {
            $check = new Process([$psql, '--version']);
            $check->setTimeout(10);
            $check->run();
            if ($check->getExitCode() !== 0) {
                $this->error("Unable to run `{$psql}`. Ensure psql is installed and in PATH, or supply --psql=/full/path/to/psql");

                return 1;
            }
        } catch (\Throwable $e) {
            $this->error("Failed to execute psql: {$e->getMessage()}");

            return 1;
        }

        // Prepare environment (PGPASSWORD)
        $env = null;
        if ($password !== null && $password !== '') {
            $env = array_merge($_ENV, $_SERVER, ['PGPASSWORD' => $password]);
        }

        // Begin with the original file path
        $currentPath = $originalPath;

        // Optionally strip specified SET directives
        $stripSet = (array) $this->option('strip-set');
        $stripSet = array_values(array_filter(array_map(fn ($s) => strtolower(trim((string) $s)), $stripSet)));
        if (! empty($stripSet)) {
            [$currentPath, $removed] = $this->sanitizeDumpSetDirectives($currentPath, $stripSet);
            if ($currentPath !== $originalPath) {
                $this->info('Created sanitized copy of dump (stripped SET directives):');
                foreach ($removed as $name => $count) {
                    $this->line("- {$name}: removed {$count} line(s)");
                }
            } else {
                $this->line('No matching SET directives found to strip.');
            }
        }

        // Optionally strip DB-level DDL (DROP/CREATE DATABASE and \connect)
        if ($this->option('strip-db-ddl')) {
            [$currentPath, $dbRemoved] = $this->sanitizeDumpDbDDL($currentPath);
            $this->info('Stripped DB-level DDL from dump:');
            foreach ($dbRemoved as $k => $cnt) {
                if ($cnt > 0) {
                    $this->line("- {$k}: removed {$cnt} line(s)");
                }
            }
        }

        // Inspect dump for DB-level operations (unless we stripped them)
        $intents = $this->option('strip-db-ddl') ? ['drops' => [], 'creates' => [], 'connects' => []] : $this->inspectDumpDbStatements($currentPath);

        // Choose initial database (-d) for psql
        $initialDb = $database;
        $maintenanceDbOpt = $this->option('maintenance-db');

        if (! $this->option('strip-db-ddl')) {
            // If the dump will drop/create the target database, don't connect to it initially.
            $targets = array_unique(array_map('strtolower', array_merge($intents['drops'], $intents['creates'])));
            $dbLower = strtolower($database);

            if (in_array($dbLower, $targets, true)) {
                $initialDb = $maintenanceDbOpt ?: 'postgres';

                if (strtolower($initialDb) === $dbLower) {
                    $this->error("Dump attempts to DROP/CREATE the target database [{$database}], but the initial connection would also be [{$initialDb}]. Re-run with --maintenance-db=postgres (or template1), or use --strip-db-ddl to import into the existing database.");

                    return 1;
                }

                $this->warn("Dump contains DROP/CREATE DATABASE for [{$database}]. Switching initial connection to [{$initialDb}] so the drop can succeed.");
            }
        } else {
            $this->line('DB-level DDL stripped; importing directly into the configured database.');
        }

        $willDropOrCreateTarget = false;
        if (! $this->option('strip-db-ddl')) {
            $targets = array_unique(array_map('strtolower', array_merge($intents['drops'], $intents['creates'])));
            $willDropOrCreateTarget = in_array(strtolower($database), $targets, true);
        }

        if ($this->option('terminate-connections') && $willDropOrCreateTarget) {
            $this->info("Terminating sessions on [{$database}] and blocking new connections...");
            $ok = $this->terminateDbSessions(
                $psql, $host, $port, $username, $password,
                $initialDb, $database,
                blockNew: true,
                retries: 10,
                sleepMs: 500
            );
            if (! $ok) {
                $this->error('Failed to terminate existing sessions; aborting.');

                return 1;
            }
        }

        // Build psql args
        $args = [$psql, '-v', 'ON_ERROR_STOP=1', '-d', $initialDb];
        if ($host) {
            $args[] = '-h';
            $args[] = $host;
        }
        if ($port) {
            $args[] = '-p';
            $args[] = $port;
        }
        if ($username) {
            $args[] = '-U';
            $args[] = $username;
        }

        $this->info("Importing {$currentPath} using connection [{$connectionName}] (initial database: [{$initialDb}])...");

        $run = fn (string $file) => $this->runPsql(array_merge($args, ['-f', $file]), dirname($file), $env, (bool) $this->option('no-output'));

        // First attempt
        [$ok, $code, $stdout, $stderr] = $run($currentPath);

        // Auto-retry by stripping unrecognized config params if requested
        if (! $ok && $this->option('auto-strip-unrecognized')) {
            $unknowns = $this->parseUnrecognizedConfigParams($stderr."\n".$stdout);
            if (! empty($unknowns)) {
                $this->warn('Detected unrecognized configuration parameter(s): '.implode(', ', $unknowns));
                $this->info('Retrying import after stripping these SET directives...');
                [$retryPath, $removed] = $this->sanitizeDumpSetDirectives($currentPath, $unknowns, true);
                foreach ($removed as $name => $count) {
                    $this->line("- stripped {$name}: {$count} line(s)");
                }
                $currentPath = $retryPath;
                [$ok, $code, $stdout, $stderr] = $run($currentPath);
            }
        }

        if ($this->option('terminate-connections') && ! $this->option('no-unblock')) {
            // Best-effort; ignore errors if DB was dropped/recreated already
            try {
                $this->line("Re-enabling connections to [{$database}]...");
                $this->terminateDbSessions(
                    $psql, $host, $port, $username, $password,
                    $initialDb, $database,
                    blockNew: false, // just run unblock step
                    retries: 0,
                    sleepMs: 0
                );
                // The call above doesn’t unblock; run explicit unblock:
                $quotedDb = '"'.str_replace('"', '""', $database).'"';
                $args = [$psql, '-v', 'ON_ERROR_STOP=1', '-d', $initialDb];
                if ($host) {
                    $args[] = '-h';
                    $args[] = $host;
                }
                if ($port) {
                    $args[] = '-p';
                    $args[] = (string) $port;
                }
                if ($username) {
                    $args[] = '-U';
                    $args[] = $username;
                }
                $env = ($password !== null && $password !== '') ? array_merge($_ENV, $_SERVER, ['PGPASSWORD' => $password]) : null;
                $args = array_merge($args, ['-c', "ALTER DATABASE {$quotedDb} WITH ALLOW_CONNECTIONS true;"]);
                (new \Symfony\Component\Process\Process($args, null, $env))->run();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $this->cleanupTempFiles();

        if (! $ok) {
            $this->printPsqlFailure($code, $stderr);

            return $code ?: 1;
        }

        $this->info('Import completed successfully.');

        return 0;
    }

    /**
     * Run psql and capture output while optionally streaming to console.
     */
    protected function runPsql(array $args, string $cwd, ?array $env, bool $noOutput): array
    {
        $process = new Process($args, $cwd, $env);
        $process->setTimeout(null);

        $stdoutBuf = '';
        $stderrBuf = '';

        try {
            if ($noOutput) {
                $process->run();
                $stdoutBuf = $process->getOutput();
                $stderrBuf = $process->getErrorOutput();
            } else {
                $process->run(function ($type, $buffer) use (&$stdoutBuf, &$stderrBuf) {
                    if ($type === Process::ERR) {
                        $stderrBuf .= $buffer;
                        $this->error(rtrim($buffer));
                    } else {
                        $stdoutBuf .= $buffer;
                        $this->output->write($buffer);
                    }
                });
            }
        } catch (\Throwable $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return [false, 1, $stdoutBuf, $stderrBuf];
        }

        $ok = $process->isSuccessful();
        $code = $process->getExitCode();

        return [$ok, $code, $stdoutBuf, $stderrBuf];
    }

    /**
     * Create a sanitized copy of the SQL file with specified SET directives removed.
     *
     * @return array [string $newPath, array $removedCounts]
     */
    protected function sanitizeDumpSetDirectives(string $src, array $stripSetNames, bool $forceNew = false): array
    {
        $strip = [];
        foreach ($stripSetNames as $name) {
            $name = strtolower(trim($name));
            if ($name !== '') {
                $strip[$name] = true;
            }
        }

        $removed = [];
        $madeChange = false;

        $fin = fopen($src, 'r');
        if (! $fin) {
            $this->error("Unable to open file for reading: {$src}");

            return [$src, $removed];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pgimp_');
        if ($tmp === false) {
            fclose($fin);
            $this->error('Unable to create temporary file for sanitized dump.');

            return [$src, $removed];
        }
        $tmp .= '.sql';
        $fout = fopen($tmp, 'w');
        if (! $fout) {
            fclose($fin);
            @unlink($tmp);
            $this->error('Unable to open temporary file for writing.');

            return [$src, $removed];
        }

        while (($line = fgets($fin)) !== false) {
            if (preg_match('/^\s*SET\s+([a-z_]+)\s*=.*;$/i', $line, $m)) {
                $name = strtolower($m[1]);
                if (isset($strip[$name])) {
                    $removed[$name] = ($removed[$name] ?? 0) + 1;
                    $madeChange = true;

                    continue; // skip
                }
            }
            fwrite($fout, $line);
        }

        fclose($fin);
        fclose($fout);

        if (! $madeChange && ! $forceNew) {
            @unlink($tmp);

            return [$src, $removed];
        }

        $this->tempFiles[] = $tmp;

        return [$tmp, $removed];
    }

    /**
     * Create a sanitized copy with DB-level DDL removed: DROP/CREATE DATABASE and \connect lines.
     *
     * @return array [string $newPath, array $removedCounts]
     */
    protected function sanitizeDumpDbDDL(string $src): array
    {
        $removed = ['drop_database' => 0, 'create_database' => 0, 'connect' => 0];
        $madeChange = false;

        $fin = fopen($src, 'r');
        if (! $fin) {
            $this->error("Unable to open file for reading: {$src}");

            return [$src, $removed];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pgimp_');
        if ($tmp === false) {
            fclose($fin);
            $this->error('Unable to create temporary file for sanitized dump.');

            return [$src, $removed];
        }
        $tmp .= '.sql';
        $fout = fopen($tmp, 'w');
        if (! $fout) {
            fclose($fin);
            @unlink($tmp);
            $this->error('Unable to open temporary file for writing.');

            return [$src, $removed];
        }

        while (($line = fgets($fin)) !== false) {
            if (preg_match('/^\s*DROP\s+DATABASE\b/i', $line)) {
                $removed['drop_database']++;
                $madeChange = true;

                continue;
            }
            if (preg_match('/^\s*CREATE\s+DATABASE\b/i', $line)) {
                $removed['create_database']++;
                $madeChange = true;

                continue;
            }
            if (preg_match('/^\s*\\\(?:connect|c)\b/i', $line)) {
                $removed['connect']++;
                $madeChange = true;

                continue;
            }
            fwrite($fout, $line);
        }

        fclose($fin);
        fclose($fout);

        if (! $madeChange) {
            @unlink($tmp);

            return [$src, $removed];
        }

        $this->tempFiles[] = $tmp;

        return [$tmp, $removed];
    }

    /**
     * Inspect the dump for DB-level statements near the top.
     * Returns arrays of database names (lowercase) for drops/creates/connects.
     */
    protected function inspectDumpDbStatements(string $src): array
    {
        $drops = [];
        $creates = [];
        $connects = [];

        $fin = fopen($src, 'r');
        if (! $fin) {
            return ['drops' => $drops, 'creates' => $creates, 'connects' => $connects];
        }

        $maxLines = 5000; // Scan header region where these usually appear
        $lines = 0;

        while (($line = fgets($fin)) !== false && $lines++ < $maxLines) {
            // DROP DATABASE [IF EXISTS] name;
            if (preg_match('/^\s*DROP\s+DATABASE\s+(?:IF\s+EXISTS\s+)?(?:"((?:[^"]|"")+)"|([^\s;]+))\s*;/i', $line, $m)) {
                $name = $m[1] !== '' ? strtolower(str_replace('""', '"', $m[1])) : strtolower($m[2]);
                $drops[] = $name;

                continue;
            }

            // CREATE DATABASE name ...
            if (preg_match('/^\s*CREATE\s+DATABASE\s+(?:"((?:[^"]|"")+)"|([^\s;]+))\b/i', $line, $m)) {
                $name = $m[1] !== '' ? strtolower(str_replace('""', '"', $m[1])) : strtolower($m[2]);
                $creates[] = $name;

                continue;
            }

            // \connect name or \c name
            if (preg_match('/^\s*\\\(?:(?:connect)|c)\s+(?:"((?:[^"]|"")+)"|([^\s;]+))/i', $line, $m)) {
                $name = $m[1] !== '' ? strtolower(str_replace('""', '"', $m[1])) : strtolower($m[2]);
                $connects[] = $name;

                continue;
            }

            // If we’ve already seen a \connect, the rest likely isn’t DB-level header; we can stop scanning early
            if (! empty($connects) && $lines > 50) {
                break;
            }
        }

        fclose($fin);

        return [
            'drops' => array_values(array_unique($drops)),
            'creates' => array_values(array_unique($creates)),
            'connects' => array_values(array_unique($connects)),
        ];
    }

    /**
     * Parse psql output for unrecognized configuration parameter errors.
     */
    protected function parseUnrecognizedConfigParams(string $text): array
    {
        $matches = [];
        preg_match_all('/unrecognized configuration parameter "([^"]+)"/i', $text, $matches);
        if (empty($matches[1])) {
            return [];
        }
        $params = array_map(fn ($s) => strtolower(trim($s)), $matches[1]);

        return array_values(array_unique($params));
    }

    protected function printPsqlFailure(?int $code, string $stderr): void
    {
        $this->error('psql exited with code '.($code ?? 1));
        $stderr = trim($stderr);
        if ($stderr !== '') {
            $this->line($stderr);
        }
    }

    protected function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }

    protected function terminateDbSessions(
        string $psql,
        ?string $host,
        ?string $port,
        ?string $username,
        ?string $password,
        string $initialDb,
        string $targetDb,
        bool $blockNew = true,
        int $retries = 10,
        int $sleepMs = 500
    ): bool {
        // Base args for psql -c
        $argsBase = [$psql, '-v', 'ON_ERROR_STOP=1', '-d', $initialDb];
        if ($host) {
            $argsBase[] = '-h';
            $argsBase[] = $host;
        }
        if ($port) {
            $argsBase[] = '-p';
            $argsBase[] = (string) $port;
        }
        if ($username) {
            $argsBase[] = '-U';
            $argsBase[] = $username;
        }

        $env = null;
        if ($password !== null && $password !== '') {
            $env = array_merge($_ENV, $_SERVER, ['PGPASSWORD' => $password]);
        }

        $quotedDb = '"'.str_replace('"', '""', $targetDb).'"';
        $terminateSql = 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '.$this->sqlQuote($targetDb).' AND pid <> pg_backend_pid();';
        $blockSql = "ALTER DATABASE {$quotedDb} WITH ALLOW_CONNECTIONS false;";
        $unblockSql = "ALTER DATABASE {$quotedDb} WITH ALLOW_CONNECTIONS true;";

        // 1) Block new connections (optional)
        if ($blockNew) {
            $args = array_merge($argsBase, ['-c', $blockSql]);
            $p = new \Symfony\Component\Process\Process($args, null, $env);
            $p->run();
            if (! $p->isSuccessful()) {
                $this->error('Failed to set ALLOW_CONNECTIONS false on '.$targetDb.': '.trim($p->getErrorOutput()));

                return false;
            }
        }

        // 2) Loop to terminate until none left or we exhaust retries
        for ($i = 0; $i <= $retries; $i++) {
            // terminate
            $args = array_merge($argsBase, ['-c', $terminateSql]);
            $p = new \Symfony\Component\Process\Process($args, null, $env);
            $p->run();

            // check count
            $countSql = 'SELECT count(*) FROM pg_stat_activity WHERE datname = '.$this->sqlQuote($targetDb).' AND pid <> pg_backend_pid();';
            $args = array_merge($argsBase, ['-t', '-A', '-c', $countSql]); // -t -A for raw count
            $p2 = new \Symfony\Component\Process\Process($args, null, $env);
            $p2->run();
            $cnt = (int) trim($p2->getOutput());

            if ($cnt === 0) {
                break;
            }

            usleep($sleepMs * 1000);
        }

        // Optionally re-enable connections immediately if requested by caller later; here we just return true.
        // The caller can re-enable after the import unless --no-unblock is passed.
        return true;
    }

    protected function sqlQuote(string $s): string
    {
        return "'".str_replace("'", "''", $s)."'";
    }
}
