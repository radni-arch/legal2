<?php

namespace Tests\Unit\Services\Graph\Extractors;

use App\Services\Graph\Extractors\LegalTopicExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalTopicExtractorTest extends TestCase
{
    protected LegalTopicExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LegalTopicExtractor();
    }

    #[Test]
    public function it_extracts_civil_law_topics(): void
    {
        $text = 'Tužitelj traži naknadu štete temeljem ugovora o kupoprodaji. Obveza prodavatelja je bila predati nekretninu.';

        $topics = $this->extractor->extract($text);

        $this->assertNotEmpty($topics);
        $topicNames = array_column($topics, 'name');
        $this->assertContains('Građansko pravo', $topicNames);
    }

    #[Test]
    public function it_extracts_criminal_law_topics(): void
    {
        $text = 'Optuženik je proglašen krivim za kazneno djelo krađe. Sud je izrekao kaznu zatvora.';

        $topics = $this->extractor->extract($text);

        $this->assertNotEmpty($topics);
        $topicNames = array_column($topics, 'name');
        $this->assertContains('Kazneno pravo', $topicNames);
    }

    #[Test]
    public function it_extracts_labor_law_topics(): void
    {
        $text = 'Radnik je dobio otkaz ugovora o radu. Poslodavac nije isplatio otpremninu ni godišnji odmor.';

        $topics = $this->extractor->extract($text);

        $this->assertNotEmpty($topics);
        $topicNames = array_column($topics, 'name');
        $this->assertContains('Radno pravo', $topicNames);
    }

    #[Test]
    public function it_extracts_multiple_topics(): void
    {
        $text = 'U ovom predmetu riječ je o ugovoru o radu između radnika i poslodavca.
                 Radnik traži naknadu štete zbog nezakonitog otkaza.';

        $topics = $this->extractor->extract($text);

        $this->assertGreaterThanOrEqual(2, count($topics));
    }

    #[Test]
    public function it_returns_empty_for_empty_text(): void
    {
        $this->assertEmpty($this->extractor->extract(''));
        $this->assertEmpty($this->extractor->extract('   '));
    }

    #[Test]
    public function it_calculates_relevance_scores(): void
    {
        $text = 'Ugovor o kupoprodaji nekretnine. Ugovor je valjan. Obveza isplate kupovnine.
                 Naknada štete zbog neispunjenja obveze iz ugovora.';

        $topics = $this->extractor->extract($text);

        $this->assertNotEmpty($topics);
        $firstTopic = $topics[0];
        $this->assertArrayHasKey('relevance', $firstTopic);
        $this->assertGreaterThan(0, $firstTopic['relevance']);
        $this->assertLessThanOrEqual(1.0, $firstTopic['relevance']);
    }

    #[Test]
    public function it_generates_deterministic_topic_ids(): void
    {
        $text = 'Tužitelj traži naknadu štete temeljem ugovora.';

        $topics1 = $this->extractor->extract($text);
        $topics2 = $this->extractor->extract($text);

        $this->assertEquals($topics1, $topics2);
    }

    #[Test]
    public function it_extracts_child_topics(): void
    {
        $text = 'Spor o vlasništvu nekretnine. Založno pravo na nekretnini. Hipoteka. Posjed.';

        $topics = $this->extractor->extract($text);

        $topicNames = array_column($topics, 'name');
        $this->assertContains('Stvarno pravo', $topicNames);

        // Child topic should have parent_id
        $stvarnoTopic = array_filter($topics, fn($t) => $t['name'] === 'Stvarno pravo');
        $stvarnoTopic = reset($stvarnoTopic);
        $this->assertArrayHasKey('parent_id', $stvarnoTopic);
    }

    #[Test]
    public function it_provides_taxonomy_access(): void
    {
        $taxonomy = $this->extractor->getTaxonomy();

        $this->assertIsArray($taxonomy);
        $this->assertArrayHasKey('građansko_pravo', $taxonomy);
        $this->assertArrayHasKey('kazneno_pravo', $taxonomy);
    }
}
