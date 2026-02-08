<?php

namespace Tests\Feature\Services\Research;

use App\Contracts\Agents\QuestionGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Question Generator Integration Tests
 *
 * Tests the full question generation pipeline including OpenAI integration.
 */
class QuestionGeneratorIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test full question generation pipeline
     * Flow: Query → Build context → OpenAI API → Parse questions → Validate → Return
     */
    public function test_question_generator_uses_openai_and_generates_valid_questions(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'questions' => [
                                'What are the requirements for criminal liability?',
                                'What defenses are available under Croatian law?',
                                'What are the penalties for this type of offense?',
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $generator = app(QuestionGeneratorInterface::class);

        $questions = $generator->generate('Research criminal liability for drug offenses');

        $this->assertIsArray($questions);
        $this->assertGreaterThanOrEqual(3, count($questions));
        $this->assertStringContainsString('criminal', strtolower($questions[0]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'openai.com')
                && str_contains($request->body(), 'research questions');
        });
    }

    /**
     * Test question refinement uses evaluation feedback
     * Flow: Previous questions + results + evaluation → OpenAI → Refined questions
     */
    public function test_question_generator_refines_based_on_previous_results(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'refined_questions' => [
                                'What specific provisions in ZKP Article 9 relate to defendant rights?',
                                'What are the constitutional safeguards under Croatian Constitution?',
                                'What case law exists regarding Miranda-style warnings in Croatian courts?',
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $generator = app(QuestionGeneratorInterface::class);

        $previousQuestions = [
            'What are defendant rights?',
            'What does the law say?',
        ];

        $previousAnswers = [
            'Defendants have certain rights under Croatian law.',
        ];

        $qualityFeedback = [
            'quality_score' => 60,
            'gaps' => [
                'Missing specific legal citations',
                'Too general, needs more detail on ZKP provisions',
            ],
            'improvements' => [
                'Reference specific articles',
                'Include case law examples',
            ],
        ];

        $refinedQuestions = $generator->refine(
            $previousQuestions,
            $previousAnswers,
            $qualityFeedback
        );

        $this->assertIsArray($refinedQuestions);
        $this->assertGreaterThanOrEqual(3, count($refinedQuestions));

        // Verify refined questions are more specific
        $this->assertStringContainsString('specific', strtolower($refinedQuestions[0]));

        // Verify refinement request was sent with context
        Http::assertSent(function ($request) {
            $body = $request->body();

            // Check that previous context was included in the request
            return str_contains($request->url(), 'openai.com')
                && str_contains($body, 'Previous Questions')
                && str_contains($body, 'Previous Answers')
                && str_contains($body, 'Quality Feedback');
        });
    }
}
