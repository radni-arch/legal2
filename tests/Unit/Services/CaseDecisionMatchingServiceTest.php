<?php

namespace Tests\Unit\Services;

use App\Models\CourtCase;
use App\Models\CourtDecision;
use App\Services\CaseDecisionMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDecisionMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaseDecisionMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CaseDecisionMatchingService();
    }

    public function test_parses_case_number_correctly(): void
    {
        $result = $this->service->parseCaseNumber('Pp Prz-75/2025');

        $this->assertEquals('Pp Prz-75', $result['base']);
        $this->assertEquals(2025, $result['year']);
    }

    public function test_parses_case_number_with_different_formats(): void
    {
        $formats = [
            'Pp Prz-123/2024' => ['base' => 'Pp Prz-123', 'year' => 2024],
            'Kv-456/2023' => ['base' => 'Kv-456', 'year' => 2023],
            'Kov-1/2025' => ['base' => 'Kov-1', 'year' => 2025],
        ];

        foreach ($formats as $input => $expected) {
            $result = $this->service->parseCaseNumber($input);
            $this->assertEquals($expected['base'], $result['base'], "Failed for: $input");
            $this->assertEquals($expected['year'], $result['year'], "Failed for: $input");
        }
    }

    public function test_match_case_finds_exact_match(): void
    {
        $court = \App\Models\Court::create([
            'external_id' => 1,
            'name' => 'Općinski sud u Zagrebu',
            'code' => 'OSZG',
        ]);

        $case = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-75/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 75,
        ]);

        $decision = \App\Models\CourtDecision::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'case_number' => 'Pp Prz-75/2025',
            'decision_date' => '2025-06-15',
            'court' => 'Općinski sud u Zagrebu',
        ]);

        $match = $this->service->matchCase($case, 'test');

        $this->assertNotNull($match);
        $this->assertEquals($decision->id, $match->court_decision_id);
        $this->assertGreaterThanOrEqual(80, $match->match_confidence);
    }

    public function test_match_case_returns_null_when_no_match(): void
    {
        $court = \App\Models\Court::create([
            'external_id' => 2,
            'name' => 'Test Court',
            'code' => 'TC',
        ]);

        $case = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-999/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 999,
        ]);

        $match = $this->service->matchCase($case, 'test');

        $this->assertNull($match);
    }

    public function test_match_unmatched_cases_processes_batch(): void
    {
        $court = \App\Models\Court::create([
            'external_id' => 10,
            'name' => 'Batch Test Court',
            'code' => 'BTC',
        ]);

        // Create 3 unmatched cases
        $case1 = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-101/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 101,
        ]);

        $case2 = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-102/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 102,
        ]);

        $case3 = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-103/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 103,
        ]);

        // Create matching decisions for 2 of them
        \App\Models\CourtDecision::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'case_number' => 'Pp Prz-101/2025',
            'decision_date' => '2025-06-15',
        ]);

        \App\Models\CourtDecision::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'case_number' => 'Pp Prz-102/2025',
            'decision_date' => '2025-06-15',
        ]);

        $result = $this->service->matchUnmatchedCases(10, 'batch_test');

        $this->assertEquals(3, $result->processedCount);
        $this->assertEquals(2, $result->matchedCount);
        $this->assertEquals(1, $result->unmatchedCount);
    }

    public function test_create_manual_match(): void
    {
        $court = \App\Models\Court::create([
            'external_id' => 20,
            'name' => 'Manual Test Court',
            'code' => 'MTC',
        ]);

        $case = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-200/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 200,
        ]);

        $decision = \App\Models\CourtDecision::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'case_number' => 'Pp Prz-200/2025',
            'decision_date' => '2025-06-15',
        ]);

        $match = $this->service->createManualMatch($case, $decision, 'Manually verified');

        $this->assertEquals('manual', $match->match_type);
        $this->assertEquals(100, $match->match_confidence);
        $this->assertEquals('verified', $match->verification_status);
        $this->assertEquals('Manually verified', $match->notes);
    }

    public function test_verify_match(): void
    {
        $court = \App\Models\Court::create([
            'external_id' => 21,
            'name' => 'Verify Test Court',
            'code' => 'VTC',
        ]);

        $case = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-201/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 201,
        ]);

        $match = \App\Models\CourtCaseDecisionMatch::create([
            'court_case_id' => $case->id,
            'court_decision_id' => \Illuminate\Support\Str::ulid(),
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 80,
            'match_source' => 'test',
            'verification_status' => 'pending',
            'match_criteria' => [],
        ]);

        $this->service->verifyMatch($match);

        $this->assertEquals('verified', $match->fresh()->verification_status);
    }

    public function test_reject_match(): void
    {
        $court = \App\Models\Court::create([
            'external_id' => 22,
            'name' => 'Reject Test Court',
            'code' => 'RTC',
        ]);

        $case = \App\Models\CourtCase::create([
            'case_number' => 'Pp Prz-202/2025',
            'year' => 2025,
            'court_id' => $court->id,
            'register' => 'Pp Prz',
            'number' => 202,
        ]);

        $match = \App\Models\CourtCaseDecisionMatch::create([
            'court_case_id' => $case->id,
            'court_decision_id' => \Illuminate\Support\Str::ulid(),
            'matched_at' => now(),
            'match_type' => 'auto',
            'match_confidence' => 60,
            'match_source' => 'test',
            'verification_status' => 'pending',
            'match_criteria' => [],
        ]);

        $this->service->rejectMatch($match, 'Wrong case');

        $match->refresh();
        $this->assertEquals('rejected', $match->verification_status);
        $this->assertStringContainsString('Wrong case', $match->notes);
    }
}
