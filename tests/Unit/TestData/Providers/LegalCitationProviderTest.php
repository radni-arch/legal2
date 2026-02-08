<?php

namespace Tests\Unit\TestData\Providers;

use Tests\TestCase;
use Tests\TestData\Providers\LegalCitationProvider;

class LegalCitationProviderTest extends TestCase
{
    private LegalCitationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new LegalCitationProvider;
    }

    public function test_can_get_zkp_articles(): void
    {
        $articles = $this->provider->getZKPArticles();

        $this->assertGreaterThanOrEqual(5, count($articles));
        $this->assertContains('ZKP Čl. 12', array_column($articles, 'citation'));
        $this->assertContains('ZKP Čl. 177', array_column($articles, 'citation'));

        // Verify structure
        foreach ($articles as $article) {
            $this->assertArrayHasKey('law', $article);
            $this->assertArrayHasKey('article', $article);
            $this->assertArrayHasKey('citation', $article);
            $this->assertArrayHasKey('description', $article);
            $this->assertEquals('ZKP', $article['law']);
        }
    }

    public function test_can_get_kz_articles(): void
    {
        $articles = $this->provider->getKZArticles();

        $this->assertGreaterThanOrEqual(5, count($articles));
        $this->assertContains('KZ Čl. 87', array_column($articles, 'citation'));
        $this->assertContains('KZ Čl. 190', array_column($articles, 'citation'));

        // Verify structure
        foreach ($articles as $article) {
            $this->assertArrayHasKey('law', $article);
            $this->assertArrayHasKey('article', $article);
            $this->assertArrayHasKey('citation', $article);
            $this->assertArrayHasKey('description', $article);
            $this->assertEquals('KZ', $article['law']);
        }
    }

    public function test_can_get_ustav_articles(): void
    {
        $articles = $this->provider->getUstavArticles();

        $this->assertGreaterThanOrEqual(4, count($articles));
        $this->assertContains('Ustav RH Čl. 29', array_column($articles, 'citation'));
        $this->assertContains('Ustav RH Čl. 21', array_column($articles, 'citation'));

        // Verify structure
        foreach ($articles as $article) {
            $this->assertArrayHasKey('law', $article);
            $this->assertArrayHasKey('article', $article);
            $this->assertArrayHasKey('citation', $article);
            $this->assertArrayHasKey('description', $article);
            $this->assertEquals('Ustav RH', $article['law']);
        }
    }

    public function test_can_get_random_citation_without_law(): void
    {
        $citation = $this->provider->getRandomCitation();

        $this->assertIsArray($citation);
        $this->assertArrayHasKey('law', $citation);
        $this->assertArrayHasKey('article', $citation);
        $this->assertArrayHasKey('citation', $citation);
        $this->assertArrayHasKey('description', $citation);
        $this->assertContains($citation['law'], ['ZKP', 'KZ', 'Ustav RH']);
    }

    public function test_can_get_random_citation_for_zkp(): void
    {
        $citation = $this->provider->getRandomCitation('ZKP');

        $this->assertIsArray($citation);
        $this->assertEquals('ZKP', $citation['law']);
        $this->assertStringStartsWith('ZKP Čl.', $citation['citation']);
    }

    public function test_can_get_random_citation_for_kz(): void
    {
        $citation = $this->provider->getRandomCitation('KZ');

        $this->assertIsArray($citation);
        $this->assertEquals('KZ', $citation['law']);
        $this->assertStringStartsWith('KZ Čl.', $citation['citation']);
    }

    public function test_can_get_random_citation_for_ustav(): void
    {
        $citation = $this->provider->getRandomCitation('Ustav RH');

        $this->assertIsArray($citation);
        $this->assertEquals('Ustav RH', $citation['law']);
        $this->assertStringStartsWith('Ustav RH Čl.', $citation['citation']);
    }

    public function test_zkp_contains_article_12_right_to_counsel(): void
    {
        $articles = $this->provider->getZKPArticles();
        $article12 = collect($articles)->firstWhere('article', '12');

        $this->assertNotNull($article12);
        $this->assertEquals('ZKP', $article12['law']);
        $this->assertEquals('ZKP Čl. 12', $article12['citation']);
        $this->assertStringContainsStringIgnoringCase('branitelj', $article12['description']);
    }

    public function test_kz_contains_article_87_crimes_against_life(): void
    {
        $articles = $this->provider->getKZArticles();
        $article87 = collect($articles)->firstWhere('article', '87');

        $this->assertNotNull($article87);
        $this->assertEquals('KZ', $article87['law']);
        $this->assertEquals('KZ Čl. 87', $article87['citation']);
        $this->assertStringContainsStringIgnoringCase('život', $article87['description']);
    }

    public function test_ustav_contains_article_29_right_to_fair_trial(): void
    {
        $articles = $this->provider->getUstavArticles();
        $article29 = collect($articles)->firstWhere('article', '29');

        $this->assertNotNull($article29);
        $this->assertEquals('Ustav RH', $article29['law']);
        $this->assertEquals('Ustav RH Čl. 29', $article29['citation']);
        $this->assertStringContainsStringIgnoringCase('pravično', $article29['description']);
    }

    public function test_all_citations_have_croatian_descriptions(): void
    {
        $allArticles = array_merge(
            $this->provider->getZKPArticles(),
            $this->provider->getKZArticles(),
            $this->provider->getUstavArticles()
        );

        foreach ($allArticles as $article) {
            $this->assertNotEmpty($article['description']);
            // Croatian specific characters
            $containsCroatian = preg_match('/[čćžšđČĆŽŠĐ]/', $article['description']);
            $this->assertTrue($containsCroatian || strlen($article['description']) > 10,
                "Description should contain Croatian text: {$article['citation']}");
        }
    }
}
