<?php

namespace Tests\Unit\CI;

use PHPUnit\Framework\TestCase;

/**
 * Tests to ensure no unresolved git merge conflict markers exist in the codebase
 * and that the CI check script is properly configured.
 *
 * Git conflict markers are:
 *   <<<<<<< branch-name  (7 '<' chars followed by space and branch)
 *   =======              (exactly 7 '=' chars, alone on a line)
 *   >>>>>>> branch-name  (7 '>' chars followed by space and branch)
 *
 * We use regex to avoid false positives from comment separators
 * like "// ========================================".
 */
class ConflictMarkerCheckTest extends TestCase
{
    /**
     * Regex patterns for git merge conflict markers.
     *
     * - <<<<<<< must be at line start (possibly after whitespace) and have exactly 7 '<'
     * - ======= must be exactly 7 '=' on a line (after trimming)
     * - >>>>>>> must be at line start (possibly after whitespace) and have exactly 7 '>'
     */
    private const CONFLICT_MARKER_PATTERNS = [
        '/^[\s]*<{7}\s/',   // <<<<<<< followed by space (start of conflict)
        '/^[\s]*<{7}$/',    // <<<<<<< at end of line (unusual but possible)
        '/^\s*={7}\s*$/',   // ======= alone on a line (conflict separator)
        '/^[\s]*>{7}\s/',   // >>>>>>> followed by space (end of conflict)
        '/^[\s]*>{7}$/',    // >>>>>>> at end of line (unusual but possible)
    ];

    /**
     * Get the project root directory.
     *
     * Computed from test file location: tests/Unit/CI/ -> 3 levels up.
     */
    private function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Test that routes/api.php contains no conflict markers.
     */
    public function test_routes_api_has_no_conflict_markers(): void
    {
        $file = $this->projectRoot() . '/routes/api.php';
        $this->assertFileExists($file);

        $foundMarkers = $this->findConflictMarkersInFile($file);

        $this->assertEmpty(
            $foundMarkers,
            "routes/api.php contains unresolved conflict markers:\n" . implode("\n", $foundMarkers)
        );
    }

    /**
     * Test that all PHP files in app/ directory have no conflict markers.
     */
    public function test_app_php_files_have_no_conflict_markers(): void
    {
        $this->assertDirectoryHasNoConflictMarkers(
            $this->projectRoot() . '/app',
            'app/'
        );
    }

    /**
     * Test that all PHP files in routes/ directory have no conflict markers.
     */
    public function test_routes_php_files_have_no_conflict_markers(): void
    {
        $this->assertDirectoryHasNoConflictMarkers(
            $this->projectRoot() . '/routes',
            'routes/'
        );
    }

    /**
     * Test that the CI conflict marker check script exists and is executable.
     */
    public function test_check_script_exists_and_is_executable(): void
    {
        $scriptPath = $this->projectRoot() . '/scripts/ci/check-conflict-markers.sh';

        $this->assertFileExists(
            $scriptPath,
            'CI conflict marker check script must exist at scripts/ci/check-conflict-markers.sh'
        );

        $this->assertTrue(
            is_executable($scriptPath),
            'scripts/ci/check-conflict-markers.sh must be executable'
        );
    }

    /**
     * Test that the CI check script exits 0 on a clean codebase.
     */
    public function test_check_script_passes_on_clean_codebase(): void
    {
        $scriptPath = $this->projectRoot() . '/scripts/ci/check-conflict-markers.sh';
        if (! file_exists($scriptPath)) {
            $this->markTestSkipped('Script not yet created');
        }

        // Run the script on the current codebase - should pass (exit 0)
        exec('bash ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $exitCode);

        $this->assertEquals(
            0,
            $exitCode,
            "Conflict marker check script should exit 0 on clean codebase.\nOutput: " . implode("\n", $output)
        );
    }

    /**
     * Find conflict markers in a single file.
     *
     * @return array<string> List of found markers with line numbers
     */
    private function findConflictMarkersInFile(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        $lines = explode("\n", $contents);

        $foundMarkers = [];
        foreach ($lines as $lineNumber => $line) {
            foreach (self::CONFLICT_MARKER_PATTERNS as $pattern) {
                if (preg_match($pattern, $line)) {
                    $foundMarkers[] = sprintf(
                        'Line %d: %s',
                        $lineNumber + 1,
                        trim($line)
                    );
                    break; // Only report each line once
                }
            }
        }

        return $foundMarkers;
    }

    /**
     * Assert that no PHP files in the given directory contain conflict markers.
     */
    private function assertDirectoryHasNoConflictMarkers(string $directory, string $label): void
    {
        if (! is_dir($directory)) {
            $this->markTestSkipped("Directory $label does not exist");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $foundMarkers = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fileMarkers = $this->findConflictMarkersInFile($file->getPathname());
            $relativePath = str_replace($this->projectRoot() . '/', '', $file->getPathname());

            foreach ($fileMarkers as $marker) {
                $foundMarkers[] = $relativePath . ':' . $marker;
            }
        }

        $this->assertEmpty(
            $foundMarkers,
            "PHP files in $label contain unresolved conflict markers:\n" . implode("\n", $foundMarkers)
        );
    }
}
