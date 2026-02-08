<?php

namespace Tests\Unit\Services;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\FactExtractionService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class FactExtractionServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected FactExtractionService $service;

    protected OpenAIService $mockOpenAI;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockOpenAI = $this->createMock(OpenAIService::class);
        $this->service = new FactExtractionService($this->mockOpenAI);
    }

    // ===== Main extractFacts() Tests =====

    /** @test */
    public function it_extracts_facts_successfully_with_llm()
    {
        $decisionId = 'decision-123';
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'legal_issues' => ['Issue 1', 'Issue 2'],
                                'holding' => 'Court granted the relief',
                                'summary' => 'This is a test summary',
                            ]),
                        ],
                    ],
                ],
            ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($decisionId);

        $this->assertTrue($result['success']);
        $this->assertEquals($decisionId, $result['decision_id']);
        $this->assertArrayHasKey('basic_facts', $result);
        $this->assertArrayHasKey('complex_facts', $result);
        $this->assertEquals('hybrid', $result['extraction_method']);
        $this->assertFalse($result['from_cache']);
    }

    /** @test */
    public function it_extracts_facts_without_llm()
    {
        $decisionId = 'decision-123';
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($decisionId, ['use_llm' => false]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('basic_facts', $result);
        $this->assertEmpty($result['complex_facts']);
        $this->assertEquals('pattern-based', $result['extraction_method']);
    }

    /** @test */
    public function it_returns_cached_results_when_available()
    {
        $decisionId = 'decision-123';
        $cachedResult = [
            'success' => true,
            'decision_id' => $decisionId,
            'basic_facts' => [],
            'complex_facts' => [],
        ];

        Cache::shouldReceive('get')
            ->with("fact_extraction:{$decisionId}:llm")
            ->andReturn($cachedResult);

        // DB should not be queried when cache hit
        DB::shouldReceive('table')->never();

        $result = $this->service->extractFacts($decisionId);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['from_cache']);
    }

    /** @test */
    public function it_returns_error_when_decision_not_found()
    {
        $decisionId = 'nonexistent';

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn(null);

        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->extractFacts($decisionId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Decision not found', $result['error']);
    }

    /** @test */
    public function it_handles_llm_extraction_failure_gracefully()
    {
        $decisionId = 'decision-123';
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->will($this->throwException(new \Exception('API Error')));

        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->atLeast()->once();
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($decisionId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('error', $result['complex_facts']);
        $this->assertEquals('API Error', $result['complex_facts']['error']);
    }

    /** @test */
    public function it_respects_cache_disabled_option()
    {
        $decisionId = 'decision-123';
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        // Cache should not be checked or set when disabled
        Cache::shouldReceive('get')->never();
        Cache::shouldReceive('put')->never();

        $result = $this->service->extractFacts($decisionId, [
            'use_llm' => false,
            'use_cache' => false,
        ]);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['from_cache']);
    }

    /** @test */
    public function it_uses_custom_cache_ttl()
    {
        $decisionId = 'decision-123';
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $expiry) {
                // Check that TTL is approximately 120 minutes from now
                $minutesDiff = abs($expiry->diffInMinutes(now()));

                return $minutesDiff >= 119 && $minutesDiff <= 121;
            });

        $result = $this->service->extractFacts($decisionId, [
            'use_llm' => false,
            'cache_ttl' => 120,
        ]);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_tracks_performance_metrics()
    {
        $decisionId = 'decision-123';
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($decisionId, ['use_llm' => false]);

        $this->assertArrayHasKey('performance', $result);
        $this->assertArrayHasKey('total_time', $result['performance']);
        $this->assertIsFloat($result['performance']['total_time']);
        $this->assertGreaterThanOrEqual(0, $result['performance']['total_time']);
    }

    // ===== Party Extraction Tests =====

    /** @test */
    public function it_extracts_plaintiffs_from_croatian_text()
    {
        // Create real decision with Croatian plaintiff text
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P-123/2024',
            'court' => 'Test Court',
        ]);

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'U ovom predmetu tužitelj: Ivan Horvat, tužitelj: Marko Kovač. Podnio je tužbu.',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $parties = $result['basic_facts']['parties'];
        $this->assertContains('Ivan Horvat', $parties['plaintiffs']);
        $this->assertContains('Marko Kovač', $parties['plaintiffs']);
    }

    /** @test */
    public function it_extracts_defendants_from_croatian_text()
    {
        // Create real decision with Croatian defendant text
        $decision = CourtDecision::factory()->create([
            'case_number' => 'P-456/2024',
            'court' => 'Test Court',
        ]);

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Tuženik: Ana Marić, protivnik: Luka Jurić. Protiv njih je podnesena tužba.',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $parties = $result['basic_facts']['parties'];
        $this->assertContains('Ana Marić', $parties['defendants']);
        $this->assertContains('Luka Jurić', $parties['defendants']);
    }

    /** @test */
    public function it_deduplicates_extracted_parties()
    {
        // Create real decision with duplicate party names
        $decision = CourtDecision::factory()->create();

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Tužitelj: Ivan Horvat, predlagatelj: Ivan Horvat. Podnio je zahtjev.',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $parties = $result['basic_facts']['parties'];
        $this->assertCount(1, $parties['plaintiffs']);
        $this->assertContains('Ivan Horvat', $parties['plaintiffs']);
    }

    /** @test */
    public function it_includes_judge_from_metadata()
    {
        $decision = $this->createMockDecision([
            'judge' => 'Sudac Petar Novak',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $parties = $result['basic_facts']['parties'];
        $this->assertEquals('Sudac Petar Novak', $parties['judge']);
    }

    // ===== Date Extraction Tests =====

    /** @test */
    public function it_extracts_filing_date_from_content()
    {
        $decision = $this->createMockDecision([
            'content' => 'Tužba podnesena dana 15. 03. 2024. godine',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertEquals('2024-03-15', $dates['filing_date']);
    }

    /** @test */
    public function it_extracts_hearing_dates_from_content()
    {
        $decision = $this->createMockDecision([
            'content' => 'Rasprava održana dana 20. 04. 2024. Ročište održano 25. 04. 2024.',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertContains('2024-04-20', $dates['hearing_dates']);
        $this->assertContains('2024-04-25', $dates['hearing_dates']);
    }

    /** @test */
    public function it_normalizes_croatian_dates_to_iso_format()
    {
        $decision = $this->createMockDecision([
            'content' => 'Tužba od 5.1.2024. godine',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertEquals('2024-01-05', $dates['filing_date']);
    }

    /** @test */
    public function it_handles_dates_with_extra_spaces()
    {
        $decision = $this->createMockDecision([
            'content' => 'Podnesena 10. 12. 2023.',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertEquals('2023-12-10', $dates['filing_date']);
    }

    /** @test */
    public function it_returns_null_for_invalid_dates()
    {
        $decision = $this->createMockDecision([
            'content' => 'Invalid date 32.13.2024',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertNull($dates['filing_date']);
    }

    /** @test */
    public function it_includes_metadata_dates()
    {
        $decision = $this->createMockDecision([
            'decision_date' => '2024-05-01',
            'publication_date' => '2024-05-15',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertEquals('2024-05-01', $dates['decision_date']);
        $this->assertEquals('2024-05-15', $dates['publication_date']);
    }

    /** @test */
    public function it_deduplicates_hearing_dates()
    {
        $decision = $this->createMockDecision([
            'content' => 'Rasprava 10.05.2024. Ročište 10.05.2024. Saslušanje 10. 05. 2024.',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $dates = $result['basic_facts']['dates'];
        $this->assertCount(1, $dates['hearing_dates']);
        $this->assertContains('2024-05-10', $dates['hearing_dates']);
    }

    // ===== Procedural Posture Tests =====

    /** @test */
    public function it_detects_appeal_from_decision_type()
    {
        // Create real decision with appeal type
        $decision = CourtDecision::factory()->create([
            'decision_type' => 'Presuda - žalba',
        ]);

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertTrue($posture['is_appeal']);
    }

    /** @test */
    public function it_detects_appeal_from_content()
    {
        // Create real decision with appeal content
        $decision = CourtDecision::factory()->create();

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Sud odlučuje o podnesenoj žalba protiv prvostupanjske presude',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertTrue($posture['is_appeal']);
    }

    /** @test */
    public function it_detects_revision_from_decision_type()
    {
        // Create real decision with revision type
        $decision = CourtDecision::factory()->create([
            'decision_type' => 'Odluka - revizija',
        ]);

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Test content',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertTrue($posture['is_revision']);
    }

    /** @test */
    public function it_detects_revision_from_content()
    {
        // Create real decision with revision content
        $decision = CourtDecision::factory()->create();

        $document = CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'content' => 'Sud odlučuje o podnesenoj revizija protiv odluke žalbenog suda',
        ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts($document->id, ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertTrue($posture['is_revision']);
    }

    /** @test */
    public function it_detects_first_instance_from_decision_type()
    {
        $decision = $this->createMockDecision([
            'decision_type' => 'Prvostupanjska presuda',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertTrue($posture['is_first_instance']);
    }

    /** @test */
    public function it_detects_first_instance_from_content()
    {
        $decision = $this->createMockDecision([
            'content' => 'Sud u prvom stupnju donio je prvostupanjsku odluku',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertTrue($posture['is_first_instance']);
    }

    /** @test */
    public function it_extracts_prior_proceedings_references()
    {
        $decision = $this->createMockDecision([
            'content' => 'Prvostepenskom presudom broj P-123/2023 odbijen je zahtjev. Odlukom br. O-456/2022 potvrđeno.',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertContains('P-123/2023', $posture['prior_proceedings']);
        $this->assertContains('O-456/2022', $posture['prior_proceedings']);
    }

    /** @test */
    public function it_deduplicates_prior_proceedings()
    {
        $decision = $this->createMockDecision([
            'content' => 'Presudom P-123/2023 donijeto. Odlukom broj P-123/2023 potvrđeno.',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $posture = $result['basic_facts']['procedural_posture'];
        $this->assertCount(1, $posture['prior_proceedings']);
    }

    // ===== LLM Complex Fact Extraction Tests =====

    /** @test */
    public function it_extracts_complex_facts_using_llm()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $llmResponse = [
            'legal_issues' => ['Ownership dispute', 'Contract breach'],
            'holding' => 'Plaintiff prevailed',
            'summary' => 'Case summary',
            'legal_grounds' => ['Article 123', 'Article 456'],
        ];

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->with(
                $this->anything(), // messages
                $this->equalTo('gpt-4o'), // model
                $this->callback(function ($options) {
                    return $options['temperature'] === 0.1 &&
                           isset($options['response_format']) &&
                           $options['response_format']['type'] === 'json_object';
                }) // options
            )
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => json_encode($llmResponse)]],
                ],
            ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123');

        $this->assertArrayHasKey('complex_facts', $result);
        $this->assertEquals($llmResponse['legal_issues'], $result['complex_facts']['legal_issues']);
        $this->assertEquals($llmResponse['holding'], $result['complex_facts']['holding']);
    }

    /** @test */
    public function it_limits_content_length_for_llm()
    {
        $longContent = str_repeat('A', 10000);
        $decision = $this->createMockDecision(['content' => $longContent]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->with($this->callback(function ($messages) {
                // Check that content in prompt is limited to 8000 chars
                // $messages is the first parameter (array of messages)
                $prompt = $messages[1]['content'];

                // Content sample is 8000 chars + template overhead (around 2000-3000 chars)
                return mb_strlen($prompt) < 12000; // Account for prompt template overhead
            }))
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => json_encode(['summary' => 'test'])]],
                ],
            ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123');
        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_handles_llm_json_parsing_errors()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => 'Invalid JSON response']],
                ],
            ]);

        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('error', $result['complex_facts']);
        $this->assertEquals('Failed to parse LLM response', $result['complex_facts']['error']);
    }

    /** @test */
    public function it_cleans_markdown_code_blocks_from_llm_response()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $jsonData = ['summary' => 'test'];
        $markdownResponse = "```json\n".json_encode($jsonData)."\n```";

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => $markdownResponse]],
                ],
            ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('test', $result['complex_facts']['summary']);
    }

    /** @test */
    public function it_handles_empty_llm_response()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => null]],
                ],
            ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('error', $result['complex_facts']);
        $this->assertEquals('Empty LLM response', $result['complex_facts']['error']);
    }

    /** @test */
    public function it_logs_llm_extraction_errors()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        $exception = new \Exception('Test error');

        $this->mockOpenAI->expects($this->once())
            ->method('chat')
            ->will($this->throwException($exception));

        Log::shouldReceive('error')->atLeast()->once()->withArgs(function ($message, $context) {
            return $message === 'LLM fact extraction failed' &&
                   $context['error'] === 'Test error';
        });
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('Test error', $result['complex_facts']['error']);
    }

    // ===== Case Metadata Tests =====

    /** @test */
    public function it_extracts_case_metadata()
    {
        $decision = $this->createMockDecision([
            'case_number' => 'P-123/2024',
            'court' => 'Općinski sud u Zagrebu',
            'jurisdiction' => 'Croatia',
            'decision_type' => 'Presuda',
            'ecli' => 'HR:OSŽG:2024:123',
            'finality' => 'Final',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->andReturn($decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $result = $this->service->extractFacts('decision-123', ['use_llm' => false]);

        $metadata = $result['basic_facts']['case_metadata'];
        $this->assertEquals('P-123/2024', $metadata['case_number']);
        $this->assertEquals('Općinski sud u Zagrebu', $metadata['court']);
        $this->assertEquals('Croatia', $metadata['jurisdiction']);
        $this->assertEquals('Presuda', $metadata['decision_type']);
        $this->assertEquals('HR:OSŽG:2024:123', $metadata['ecli']);
        $this->assertEquals('Final', $metadata['finality']);
    }

    // ===== Decision Comparison Tests =====

    /** @test */
    public function it_compares_facts_between_two_decisions()
    {
        $decision1 = $this->createMockDecision(['court' => 'Court A', 'jurisdiction' => 'Croatia']);
        $decision2 = $this->createMockDecision(['court' => 'Court A', 'jurisdiction' => 'Croatia']);

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision1, $decision2);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->twice();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2', ['use_llm' => false]);

        $this->assertTrue($result['success']);
        $this->assertEquals('dec-1', $result['decision1']['id']);
        $this->assertEquals('dec-2', $result['decision2']['id']);
        $this->assertArrayHasKey('basic_similarity', $result);
        $this->assertTrue($result['basic_similarity']['same_court']);
    }

    /** @test */
    public function it_returns_error_when_comparison_fails()
    {
        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn(null);

        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to extract facts', $result['error']);
    }

    /** @test */
    public function it_detects_same_court_in_comparison()
    {
        $decision1 = $this->createMockDecision(['court' => 'Općinski sud u Zagrebu']);
        $decision2 = $this->createMockDecision(['court' => 'Općinski sud u Zagrebu']);

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision1, $decision2);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->twice();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2', ['use_llm' => false]);

        $this->assertTrue($result['basic_similarity']['same_court']);
    }

    /** @test */
    public function it_detects_different_courts_in_comparison()
    {
        $decision1 = $this->createMockDecision(['court' => 'Court A']);
        $decision2 = $this->createMockDecision(['court' => 'Court B']);

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision1, $decision2);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->twice();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2', ['use_llm' => false]);

        $this->assertFalse($result['basic_similarity']['same_court']);
    }

    /** @test */
    public function it_finds_shared_parties_in_comparison()
    {
        $decision1 = $this->createMockDecision([
            'content' => 'Tužitelj: Ivan Horvat, Tužitelj: Ana Marić',
        ]);
        $decision2 = $this->createMockDecision([
            'content' => 'Tužitelj: Ivan Horvat, Tužitelj: Petar Novak',
        ]);

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision1, $decision2);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->twice();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2', ['use_llm' => false]);

        $sharedParties = $result['basic_similarity']['shared_parties'];
        $this->assertContains('Ivan Horvat', $sharedParties['plaintiffs']);
    }

    /** @test */
    public function it_compares_complex_facts_when_available()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision, $decision);

        $llmResponse = [
            'legal_issues' => ['Issue A', 'Issue B'],
            'legal_grounds' => ['Article 1', 'Article 2'],
            'holding' => 'Test holding',
            'relief_sought' => 'Damages',
        ];

        $this->mockOpenAI->expects($this->exactly(2))
            ->method('chat')
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => json_encode($llmResponse)]],
                ],
            ]);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->twice();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2');

        $this->assertArrayHasKey('complex_similarity', $result);
        $this->assertCount(2, $result['complex_similarity']['shared_legal_issues']);
    }

    /** @test */
    public function it_handles_complex_facts_comparison_errors()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision, $decision);

        $this->mockOpenAI->expects($this->exactly(2))
            ->method('chat')
            ->will($this->throwException(new \Exception('API error')));

        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('info')->andReturn(null);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->atLeast()->once();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('error', $result['complex_similarity']);
    }

    /** @test */
    public function it_uses_string_similarity_for_text_comparison()
    {
        $decision1 = $this->createMockDecision();
        $decision2 = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->twice()
            ->andReturn($decision1, $decision2);

        $llmResponse1 = [
            'holding' => 'The plaintiff is entitled to damages',
            'relief_sought' => 'Monetary compensation',
        ];
        $llmResponse2 = [
            'holding' => 'The plaintiff is entitled to damages',
            'relief_sought' => 'Financial reimbursement',
        ];

        $this->mockOpenAI->expects($this->exactly(2))
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                ['choices' => [['message' => ['content' => json_encode($llmResponse1)]]]],
                ['choices' => [['message' => ['content' => json_encode($llmResponse2)]]]]
            );

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->twice();

        $result = $this->service->compareDecisionFacts('dec-1', 'dec-2');

        // Holdings are identical, so should be similar
        $this->assertTrue($result['complex_similarity']['similar_holdings']);
        // Relief sought are different but somewhat similar
        $this->assertIsBool($result['complex_similarity']['similar_relief_sought']);
    }

    // ===== Batch Extraction Tests =====

    /** @test */
    public function it_extracts_facts_from_multiple_decisions()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->times(3)
            ->andReturn($decision, $decision, $decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->times(3);

        $result = $this->service->batchExtractFacts(['dec-1', 'dec-2', 'dec-3'], ['use_llm' => false]);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['extracted']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['results']);
    }

    /** @test */
    public function it_handles_failures_in_batch_extraction()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->times(3)
            ->andReturn($decision, null, $decision);

        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->times(2);

        $result = $this->service->batchExtractFacts(['dec-1', 'dec-2', 'dec-3'], ['use_llm' => false]);

        $this->assertFalse($result['success']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(2, $result['extracted']);
        $this->assertEquals(1, $result['failed']);
    }

    /** @test */
    public function it_logs_batch_extraction_errors()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->once()
            ->andThrow(new \Exception('Database error'));

        // extractFacts calls Log::error, batchExtractFacts calls Log::warning for per-decision errors
        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $result = $this->service->batchExtractFacts(['dec-1'], ['use_llm' => false]);

        $this->assertFalse($result['success']);
        $this->assertEquals(1, $result['failed']);
        $this->assertArrayHasKey('dec-1', $result['errors']);
    }

    /** @test */
    public function it_continues_batch_processing_after_errors()
    {
        $decision = $this->createMockDecision();

        DB::shouldReceive('table->join->where->select->first')
            ->times(4)
            ->andReturnUsing(function () use ($decision) {
                static $calls = 0;
                $calls++;
                if ($calls === 2) {
                    throw new \Exception('Error on second call');
                }

                return $decision;
            });

        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->atLeast()->once();

        $result = $this->service->batchExtractFacts(['dec-1', 'dec-2', 'dec-3', 'dec-4'], ['use_llm' => false]);

        $this->assertEquals(4, $result['total']);
        $this->assertEquals(3, $result['extracted']);
        $this->assertEquals(1, $result['failed']);
    }

    // ===== Helper Methods =====

    protected function createMockDecision(array $overrides = []): object
    {
        $defaults = [
            'id' => 'doc-123',
            'decision_id' => 'dec-123',
            'content' => 'Test decision content',
            'case_number' => 'P-123/2024',
            'title' => 'Test Case',
            'court' => 'Test Court',
            'jurisdiction' => 'Croatia',
            'judge' => 'Test Judge',
            'decision_date' => '2024-05-01',
            'publication_date' => '2024-05-15',
            'decision_type' => 'Presuda',
            'ecli' => 'HR:TEST:2024:123',
            'finality' => 'Final',
        ];

        return (object) array_merge($defaults, $overrides);
    }
}
