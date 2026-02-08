<?php

namespace Tests\Unit\Models;

use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaKeywordMatch;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaKeywordTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable foreign key constraints for unit tests (these tests don't need full notice records)
        \DB::statement('SET session_replication_role = replica');
    }

    protected function tearDown(): void
    {
        // Re-enable foreign key constraints after tests
        \DB::statement('SET session_replication_role = origin');
        parent::tearDown();
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $keyword = new EoglasnaKeyword;
        $this->assertEquals('eoglasna_keywords', $keyword->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'stečaj AND Zagreb',
            'scope' => 'all_courts',
            'deep_scan' => true,
            'enabled' => true,
            'last_run_at' => now(),
            'last_date_published' => now()->subDays(7),
            'notes' => 'Monitor bankruptcy cases in Zagreb',
        ]);

        $this->assertEquals('stečaj AND Zagreb', $keyword->query);
        $this->assertEquals('all_courts', $keyword->scope);
        $this->assertTrue($keyword->deep_scan);
        $this->assertTrue($keyword->enabled);
        $this->assertEquals('Monitor bankruptcy cases in Zagreb', $keyword->notes);
    }

    /** @test */
    public function it_casts_deep_scan_as_boolean()
    {
        $deepScan = EoglasnaKeyword::create([
            'query' => 'test query',
            'deep_scan' => true,
        ]);

        $noDeepScan = EoglasnaKeyword::create([
            'query' => 'another query',
            'deep_scan' => false,
        ]);

        $this->assertIsBool($deepScan->deep_scan);
        $this->assertTrue($deepScan->deep_scan);
        $this->assertFalse($noDeepScan->deep_scan);
    }

    /** @test */
    public function it_casts_enabled_as_boolean()
    {
        $enabled = EoglasnaKeyword::create([
            'query' => 'enabled query',
            'enabled' => true,
        ]);

        $disabled = EoglasnaKeyword::create([
            'query' => 'disabled query',
            'enabled' => false,
        ]);

        $this->assertIsBool($enabled->enabled);
        $this->assertTrue($enabled->enabled);
        $this->assertFalse($disabled->enabled);
    }

    /** @test */
    public function it_casts_datetime_fields()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test',
            'last_run_at' => '2024-01-15 10:00:00',
            'last_date_published' => '2024-01-10 08:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $keyword->last_run_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $keyword->last_date_published);
        $this->assertEquals('2024-01-15 10:00:00', $keyword->last_run_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-10 08:00:00', $keyword->last_date_published->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_has_many_matches()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'stečaj',
            'enabled' => true,
        ]);

        EoglasnaKeywordMatch::create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => 'uuid-123',
            'matched_at' => now(),
            'matched_fields' => ['title', 'content'],
        ]);

        EoglasnaKeywordMatch::create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => 'uuid-456',
            'matched_at' => now(),
            'matched_fields' => ['title'],
        ]);

        $this->assertCount(2, $keyword->matches);
        $this->assertInstanceOf(EoglasnaKeywordMatch::class, $keyword->matches->first());
    }

    /** @test */
    public function it_stores_croatian_legal_search_queries()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'likvidacija OR stečajni postupak',
            'scope' => 'commercial_courts',
            'deep_scan' => true,
            'enabled' => true,
            'notes' => 'Praćenje stečajnih postupaka trgovačkih društava',
        ]);

        $this->assertEquals('likvidacija OR stečajni postupak', $keyword->query);
        $this->assertEquals('commercial_courts', $keyword->scope);
        $this->assertTrue($keyword->deep_scan);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test',
            'scope' => null,
            'last_run_at' => null,
            'last_date_published' => null,
            'notes' => null,
        ]);

        // scope has a default value of 'notice' set in the boot method
        $this->assertEquals('notice', $keyword->scope);
        $this->assertNull($keyword->last_run_at);
        $this->assertNull($keyword->last_date_published);
        $this->assertNull($keyword->notes);
    }

    /** @test */
    public function it_can_update_last_run_timestamp()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test query',
            'enabled' => true,
        ]);

        $this->assertNull($keyword->last_run_at);

        $runTime = now();
        $keyword->update(['last_run_at' => $runTime]);

        $this->assertNotNull($keyword->fresh()->last_run_at);
        $this->assertEquals(
            $runTime->format('Y-m-d H:i:s'),
            $keyword->fresh()->last_run_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_can_disable_keyword_monitoring()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test',
            'enabled' => true,
        ]);

        $this->assertTrue($keyword->enabled);

        $keyword->update(['enabled' => false]);

        $this->assertFalse($keyword->fresh()->enabled);
    }

    /** @test */
    public function it_can_toggle_deep_scan_mode()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'important case',
            'deep_scan' => false,
        ]);

        $this->assertFalse($keyword->deep_scan);

        $keyword->update(['deep_scan' => true]);

        $this->assertTrue($keyword->fresh()->deep_scan);
    }

    /** @test */
    public function it_updates_last_date_published()
    {
        $keyword = EoglasnaKeyword::create([
            'query' => 'test',
            'last_date_published' => '2024-01-01 00:00:00',
        ]);

        $newDate = now();
        $keyword->update(['last_date_published' => $newDate]);

        $this->assertEquals(
            $newDate->format('Y-m-d H:i:s'),
            $keyword->fresh()->last_date_published->format('Y-m-d H:i:s')
        );
    }
}
