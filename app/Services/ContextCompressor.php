<?php

namespace App\Services;

/**
 * Compresses retrieved documents to fit more context into LLM prompts
 */
class ContextCompressor
{
    protected int $defaultTokenBudget = 4000;

    /**
     * Compress search results to fit within token budget
     *
     * @param  array  $results  Search results (laws, decisions, cases)
     * @param  int  $tokenBudget  Maximum tokens for compressed content
     * @return array Compressed results with full and compressed content
     */
    public function compress(array $results, ?int $tokenBudget = null): array
    {
        $tokenBudget = $tokenBudget ?? $this->defaultTokenBudget;

        $compressed = [];
        $tokensUsed = 0;

        foreach ($results as $result) {
            if ($tokensUsed >= $tokenBudget) {
                break;
            }

            $content = $result['content'] ?? '';

            if (empty($content)) {
                $compressed[] = $result;

                continue;
            }

            // Estimate tokens (rough: 1 token ≈ 4 characters)
            $contentTokens = (int) (strlen($content) / 4);

            if ($contentTokens <= 200) {
                // Short enough, include as-is
                $compressed[] = $result;
                $tokensUsed += $contentTokens;
            } else {
                // Needs compression
                $availableTokens = min(300, $tokenBudget - $tokensUsed);

                $compressedContent = $this->compressContent(
                    $content,
                    $availableTokens,
                    $result
                );

                $result['full_content'] = $content; // Preserve original
                $result['content'] = $compressedContent;
                $result['compressed'] = true;

                $compressed[] = $result;
                $tokensUsed += $availableTokens;
            }
        }

        return $compressed;
    }

    /**
     * Compress single content to target token count
     */
    protected function compressContent(string $content, int $targetTokens, array $metadata): string
    {
        // Target chars = tokens × 4
        $targetChars = $targetTokens * 4;

        // Strategy 1: Extract sentences with legal keywords
        $keywords = $this->extractLegalKeywords($metadata);
        $relevantSentences = $this->extractRelevantSentences($content, $keywords);

        if (strlen($relevantSentences) <= $targetChars) {
            return $relevantSentences;
        }

        // Strategy 2: Truncate with ellipsis, preserve citations
        $citations = $this->extractCitations($content);

        $truncated = substr($relevantSentences, 0, $targetChars - 100);
        $lastPeriod = strrpos($truncated, '.');

        if ($lastPeriod !== false) {
            $truncated = substr($truncated, 0, $lastPeriod + 1);
        }

        // Add citations at end
        if (! empty($citations)) {
            $citationText = ' [Cites: '.implode(', ', array_slice($citations, 0, 3)).']';
            $truncated .= $citationText;
        }

        $truncated .= ' [...]';

        return $truncated;
    }

    /**
     * Extract legal keywords from metadata
     */
    protected function extractLegalKeywords(array $metadata): array
    {
        $keywords = [];

        // From title
        if (isset($metadata['title'])) {
            $keywords[] = strtolower($metadata['title']);
        }

        // From law number
        if (isset($metadata['law_number'])) {
            $keywords[] = $metadata['law_number'];
        }

        // From tags
        if (isset($metadata['tags']) && is_array($metadata['tags'])) {
            $keywords = array_merge($keywords, $metadata['tags']);
        }

        return array_unique($keywords);
    }

    /**
     * Extract sentences containing legal keywords
     */
    protected function extractRelevantSentences(string $content, array $keywords): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $content);

        if (empty($sentences) || empty($keywords)) {
            return $content;
        }

        $scoredSentences = [];

        foreach ($sentences as $sentence) {
            $score = 0;
            $lowerSentence = mb_strtolower($sentence);

            foreach ($keywords as $keyword) {
                $lowerKeyword = mb_strtolower($keyword);

                if (mb_strpos($lowerSentence, $lowerKeyword) !== false) {
                    $score += 10;
                }
            }

            // Boost for legal citation patterns
            if (preg_match('/čl(anak|\.)\s*\d+/', $sentence)) {
                $score += 5;
            }

            if (preg_match('/NN\s+\d+\/\d+/', $sentence)) {
                $score += 5;
            }

            if (preg_match('/Zakon o/i', $sentence)) {
                $score += 3;
            }

            $scoredSentences[] = ['sentence' => $sentence, 'score' => $score];
        }

        // Sort by score
        usort($scoredSentences, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Take top 50% of sentences
        $topCount = max(3, (int) (count($scoredSentences) / 2));
        $topSentences = array_slice($scoredSentences, 0, $topCount);

        // Sort by original order (preserve flow)
        usort($topSentences, function ($a, $b) use ($sentences) {
            $indexA = array_search($a['sentence'], $sentences);
            $indexB = array_search($b['sentence'], $sentences);

            return $indexA <=> $indexB;
        });

        return implode(' ', array_column($topSentences, 'sentence'));
    }

    /**
     * Extract legal citations from content
     */
    protected function extractCitations(string $content): array
    {
        $citations = [];

        // NN law numbers
        preg_match_all('/NN\s+\d+\/\d+/i', $content, $matches);
        $citations = array_merge($citations, $matches[0]);

        // Article references
        preg_match_all('/čl(anak|\.)\s*\d+/i', $content, $matches);
        $citations = array_merge($citations, $matches[0]);

        return array_unique($citations);
    }

    /**
     * Calculate compression ratio
     */
    public function calculateCompressionRatio(array $original, array $compressed): float
    {
        $originalSize = 0;
        $compressedSize = 0;

        foreach ($original as $item) {
            $originalSize += strlen($item['content'] ?? '');
        }

        foreach ($compressed as $item) {
            $compressedSize += strlen($item['content'] ?? '');
        }

        if ($originalSize === 0) {
            return 1.0;
        }

        return $compressedSize / $originalSize;
    }
}
