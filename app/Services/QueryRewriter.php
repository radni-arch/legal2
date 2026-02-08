<?php

namespace App\Services;

use App\Contracts\Services\QueryRewriterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rewrites user queries into optimized variants for better search results
 */
class QueryRewriter implements QueryRewriterInterface
{
    public function __construct(protected OpenAIService $openai) {}

    /**
     * Rewrite query into 3 optimized variants
     *
     * @param  string  $query  Original user query
     * @param  string  $language  Target language (hr, en)
     * @return array [specific, broad, structured]
     */
    public function rewrite(string $query, string $language = 'hr'): array
    {
        // Check cache first (queries valid for 24h)
        $cacheKey = 'query_rewrite:'.md5($query.$language);

        if (Cache::has($cacheKey)) {
            Log::debug('Using cached query rewrite', ['query' => $query]);

            return Cache::get($cacheKey);
        }

        $prompt = $this->buildRewritePrompt($query, $language);

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => $this->getSystemPrompt($language)],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                throw new \Exception('Empty response from LLM');
            }

            $data = json_decode($content, true);

            if (! $data) {
                throw new \Exception('Failed to decode JSON response');
            }

            $variants = [
                $data['specific'] ?? $query,
                $data['broad'] ?? $query,
                $data['structured'] ?? $query,
            ];

            // Cache for 24 hours
            Cache::put($cacheKey, $variants, now()->addDay());

            Log::info('Query rewritten successfully', [
                'original' => $query,
                'variants' => $variants,
            ]);

            return $variants;

        } catch (\Exception $e) {
            Log::error('Query rewriting failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Fallback: return original query 3 times
            return [$query, $query, $query];
        }
    }

    protected function buildRewritePrompt(string $query, string $language): string
    {
        return <<<PROMPT
Original Query: "{$query}"

Rewrite this legal query into 3 optimized search variants:

1. **SPECIFIC**: Extract exact legal terms, law numbers (e.g., "NN 93/14"), article references (e.g., "članak 93"), court names. Use precise legal terminology.

2. **BROAD**: Expand to related concepts, synonyms, and alternative phrasings. Include adjacent legal topics that might be relevant.

3. **STRUCTURED**: Convert to formal Croatian legal terminology. Use standard legal phrases and official document language.

EXAMPLES:

Input: "Can employer fire me without notice?"
Output:
{
  "specific": "nezakonit otkaz bez otkaznog roka Zakon o radu članak 93",
  "broad": "prestanak ugovora o radu otkazni rok zaštita radnika otkaz",
  "structured": "raskid ugovora o radu otkazni rok zaposlenika Zakon o radu"
}

Input: "I signed contract but company didn't deliver"
Output:
{
  "specific": "neispunjenje ugovora povreda ugovorne obveze Zakon o obveznim odnosima",
  "broad": "ugovor obveza isporuka naknada štete ugovorna odgovornost",
  "structured": "povreda ugovorne obveze neispunjenje obveze ugovornih strana"
}

Respond with JSON:
{
  "specific": "...",
  "broad": "...",
  "structured": "..."
}
PROMPT;
    }

    protected function getSystemPrompt(string $language): string
    {
        $lang = $language === 'hr' ? 'Croatian' : 'English';

        return <<<PROMPT
You are an expert legal search query optimizer specializing in {$lang} law.

Your task is to transform user queries (which may be informal or in natural language) into optimized search queries that will retrieve the most relevant legal documents.

RULES:
- All variants must be in {$lang}
- Use correct legal terminology
- Include relevant law numbers and article references when applicable
- Preserve legal precision
- Consider both semantic and keyword search
- Each variant should be distinct and serve a different search strategy

LEGAL TERMINOLOGY ({$lang}):
- Employment law: "Zakon o radu", "radni odnos", "ugovor o radu"
- Contract law: "Zakon o obveznim odnosima", "ugovor", "ugovorna obveza"
- Termination: "otkaz", "raskid", "prestanak"
- Notice period: "otkazni rok"
- Unlawful: "nezakonit", "protupravno"
- Liability: "odgovornost", "naknada štete"
- Court: "sud", "Vrhovni sud", "Županijski sud"
PROMPT;
    }

    /**
     * Get single best query variant (specific > structured > broad)
     */
    public function rewriteBest(string $query, string $language = 'hr'): string
    {
        $variants = $this->rewrite($query, $language);

        return $variants[0]; // Specific variant
    }

    /**
     * Analyze query intent
     */
    public function analyzeIntent(string $query): array
    {
        $prompt = <<<PROMPT
Analyze this legal query and identify:
1. Primary legal domain (employment, contract, property, criminal, etc.)
2. Specific law references (if any)
3. Query type (factual, procedural, advisory, research)
4. Key entities (parties, courts, law numbers)

Query: "{$query}"

Respond with JSON:
{
  "domain": "...",
  "law_references": [...],
  "query_type": "...",
  "entities": [...]
}
PROMPT;

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => 'You are a legal query analyst.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                throw new \Exception('Empty response from LLM');
            }

            $result = json_decode($content, true);

            if (! $result) {
                throw new \Exception('Failed to decode JSON response');
            }

            Log::info('Query intent analyzed', [
                'query' => $query,
                'intent' => $result,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Query intent analysis failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'domain' => 'unknown',
                'law_references' => [],
                'query_type' => 'unknown',
                'entities' => [],
            ];
        }
    }
}
