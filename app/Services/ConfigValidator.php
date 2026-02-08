<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConfigValidator
{
    /**
     * Validation errors collected during validation.
     */
    protected array $errors = [];

    /**
     * Validation warnings collected during validation.
     */
    protected array $warnings = [];

    /**
     * Validate all configuration.
     *
     * @return array{errors: array, warnings: array}
     */
    public function validate(): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('ConfigValidator: validate initiated', [
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'user_id' => auth()->id(),
        ]);

        try {
            $this->errors = [];
            $this->warnings = [];

            // Run all validation checks
            $this->validateApplicationConfig();
            $this->validateDatabaseConfig();
            $this->validateNeo4jConfig();
            $this->validateOpenAIConfig();
            $this->validateAwsConfig();
            $this->validateQueueConfig();
            $this->validateCacheConfig();
            $this->validateSessionConfig();
            $this->validateMailConfig();
            $this->validateMcpConfig();
            $this->validateProductionSettings();
            $this->validateSecuritySettings();

            $result = [
                'errors' => $this->errors,
                'warnings' => $this->warnings,
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('ConfigValidator: validate completed', [
                'errors_count' => count($this->errors),
                'warnings_count' => count($this->warnings),
                'validation_passed' => empty($this->errors),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('ConfigValidator: validate failed with unexpected exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(
                'Configuration validation failed: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validate application core configuration.
     */
    protected function validateApplicationConfig(): void
    {
        // APP_KEY is required
        if (empty(config('app.key'))) {
            $this->errors[] = 'APP_KEY is not set. Generate with: php artisan key:generate';
        }

        // APP_URL should be set
        if (config('app.url') === 'http://localhost') {
            $this->warnings[] = 'APP_URL is set to default localhost. Update for production.';
        }

        // APP_URL should use HTTPS in production
        if (app()->isProduction() && ! str_starts_with(config('app.url'), 'https://')) {
            $this->errors[] = 'APP_URL must use HTTPS in production environment';
        }

        // APP_ENV should be set appropriately
        $validEnvironments = ['local', 'development', 'staging', 'production'];
        if (! in_array(config('app.env'), $validEnvironments)) {
            $this->warnings[] = 'APP_ENV has unusual value: '.config('app.env');
        }
    }

    /**
     * Validate database configuration.
     */
    protected function validateDatabaseConfig(): void
    {
        $connection = config('database.default');

        // Database connection should be configured
        if (empty($connection)) {
            $this->errors[] = 'DB_CONNECTION is not set';

            return;
        }

        $config = config("database.connections.{$connection}");

        if (empty($config)) {
            $this->errors[] = "Database connection '{$connection}' is not configured";

            return;
        }

        // For non-SQLite connections, validate credentials
        if ($connection !== 'sqlite') {
            if (empty($config['host'])) {
                $this->errors[] = 'DB_HOST is not set';
            }

            if (empty($config['database'])) {
                $this->errors[] = 'DB_DATABASE is not set';
            }

            if (empty($config['username'])) {
                $this->errors[] = 'DB_USERNAME is not set';
            }

            if (empty($config['password']) && app()->isProduction()) {
                $this->errors[] = 'DB_PASSWORD must be set in production';
            }
        }

        // PostgreSQL is recommended for production (pgvector support)
        if (app()->isProduction() && $connection !== 'pgsql') {
            $this->warnings[] = 'PostgreSQL (pgsql) is recommended for production (required for pgvector support)';
        }
    }

    /**
     * Validate Neo4j configuration.
     */
    protected function validateNeo4jConfig(): void
    {
        if (! config('neo4j.enabled', false)) {
            return;
        }

        // Neo4j URI is required
        if (empty(config('neo4j.uri'))) {
            $this->errors[] = 'NEO4J_URI is required when Neo4j is enabled';
        }

        // Neo4j username is required
        if (empty(config('neo4j.user')) && empty(config('neo4j.connections.bolt.username'))) {
            $this->errors[] = 'NEO4J_USERNAME is required when Neo4j is enabled';
        }

        // Neo4j password is required
        $password = config('neo4j.password') ?? config('neo4j.connections.bolt.password');
        if (empty($password)) {
            $this->errors[] = 'NEO4J_PASSWORD is required when Neo4j is enabled';
        }

        // Warn about default Neo4j password
        if ($password === 'neo4j' || $password === 'secret') {
            $this->errors[] = 'NEO4J_PASSWORD is using default value. Change immediately for security!';
        }

        // Neo4j host should be set
        if (empty(config('neo4j.connections.bolt.host'))) {
            $this->warnings[] = 'NEO4J_HOST is not set';
        }
    }

    /**
     * Validate OpenAI configuration.
     */
    protected function validateOpenAIConfig(): void
    {
        // OpenAI API key should be set
        if (empty(config('openai.api_key'))) {
            $this->warnings[] = 'OPENAI_API_KEY is not set. Required for AI features.';

            return;
        }

        // Validate API key format (should start with sk-)
        $apiKey = config('openai.api_key');
        if (! str_starts_with($apiKey, 'sk-')) {
            $this->warnings[] = 'OPENAI_API_KEY has unexpected format (should start with sk-)';
        }
    }

    /**
     * Validate AWS configuration.
     */
    protected function validateAwsConfig(): void
    {
        $hasAccessKey = ! empty(config('services.ses.key')) || ! empty(env('AWS_ACCESS_KEY_ID'));
        $hasSecretKey = ! empty(config('services.ses.secret')) || ! empty(env('AWS_SECRET_ACCESS_KEY'));

        // If one is set, both should be set
        if ($hasAccessKey && ! $hasSecretKey) {
            $this->errors[] = 'AWS_SECRET_ACCESS_KEY is required when AWS_ACCESS_KEY_ID is set';
        }

        if (! $hasAccessKey && $hasSecretKey) {
            $this->errors[] = 'AWS_ACCESS_KEY_ID is required when AWS_SECRET_ACCESS_KEY is set';
        }

        // Check S3 bucket if AWS is configured
        if ($hasAccessKey && $hasSecretKey) {
            if (empty(env('AWS_BUCKET'))) {
                $this->warnings[] = 'AWS_BUCKET is not set. Required for S3 file storage.';
            }

            if (empty(env('AWS_DEFAULT_REGION'))) {
                $this->warnings[] = 'AWS_DEFAULT_REGION is not set. Defaulting to us-east-1.';
            }
        }
    }

    /**
     * Validate queue configuration.
     */
    protected function validateQueueConfig(): void
    {
        $connection = config('queue.default');

        if (empty($connection)) {
            $this->warnings[] = 'QUEUE_CONNECTION is not set';

            return;
        }

        // Recommend Redis for production
        if (app()->isProduction() && ! in_array($connection, ['redis', 'sqs', 'beanstalkd'])) {
            $this->warnings[] = "Queue connection '{$connection}' is not recommended for production. Use redis, sqs, or beanstalkd.";
        }

        // Validate Redis queue configuration
        if ($connection === 'redis') {
            $this->validateRedisConfig('Queue');
        }
    }

    /**
     * Validate cache configuration.
     */
    protected function validateCacheConfig(): void
    {
        $driver = config('cache.default');

        if (empty($driver)) {
            $this->warnings[] = 'CACHE_STORE is not set';

            return;
        }

        // Recommend Redis or Memcached for production
        if (app()->isProduction() && ! in_array($driver, ['redis', 'memcached', 'dynamodb'])) {
            $this->warnings[] = "Cache driver '{$driver}' is not recommended for production. Use redis, memcached, or dynamodb.";
        }

        // Validate Redis cache configuration
        if ($driver === 'redis') {
            $this->validateRedisConfig('Cache');
        }
    }

    /**
     * Validate Redis configuration.
     *
     * @param  string  $context  Context for error messages (Queue, Cache, etc.)
     */
    protected function validateRedisConfig(string $context = 'Redis'): void
    {
        if (empty(config('database.redis.default.host'))) {
            $this->errors[] = "{$context}: REDIS_HOST is not set";
        }

        // Redis password should be set in production
        $password = config('database.redis.default.password');
        if (app()->isProduction() && (empty($password) || $password === 'null')) {
            $this->errors[] = "{$context}: REDIS_PASSWORD must be set in production for security";
        }
    }

    /**
     * Validate session configuration.
     */
    protected function validateSessionConfig(): void
    {
        $driver = config('session.driver');

        // Recommend database or Redis for production
        if (app()->isProduction() && in_array($driver, ['file', 'array'])) {
            $this->warnings[] = "Session driver '{$driver}' is not recommended for production. Use database or redis.";
        }

        // Session should be encrypted in production
        if (app()->isProduction() && ! config('session.encrypt')) {
            $this->warnings[] = 'SESSION_ENCRYPT should be true in production';
        }

        // Secure cookies in production
        if (app()->isProduction() && ! config('session.secure')) {
            $this->errors[] = 'SESSION_SECURE_COOKIE must be true in production (requires HTTPS)';
        }
    }

    /**
     * Validate mail configuration.
     */
    protected function validateMailConfig(): void
    {
        $mailer = config('mail.default');

        if (empty($mailer)) {
            $this->warnings[] = 'MAIL_MAILER is not set';

            return;
        }

        // Warn about using 'log' mailer in production
        if (app()->isProduction() && $mailer === 'log') {
            $this->errors[] = "Mail driver 'log' should not be used in production. Configure SMTP or a service.";
        }

        // Validate SMTP configuration
        if ($mailer === 'smtp') {
            $config = config('mail.mailers.smtp');

            if (empty($config['host'])) {
                $this->errors[] = 'MAIL_HOST is required for SMTP mailer';
            }

            if (empty($config['username']) || empty($config['password'])) {
                $this->warnings[] = 'MAIL_USERNAME and MAIL_PASSWORD should be set for SMTP authentication';
            }
        }

        // From address should be set
        if (empty(config('mail.from.address')) || config('mail.from.address') === 'hello@example.com') {
            $this->warnings[] = 'MAIL_FROM_ADDRESS should be set to a valid email address';
        }
    }

    /**
     * Validate MCP configuration.
     */
    protected function validateMcpConfig(): void
    {
        // MCP API token should be set if auth is enabled
        if (config('services.mcp.auth.enabled', true)) {
            $token = config('services.mcp.auth.token') ?? config('mcp.api_token');

            if (empty($token)) {
                $this->warnings[] = 'MCP_API_TOKEN should be set when MCP authentication is enabled';
            }

            // Token should be strong (at least 32 characters)
            if (! empty($token) && strlen($token) < 32) {
                $this->warnings[] = 'MCP_API_TOKEN should be at least 32 characters for security';
            }
        }
    }

    /**
     * Validate production-specific settings.
     */
    protected function validateProductionSettings(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        // Debug mode must be false
        if (config('app.debug')) {
            $this->errors[] = 'APP_DEBUG must be false in production environment';
        }

        // Log level should not be debug
        if (config('logging.level') === 'debug') {
            $this->warnings[] = 'LOG_LEVEL should not be "debug" in production (use error or warning)';
        }

        // Ensure environment is set to production
        if (config('app.env') !== 'production') {
            $this->errors[] = 'APP_ENV should be "production" in production environment';
        }
    }

    /**
     * Validate security settings.
     */
    protected function validateSecuritySettings(): void
    {
        // BCrypt rounds should be reasonable
        $rounds = config('hashing.bcrypt.rounds', 10);
        if ($rounds < 10) {
            $this->warnings[] = 'BCRYPT_ROUNDS is less than 10. Consider increasing for better security.';
        }
        if ($rounds > 14) {
            $this->warnings[] = 'BCRYPT_ROUNDS is greater than 14. This may impact performance.';
        }

        // Check for default passwords
        $this->checkDefaultPasswords();
    }

    /**
     * Check for default/weak passwords.
     */
    protected function checkDefaultPasswords(): void
    {
        $checks = [
            ['password', 'Generic password'],
            ['secret', 'Generic password'],
            ['admin', 'Generic password'],
            ['root', 'Generic password'],
            ['123456', 'Weak password'],
            ['password123', 'Weak password'],
        ];

        $envVars = [
            'DB_PASSWORD' => 'Database',
            'REDIS_PASSWORD' => 'Redis',
            'NEO4J_PASSWORD' => 'Neo4j',
            'MAIL_PASSWORD' => 'Mail',
        ];

        foreach ($envVars as $var => $service) {
            $value = env($var);
            if (empty($value)) {
                continue;
            }

            foreach ($checks as [$pattern, $type]) {
                if (str_contains(strtolower($value), $pattern)) {
                    $this->errors[] = "{$service} password appears to use default or weak value. Change immediately!";
                    break;
                }
            }

            // Check password length
            if (strlen($value) < 12) {
                $this->warnings[] = "{$service} password is less than 12 characters. Use longer passwords for better security.";
            }
        }
    }

    /**
     * Get all errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warnings.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if validation passed (no errors).
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed (has errors).
     */
    public function fails(): bool
    {
        return ! $this->passes();
    }
}
