<?php

namespace Tests\Unit\Services\LegalArtillery;

use Tests\TestCase;

/**
 * Verifies that legacy LegalArtillery classes have been properly removed.
 *
 * SOT-016: Remove/archive unused Legal Artillery legacy services.
 *
 * Classes removed:
 * - RecursiveDocumentWriter: Legacy recursive document generation, replaced by DevastatingArgumentBuilder pipeline
 * - IterativeRefiner: Legacy iterative refinement loop, replaced by QualityGate validation
 * - LegacyDevastatingArgumentBuilder: Legacy argument builder, replaced by DevastatingArgumentBuilder
 *
 * All three classes had zero references in active production code (only in their own files and tests).
 */
class LegacyCleanupVerificationTest extends TestCase
{
    /**
     * Verify RecursiveDocumentWriter class file has been removed from disk.
     */
    public function test_recursive_document_writer_class_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Services/LegalArtillery/RecursiveDocumentWriter.php'),
            'RecursiveDocumentWriter.php should have been removed as dead code (SOT-016)'
        );
    }

    /**
     * Verify IterativeRefiner class file has been removed from disk.
     */
    public function test_iterative_refiner_class_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Services/LegalArtillery/IterativeRefiner.php'),
            'IterativeRefiner.php should have been removed as dead code (SOT-016)'
        );
    }

    /**
     * Verify LegacyDevastatingArgumentBuilder class file has been removed from disk.
     */
    public function test_legacy_devastating_argument_builder_class_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Services/LegalArtillery/LegacyDevastatingArgumentBuilder.php'),
            'LegacyDevastatingArgumentBuilder.php should have been removed as dead code (SOT-016)'
        );
    }

    /**
     * Verify that no active production code references the removed classes.
     *
     * Scans all PHP files under app/ to ensure no use statements, type hints,
     * or string references to the removed class names exist.
     */
    public function test_no_active_code_references_removed_classes(): void
    {
        $removedClasses = [
            'RecursiveDocumentWriter',
            'IterativeRefiner',
            'LegacyDevastatingArgumentBuilder',
        ];

        $appPath = app_path();
        $phpFiles = $this->getPhpFiles($appPath);

        foreach ($phpFiles as $file) {
            $contents = file_get_contents($file);
            $relativePath = str_replace(base_path() . '/', '', $file);

            foreach ($removedClasses as $className) {
                $this->assertStringNotContainsString(
                    $className,
                    $contents,
                    "Active code file '{$relativePath}' still references removed class '{$className}'"
                );
            }
        }
    }

    /**
     * Verify that the orphaned test files for removed classes have also been deleted.
     */
    public function test_orphaned_test_files_removed(): void
    {
        $orphanedTests = [
            'tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php',
            'tests/Unit/Services/LegalArtillery/IterativeRefinerTest.php',
            'tests/Unit/Services/LegalArtillery/LegacyDevastatingArgumentBuilderTest.php',
        ];

        foreach ($orphanedTests as $testFile) {
            $fullPath = base_path($testFile);
            $this->assertFileDoesNotExist(
                $fullPath,
                "Orphaned test file '{$testFile}' should have been removed along with its class (SOT-016)"
            );
        }
    }

    /**
     * Verify that config files do not reference removed classes.
     */
    public function test_no_config_references_to_removed_classes(): void
    {
        $removedClasses = [
            'RecursiveDocumentWriter',
            'IterativeRefiner',
            'LegacyDevastatingArgumentBuilder',
        ];

        $configPath = config_path();
        $configFiles = $this->getPhpFiles($configPath);

        foreach ($configFiles as $file) {
            $contents = file_get_contents($file);
            $relativePath = str_replace(base_path() . '/', '', $file);

            foreach ($removedClasses as $className) {
                $this->assertStringNotContainsString(
                    $className,
                    $contents,
                    "Config file '{$relativePath}' still references removed class '{$className}'"
                );
            }
        }
    }

    /**
     * Verify that non-legacy LegalArtillery services still exist and are intact.
     */
    public function test_active_legal_artillery_services_still_exist(): void
    {
        $activeServices = [
            'ArgumentValidator',
            'AttachmentCollector',
            'CaseBridge',
            'DevastatingArgumentBuilder',
            'DigitalSigner',
            'DocumentInventory',
            'DocxRenderer',
            'EKomunikacijaDispatcher',
            'EscalationLadderSuggester',
            'GmailDispatcher',
            'LlmClient',
            'PiiRedactor',
            'ProfileContextBuilder',
            'PromptVersionManager',
            'QualityGate',
            'ResponseHandler',
            'SampleDocumentStore',
            'ScenarioLoader',
        ];

        foreach ($activeServices as $service) {
            $this->assertFileExists(
                app_path("Services/LegalArtillery/{$service}.php"),
                "Active service '{$service}.php' should NOT have been removed"
            );
        }
    }

    /**
     * Recursively get all PHP files in a directory.
     *
     * @return array<string>
     */
    private function getPhpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
