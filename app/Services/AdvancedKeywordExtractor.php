<?php

namespace App\Services;

use App\Contracts\Services\KeywordExtractorInterface;
use App\Exceptions\AnalysisException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Advanced Keyword Extractor using hybrid NLP approach
 *
 * Combines:
 * 1. OpenAI embeddings (text-embedding-3-large) for semantic analysis
 * 2. TF-IDF for statistical term importance
 * 3. Croatian legal term dictionary for domain-specific boosting
 *
 * Falls back to TF-IDF + dictionary when OpenAI API unavailable
 */
class AdvancedKeywordExtractor implements KeywordExtractorInterface
{
    protected OpenAIService $openai;

    protected bool $useEmbeddings;

    protected string $embeddingsModel;

    protected float $semanticWeight;

    protected float $tfidfWeight;

    protected int $maxKeywords;

    protected bool $extractNgrams;

    protected int $maxNgramSize;

    protected float $ngramMinScore;

    protected float $emergingTermThreshold;

    // Croatian legal concept embeddings cache
    protected array $legalConceptEmbeddings = [];

    // Core legal concepts for semantic similarity (including multi-word)
    protected array $legalConcepts = [
        // Single-word concepts
        'zakon',       // law
        'ugovor',      // contract
        'presuda',     // judgment
        'odluka',      // decision
        'pravo',       // right/law
        'obveza',      // obligation
        'postupak',    // procedure
        'tužba',       // lawsuit
        'žalba',       // appeal
        'sud',         // court

        // Multi-word legal concepts (Croatian)
        'izvršenje presude',        // execution of judgment
        'ugovor o djelu',           // work contract
        'parničko pravo',           // procedural law
        'materijalno pravo',        // substantive law
        'pravna sigurnost',         // legal certainty
        'sudska praksa',            // court practice/jurisprudence
        'pravni lijek',             // legal remedy
        'pravna snaga',             // legal force
        'pravomočna presuda',       // final judgment
        'razvrgnuće ugovora',       // contract dissolution
        'poništenje ugovora',       // contract annulment
        'kaznena odgovornost',      // criminal liability
        'građanska odgovornost',    // civil liability
        'naknada štete',            // damages/compensation
        'procesna pretpostavka',    // procedural requirement
        'pravni posao',             // legal transaction
        'protupravno postupanje',   // unlawful conduct
        'odšteta',                  // damages
    ];

    public function __construct(OpenAIService $openai)
    {
        $this->openai = $openai;
        $this->useEmbeddings = config('keywords.use_embeddings', true);
        $this->embeddingsModel = config('openai.models.embeddings_analysis', 'text-embedding-3-large');
        $this->semanticWeight = config('keywords.semantic_weight', 0.6);
        $this->tfidfWeight = config('keywords.tfidf_weight', 0.4);
        $this->maxKeywords = config('keywords.max_keywords', 10);
        $this->extractNgrams = config('keywords.extract_ngrams', true);
        $this->maxNgramSize = config('keywords.max_ngram_size', 3);
        $this->ngramMinScore = config('keywords.ngram_min_score', 0.3);
        $this->emergingTermThreshold = config('keywords.emerging_term_threshold', 0.7);
    }

    /**
     * Extract keywords using hybrid approach
     *
     * @param  string  $content  Document content
     * @param  int|null  $maxKeywords  Maximum keywords to return (overrides config)
     * @param  array  $options  Additional options
     * @return array Associative array of keyword => weight (0-1 range)
     *
     * @throws AnalysisException if keyword extraction fails completely
     */
    public function extract(string $content, ?int $maxKeywords = null, array $options = []): array
    {
        try {
            Log::info('Keyword extraction initiated', [
                'content_length' => strlen($content),
                'max_keywords' => $maxKeywords ?? $this->maxKeywords,
                'use_embeddings' => $this->useEmbeddings,
            ]);

            $startTime = microtime(true);

            if (empty(trim($content))) {
                Log::warning('Keyword extraction received empty content');

                return [];
            }

            $maxKeywords = $maxKeywords ?? $this->maxKeywords;
            $useCache = $options['use_cache'] ?? true;

            $keywords = [];

            try {
                if ($this->useEmbeddings) {
                    $keywords = $this->hybridExtraction($content, $maxKeywords, $useCache);
                } else {
                    $keywords = $this->tfidfExtraction($content, $maxKeywords);
                }
            } catch (\Exception $e) {
                Log::warning('Keyword extraction - Embeddings failed, falling back to TF-IDF', [
                    'error' => $e->getMessage(),
                    'content_length' => strlen($content),
                ]);

                // Fallback to TF-IDF only
                $keywords = $this->tfidfExtraction($content, $maxKeywords);
            }

            Log::info('Keyword extraction completed', [
                'keyword_count' => count($keywords),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $keywords;

        } catch (\Exception $e) {
            Log::error('Keyword extraction failed completely', [
                'content_length' => strlen($content),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Keyword extraction failed: '.$e->getMessage(),
                AnalysisException::EXTRACTION_FAILED,
                $e
            );
        }
    }

    /**
     * Hybrid extraction combining embeddings and TF-IDF
     * Now includes multi-word phrase extraction (n-grams)
     */
    protected function hybridExtraction(string $content, int $maxKeywords, bool $useCache): array
    {
        // Step 1: Extract single words (unigrams) with TF-IDF
        $unigramScores = $this->computeTfIdf($content);

        // Step 2: Extract multi-word phrases (n-grams) if enabled
        $ngramScores = [];
        if ($this->extractNgrams) {
            $ngramScores = $this->extractNgrams($content, $useCache);
        }

        // Step 3: Get embedding-based semantic scores for both unigrams and n-grams
        $allCandidates = array_merge($unigramScores, $ngramScores);
        $semanticScores = $this->computeSemanticScores($content, $allCandidates, $useCache);

        // Step 4: Combine TF-IDF/frequency scores with semantic scores
        $combinedScores = $this->combineScores($allCandidates, $semanticScores);

        // Step 5: Filter emerging terms (terms with high semantic similarity but not in dictionary)
        $combinedScores = $this->boostEmergingTerms($combinedScores, $semanticScores);

        // Step 6: Sort and return top keywords
        arsort($combinedScores);
        $topKeywords = array_slice($combinedScores, 0, $maxKeywords, true);

        // Normalize to 0-1 range
        return $this->normalizeWeights($topKeywords);
    }

    /**
     * TF-IDF only extraction (fallback)
     */
    protected function tfidfExtraction(string $content, int $maxKeywords): array
    {
        $tfidfScores = $this->computeTfIdf($content);

        arsort($tfidfScores);
        $topKeywords = array_slice($tfidfScores, 0, $maxKeywords, true);

        return $this->normalizeWeights($topKeywords);
    }

    /**
     * Compute TF-IDF scores with legal term boosting
     */
    protected function computeTfIdf(string $content): array
    {
        // Legal terms with boost weights
        $legalTerms = $this->getLegalTermsDictionary();

        // Stopwords for Croatian and English
        $stopwords = $this->getStopwords();

        // Tokenize
        $words = $this->tokenize($content);

        // Filter stopwords and short words
        $words = array_filter($words, function ($word) use ($stopwords) {
            return mb_strlen($word) > 3 && ! in_array($word, $stopwords);
        });

        // Count frequencies (TF)
        $frequencies = array_count_values($words);

        // Apply legal term boosting and IDF approximation
        foreach ($frequencies as $word => $freq) {
            // Base TF score
            $tf = $freq / count($words);

            // IDF approximation: log(1 + 1/freq) - rare words get higher scores
            $idf = log(1 + 1 / $freq);

            // Legal term boost
            $boost = $legalTerms[$word] ?? 1.0;

            // Combined TF-IDF score with boost
            $frequencies[$word] = $tf * $idf * $boost;
        }

        return $frequencies;
    }

    /**
     * Compute semantic similarity scores using embeddings
     */
    protected function computeSemanticScores(string $content, array $candidateWords, bool $useCache): array
    {
        // Get embeddings for candidate words
        $wordEmbeddings = $this->getWordEmbeddings(array_keys($candidateWords), $useCache);

        // Get legal concept embeddings
        $conceptEmbeddings = $this->getLegalConceptEmbeddings($useCache);

        // Compute similarity scores
        $semanticScores = [];

        foreach ($wordEmbeddings as $word => $wordEmbedding) {
            if (empty($wordEmbedding)) {
                $semanticScores[$word] = 0.0;

                continue;
            }

            // Find max similarity to any legal concept
            $maxSimilarity = 0.0;

            foreach ($conceptEmbeddings as $conceptEmbedding) {
                if (empty($conceptEmbedding)) {
                    continue;
                }

                $similarity = $this->cosineSimilarity($wordEmbedding, $conceptEmbedding);
                $maxSimilarity = max($maxSimilarity, $similarity);
            }

            $semanticScores[$word] = $maxSimilarity;
        }

        return $semanticScores;
    }

    /**
     * Get embeddings for multiple words (with caching)
     */
    protected function getWordEmbeddings(array $words, bool $useCache): array
    {
        $embeddings = [];

        // Batch words into groups of 10 for efficiency
        $batches = array_chunk($words, 10, true);

        foreach ($batches as $batch) {
            foreach ($batch as $word) {
                $cacheKey = "embedding:{$this->embeddingsModel}:".md5($word);

                if ($useCache && Cache::has($cacheKey)) {
                    $embeddings[$word] = Cache::get($cacheKey);

                    continue;
                }

                try {
                    $embedding = $this->openai->createEmbedding($word, $this->embeddingsModel);
                    $embeddings[$word] = $embedding;

                    if ($useCache) {
                        Cache::put($cacheKey, $embedding, now()->addDays(30));
                    }
                } catch (\Exception $e) {
                    Log::debug('AdvancedKeywordExtractor - Failed to get embedding for word', [
                        'word' => $word,
                        'error' => $e->getMessage(),
                    ]);
                    $embeddings[$word] = [];
                }
            }

            // Small delay to respect rate limits
            if (count($batches) > 1) {
                usleep(100000); // 100ms
            }
        }

        return $embeddings;
    }

    /**
     * Get embeddings for legal concepts (with caching)
     */
    protected function getLegalConceptEmbeddings(bool $useCache): array
    {
        if (! empty($this->legalConceptEmbeddings)) {
            return $this->legalConceptEmbeddings;
        }

        $embeddings = [];

        foreach ($this->legalConcepts as $concept) {
            $cacheKey = "legal_concept_embedding:{$this->embeddingsModel}:".md5($concept);

            if ($useCache && Cache::has($cacheKey)) {
                $embeddings[] = Cache::get($cacheKey);

                continue;
            }

            try {
                $embedding = $this->openai->createEmbedding($concept, $this->embeddingsModel);
                $embeddings[] = $embedding;

                if ($useCache) {
                    Cache::put($cacheKey, $embedding, now()->addDays(90)); // Longer cache for concepts
                }
            } catch (\Exception $e) {
                Log::debug('AdvancedKeywordExtractor - Failed to get embedding for concept', [
                    'concept' => $concept,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->legalConceptEmbeddings = $embeddings;

        return $embeddings;
    }

    /**
     * Cosine similarity between two embedding vectors
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Combine TF-IDF and semantic scores with weighted average
     */
    protected function combineScores(array $tfidfScores, array $semanticScores): array
    {
        $combined = [];

        foreach ($tfidfScores as $word => $tfidfScore) {
            $semanticScore = $semanticScores[$word] ?? 0.0;

            // Weighted combination
            $combined[$word] = ($this->tfidfWeight * $tfidfScore) + ($this->semanticWeight * $semanticScore);
        }

        return $combined;
    }

    /**
     * Normalize weights to 0-1 range
     */
    protected function normalizeWeights(array $weights): array
    {
        if (empty($weights)) {
            return [];
        }

        $max = max($weights);

        if ($max == 0) {
            return $weights;
        }

        return array_map(fn ($w) => round($w / $max, 3), $weights);
    }

    /**
     * Tokenize content
     */
    protected function tokenize(string $content): array
    {
        $words = preg_split('/\s+/', mb_strtolower($content));

        return array_filter($words, fn ($w) => ! empty($w));
    }

    /**
     * Get Croatian legal terms dictionary
     */
    protected function getLegalTermsDictionary(): array
    {
        return [
            // Core legal concepts
            'ugovor' => 2.5,        // contract
            'obveza' => 2.5,        // obligation
            'pravo' => 2.5,         // right/law
            'zakon' => 3.0,         // law/statute
            'odredba' => 2.5,       // provision
            'postupak' => 2.5,      // procedure
            'naknada' => 2.0,       // compensation
            'presuda' => 2.5,       // judgment/verdict
            'odluka' => 2.5,        // decision
            'rješenje' => 2.0,      // resolution

            // Legal entities and parties
            'tužitelj' => 2.0,      // plaintiff
            'tuženik' => 2.0,       // defendant
            'stranka' => 2.0,       // party
            'sud' => 2.5,           // court
            'sudac' => 2.0,         // judge
            'svjedok' => 2.0,       // witness
            'odvjetnik' => 2.0,     // lawyer

            // Legal processes
            'žalba' => 2.0,         // appeal
            'tužba' => 2.5,         // lawsuit
            'parnica' => 2.0,       // litigation
            'izvršenje' => 2.0,     // execution/enforcement
            'dokazivanje' => 2.0,   // proving/evidence
            'saslušanje' => 2.0,    // hearing
            'pretres' => 2.0,       // trial

            // Legal effects and outcomes
            'ništavost' => 2.0,     // nullity
            'poništenje' => 2.0,    // annulment
            'razvrgnuće' => 2.0,    // dissolution
            'prekid' => 1.8,        // termination
            'prestanak' => 1.8,     // cessation
            'stupanje' => 1.8,      // coming into force

            // Specific legal areas
            'kazneno' => 2.0,       // criminal
            'građansko' => 2.0,     // civil
            'upravno' => 2.0,       // administrative
            'trgovačko' => 2.0,     // commercial
            'radno' => 1.8,         // labor
            'obiteljsko' => 1.8,    // family

            // Important legal modifiers
            'zakonit' => 2.0,       // lawful
            'nezakonit' => 2.0,     // unlawful
            'valjan' => 1.8,        // valid
            'ništav' => 2.0,        // void
            'pravomočan' => 2.0,    // final/legally binding
            'izvršan' => 1.8,       // executable

            // Legal documents and norms
            'uredba' => 2.0,        // ordinance/regulation
            'pravilnik' => 2.0,     // rulebook
            'statut' => 2.0,        // statute
            'protokol' => 1.8,      // protocol
            'sporazum' => 2.0,      // agreement
            'konvencija' => 2.0,    // convention
        ];
    }

    /**
     * Extract n-grams (bigrams, trigrams) from content
     *
     * @param  string  $content  Document content
     * @param  bool  $useCache  Whether to use embedding cache
     * @return array N-gram => score mapping
     */
    protected function extractNgrams(string $content, bool $useCache): array
    {
        $stopwords = $this->getStopwords();
        $words = $this->tokenize($content);

        // Filter out stopwords but keep them for n-gram formation
        $ngramScores = [];

        // Extract bigrams (2-word phrases)
        if ($this->maxNgramSize >= 2) {
            for ($i = 0; $i < count($words) - 1; $i++) {
                $bigram = $words[$i].' '.$words[$i + 1];

                // Skip if both words are stopwords or too short
                if (in_array($words[$i], $stopwords) && in_array($words[$i + 1], $stopwords)) {
                    continue;
                }
                if (mb_strlen($words[$i]) <= 2 || mb_strlen($words[$i + 1]) <= 2) {
                    continue;
                }

                // Count frequency
                if (! isset($ngramScores[$bigram])) {
                    $ngramScores[$bigram] = 0;
                }
                $ngramScores[$bigram]++;
            }
        }

        // Extract trigrams (3-word phrases)
        if ($this->maxNgramSize >= 3) {
            for ($i = 0; $i < count($words) - 2; $i++) {
                $trigram = $words[$i].' '.$words[$i + 1].' '.$words[$i + 2];

                // Skip if all words are stopwords
                if (in_array($words[$i], $stopwords) &&
                    in_array($words[$i + 1], $stopwords) &&
                    in_array($words[$i + 2], $stopwords)) {
                    continue;
                }

                // At least one word should be significant (length > 3)
                if (mb_strlen($words[$i]) <= 3 &&
                    mb_strlen($words[$i + 1]) <= 3 &&
                    mb_strlen($words[$i + 2]) <= 3) {
                    continue;
                }

                // Count frequency
                if (! isset($ngramScores[$trigram])) {
                    $ngramScores[$trigram] = 0;
                }
                $ngramScores[$trigram]++;
            }
        }

        // Filter n-grams by minimum frequency (must appear at least twice OR match known phrases)
        $legalTerms = $this->getLegalTermsDictionary();
        $filteredNgrams = [];

        foreach ($ngramScores as $ngram => $frequency) {
            // Keep if appears multiple times OR matches known legal phrase pattern
            if ($frequency >= 2 || $this->matchesLegalPattern($ngram, $legalTerms)) {
                // Apply basic TF-IDF-like scoring
                $tf = $frequency / count($words);
                $idf = log(1 + 1 / $frequency);

                // Boost if matches legal term pattern
                $boost = $this->matchesLegalPattern($ngram, $legalTerms) ? 2.0 : 1.0;

                $filteredNgrams[$ngram] = $tf * $idf * $boost;
            }
        }

        return $filteredNgrams;
    }

    /**
     * Check if n-gram matches legal term patterns
     *
     * @param  string  $ngram  Multi-word phrase
     * @param  array  $legalTerms  Legal terms dictionary
     */
    protected function matchesLegalPattern(string $ngram, array $legalTerms): bool
    {
        // Check if any word in the n-gram is a legal term
        $words = explode(' ', $ngram);

        foreach ($words as $word) {
            if (isset($legalTerms[$word])) {
                return true;
            }
        }

        // Check if the n-gram itself is in the legal concepts list
        if (in_array($ngram, $this->legalConcepts)) {
            return true;
        }

        return false;
    }

    /**
     * Boost emerging terms that have high semantic similarity
     * but are not in the predefined dictionary
     *
     * This helps surface new legal terminology and concepts
     *
     * @param  array  $combinedScores  Combined TF-IDF + semantic scores
     * @param  array  $semanticScores  Semantic similarity scores
     * @return array Boosted scores
     */
    protected function boostEmergingTerms(array $combinedScores, array $semanticScores): array
    {
        $legalTerms = $this->getLegalTermsDictionary();

        foreach ($combinedScores as $term => $score) {
            // Check if term is NOT in dictionary but HAS high semantic similarity
            if (! isset($legalTerms[$term]) &&
                isset($semanticScores[$term]) &&
                $semanticScores[$term] >= $this->emergingTermThreshold) {

                // Boost the score to surface emerging terminology
                $combinedScores[$term] *= 1.5;

                if (config('keywords.log_extraction', false)) {
                    Log::info('AdvancedKeywordExtractor - Emerging term detected', [
                        'term' => $term,
                        'semantic_score' => $semanticScores[$term],
                        'boosted_score' => $combinedScores[$term],
                    ]);
                }
            }
        }

        return $combinedScores;
    }

    /**
     * Get stopwords for Croatian and English
     */
    protected function getStopwords(): array
    {
        return [
            // Croatian
            'je', 'su', 'biti', 'ima', 'da', 'za', 'na', 'u', 'i', 'ili', 'te', 'se',
            'koji', 'koja', 'koje', 'ovaj', 'taj', 'ova', 'ovo', 'isto', 'kao', 'ili',
            'će', 'bi', 'bio', 'bila', 'bilo', 'nisu', 'niti', 'bez', 'iz', 'od', 'do',
            'po', 'preko', 'zbog', 'radi', 'prema', 'uz', 'pri', 'među', 'samo', 'već',

            // English
            'the', 'of', 'and', 'to', 'a', 'in', 'is', 'it', 'you', 'that', 'he', 'was',
            'for', 'on', 'are', 'with', 'as', 'his', 'they', 'be', 'at', 'one', 'have',
            'this', 'from', 'by', 'but', 'not', 'what', 'all', 'were', 'when', 'we',
            'there', 'can', 'an', 'your', 'which', 'their', 'if', 'will', 'up', 'other',
        ];
    }

    /**
     * Get configuration statistics for monitoring
     */
    public function getConfig(): array
    {
        return [
            'use_embeddings' => $this->useEmbeddings,
            'embeddings_model' => $this->embeddingsModel,
            'semantic_weight' => $this->semanticWeight,
            'tfidf_weight' => $this->tfidfWeight,
            'max_keywords' => $this->maxKeywords,
            'extract_ngrams' => $this->extractNgrams,
            'max_ngram_size' => $this->maxNgramSize,
            'ngram_min_score' => $this->ngramMinScore,
            'emerging_term_threshold' => $this->emergingTermThreshold,
            'legal_concepts_count' => count($this->legalConcepts),
            'legal_terms_count' => count($this->getLegalTermsDictionary()),
        ];
    }
}
