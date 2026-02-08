<?php

namespace Tests\Feature;

use App\Agents\LegalArtilleryOrchestrator;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Models\DocumentContext;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\ProfileContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseIntakeToLegalArtilleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_and_evidence_ids_flow_into_generation_context(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();
        $evidence = Evidence::factory()->create(['case_id' => $case->id]);

        $llm = new class([
            'DRAFT DOCUMENT',
            json_encode([
                'scores' => [
                    'legal_rigor' => 50,
                    'persuasiveness' => 50,
                    'clarity' => 50,
                    'evidence_integration' => 50,
                    'formatting' => 50,
                ],
                'feedback' => [
                    'strengths' => [],
                    'weaknesses' => [],
                    'specific_improvements' => [],
                ],
            ]),
        ]) extends LlmClient {
            private array $responses;

            public function __construct(array $responses)
            {
                $this->responses = $responses;
                parent::__construct('anthropic', 'test-model', 256);
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate(string $systemPrompt, string $userPrompt, ?int $maxTokens = null): string
            {
                return array_shift($this->responses) ?? '';
            }

            public function getTokensUsed(): int
            {
                return 0;
            }
        };

        $contextBuilder = new ProfileContextBuilder();
        $orchestrator = new LegalArtilleryOrchestrator($llm, $contextBuilder);

        $run = $orchestrator->generate(
            profile: DocumentProfile::fromConfig('predsjednik_suda'),
            caseContext: CaseContext::fromConfig(),
            userId: $user->id,
            maxIterations: 1,
            additionalContext: [
                'case_id' => (string) $case->id,
                'evidence_ids' => [(string) $evidence->id],
            ],
        );

        $this->assertSame((string) $case->id, (string) $run->case_id);

        $context = DocumentContext::where('generation_run_id', $run->id)->firstOrFail();
        $assembled = json_decode($context->assembled_context, true);
        $rawInput = json_decode($context->raw_input, true);

        $this->assertSame((string) $case->id, $assembled['case_id']);
        $this->assertSame([(string) $evidence->id], $assembled['evidence_ids']);
        $this->assertSame((string) $case->id, $rawInput['additional']['case_id']);
        $this->assertSame([(string) $evidence->id], $rawInput['additional']['evidence_ids']);
        $this->assertSame([(string) $case->id], $context->case_ids);
        $this->assertSame([(string) $evidence->id], $context->evidence_ids);
        $this->assertSame(
            (string) $evidence->id,
            $assembled['enhanced_context']['evidence'][0]['id'] ?? null
        );
    }
}
