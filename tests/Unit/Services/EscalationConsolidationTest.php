<?php

namespace Tests\Unit\Services;

use App\Contracts\LegalArtillery\EscalationSuggesterInterface;
use App\Services\LegalArtillery\EscalationLadderSuggester;
use Tests\TestCase;

class EscalationConsolidationTest extends TestCase
{
    public function test_canonical_service_resolves_from_container(): void
    {
        $service = app(EscalationLadderSuggester::class);

        $this->assertInstanceOf(EscalationLadderSuggester::class, $service);
    }

    public function test_interface_resolves_to_canonical_implementation(): void
    {
        $service = app(EscalationSuggesterInterface::class);

        $this->assertInstanceOf(EscalationLadderSuggester::class, $service);
        $this->assertInstanceOf(EscalationSuggesterInterface::class, $service);
    }

    public function test_suggestNextRung_returns_expected_structure(): void
    {
        config([
            'legal-artillery.profiles' => [
                'initial_request' => [
                    'name' => 'Initial Request',
                    'recipient' => ['name' => 'Court'],
                    'legal_basis' => ['ZPP'],
                    'tone' => 'formal',
                    'structure' => ['heading', 'body'],
                    'docx_template' => null,
                ],
                'formal_complaint' => [
                    'name' => 'Formal Complaint',
                    'recipient' => ['name' => 'Court'],
                    'legal_basis' => ['ZPP'],
                    'tone' => 'formal',
                    'structure' => ['heading', 'body'],
                    'docx_template' => null,
                ],
            ],
            'escalation-ladders' => [
                'ladders' => [
                    'default' => [
                        [
                            'profile' => 'initial_request',
                            'min_wait_days' => 0,
                            'escalate_on' => ['denied', 'ignored'],
                        ],
                        [
                            'profile' => 'formal_complaint',
                            'reason' => 'Escalate after denial.',
                            'required_inputs' => ['case_number'],
                        ],
                    ],
                ],
            ],
        ]);

        $suggester = app(EscalationSuggesterInterface::class);
        $result = $suggester->suggestNextRung(
            failedProfileKey: 'initial_request',
            responseType: 'denied',
            elapsedDays: 1,
            ladderKey: 'default',
        );

        $this->assertArrayHasKey('next_profile_key', $result);
        $this->assertArrayHasKey('next_profile_name', $result);
        $this->assertArrayHasKey('required_inputs', $result);
        $this->assertArrayHasKey('attachments', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertSame('formal_complaint', $result['next_profile_key']);
        $this->assertSame('Escalate after denial.', $result['reason']);
        $this->assertSame(['case_number'], $result['required_inputs']);
    }

    public function test_suggestNext_returns_next_in_hierarchy(): void
    {
        config(['escalation-ladders.hierarchy' => ['alpha', 'beta', 'gamma']]);

        $suggester = app(EscalationSuggesterInterface::class);

        $this->assertSame('alpha', $suggester->suggestNext(null));
        $this->assertSame('beta', $suggester->suggestNext('alpha'));
        $this->assertSame('gamma', $suggester->suggestNext('beta'));
        $this->assertNull($suggester->suggestNext('gamma'));
    }

    public function test_isTerminal_identifies_last_rung(): void
    {
        config(['escalation-ladders.hierarchy' => ['alpha', 'beta', 'gamma']]);

        $suggester = app(EscalationSuggesterInterface::class);

        $this->assertFalse($suggester->isTerminal(null));
        $this->assertFalse($suggester->isTerminal('alpha'));
        $this->assertFalse($suggester->isTerminal('beta'));
        $this->assertTrue($suggester->isTerminal('gamma'));
    }

    public function test_hierarchy_returns_configured_hierarchy(): void
    {
        config(['escalation-ladders.hierarchy' => ['alpha', 'beta', 'gamma']]);

        $suggester = app(EscalationSuggesterInterface::class);

        $this->assertSame(['alpha', 'beta', 'gamma'], $suggester->hierarchy());
    }

    public function test_no_code_references_deprecated_root_escalation_classes(): void
    {
        $deprecatedClasses = [
            'App\\Services\\EscalationLadderSuggester',
            'App\\Services\\EscalationHierarchySuggester',
        ];

        // Directories to scan (exclude vendor, logs, docs, and test that checks for this)
        $scanDirs = [
            base_path('app'),
            base_path('config'),
            base_path('routes'),
        ];

        foreach ($deprecatedClasses as $className) {
            $shortName = class_basename($className);
            $namespace = 'App\\Services\\' . $shortName;

            foreach ($scanDirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $contents = file_get_contents($file->getPathname());

                    // Check for use statements or fully qualified references
                    $this->assertStringNotContainsString(
                        'use ' . $namespace . ';',
                        $contents,
                        "File {$file->getPathname()} still references deprecated class {$namespace}"
                    );
                }
            }
        }
    }

    public function test_deprecated_root_service_files_do_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            base_path('app/Services/EscalationLadderSuggester.php'),
            'Deprecated EscalationLadderSuggester still exists in app/Services/'
        );

        $this->assertFileDoesNotExist(
            base_path('app/Services/EscalationHierarchySuggester.php'),
            'Deprecated EscalationHierarchySuggester still exists in app/Services/'
        );
    }
}
