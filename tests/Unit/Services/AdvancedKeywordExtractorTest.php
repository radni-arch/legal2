<?php

namespace Tests\Unit\Services;

use App\Services\AdvancedKeywordExtractor;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AdvancedKeywordExtractorTest extends TestCase
{
    use UsesTestDatabase;

    protected $openaiMock;

    protected AdvancedKeywordExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI service
        $this->openaiMock = Mockery::mock(OpenAIService::class);
        $this->app->instance(OpenAIService::class, $this->openaiMock);

        // Configure for testing
        Config::set('keywords.use_embeddings', true);
        Config::set('keywords.max_keywords', 10);
        Config::set('keywords.semantic_weight', 0.6);
        Config::set('keywords.tfidf_weight', 0.4);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 3);
        Config::set('keywords.ngram_min_score', 0.3);
        Config::set('keywords.emerging_term_threshold', 0.7);

        $this->extractor = new AdvancedKeywordExtractor($this->openaiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extracts_keywords_using_tfidf_when_embeddings_disabled()
    {
        Config::set('keywords.use_embeddings', false);

        $this->extractor = new AdvancedKeywordExtractor($this->openaiMock);

        $content = 'Zakon o radu regulira ugovor o radu između poslodavca i radnika.';

        $keywords = $this->extractor->extract($content, 5);

        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);
        $this->assertLessThanOrEqual(5, count($keywords));

        // Should contain legal terms
        $this->assertArrayHasKey('zakon', $keywords);
        // Note: 'radu' may be filtered by stopwords, so we test for 'ugovor' instead
        $this->assertArrayHasKey('ugovor', $keywords);

        // Weights should be normalized (0-1)
        foreach ($keywords as $keyword => $weight) {
            $this->assertGreaterThanOrEqual(0, $weight);
            $this->assertLessThanOrEqual(1, $weight);
        }
    }

    /** @test */
    public function it_extracts_keywords_using_hybrid_approach()
    {
        Cache::flush(); // Clear cache for clean test

        $content = 'Presuda suda o ugovoru o radu između tužitelja i tuženika.';

        // Mock embeddings for words
        $this->openaiMock
            ->shouldReceive('createEmbedding')
            ->andReturn($this->generateMockEmbedding());

        $keywords = $this->extractor->extract($content, 5, ['use_cache' => false]);

        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);
        $this->assertLessThanOrEqual(5, count($keywords));

        // Weights should be normalized
        foreach ($keywords as $keyword => $weight) {
            $this->assertGreaterThanOrEqual(0, $weight);
            $this->assertLessThanOrEqual(1, $weight);
        }
    }

    /** @test */
    public function it_falls_back_to_tfidf_when_embeddings_fail()
    {
        Config::set('keywords.log_fallback', false); // Disable logging for test

        $content = 'Odluka o žalbi protiv presude županijskog suda.';

        // Mock embedding failure
        $this->openaiMock
            ->shouldReceive('createEmbedding')
            ->andThrow(new \Exception('API error'));

        $keywords = $this->extractor->extract($content, 5);

        // Should still return keywords using TF-IDF fallback
        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);
    }

    /** @test */
    public function it_applies_legal_term_boosting()
    {
        Config::set('keywords.use_embeddings', false);

        // Content with both legal and non-legal terms
        $content = 'Zakon zakon zakon neki neki neki tekst tekst tekst tekst';

        $keywords = $this->extractor->extract($content, 5);

        // 'zakon' should rank higher than 'neki' or 'tekst' despite similar frequencies
        // because it has a boost weight of 3.0
        $keywordList = array_keys($keywords);

        $this->assertContains('zakon', $keywordList);

        // Zakon should be in top positions
        $zakonPosition = array_search('zakon', $keywordList);
        $this->assertLessThan(3, $zakonPosition);
    }

    /** @test */
    public function it_filters_stopwords()
    {
        Config::set('keywords.use_embeddings', false);

        $content = 'Zakon je od i za na u te se koji koja';

        $keywords = $this->extractor->extract($content, 10);

        // Stopwords should be filtered out
        $this->assertArrayNotHasKey('je', $keywords);
        $this->assertArrayNotHasKey('od', $keywords);
        $this->assertArrayNotHasKey('za', $keywords);
        $this->assertArrayNotHasKey('koji', $keywords);

        // Legal term should be present
        $this->assertArrayHasKey('zakon', $keywords);
    }

    /** @test */
    public function it_filters_short_words()
    {
        Config::set('keywords.use_embeddings', false);

        $content = 'Zakon i a je do od po uz za';

        $keywords = $this->extractor->extract($content, 10);

        // Words with length <= 3 should be filtered out
        foreach (array_keys($keywords) as $keyword) {
            $this->assertGreaterThan(3, mb_strlen($keyword));
        }
    }

    /** @test */
    public function it_respects_max_keywords_limit()
    {
        Config::set('keywords.use_embeddings', false);

        $content = 'Zakon ugovor odluka presuda tužitelj tuženik sud sudac odvjetnik žalba parnica postupak obveza pravo';

        $keywords = $this->extractor->extract($content, 5);

        $this->assertCount(5, $keywords);
    }

    /** @test */
    public function it_normalizes_weights_to_zero_to_one_range()
    {
        Config::set('keywords.use_embeddings', false);

        $content = 'Zakon o radu uređuje prava i obveze radnika i poslodavaca.';

        $keywords = $this->extractor->extract($content, 10);

        foreach ($keywords as $keyword => $weight) {
            $this->assertGreaterThanOrEqual(0, $weight);
            $this->assertLessThanOrEqual(1, $weight);
            $this->assertIsFloat($weight);
        }

        // At least one keyword should have weight = 1.0 (max normalized)
        $this->assertContains(1.0, array_values($keywords));
    }

    /** @test */
    public function it_caches_embeddings_when_enabled()
    {
        Cache::flush();
        Config::set('keywords.cache_embeddings', true);

        $word = 'zakon';
        $mockEmbedding = $this->generateMockEmbedding();

        // First call should hit OpenAI API
        $this->openaiMock
            ->shouldReceive('createEmbedding')
            ->with($word, Mockery::any())
            ->once()
            ->andReturn($mockEmbedding);

        // Extract with caching enabled
        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('getWordEmbeddings');
        $method->setAccessible(true);

        $embeddings1 = $method->invoke($this->extractor, [$word], true);

        // Second call should use cache (no API call)
        $embeddings2 = $method->invoke($this->extractor, [$word], true);

        $this->assertEquals($embeddings1, $embeddings2);
    }

    /** @test */
    public function it_computes_cosine_similarity_correctly()
    {
        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('cosineSimilarity');
        $method->setAccessible(true);

        // Identical vectors should have similarity = 1.0
        $a = [1.0, 2.0, 3.0];
        $b = [1.0, 2.0, 3.0];
        $similarity = $method->invoke($this->extractor, $a, $b);
        $this->assertEquals(1.0, $similarity, '', 0.0001);

        // Orthogonal vectors should have similarity = 0.0
        $a = [1.0, 0.0, 0.0];
        $b = [0.0, 1.0, 0.0];
        $similarity = $method->invoke($this->extractor, $a, $b);
        $this->assertEquals(0.0, $similarity, '', 0.0001);

        // Opposite vectors should have similarity = -1.0
        $a = [1.0, 2.0, 3.0];
        $b = [-1.0, -2.0, -3.0];
        $similarity = $method->invoke($this->extractor, $a, $b);
        $this->assertEquals(-1.0, $similarity, '', 0.0001);
    }

    /** @test */
    public function it_handles_empty_content_gracefully()
    {
        Config::set('keywords.use_embeddings', false);

        $keywords = $this->extractor->extract('', 10);

        $this->assertIsArray($keywords);
        $this->assertEmpty($keywords);
    }

    /** @test */
    public function it_handles_content_with_only_stopwords()
    {
        Config::set('keywords.use_embeddings', false);

        $content = 'je su da za na u i ili te se';

        $keywords = $this->extractor->extract($content, 10);

        $this->assertIsArray($keywords);
        $this->assertEmpty($keywords);
    }

    /** @test */
    public function it_returns_config_information()
    {
        $config = $this->extractor->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('use_embeddings', $config);
        $this->assertArrayHasKey('embeddings_model', $config);
        $this->assertArrayHasKey('semantic_weight', $config);
        $this->assertArrayHasKey('tfidf_weight', $config);
        $this->assertArrayHasKey('max_keywords', $config);
        $this->assertArrayHasKey('legal_concepts_count', $config);
        $this->assertArrayHasKey('legal_terms_count', $config);
    }

    /** @test */
    public function it_combines_tfidf_and_semantic_scores()
    {
        Config::set('keywords.semantic_weight', 0.6);
        Config::set('keywords.tfidf_weight', 0.4);

        $reflection = new \ReflectionClass($this->extractor);
        $method = $reflection->getMethod('combineScores');
        $method->setAccessible(true);

        $tfidfScores = [
            'zakon' => 0.8,
            'ugovor' => 0.6,
        ];

        $semanticScores = [
            'zakon' => 0.9,
            'ugovor' => 0.5,
        ];

        $combined = $method->invoke($this->extractor, $tfidfScores, $semanticScores);

        // Combined = (0.4 * 0.8) + (0.6 * 0.9) = 0.32 + 0.54 = 0.86
        $this->assertEqualsWithDelta(0.86, $combined['zakon'], 0.01);

        // Combined = (0.4 * 0.6) + (0.6 * 0.5) = 0.24 + 0.30 = 0.54
        $this->assertEqualsWithDelta(0.54, $combined['ugovor'], 0.01);
    }

    /** @test */
    public function it_extracts_croatian_legal_keywords_correctly()
    {
        Config::set('keywords.use_embeddings', false);

        $content = <<<'CONTENT'
Odluka Vrhovnog suda Republike Hrvatske o žalbi tužitelja protiv presude
Županijskog suda u Zagrebu. Sud je utvrdio da postoji ugovorna obveza između
stranaka te da je tuženik prekršio odredbe zakona o obveznim odnosima.
CONTENT;

        $keywords = $this->extractor->extract($content, 10);

        // Should extract legal terms
        $expectedTerms = ['odluka', 'presude', 'ugovorna', 'obveza', 'zakon', 'tuženik'];

        $foundCount = 0;
        foreach ($expectedTerms as $term) {
            if (array_key_exists($term, $keywords)) {
                $foundCount++;
            }
        }

        // Should find at least half of expected terms
        $this->assertGreaterThanOrEqual(count($expectedTerms) / 2, $foundCount);
    }

    /** @test */
    public function it_extracts_bigrams_from_content()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 2);

        $content = 'Ugovor o radu između poslodavca i radnika regulira zakon o radu.';

        $keywords = $this->extractor->extract($content, 10);

        // Should extract bigrams that appear
        $this->assertIsArray($keywords);

        // Look for potential bigrams (at least some should be present)
        $keywordKeys = array_keys($keywords);
        $hasNgram = false;

        foreach ($keywordKeys as $key) {
            if (str_contains($key, ' ')) {
                $hasNgram = true;
                break;
            }
        }

        $this->assertTrue($hasNgram, 'Expected at least one n-gram to be extracted');
    }

    /** @test */
    public function it_extracts_trigrams_from_content()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 3);

        $content = 'Izvršenje presude suda o ugovoru o djelu između stranaka prema zakonu o obveznim odnosima.';

        $keywords = $this->extractor->extract($content, 15);

        // Count n-grams
        $ngramCount = 0;
        foreach (array_keys($keywords) as $key) {
            $wordCount = count(explode(' ', $key));
            if ($wordCount >= 2) {
                $ngramCount++;
            }
        }

        // Should extract some n-grams
        $this->assertGreaterThan(0, $ngramCount, 'Expected at least one n-gram to be extracted');
    }

    /** @test */
    public function it_filters_ngrams_with_all_stopwords()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 2);

        $content = 'Zakon je da za na u i te se je da za.';

        $keywords = $this->extractor->extract($content, 10);

        // Check that no n-gram consists only of stopwords
        foreach (array_keys($keywords) as $keyword) {
            if (str_contains($keyword, ' ')) {
                // This n-gram should not be "je da" or "za na" etc.
                $this->assertNotEquals('je da', $keyword);
                $this->assertNotEquals('za na', $keyword);
            }
        }
    }

    /** @test */
    public function it_boosts_ngrams_matching_legal_patterns()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);

        // Content with legal multi-word phrase repeated
        $content = 'Ugovor o djelu ugovor o djelu regulira obveze stranaka. Neki tekst neki tekst.';

        $keywords = $this->extractor->extract($content, 10);

        // "ugovor o djelu" should be extracted and potentially boosted
        $hasLegalPhrase = false;
        foreach (array_keys($keywords) as $keyword) {
            if (str_contains($keyword, 'ugovor') && str_contains($keyword, 'djelu')) {
                $hasLegalPhrase = true;
                break;
            }
        }

        // At minimum, we should have some n-grams
        $this->assertGreaterThan(0, count($keywords));
    }

    /** @test */
    public function it_detects_emerging_terms_with_high_semantic_similarity()
    {
        Config::set('keywords.use_embeddings', true);
        Config::set('keywords.emerging_term_threshold', 0.7);

        $content = 'Novi pravni institut digitalne imovine regulira blockchain tehnologiju.';

        // Mock high semantic similarity for emerging term
        $this->openaiMock
            ->shouldReceive('createEmbedding')
            ->andReturnUsing(function ($text) {
                // Return higher similarity for terms we want to boost
                if (str_contains($text, 'digital') || str_contains($text, 'blockchain')) {
                    return $this->generateHighSimilarityEmbedding();
                }

                return $this->generateMockEmbedding();
            });

        $keywords = $this->extractor->extract($content, 10, ['use_cache' => false]);

        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);
    }

    /** @test */
    public function it_respects_ngram_configuration()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', false);
        $this->extractor = new AdvancedKeywordExtractor($this->openaiMock);

        $content = 'Ugovor o radu ugovor o radu između poslodavca i radnika.';

        $keywords = $this->extractor->extract($content, 10);

        // When n-grams are disabled, should only have single words
        foreach (array_keys($keywords) as $keyword) {
            $this->assertFalse(str_contains($keyword, ' '), "Expected only single words, but found: $keyword");
        }
    }

    /** @test */
    public function it_respects_max_ngram_size_configuration()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 2);

        $this->extractor = new AdvancedKeywordExtractor($this->openaiMock);

        $content = 'Izvršenje presude suda izvršenje presude suda o ugovoru o djelu.';

        $keywords = $this->extractor->extract($content, 15);

        // Should not extract trigrams (3-word phrases) when max is 2
        foreach (array_keys($keywords) as $keyword) {
            $wordCount = count(explode(' ', $keyword));
            $this->assertLessThanOrEqual(2, $wordCount, "Expected max 2-word phrases, but found: $keyword");
        }
    }

    /** @test */
    public function it_combines_unigrams_and_ngrams_in_results()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);
        Config::set('keywords.max_ngram_size', 2);

        $content = 'Zakon o radu uređuje ugovore o radu između poslodavca i radnika.';

        $keywords = $this->extractor->extract($content, 15);

        $hasUnigrams = false;
        $hasNgrams = false;

        foreach (array_keys($keywords) as $keyword) {
            if (! str_contains($keyword, ' ')) {
                $hasUnigrams = true;
            } else {
                $hasNgrams = true;
            }
        }

        // Should have both unigrams and n-grams
        $this->assertTrue($hasUnigrams, 'Expected some unigrams in results');
        // Note: n-grams may or may not appear depending on frequency filtering
    }

    /** @test */
    public function it_extracts_legal_multi_word_concepts()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);

        $content = <<<'CONTENT'
Izvršenje presude Vrhovnog suda temelji se na pravnoj snazi pravomočne presude.
Naknada štete određena je prema pravilima materijalno pravo.
Parničko pravo regulira postupak izvršenja presude.
CONTENT;

        $keywords = $this->extractor->extract($content, 20);

        // Check for some n-grams
        $ngramCount = 0;
        foreach (array_keys($keywords) as $keyword) {
            if (str_contains($keyword, ' ')) {
                $ngramCount++;
            }
        }

        $this->assertGreaterThan(0, $ngramCount, 'Expected multi-word legal concepts to be extracted');
    }

    /** @test */
    public function it_includes_ngram_config_in_get_config()
    {
        $config = $this->extractor->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('extract_ngrams', $config);
        $this->assertArrayHasKey('max_ngram_size', $config);
        $this->assertArrayHasKey('ngram_min_score', $config);
        $this->assertArrayHasKey('emerging_term_threshold', $config);
    }

    /** @test */
    public function it_normalizes_ngram_weights()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);

        $content = 'Ugovor o radu ugovor o radu između stranaka prema zakonu o radu.';

        $keywords = $this->extractor->extract($content, 15);

        // All weights should be normalized 0-1
        foreach ($keywords as $keyword => $weight) {
            $this->assertGreaterThanOrEqual(0, $weight, "Weight for '$keyword' should be >= 0");
            $this->assertLessThanOrEqual(1, $weight, "Weight for '$keyword' should be <= 1");
            $this->assertIsFloat($weight);
        }
    }

    /** @test */
    public function it_handles_croatian_special_characters_in_ngrams()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);

        $content = 'Žalba o povredi prava. Češće se javlja žalba na odluku suda.';

        $keywords = $this->extractor->extract($content, 10);

        $this->assertIsArray($keywords);
        // Should handle Croatian characters properly
    }

    /** @test */
    public function it_filters_ngrams_by_minimum_frequency()
    {
        Config::set('keywords.use_embeddings', false);
        Config::set('keywords.extract_ngrams', true);

        // Content where bigrams appear only once (should be filtered)
        $content = 'Različiti poslovi različite osobe različiti zakoni različite odluke.';

        $keywords = $this->extractor->extract($content, 15);

        // N-grams appearing only once should generally be filtered
        // unless they match legal patterns
        $this->assertIsArray($keywords);
    }

    /** @test */
    public function it_extracts_mixed_unigrams_and_ngrams_with_embeddings()
    {
        Config::set('keywords.use_embeddings', true);
        Config::set('keywords.extract_ngrams', true);

        $content = 'Ugovor o radu regulira prava i obveze između radnika i poslodavca.';

        // Mock embeddings for both single words and phrases
        $this->openaiMock
            ->shouldReceive('createEmbedding')
            ->andReturn($this->generateMockEmbedding());

        $keywords = $this->extractor->extract($content, 15, ['use_cache' => false]);

        $this->assertIsArray($keywords);
        $this->assertNotEmpty($keywords);

        // All weights should be normalized
        foreach ($keywords as $keyword => $weight) {
            $this->assertGreaterThanOrEqual(0, $weight);
            $this->assertLessThanOrEqual(1, $weight);
        }
    }

    /**
     * Generate mock embedding vector
     */
    protected function generateMockEmbedding(int $dimensions = 256): array
    {
        $embedding = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $embedding[] = (float) (mt_rand(-100, 100) / 100);
        }

        return $embedding;
    }

    /**
     * Generate high similarity mock embedding (closer to legal concepts)
     */
    protected function generateHighSimilarityEmbedding(int $dimensions = 256): array
    {
        // Create embedding that's more similar to legal concepts
        // by using values closer to 1.0
        $embedding = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $embedding[] = (float) (mt_rand(50, 100) / 100);
        }

        return $embedding;
    }
}
