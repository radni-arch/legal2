<?php

namespace App\Contracts\Agents;

/**
 * Contract for research question generation
 */
interface QuestionGeneratorInterface
{
    /**
     * Generate research questions from a query
     *
     * @param  string  $query  Original research query
     * @param  array  $context  Additional context (previous answers, etc.)
     * @return array Array of research questions
     */
    public function generate(string $query, array $context = []): array;

    /**
     * Refine questions based on previous iteration
     *
     * @param  array  $questions  Original questions
     * @param  array  $previousAnswers  Previous answers
     * @param  array  $qualityFeedback  Quality feedback
     * @return array Refined questions
     */
    public function refine(array $questions, array $previousAnswers, array $qualityFeedback): array;
}
