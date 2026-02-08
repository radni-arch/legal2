<?php

namespace Tests\Unit\DTOs\Defense;

use App\DTOs\Defense\DefenseFlag;
use App\DTOs\Defense\DefenseReport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DefenseReportTest extends TestCase
{
    /** @test */
    public function it_creates_defense_report_with_all_properties(): void
    {
        $generatedAt = Carbon::parse('2024-02-04 12:00:00');
        $flags = [
            new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_CRITICAL,
                title: 'APSOLUTNA ZASTARA ISTEKLA',
                description: 'Kazneno djelo je zastarjelo.',
                legalBasis: 'cl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Podnijeti prigovor.',
                confidence: 0.95,
            ),
            new DefenseFlag(
                tactic: 'ne_bis_in_idem',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: 'Dvostruko gonjenje',
                description: 'Paralelni postupci.',
                legalBasis: 'cl. 31. Ustav RH',
                echrBasis: 'Maresti v. Croatia (2009)',
                evidence: [],
                recommendedAction: 'Istaknuti prigovor.',
                confidence: 0.75,
            ),
        ];

        $report = new DefenseReport(
            caseId: 'case-123',
            flags: $flags,
            generatedAt: $generatedAt,
            processingTime: 2.5,
            detectorsRun: ['zastara', 'ne_bis_in_idem', 'chain_of_custody'],
        );

        $this->assertEquals('case-123', $report->caseId);
        $this->assertCount(2, $report->flags);
        $this->assertInstanceOf(DefenseFlag::class, $report->flags[0]);
        $this->assertEquals($generatedAt, $report->generatedAt);
        $this->assertEquals(2.5, $report->processingTime);
        $this->assertEquals(['zastara', 'ne_bis_in_idem', 'chain_of_custody'], $report->detectorsRun);
    }

    /** @test */
    public function it_creates_report_with_empty_flags(): void
    {
        $report = new DefenseReport(
            caseId: 'case-empty',
            flags: [],
            generatedAt: Carbon::now(),
            processingTime: 0.1,
            detectorsRun: ['zastara'],
        );

        $this->assertEquals('case-empty', $report->caseId);
        $this->assertEmpty($report->flags);
    }

    /** @test */
    public function it_converts_to_array(): void
    {
        $generatedAt = Carbon::parse('2024-02-04 14:30:00');
        $flag = new DefenseFlag(
            tactic: 'chain_of_custody',
            severity: DefenseFlag::SEVERITY_MEDIUM,
            title: 'Gap in custody',
            description: '90 day gap found.',
            legalBasis: 'cl. 250. ZKP',
            echrBasis: null,
            evidence: ['gap_days' => 90],
            recommendedAction: 'Request documentation.',
            confidence: 0.7,
        );

        $report = new DefenseReport(
            caseId: 'case-456',
            flags: [$flag],
            generatedAt: $generatedAt,
            processingTime: 1.23,
            detectorsRun: ['chain_of_custody'],
        );

        $array = $report->toArray();

        $this->assertEquals('case-456', $array['case_id']);
        $this->assertCount(1, $array['flags']);
        $this->assertIsArray($array['flags'][0]);
        $this->assertEquals('chain_of_custody', $array['flags'][0]['tactic']);
        $this->assertEquals('2024-02-04 14:30:00', $array['generated_at']);
        $this->assertEquals(1.23, $array['processing_time']);
        $this->assertEquals(['chain_of_custody'], $array['detectors_run']);
        $this->assertArrayHasKey('summary', $array);
    }

    /** @test */
    public function it_generates_summary_statistics(): void
    {
        $flags = [
            new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_CRITICAL,
                title: 'Critical 1',
                description: 'Desc',
                legalBasis: 'Test',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Action',
                confidence: 0.9,
            ),
            new DefenseFlag(
                tactic: 'time',
                severity: DefenseFlag::SEVERITY_CRITICAL,
                title: 'Critical 2',
                description: 'Desc',
                legalBasis: 'Test',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Action',
                confidence: 0.85,
            ),
            new DefenseFlag(
                tactic: 'ne_bis',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: 'High 1',
                description: 'Desc',
                legalBasis: 'Test',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Action',
                confidence: 0.75,
            ),
            new DefenseFlag(
                tactic: 'custody',
                severity: DefenseFlag::SEVERITY_MEDIUM,
                title: 'Medium 1',
                description: 'Desc',
                legalBasis: 'Test',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Action',
                confidence: 0.6,
            ),
            new DefenseFlag(
                tactic: 'info_only',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Info 1',
                description: 'Desc',
                legalBasis: 'Test',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Action',
                confidence: 0.5,
            ),
        ];

        $report = new DefenseReport(
            caseId: 'case-summary',
            flags: $flags,
            generatedAt: Carbon::now(),
            processingTime: 3.0,
            detectorsRun: ['zastara', 'time', 'ne_bis', 'custody', 'info_only'],
        );

        $array = $report->toArray();
        $summary = $array['summary'];

        $this->assertEquals(5, $summary['total_flags']);
        $this->assertEquals(2, $summary['critical_count']);
        $this->assertEquals(1, $summary['high_count']);
        $this->assertEquals(1, $summary['medium_count']);
        $this->assertEquals(0, $summary['low_count']);
        $this->assertEquals(1, $summary['info_count']);
    }
}
