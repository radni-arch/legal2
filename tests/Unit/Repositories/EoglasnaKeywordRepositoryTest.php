<?php

namespace Tests\Unit\Repositories;

use App\Models\EoglasnaKeyword;
use App\Repositories\EoglasnaKeywordRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaKeywordRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    private EoglasnaKeywordRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EoglasnaKeywordRepository;
    }

    /** @test */
    public function it_returns_enabled_keywords()
    {
        EoglasnaKeyword::create(['query' => 'keyword1', 'enabled' => true]);
        EoglasnaKeyword::create(['query' => 'keyword2', 'enabled' => true]);
        EoglasnaKeyword::create(['query' => 'keyword3', 'enabled' => false]);

        $result = $this->repository->getEnabled();

        $this->assertCount(2, $result);
        $this->assertEquals('keyword1', $result->first()->query);
    }

    /** @test */
    public function it_returns_empty_collection_when_no_enabled_keywords()
    {
        EoglasnaKeyword::create(['query' => 'keyword1', 'enabled' => false]);
        EoglasnaKeyword::create(['query' => 'keyword2', 'enabled' => false]);

        $result = $this->repository->getEnabled();

        $this->assertCount(0, $result);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    /** @test */
    public function it_returns_keywords_ordered_by_id()
    {
        $keyword3 = EoglasnaKeyword::create(['query' => 'keyword3', 'enabled' => true]);
        $keyword1 = EoglasnaKeyword::create(['query' => 'keyword1', 'enabled' => true]);
        $keyword2 = EoglasnaKeyword::create(['query' => 'keyword2', 'enabled' => true]);

        // Force specific IDs for testing order
        $keyword3->id = 3;
        $keyword1->id = 1;
        $keyword2->id = 2;
        $keyword3->save();
        $keyword1->save();
        $keyword2->save();

        $result = $this->repository->getEnabled();

        $this->assertEquals(1, $result->first()->id);
        $this->assertEquals(3, $result->last()->id);
    }

    /** @test */
    public function it_only_returns_enabled_keywords()
    {
        EoglasnaKeyword::create(['query' => 'enabled1', 'enabled' => true]);
        EoglasnaKeyword::create(['query' => 'disabled1', 'enabled' => false]);
        EoglasnaKeyword::create(['query' => 'enabled2', 'enabled' => true]);
        EoglasnaKeyword::create(['query' => 'disabled2', 'enabled' => false]);
        EoglasnaKeyword::create(['query' => 'enabled3', 'enabled' => true]);

        $result = $this->repository->getEnabled();

        $this->assertCount(3, $result);
        $result->each(function ($keyword) {
            $this->assertTrue($keyword->enabled);
        });
    }

    /** @test */
    public function it_updates_last_run_at_when_touching_run()
    {
        Carbon::setTestNow('2024-01-20 10:00:00');

        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
            'enabled' => true,
        ]);

        $this->assertNull($keyword->last_run_at);

        $this->repository->touchRun($keyword);

        $keyword->refresh();
        $this->assertNotNull($keyword->last_run_at);
        $this->assertEquals('2024-01-20 10:00:00', $keyword->last_run_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_overwrites_previous_last_run_at_when_touching_run()
    {
        Carbon::setTestNow('2024-01-15 10:00:00');

        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
            'last_run_at' => '2024-01-10 08:00:00',
        ]);

        $this->repository->touchRun($keyword);

        $keyword->refresh();
        $this->assertEquals('2024-01-15 10:00:00', $keyword->last_run_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_persists_last_run_at_to_database()
    {
        Carbon::setTestNow('2024-01-20 10:00:00');

        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
        ]);

        $this->repository->touchRun($keyword);

        $fromDb = EoglasnaKeyword::find($keyword->id);
        $this->assertEquals('2024-01-20 10:00:00', $fromDb->last_run_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_updates_cursor_with_last_date_published()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
            'last_date_published' => null,
        ]);

        $newDate = Carbon::parse('2024-01-20 15:00:00');
        $this->repository->updateCursor($keyword, $newDate);

        $keyword->refresh();
        $this->assertNotNull($keyword->last_date_published);
        $this->assertEquals('2024-01-20 15:00:00', $keyword->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_does_not_update_cursor_when_last_date_is_null()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
            'last_date_published' => Carbon::parse('2024-01-15 10:00:00'),
        ]);

        $this->repository->updateCursor($keyword, null);

        $keyword->refresh();
        $this->assertEquals('2024-01-15 10:00:00', $keyword->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_overwrites_previous_last_date_published()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
            'last_date_published' => Carbon::parse('2024-01-10 08:00:00'),
        ]);

        $newDate = Carbon::parse('2024-01-25 16:00:00');
        $this->repository->updateCursor($keyword, $newDate);

        $keyword->refresh();
        $this->assertEquals('2024-01-25 16:00:00', $keyword->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_persists_cursor_update_to_database()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test keyword',
        ]);

        $newDate = Carbon::parse('2024-01-20 15:00:00');
        $this->repository->updateCursor($keyword, $newDate);

        $fromDb = EoglasnaKeyword::find($keyword->id);
        $this->assertEquals('2024-01-20 15:00:00', $fromDb->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_complete_workflow()
    {
        Carbon::setTestNow('2024-01-20 10:00:00');

        // Create keyword
        $keyword = EoglasnaKeyword::create([
            'query' => 'stečaj AND Zagreb',
            'enabled' => true,
        ]);

        // Get enabled keywords
        $enabled = $this->repository->getEnabled();
        $this->assertCount(1, $enabled);

        // Touch run
        $this->repository->touchRun($keyword);
        $keyword->refresh();
        $this->assertEquals('2024-01-20 10:00:00', $keyword->last_run_at->format('Y-m-d H:i:s'));

        // Update cursor
        $newDate = Carbon::parse('2024-01-20 12:00:00');
        $this->repository->updateCursor($keyword, $newDate);
        $keyword->refresh();
        $this->assertEquals('2024-01-20 12:00:00', $keyword->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_multiple_keywords_with_different_states()
    {
        Carbon::setTestNow('2024-01-20 10:00:00');

        $keyword1 = EoglasnaKeyword::create(['query' => 'keyword1', 'enabled' => true]);
        $keyword2 = EoglasnaKeyword::create(['query' => 'keyword2', 'enabled' => true]);
        $keyword3 = EoglasnaKeyword::create(['query' => 'keyword3', 'enabled' => false]);

        $this->repository->touchRun($keyword1);
        $this->repository->updateCursor($keyword1, Carbon::parse('2024-01-20 11:00:00'));

        $this->repository->touchRun($keyword2);
        $this->repository->updateCursor($keyword2, Carbon::parse('2024-01-20 12:00:00'));

        $enabled = $this->repository->getEnabled();
        $this->assertCount(2, $enabled);

        $keyword1->refresh();
        $keyword2->refresh();

        $this->assertEquals('2024-01-20 11:00:00', $keyword1->last_date_published->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-20 12:00:00', $keyword2->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function get_enabled_returns_collection_instance()
    {
        $result = $this->repository->getEnabled();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }
}
