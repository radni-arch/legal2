<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Repositories\GraphMetricsRepository;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Tests for XSS Protection in GraphViewer
 *
 * These tests verify that user-controlled content from Neo4j is properly
 * escaped to prevent XSS attacks. All content should be auto-escaped by
 * Blade's {{ }} syntax.
 */
class GraphViewerXssProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected $graphService;

    protected $metricsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GraphDatabaseService
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);

        // Mock the GraphMetricsRepository
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Setup default mocks for component mount
     */
    protected function setupDefaultMocks(): void
    {
        // Mock statistics query (node counts)
        $statsRecord = Mockery::mock();
        $statsRecord->shouldReceive('get')->with('label')->andReturn('LawDocument');
        $statsRecord->shouldReceive('get')->with('count')->andReturn(50);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'labels(n)')))
            ->andReturn(collect([$statsRecord]));

        // Mock relationship count query
        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(100);

        $relCountResult = Mockery::mock();
        $relCountResult->shouldReceive('first')->andReturn($relCountRecord);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'MATCH ()-[r]->()')))
            ->andReturn($relCountResult);

        // Mock recent nodes query
        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'created_at IS NOT NULL')))
            ->andReturn(collect([]));

        // Mock metrics repository
        $this->metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $this->metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $this->metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);
    }

    /** @test */
    public function it_escapes_xss_in_node_title(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = '<script>alert("xss")</script>';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'title' => $xssPayload,
                'law_number' => 'NN 50/10',
            ])
            // Should see escaped HTML entities, not raw script tag
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
            // Should NOT see the raw script tag
            ->assertDontSee($xssPayload, false);
    }

    /** @test */
    public function it_escapes_xss_in_amendment_law_numbers(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = '<img src=x onerror=alert(1)>';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'law_number' => 'NN 50/10',
                'amendments' => [$xssPayload, 'NN 123/21'],
            ])
            ->assertSee('Amendments (2)')
            // Should see escaped HTML
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            // Should NOT see raw img tag
            ->assertDontSee($xssPayload, false);
    }

    /** @test */
    public function it_escapes_xss_in_holding_text(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = '<iframe src="evil.com"></iframe>';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'decision-123',
                'case_number' => 'Us-1234/2024',
                'holding' => $xssPayload,
            ])
            // Should see escaped HTML
            ->assertSee('&lt;iframe src=&quot;evil.com&quot;&gt;&lt;/iframe&gt;', false)
            // Should NOT see raw iframe tag
            ->assertDontSee($xssPayload, false);
    }

    /** @test */
    public function it_escapes_xss_in_repealing_law_reference(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = '"><script>evil()</script>';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'law_number' => 'NN 50/10',
                'repeal_date' => '2025-12-31',
                'repealed_by' => $xssPayload,
            ])
            ->assertSee('Repealed')
            // Should see escaped HTML
            ->assertSee('&quot;&gt;&lt;script&gt;evil()&lt;/script&gt;', false)
            // Should NOT see raw script tag
            ->assertDontSee($xssPayload, false);
    }

    /** @test */
    public function it_escapes_xss_in_parent_law_number(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = '<svg/onload=alert("xss")>';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'law_number' => 'NN 50/10',
                'parent_law_number' => $xssPayload,
            ])
            ->assertSee('Amends Original Law')
            // Should see escaped HTML
            ->assertSee('&lt;svg/onload=alert(&quot;xss&quot;)&gt;', false)
            // Should NOT see raw svg tag
            ->assertDontSee($xssPayload, false);
    }

    /** @test */
    public function it_escapes_xss_in_law_number_field(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = 'NN<script>alert(1)</script>50/10';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'law_number' => $xssPayload,
            ])
            // Should see escaped HTML
            ->assertSee('NN&lt;script&gt;alert(1)&lt;/script&gt;50/10', false)
            // Should NOT see raw script tag
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @test */
    public function it_escapes_xss_in_case_number(): void
    {
        $this->setupDefaultMocks();

        $xssPayload = 'Us<img src=x onerror=alert(1)>1234/2024';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'decision-123',
                'case_number' => $xssPayload,
            ])
            // Should see escaped HTML
            ->assertSee('Us&lt;img src=x onerror=alert(1)&gt;1234/2024', false)
            // Should NOT see raw img tag
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    /** @test */
    public function it_escapes_multiple_xss_payloads_in_same_node(): void
    {
        $this->setupDefaultMocks();

        $titlePayload = '<script>alert("title")</script>';
        $amendmentPayload = '<img src=x onerror=alert(1)>';
        $repealPayload = '"><script>evil()</script>';

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'title' => $titlePayload,
                'law_number' => 'NN 50/10',
                'amendments' => [$amendmentPayload],
                'repealed_by' => $repealPayload,
            ])
            // All should be escaped
            ->assertSee('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('&quot;&gt;&lt;script&gt;evil()&lt;/script&gt;', false)
            // None should be raw
            ->assertDontSee($titlePayload, false)
            ->assertDontSee($amendmentPayload, false)
            ->assertDontSee($repealPayload, false);
    }
}
