<?php

namespace App\Services\Agents;

use App\Contracts\Agents\QuestionGeneratorInterface;
use App\Services\AI\OpenAIChatService;
use Illuminate\Support\Facades\Log;

/**
 * Question Generator Service
 *
 * Generates and refines research questions for autonomous research.
 */
class QuestionGeneratorService implements QuestionGeneratorInterface
{
    protected int $minQuestions = 3;

    protected int $maxQuestions = 5;

    public function __construct(
        protected OpenAIChatService $chat
    ) {}

    /**
     * Generate research questions from query
     */
    public function generate(string $query, array $context = []): array
    {
        $startTime = microtime(true);

        $prompt = $this->buildGenerationPrompt($query, $context);

        $response = $this->chat->chat([
            ['role' => 'system', 'content' => 'You are a legal research assistant specialized in Croatian law.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4,
        ]);

        $data = json_decode($response['choices'][0]['message']['content'], true);
        $questions = $data['questions'] ?? [];

        // Validate question count
        if (count($questions) < $this->minQuestions) {
            Log::warning('Generated too few questions', ['count' => count($questions)]);
        }

        if (count($questions) > $this->maxQuestions) {
            $questions = array_slice($questions, 0, $this->maxQuestions);
        }

        Log::info('Questions generated', [
            'count' => count($questions),
            'time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);

        return $questions;
    }

    /**
     * Refine questions based on previous iteration
     */
    public function refine(array $questions, array $previousAnswers, array $qualityFeedback): array
    {
        $prompt = $this->buildRefinementPrompt($questions, $previousAnswers, $qualityFeedback);

        $response = $this->chat->chat([
            ['role' => 'system', 'content' => 'You are a legal research assistant improving research questions.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.5, // Slightly higher for creativity
        ]);

        $data = json_decode($response['choices'][0]['message']['content'], true);

        return $data['refined_questions'] ?? $questions;
    }

    /**
     * Build question generation prompt
     */
    protected function buildGenerationPrompt(string $query, array $context): string
    {
        $contextStr = ! empty($context) ? "\n\nContext:\n".json_encode($context, JSON_PRETTY_PRINT) : '';

        return <<<PROMPT
Generate {$this->minQuestions}-{$this->maxQuestions} specific research questions to thoroughly answer this Croatian legal query:

Query: {$query}{$contextStr}

Generate questions that:
1. Break down the main query into specific sub-questions
2. Cover different aspects of Croatian law (ZKP, Ustav RH, Kazneni zakon)
3. Are specific enough to guide targeted searches
4. Together will provide a complete answer

Return JSON:
{
    "questions": [
        "Specific question 1",
        "Specific question 2",
        ...
    ]
}
PROMPT;
    }

    /**
     * Build question refinement prompt
     */
    protected function buildRefinementPrompt(array $questions, array $previousAnswers, array $qualityFeedback): string
    {
        $questionsStr = json_encode($questions, JSON_PRETTY_PRINT);
        $answersStr = json_encode($previousAnswers, JSON_PRETTY_PRINT);
        $feedbackStr = json_encode($qualityFeedback, JSON_PRETTY_PRINT);

        return <<<PROMPT
The previous research iteration yielded incomplete answers. Refine the research questions.

Previous Questions:
{$questionsStr}

Previous Answers:
{$answersStr}

Quality Feedback:
{$feedbackStr}

Generate improved questions that:
1. Address gaps identified in quality feedback
2. Are more specific where previous questions were too broad
3. Focus on missing information
4. Maintain {$this->minQuestions}-{$this->maxQuestions} questions total

Return JSON:
{
    "refined_questions": ["Question 1", "Question 2", ...]
}
PROMPT;
    }
}
