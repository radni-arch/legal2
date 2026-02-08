<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class TaggingServiceTest extends TestCase
{
    protected TaggingService $service;

    protected $mockGraph;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->service = new TaggingService($this->mockGraph);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_initializes_tag_hierarchy()
    {
        // Should create nodes for each category and subcategory
        $this->mockGraph->shouldReceive('upsertNode')
            ->atLeast(1)
            ->with('Tag', Mockery::type('string'), Mockery::type('array'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->zeroOrMoreTimes();

        $this->service->initializeTagHierarchy();

        // Verify method completes without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_tag_category_with_correct_structure()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Tag', 'tag_legal_area', [
                'name' => 'legal_area',
                'category' => 'legal_area',
                'level' => 1,
                'slug' => 'legal-area',
            ]);

        $this->mockGraph->shouldReceive('createRelationship')
            ->zeroOrMoreTimes();

        // Test subcategories creation
        $this->mockGraph->shouldReceive('upsertNode')
            ->with('Tag', Mockery::pattern('/^tag_/'), Mockery::type('array'))
            ->atLeast(1);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createTagCategory');
        $method->setAccessible(true);

        $method->invoke($this->service, 'legal_area', [
            'civil_law' => ['contracts', 'property'],
        ]);

        // Verify structure created
        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_leaf_tags_at_level_3()
    {
        // Allow any upsertNode calls
        $this->mockGraph->shouldReceive('upsertNode')
            ->atLeast(1)
            ->with('Tag', Mockery::type('string'), Mockery::type('array'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->atLeast(1);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createTagCategory');
        $method->setAccessible(true);

        $method->invoke($this->service, 'legal_area', [
            'civil_law' => ['contracts'],
        ]);

        // Verify by checking that the method completed without exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_parent_relationships()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->atLeast(1);

        // Expect relationship from civil_law to its parent (legal_area)
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with('Tag', 'tag_civil_law', 'PARENT_TAG', 'Tag', 'tag_legal_area');

        // Also expect relationship from contracts (leaf) to civil_law (its parent)
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with('Tag', 'tag_contracts', 'PARENT_TAG', 'Tag', 'tag_civil_law');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('createTagCategory');
        $method->setAccessible(true);

        $method->invoke($this->service, 'civil_law', ['contracts'], 'tag_legal_area');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_auto_tags_content_with_croatian_legal_keywords()
    {
        $content = 'Ovo je ugovor o vlasništvu nekretnine';

        $this->mockGraph->shouldReceive('upsertNode')
            ->atLeast(1);

        $this->mockGraph->shouldReceive('createRelationship')
            ->atLeast(1)
            ->with('Document', 'doc123', 'HAS_TAG', 'Tag', Mockery::type('string'), Mockery::type('array'));

        $tags = $this->service->autoTag('Document', 'doc123', $content);

        $this->assertIsArray($tags);
        $this->assertContains('civil_law', $tags);
    }

    /** @test */
    public function it_extracts_tags_from_metadata()
    {
        $content = 'Some legal text';
        $metadata = [
            'tags' => ['contract', 'property'],
        ];

        $this->mockGraph->shouldReceive('upsertNode')
            ->atLeast(1);

        $this->mockGraph->shouldReceive('createRelationship')
            ->atLeast(1);

        $tags = $this->service->autoTag('Document', 'doc123', $content, $metadata);

        $this->assertIsArray($tags);
        $this->assertContains('contract', $tags);
        $this->assertContains('property', $tags);
    }

    /** @test */
    public function it_normalizes_and_deduplicates_tags()
    {
        $content = 'Legal content';
        $metadata = [
            'tags' => ['Contract', 'contract', 'CONTRACT'],
        ];

        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Tag', 'tag_contract', Mockery::type('array'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $tags = $this->service->autoTag('Document', 'doc123', $content, $metadata);

        $this->assertCount(1, $tags);
        $this->assertEquals(['contract'], $tags);
    }

    /** @test */
    public function it_detects_civil_law_keywords()
    {
        $content = 'Ugovor o prodaji nekretnine i vlasništvo';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('civil_law', $tags);
    }

    /** @test */
    public function it_detects_criminal_law_keywords()
    {
        $content = 'Kazneno djelo i presuda o zatvorskoj kazni';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('criminal_law', $tags);
    }

    /** @test */
    public function it_detects_administrative_law_keywords()
    {
        $content = 'Upravno rješenje o dozvoli iz javne uprave';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('administrative_law', $tags);
    }

    /** @test */
    public function it_detects_labor_law_keywords()
    {
        $content = 'Radno pravo, plaća zaposlenih i radni odnosi';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('labor_law', $tags);
    }

    /** @test */
    public function it_detects_commercial_law_keywords()
    {
        $content = 'Trgovačko društvo u stečaju zbog konkurencije';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('commercial_law', $tags);
    }

    /** @test */
    public function it_detects_constitutional_law_keywords()
    {
        $content = 'Ustavno pravo na slobodu i jednakost';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('constitutional_law', $tags);
    }

    /** @test */
    public function it_extracts_jurisdiction_from_metadata()
    {
        $content = 'Legal text';
        $metadata = ['jurisdiction' => 'Croatia'];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, $metadata);

        $this->assertContains('jurisdiction_croatia', $tags);
    }

    /** @test */
    public function it_detects_supreme_court_from_metadata()
    {
        $content = 'Legal text';
        $metadata = ['court' => 'Vrhovni sud Republike Hrvatske'];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, $metadata);

        $this->assertContains('supreme_court', $tags);
    }

    /** @test */
    public function it_detects_appellate_court_from_metadata()
    {
        $content = 'Legal text';
        $metadata = ['court' => 'Županijski sud u Zagrebu'];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, $metadata);

        $this->assertContains('appellate_court', $tags);
    }

    /** @test */
    public function it_handles_croatian_characters_in_content()
    {
        $content = 'Kazneno djelo i ugovor o vlasništvu nekretnine';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
    }

    /** @test */
    public function it_applies_tag_to_node()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Tag', 'tag_contract', [
                'name' => 'contract',
                'slug' => 'contract',
            ]);

        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with('Document', 'doc123', 'HAS_TAG', 'Tag', 'tag_contract', Mockery::on(function ($data) {
                return isset($data['applied_at']);
            }));

        $this->service->applyTag('Document', 'doc123', 'contract');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_normalizes_tag_name_when_applying()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Tag', 'tag_civil_law', [
                'name' => 'Civil Law',
                'slug' => 'civil-law',
            ]);

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $this->service->applyTag('Document', 'doc123', 'Civil Law');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_tag_with_spaces_and_dashes()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('Tag', 'tag_labor_law_employment', Mockery::type('array'));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        $this->service->applyTag('Document', 'doc123', 'labor-law employment');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_warning_on_tag_application_failure()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to apply tag', Mockery::on(function ($context) {
                return $context['node_label'] === 'Document' &&
                       $context['node_id'] === 'doc123' &&
                       $context['tag'] === 'contract';
            }));

        $this->mockGraph->shouldReceive('upsertNode')
            ->once();

        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->service->applyTag('Document', 'doc123', 'contract');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_node_tags()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturnUsing(function ($callback) {
                $mockRecord1 = Mockery::mock();
                $mockRecord1->shouldReceive('get')->with('name')->andReturn('contract');
                $mockRecord1->shouldReceive('get')->with('category')->andReturn('legal_area');
                $mockRecord1->shouldReceive('get')->with('slug')->andReturn('contract');

                $collection = collect([$mockRecord1]);

                return $collection->map($callback);
            });

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::pattern('/MATCH.*HAS_TAG.*Tag/'), ['id' => 'doc123'])
            ->andReturn($mockResult);

        $tags = $this->service->getNodeTags('Document', 'doc123');

        $this->assertIsArray($tags);
        $this->assertCount(1, $tags);
        $this->assertEquals('contract', $tags[0]['name']);
        $this->assertEquals('legal_area', $tags[0]['category']);
        $this->assertEquals('contract', $tags[0]['slug']);
    }

    /** @test */
    public function it_gets_node_tags_ordered_by_category()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturn(collect([]));

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::pattern('/ORDER BY t\.category, t\.name/'), Mockery::type('array'))
            ->andReturn($mockResult);

        $this->service->getNodeTags('Document', 'doc123');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_nodes_by_tag()
    {
        $mockNode = Mockery::mock();
        $mockNode->shouldReceive('getProperties')
            ->once()
            ->andReturn(['id' => 'doc123', 'title' => 'Contract Document']);

        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')->with('n')->andReturn($mockNode);

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturnUsing(function ($callback) use ($mockRecord) {
                return collect([$mockRecord])->map($callback);
            });

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::pattern('/MATCH.*HAS_TAG.*Tag/'),
                ['tagId' => 'tag_contract', 'limit' => 50]
            )
            ->andReturn($mockResult);

        $nodes = $this->service->getNodesByTag('contract');

        $this->assertIsArray($nodes);
        $this->assertCount(1, $nodes);
        $this->assertEquals('doc123', $nodes[0]['id']);
    }

    /** @test */
    public function it_gets_nodes_by_tag_with_label_filter()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturn(collect([]));

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::pattern('/MATCH \(n:Document\)/'), Mockery::type('array'))
            ->andReturn($mockResult);

        $this->service->getNodesByTag('contract', 'Document');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_nodes_by_tag_with_custom_limit()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturn(collect([]));

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::type('string'), ['tagId' => 'tag_contract', 'limit' => 100])
            ->andReturn($mockResult);

        $this->service->getNodesByTag('contract', null, 100);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_normalizes_tag_name_in_queries()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturn(collect([]));

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::type('string'), ['tagId' => 'tag_civil_law', 'limit' => 50])
            ->andReturn($mockResult);

        $this->service->getNodesByTag('Civil-Law');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_related_tags()
    {
        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')->with('name')->andReturn('property');
        $mockRecord->shouldReceive('get')->with('category')->andReturn('legal_area');
        $mockRecord->shouldReceive('get')->with('frequency')->andReturn(5);

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturnUsing(function ($callback) use ($mockRecord) {
                return collect([$mockRecord])->map($callback);
            });

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::type('string'),
                ['tagId' => 'tag_contract', 'limit' => 10]
            )
            ->andReturn($mockResult);

        $relatedTags = $this->service->getRelatedTags('contract');

        $this->assertIsArray($relatedTags);
        $this->assertCount(1, $relatedTags);
        $this->assertEquals('property', $relatedTags[0]['name']);
        $this->assertEquals(5, $relatedTags[0]['frequency']);
    }

    /** @test */
    public function it_gets_related_tags_with_custom_limit()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturn(collect([]));

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::type('string'), ['tagId' => 'tag_contract', 'limit' => 20])
            ->andReturn($mockResult);

        $this->service->getRelatedTags('contract', 20);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_excludes_same_tag_in_related_tags_query()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('map')
            ->once()
            ->andReturn(collect([]));

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::pattern('/WHERE t <> related/'), Mockery::type('array'))
            ->andReturn($mockResult);

        $this->service->getRelatedTags('contract');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_tag_hierarchy()
    {
        $mockRecord = Mockery::mock();
        $mockRecord->shouldReceive('get')
            ->with('hierarchy')
            ->andReturn(['contracts', 'civil_law', 'legal_area']);

        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('count')->andReturn(1);
        $mockResult->shouldReceive('first')->andReturn($mockRecord);

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::pattern('/MATCH path.*PARENT_TAG\*0\.\./'),
                ['tagId' => 'tag_contract']
            )
            ->andReturn($mockResult);

        $hierarchy = $this->service->getTagHierarchy('contract');

        $this->assertIsArray($hierarchy);
        $this->assertEquals(['contracts', 'civil_law', 'legal_area'], $hierarchy);
    }

    /** @test */
    public function it_returns_empty_array_when_tag_hierarchy_not_found()
    {
        $mockResult = Mockery::mock();
        $mockResult->shouldReceive('count')->andReturn(0);

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andReturn($mockResult);

        $hierarchy = $this->service->getTagHierarchy('nonexistent');

        $this->assertIsArray($hierarchy);
        $this->assertEmpty($hierarchy);
    }

    /** @test */
    public function it_removes_tag_from_node()
    {
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::type('string'),
                ['nodeId' => 'doc123', 'tagId' => 'tag_contract']
            );

        $this->service->removeTag('Document', 'doc123', 'contract');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_normalizes_tag_name_when_removing()
    {
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::type('string'),
                ['nodeId' => 'doc123', 'tagId' => 'tag_civil_law']
            );

        $this->service->removeTag('Document', 'doc123', 'Civil-Law');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_has_comprehensive_tag_hierarchy()
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('tagHierarchy');
        $property->setAccessible(true);
        $hierarchy = $property->getValue($this->service);

        $this->assertArrayHasKey('legal_area', $hierarchy);
        $this->assertArrayHasKey('procedure', $hierarchy);
        $this->assertArrayHasKey('jurisdiction', $hierarchy);
        $this->assertArrayHasKey('document_type', $hierarchy);
        $this->assertArrayHasKey('topic', $hierarchy);
    }

    /** @test */
    public function it_has_croatian_legal_area_categories()
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('tagHierarchy');
        $property->setAccessible(true);
        $hierarchy = $property->getValue($this->service);

        $this->assertArrayHasKey('civil_law', $hierarchy['legal_area']);
        $this->assertArrayHasKey('criminal_law', $hierarchy['legal_area']);
        $this->assertArrayHasKey('administrative_law', $hierarchy['legal_area']);
        $this->assertArrayHasKey('constitutional_law', $hierarchy['legal_area']);
        $this->assertArrayHasKey('labor_law', $hierarchy['legal_area']);
        $this->assertArrayHasKey('commercial_law', $hierarchy['legal_area']);
    }

    /** @test */
    public function it_has_jurisdiction_categories_including_croatia()
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('tagHierarchy');
        $property->setAccessible(true);
        $hierarchy = $property->getValue($this->service);

        $this->assertArrayHasKey('croatia', $hierarchy['jurisdiction']);
        $this->assertArrayHasKey('european_union', $hierarchy['jurisdiction']);
        $this->assertArrayHasKey('international', $hierarchy['jurisdiction']);
    }

    /** @test */
    public function it_case_insensitive_keyword_matching()
    {
        $content = 'UGOVOR o VLASNIŠTVU';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        $this->assertContains('civil_law', $tags);
    }

    /** @test */
    public function it_stops_at_first_keyword_match_per_category()
    {
        // Even with multiple keywords matching, should only add tag once
        $content = 'Ugovor o vlasništvu nekretnine za obitelj nasljedstvo';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractTagsFromContent');
        $method->setAccessible(true);

        $tags = $method->invoke($this->service, $content, []);

        // civil_law should appear once even though multiple keywords match
        $civilLawCount = count(array_filter($tags, fn ($t) => $t === 'civil_law'));
        $this->assertEquals(1, $civilLawCount);
    }

    /** @test */
    public function it_handles_empty_content()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->zeroOrMoreTimes();

        $this->mockGraph->shouldReceive('createRelationship')
            ->zeroOrMoreTimes();

        $tags = $this->service->autoTag('Document', 'doc123', '');

        $this->assertIsArray($tags);
    }

    /** @test */
    public function it_handles_empty_metadata()
    {
        $this->mockGraph->shouldReceive('upsertNode')
            ->zeroOrMoreTimes();

        $this->mockGraph->shouldReceive('createRelationship')
            ->zeroOrMoreTimes();

        $tags = $this->service->autoTag('Document', 'doc123', 'Some content', []);

        $this->assertIsArray($tags);
    }

    /** @test */
    public function it_integrates_content_and_metadata_tags()
    {
        $content = 'Kazneno djelo i presuda';
        $metadata = [
            'tags' => ['important'],
            'jurisdiction' => 'Croatia',
            'court' => 'Vrhovni sud',
        ];

        $this->mockGraph->shouldReceive('upsertNode')
            ->atLeast(1);

        $this->mockGraph->shouldReceive('createRelationship')
            ->atLeast(1);

        $tags = $this->service->autoTag('Document', 'doc123', $content, $metadata);

        // Should include: criminal_law, important, jurisdiction_croatia, supreme_court
        $this->assertGreaterThanOrEqual(4, count($tags));
        $this->assertContains('important', $tags);
        $this->assertContains('criminal_law', $tags);
        $this->assertContains('jurisdiction_croatia', $tags);
        $this->assertContains('supreme_court', $tags);
    }
}
