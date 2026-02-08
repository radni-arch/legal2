<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\LegalArtillery\Argument;
use App\DTOs\LegalArtillery\ArgumentChain;
use App\DTOs\LegalArtillery\ChainValidationResult;
use App\Services\LegalArtillery\ArgumentValidator;
use App\Services\LegalArtillery\DevastatingArgumentBuilder;
use App\Services\LegalArtillery\LlmClient;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class ArgumentValidatorTest extends TestCase
{
    private ArgumentValidator $validator;
    private LlmClient $llmMock;
    private DevastatingArgumentBuilder $argumentBuilderMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->llmMock = Mockery::mock(LlmClient::class);
        $this->argumentBuilderMock = Mockery::mock(DevastatingArgumentBuilder::class);
        $this->validator = new ArgumentValidator($this->llmMock, $this->argumentBuilderMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Tests for validateArgumentChain()
    // =========================================================================

    public function test_validate_includes_chain_evaluation_and_parses_response(): void
    {
        $content = 'Testni dokument s argumentima.';
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'procedural_irregularity',
                    provisions: ['PZ čl.150 st.4'],
                    precedents: ['VSRH Kz-789/2020'],
                    strength: 7,
                    text: 'Nezakonit dokaz vodi do isključenja dokaza.',
                ),
            ],
            combinedStrength: 7,
            vulnerabilities: ['Slaba veza s presudom'],
            recommendations: ['Dodati više presuda'],
        );

        $this->argumentBuilderMock->shouldReceive('getChainsForProfile')
            ->once()
            ->with('ustavni_sud')
            ->andReturn([$chain]);

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->withArgs(function (string $systemPrompt, string $userPrompt, int $tokens): bool {
                $this->assertStringContainsString('ARGUMENTACIJSKI LANCI ZA PROFIL', $userPrompt);
                $this->assertStringContainsString('logicku kontinuitet', mb_strtolower($userPrompt));
                $this->assertStringContainsString('nokaut', mb_strtolower($userPrompt));
                $this->assertStringContainsString('Korak 1', $userPrompt);
                $this->assertEquals(2048, $tokens);

                return true;
            })
            ->andReturn(json_encode([
                'overall_score' => 8,
                'citation_accuracy' => 8,
                'argument_strength' => 8,
                'logical_coherence' => 7,
                'chain_evaluation' => [
                    'logical_continuity' => 8,
                    'knockout_inevitability' => 7,
                    'vulnerabilities' => ['Slaba veza s presudom'],
                ],
                'verdict' => 'DEVASTATING',
                'issues' => [],
                'improvements' => ['Dodati više presuda'],
                'strengths' => ['Argumenti su povezani'],
            ]));

        $result = $this->validator->validate($content, 'ustavni_sud');

        $this->assertArrayHasKey('chain_evaluation', $result);
        $this->assertEquals(8, $result['chain_evaluation']['logical_continuity']);
        $this->assertEquals(7, $result['chain_evaluation']['knockout_inevitability']);
        $this->assertEquals(['Slaba veza s presudom'], $result['chain_evaluation']['vulnerabilities']);
    }

    public function test_validate_argument_chain_returns_chain_validation_result(): void
    {
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'procedural_irregularity',
                    provisions: ['PZ čl.150 st.4'],
                    precedents: [],
                    strength: 8,
                    text: 'Nezakoniti dokaz vodi do nedopuštenog dokaza',
                ),
                new Argument(
                    type: 'exclusionary_rule',
                    provisions: ['PZ čl.150 st.4'],
                    precedents: [],
                    strength: 8,
                    text: 'Dokaz je nedopušten i ne može se koristiti',
                ),
            ],
            combinedStrength: 8,
            vulnerabilities: [],
            recommendations: [],
        );

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'chain_score' => 85,
                'argument_scores' => [
                    ['step' => 0, 'score' => 90, 'reasoning' => 'Solid premise'],
                    ['step' => 1, 'score' => 80, 'reasoning' => 'Good conclusion'],
                ],
                'weak_links' => [],
                'improvement_priority' => ['Add more case law'],
            ]));

        $result = $this->validator->validateArgumentChain($chain);

        $this->assertInstanceOf(ChainValidationResult::class, $result);
        $this->assertEquals(85, $result->chainScore);
        $this->assertCount(2, $result->argumentScores);
        $this->assertIsArray($result->weakLinks);
        $this->assertIsArray($result->improvementPriority);
    }

    public function test_validate_argument_chain_identifies_weak_links(): void
    {
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'possible_violation',
                    provisions: ['PZ čl.150 st.4'],
                    precedents: [],
                    strength: 5,
                    text: 'Possible violation therefore invalid',
                ),
                new Argument(
                    type: 'exclusionary',
                    provisions: [],
                    precedents: [],
                    strength: 4,
                    text: 'Therefore invalid must be excluded',
                ),
                new Argument(
                    type: 'conclusion',
                    provisions: [],
                    precedents: [],
                    strength: 3,
                    text: 'Must be excluded case dismissed',
                ),
            ],
            combinedStrength: 5,
            vulnerabilities: ['Gap in logic', 'Leap in reasoning'],
            recommendations: ['Add case law'],
        );

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'chain_score' => 45,
                'argument_scores' => [
                    ['step' => 0, 'score' => 60, 'reasoning' => 'Weak premise'],
                    ['step' => 1, 'score' => 40, 'reasoning' => 'Gap in logic'],
                    ['step' => 2, 'score' => 35, 'reasoning' => 'Unsupported conclusion'],
                ],
                'weak_links' => [
                    ['step' => 1, 'issue' => 'Gap between premise and conclusion'],
                    ['step' => 2, 'issue' => 'Leap in reasoning'],
                ],
                'improvement_priority' => [
                    'Strengthen step 1 with case law',
                    'Add intermediate step before conclusion',
                ],
            ]));

        $result = $this->validator->validateArgumentChain($chain);

        $this->assertEquals(45, $result->chainScore);
        $this->assertCount(2, $result->weakLinks);
        $this->assertEquals('Gap between premise and conclusion', $result->weakLinks[0]['issue']);
    }

    public function test_validate_argument_chain_calculates_combined_score(): void
    {
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'legal_basis',
                    provisions: ['PZ čl.150'],
                    precedents: [],
                    strength: 7,
                    text: 'Step 1 leads to Result 1',
                ),
            ],
            combinedStrength: 7,
            vulnerabilities: [],
            recommendations: [],
        );

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'chain_score' => 70,
                'argument_scores' => [['step' => 0, 'score' => 70, 'reasoning' => 'OK']],
                'weak_links' => [],
                'improvement_priority' => [],
            ]));

        $result = $this->validator->validateArgumentChain($chain);

        $this->assertGreaterThanOrEqual(0, $result->chainScore);
        $this->assertLessThanOrEqual(100, $result->chainScore);
    }

    // =========================================================================
    // Tests for checkCitationAccuracy()
    // =========================================================================

    public function test_check_citation_accuracy_finds_valid_provisions(): void
    {
        $content = 'Temeljem članka 150. stavak 4. Prekršajnog zakona (PZ), svi dokazi prikupljeni protuzakonitom pretragom su nedopušteni.';

        $provisions = new Collection([
            (object) ['id' => 1, 'article' => '150', 'paragraph' => '4', 'law' => 'PZ', 'text' => 'Dokazi pribavljeni...'],
            (object) ['id' => 2, 'article' => '18', 'paragraph' => '1', 'law' => 'Ustav', 'text' => 'Nepovredivost doma...'],
        ]);

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'citations_found' => [
                    ['article' => '150', 'paragraph' => '4', 'law' => 'PZ', 'valid' => true, 'provision_id' => 1],
                ],
                'accuracy_score' => 100,
                'issues' => [],
            ]));

        $result = $this->validator->checkCitationAccuracy($content, $provisions);

        $this->assertArrayHasKey('citations_found', $result);
        $this->assertArrayHasKey('accuracy_score', $result);
        $this->assertCount(1, $result['citations_found']);
        $this->assertTrue($result['citations_found'][0]['valid']);
    }

    public function test_check_citation_accuracy_reports_invalid_citations(): void
    {
        $content = 'Prema članku 999. stavak 99. nepostojećeg zakona (FAKE), sve je nezakonito.';

        $provisions = new Collection([
            (object) ['id' => 1, 'article' => '150', 'paragraph' => '4', 'law' => 'PZ', 'text' => 'Dokazi pribavljeni...'],
        ]);

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'citations_found' => [
                    ['article' => '999', 'paragraph' => '99', 'law' => 'FAKE', 'valid' => false, 'provision_id' => null, 'error' => 'Provision not found in database'],
                ],
                'accuracy_score' => 0,
                'issues' => ['Citation to non-existent provision: FAKE čl.999 st.99'],
            ]));

        $result = $this->validator->checkCitationAccuracy($content, $provisions);

        $this->assertFalse($result['citations_found'][0]['valid']);
        $this->assertNotEmpty($result['issues']);
        $this->assertEquals(0, $result['accuracy_score']);
    }

    public function test_check_citation_accuracy_handles_empty_content(): void
    {
        $content = '';
        $provisions = new Collection();

        $result = $this->validator->checkCitationAccuracy($content, $provisions);

        $this->assertArrayHasKey('citations_found', $result);
        $this->assertEmpty($result['citations_found']);
        $this->assertEquals(100, $result['accuracy_score']); // No citations = 100% accurate
    }

    // =========================================================================
    // Tests for checkPrecedentUsage()
    // =========================================================================

    public function test_check_precedent_usage_finds_valid_precedents(): void
    {
        $content = 'Kako je utvrđeno presudom Ustavnog suda U-III-2340/2015, nezakonita pretraga povređuje ustavna prava.';

        $precedents = new Collection([
            (object) ['id' => 1, 'case_number' => 'U-III-2340/2015', 'court' => 'USRH', 'key_principle' => 'Nezakoniti dokazi...'],
            (object) ['id' => 2, 'case_number' => 'Kž-123/2020', 'court' => 'VSRH', 'key_principle' => 'Standard dokazivanja...'],
        ]);

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'precedents_found' => [
                    ['case_number' => 'U-III-2340/2015', 'valid' => true, 'precedent_id' => 1, 'usage_correct' => true],
                ],
                'usage_score' => 100,
                'issues' => [],
            ]));

        $result = $this->validator->checkPrecedentUsage($content, $precedents);

        $this->assertArrayHasKey('precedents_found', $result);
        $this->assertCount(1, $result['precedents_found']);
        $this->assertTrue($result['precedents_found'][0]['valid']);
        $this->assertEquals(100, $result['usage_score']);
    }

    public function test_check_precedent_usage_reports_invalid_precedents(): void
    {
        $content = 'Presuda FAKE-999/9999 jasno pokazuje da je sve nezakonito.';

        $precedents = new Collection([
            (object) ['id' => 1, 'case_number' => 'U-III-2340/2015', 'court' => 'USRH', 'key_principle' => 'Nezakoniti dokazi...'],
        ]);

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'precedents_found' => [
                    ['case_number' => 'FAKE-999/9999', 'valid' => false, 'precedent_id' => null, 'error' => 'Precedent not found'],
                ],
                'usage_score' => 0,
                'issues' => ['Reference to non-existent precedent: FAKE-999/9999'],
            ]));

        $result = $this->validator->checkPrecedentUsage($content, $precedents);

        $this->assertFalse($result['precedents_found'][0]['valid']);
        $this->assertNotEmpty($result['issues']);
    }

    public function test_check_precedent_usage_handles_empty_content(): void
    {
        $content = '';
        $precedents = new Collection();

        $result = $this->validator->checkPrecedentUsage($content, $precedents);

        $this->assertArrayHasKey('precedents_found', $result);
        $this->assertEmpty($result['precedents_found']);
        $this->assertEquals(100, $result['usage_score']);
    }

    // =========================================================================
    // Tests for assessDevastationLevel()
    // =========================================================================

    public function test_assess_devastation_level_returns_score_between_1_and_10(): void
    {
        $content = 'Ovaj pravni podnesak temelji se na jasnim zakonskim odredbama i nepobijivoj sudskoj praksi.';

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'devastation_level' => 8,
                'factors' => [
                    'legal_foundation' => 9,
                    'logical_chain' => 8,
                    'precedent_support' => 7,
                    'opponent_rebuttal_difficulty' => 8,
                ],
                'assessment' => 'Strong document with solid foundation',
            ]));

        $score = $this->validator->assessDevastationLevel($content);

        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(1, $score);
        $this->assertLessThanOrEqual(10, $score);
        $this->assertEquals(8, $score);
    }

    public function test_assess_devastation_level_weak_document(): void
    {
        $content = 'Mislim da bi ovo moglo biti nezakonito, ali nisam siguran.';

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'devastation_level' => 2,
                'factors' => [
                    'legal_foundation' => 2,
                    'logical_chain' => 3,
                    'precedent_support' => 1,
                    'opponent_rebuttal_difficulty' => 2,
                ],
                'assessment' => 'Weak document lacking legal foundation',
            ]));

        $score = $this->validator->assessDevastationLevel($content);

        $this->assertEquals(2, $score);
    }

    public function test_assess_devastation_level_devastating_document(): void
    {
        $content = 'Temeljem članka 150. PZ, članka 18. Ustava, i presuda USRH U-III-2340/2015 te ECHR Smirnov v. Russia, nezakonito prikupljeni dokazi MORAJU biti isključeni. Svaki korak logički proizlazi iz prethodnog, a protivnička strana nema pravno utemeljenog odgovora.';

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'devastation_level' => 10,
                'factors' => [
                    'legal_foundation' => 10,
                    'logical_chain' => 10,
                    'precedent_support' => 10,
                    'opponent_rebuttal_difficulty' => 10,
                ],
                'assessment' => 'DEVASTATING - Opponent has no viable response',
            ]));

        $score = $this->validator->assessDevastationLevel($content);

        $this->assertEquals(10, $score);
    }

    public function test_assess_devastation_level_clamps_invalid_scores(): void
    {
        $content = 'Test document';

        // LLM returns invalid score
        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'devastation_level' => 15, // Invalid: above 10
                'factors' => [],
                'assessment' => 'Test',
            ]));

        $score = $this->validator->assessDevastationLevel($content);

        // Should be clamped to 10
        $this->assertEquals(10, $score);
    }

    public function test_assess_devastation_level_clamps_negative_scores(): void
    {
        $content = 'Test document';

        // LLM returns invalid score
        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'devastation_level' => -5, // Invalid: below 1
                'factors' => [],
                'assessment' => 'Test',
            ]));

        $score = $this->validator->assessDevastationLevel($content);

        // Should be clamped to 1
        $this->assertEquals(1, $score);
    }

    // =========================================================================
    // Tests for combined strength validation
    // =========================================================================

    public function test_combined_strength_exceeds_threshold(): void
    {
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'strong_legal_basis',
                    provisions: ['PZ čl.150', 'Ustav čl.18'],
                    precedents: [],
                    strength: 9,
                    text: 'Strong legal basis leads to clear outcome',
                ),
            ],
            combinedStrength: 9,
            vulnerabilities: [],
            recommendations: [],
        );

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'chain_score' => 90,
                'argument_scores' => [['step' => 0, 'score' => 90, 'reasoning' => 'Excellent']],
                'weak_links' => [],
                'improvement_priority' => [],
            ]));

        $result = $this->validator->validateArgumentChain($chain);

        // Combined strength should exceed typical threshold (e.g., 70)
        $this->assertTrue($result->chainScore >= 70);
        $this->assertTrue($result->exceedsThreshold(70));
    }

    public function test_combined_strength_below_threshold(): void
    {
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'weak_premise',
                    provisions: [],
                    precedents: [],
                    strength: 3,
                    text: 'Weak premise leads to uncertain conclusion',
                ),
            ],
            combinedStrength: 3,
            vulnerabilities: ['Missing legal basis'],
            recommendations: ['Add legal citations'],
        );

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'chain_score' => 30,
                'argument_scores' => [['step' => 0, 'score' => 30, 'reasoning' => 'Weak']],
                'weak_links' => [['step' => 0, 'issue' => 'Missing legal basis']],
                'improvement_priority' => ['Add legal citations'],
            ]));

        $result = $this->validator->validateArgumentChain($chain);

        $this->assertTrue($result->chainScore < 70);
        $this->assertFalse($result->exceedsThreshold(70));
    }

    // =========================================================================
    // Tests for edge cases and error handling
    // =========================================================================

    public function test_handles_malformed_llm_response_for_chain_validation(): void
    {
        $chain = new ArgumentChain(
            arguments: [
                new Argument(
                    type: 'test',
                    provisions: [],
                    precedents: [],
                    strength: 5,
                    text: 'Test argument A leads to B',
                ),
            ],
            combinedStrength: 5,
            vulnerabilities: [],
            recommendations: [],
        );

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn('This is not valid JSON at all');

        $result = $this->validator->validateArgumentChain($chain);

        // Should return a default/fallback result
        $this->assertInstanceOf(ChainValidationResult::class, $result);
        $this->assertEquals(0, $result->chainScore);
    }

    public function test_handles_malformed_llm_response_for_devastation_assessment(): void
    {
        $content = 'Test content';

        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn('Not JSON');

        $score = $this->validator->assessDevastationLevel($content);

        // Should return minimum score on error
        $this->assertEquals(1, $score);
    }

    // =========================================================================
    // Task 14: Tests for validateDocument() with DocumentProfile and CaseContext
    // =========================================================================

    private function createMockProfile(): \App\DTOs\DocumentProfile
    {
        return new \App\DTOs\DocumentProfile(
            key: 'predsjednik_suda',
            name: 'Prituzba predsjedniku suda',
            recipient: [
                'title' => 'Predsjednik Opcinskog suda u Zagrebu',
                'address' => 'Ulica grada Vukovara 84, 10000 Zagreb',
            ],
            legalBasis: ['cl.27 Ustava RH', 'cl.35 ZS'],
            tone: 'assertive_formal',
            structure: ['Uvod', 'Cinjenice', 'Pravna analiza', 'Zahtjev'],
            docxTemplate: 'legal-formal',
            requiresAttachments: false,
        );
    }

    private function createMockCaseContext(): \App\DTOs\CaseContext
    {
        return new \App\DTOs\CaseContext(
            caseNumber: 'Kir-123/2025',
            searchDate: '2025-06-09',
            archiveDate: '2025-06-10',
            addressSearched: 'Testna ulica 1, Zagreb',
            warrantReference: 'Kir-123/2025-4',
            policeRequestKlasa: '511-01-01/01-01/01',
            policeRequestUrbroj: '511-01-01-25-1',
            legalBasisWarrant: 'cl.240 ZKP',
            suspectedOffense: 'cl.229 KZ',
            judge: 'Ivan Horvat',
            denialDate: '2025-06-15',
            countyCourtResponseDate: '2025-06-20',
            sender: new \App\DTOs\SenderIdentity(
                name: 'Test Korisnik',
                oib: '12345678901',
                address: 'Testna adresa 1, Zagreb',
                email: 'test@example.com',
                phone: '+385 1 234 5678',
            ),
        );
    }

    public function test_validate_document_returns_validation_result(): void
    {
        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'overall_score' => 8,
                'citation_accuracy' => 9,
                'argument_strength' => 7,
                'logical_coherence' => 8,
                'issues' => [
                    ['severity' => 'minor', 'description' => 'Nedostaje poziv na cl.34 Ustava'],
                ],
                'improvements' => [
                    'Dodati referencu na ECHR praksu',
                ],
                'strengths' => [
                    'Jaki pravni argumenti',
                    'Dobra struktura dokumenta',
                ],
                'verdict' => 'FIRE_READY',
            ]));

        $profile = $this->createMockProfile();
        $context = $this->createMockCaseContext();

        $result = $this->validator->validateDocument(
            'Testni sadrzaj dokumenta s pravnim argumentima...',
            $profile,
            $context
        );

        $this->assertInstanceOf(\App\DTOs\LegalArtillery\ValidationResult::class, $result);
        $this->assertEquals(8, $result->score);
        $this->assertEquals('FIRE_READY', $result->verdict);
        $this->assertTrue($result->isValid);
        $this->assertCount(1, $result->issues);
        $this->assertEquals('minor', $result->issues[0]['severity']);
        $this->assertCount(1, $result->suggestions);
        $this->assertCount(2, $result->strengths);
        $this->assertEquals(9, $result->citationAccuracy);
        $this->assertEquals(7, $result->argumentStrength);
        $this->assertEquals(8, $result->logicalCoherence);
    }

    public function test_validate_document_handles_invalid_json(): void
    {
        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn('This is not valid JSON at all');

        $profile = $this->createMockProfile();
        $context = $this->createMockCaseContext();

        $result = $this->validator->validateDocument('Test content', $profile, $context);

        $this->assertInstanceOf(\App\DTOs\LegalArtillery\ValidationResult::class, $result);
        $this->assertFalse($result->isValid);
        $this->assertEquals(0, $result->score);
        $this->assertEquals('PARSE_ERROR', $result->verdict);
        $this->assertNotEmpty($result->issues);
    }

    public function test_validate_document_extracts_json_from_markdown(): void
    {
        $this->llmMock->shouldReceive('generate')
            ->once()
            ->andReturn("Here is my analysis:\n\n```json\n" . json_encode([
                'overall_score' => 7,
                'citation_accuracy' => 8,
                'argument_strength' => 6,
                'logical_coherence' => 7,
                'issues' => [],
                'improvements' => [],
                'strengths' => ['Good structure'],
                'verdict' => 'STRONG',
            ]) . "\n```\n\nThat's my assessment.");

        $profile = $this->createMockProfile();
        $context = $this->createMockCaseContext();

        $result = $this->validator->validateDocument('Test content', $profile, $context);

        $this->assertTrue($result->isValid);
        $this->assertEquals(7, $result->score);
        $this->assertEquals('STRONG', $result->verdict);
    }

    // =========================================================================
    // Task 14: Tests for getValidationPrompt()
    // =========================================================================

    public function test_get_validation_prompt_includes_profile_name(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        $this->assertStringContainsString($profile->name, $prompt);
    }

    public function test_get_validation_prompt_includes_legal_basis(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        foreach ($profile->legalBasis as $basis) {
            $this->assertStringContainsString($basis, $prompt);
        }
    }

    public function test_get_validation_prompt_includes_structure_sections(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        foreach ($profile->structure as $section) {
            $this->assertStringContainsString($section, $prompt);
        }
    }

    public function test_get_validation_prompt_includes_tone_reference(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        $this->assertStringContainsString($profile->tone, $prompt);
    }

    public function test_get_validation_prompt_includes_citation_check(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        // Should mention citation accuracy
        $promptLower = mb_strtolower($prompt);
        $this->assertTrue(str_contains($promptLower, 'citat'));
    }

    public function test_get_validation_prompt_includes_argument_check(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        // Should mention argument checking
        $promptLower = mb_strtolower($prompt);
        $this->assertTrue(str_contains($promptLower, 'argument'));
    }

    public function test_get_validation_prompt_includes_fabrication_check(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        // Should mention checking for fabricated/false facts
        $promptLower = mb_strtolower($prompt);
        $hasFabricatedCheck = str_contains($promptLower, 'izmisljen') ||
            str_contains($promptLower, 'fabricir') ||
            str_contains($promptLower, 'lazn') ||
            str_contains($promptLower, 'nepostoj');

        $this->assertTrue($hasFabricatedCheck, 'Prompt should include fabricated facts check');
    }

    public function test_get_validation_prompt_includes_actionable_check(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        // Should mention checking for specific/actionable requests
        $promptLower = mb_strtolower($prompt);
        $hasActionableCheck = str_contains($promptLower, 'zahtjev') ||
            str_contains($promptLower, 'konkret') ||
            str_contains($promptLower, 'specificn') ||
            str_contains($promptLower, 'mjerljiv');

        $this->assertTrue($hasActionableCheck, 'Prompt should include actionable requests check');
    }

    public function test_get_validation_prompt_includes_json_format(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('overall_score', $prompt);
        $this->assertStringContainsString('verdict', $prompt);
    }

    public function test_get_validation_prompt_includes_verdict_scale(): void
    {
        $profile = $this->createMockProfile();

        $prompt = $this->validator->getValidationPrompt($profile);

        $this->assertStringContainsString('WEAK', $prompt);
        $this->assertStringContainsString('MODERATE', $prompt);
        $this->assertStringContainsString('STRONG', $prompt);
        $this->assertStringContainsString('DEVASTATING', $prompt);
        $this->assertStringContainsString('FIRE_READY', $prompt);
    }
}
