<?php

namespace Tests\Unit\Models;

use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaKeywordMatch;
use App\Models\EoglasnaNotice;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaKeywordMatchTest extends TestCase
{
    use UsesTestDatabase;

    protected EoglasnaKeyword $keyword;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a keyword to use in tests (foreign key requirement)
        $this->keyword = EoglasnaKeyword::factory()->create();

        // Create notices with specific UUIDs that tests expect (foreign key requirement)
        $noticeUuids = [
            'notice-uuid-123', 'uuid-123', 'notice-abc', 'notice-xyz',
            'notice-1', 'notice-2', 'notice-3', 'target-notice',
            'other-notice', 'uuid-minimal', 'stecaj-notice-001', 'uuid-empty',
        ];

        foreach ($noticeUuids as $uuid) {
            EoglasnaNotice::factory()->create([
                'uuid' => $uuid,
                'title' => 'Test notice '.$uuid,
            ]);
        }
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $match = new EoglasnaKeywordMatch;
        $this->assertEquals('eoglasna_keyword_matches', $match->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'notice-uuid-123',
            'matched_at' => now(),
            'matched_fields' => ['title', 'content', 'participants'],
        ]);

        $this->assertEquals($this->keyword->id, $match->keyword_id);
        $this->assertEquals('notice-uuid-123', $match->notice_uuid);
        $this->assertNotNull($match->matched_at);
        $this->assertCount(3, $match->matched_fields);
    }

    /** @test */
    public function it_casts_matched_at_as_datetime()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'uuid-123',
            'matched_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $match->matched_at);
        $this->assertEquals('2024-01-15 10:30:00', $match->matched_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_casts_matched_fields_as_array()
    {
        $fields = ['title', 'case_number', 'participants', 'court_name'];

        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'uuid-123',
            'matched_at' => now(),
            'matched_fields' => $fields,
        ]);

        $this->assertIsArray($match->matched_fields);
        $this->assertEquals($fields, $match->matched_fields);
        $this->assertCount(4, $match->matched_fields);
    }

    /** @test */
    public function it_stores_single_field_match()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'notice-abc',
            'matched_at' => now(),
            'matched_fields' => ['title'],
        ]);

        $this->assertCount(1, $match->matched_fields);
        $this->assertEquals('title', $match->matched_fields[0]);
    }

    /** @test */
    public function it_stores_multiple_field_matches()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'notice-xyz',
            'matched_at' => now(),
            'matched_fields' => [
                'title',
                'content',
                'case_number',
                'participants',
                'court_notice_details',
            ],
        ]);

        $this->assertCount(5, $match->matched_fields);
        $this->assertContains('title', $match->matched_fields);
        $this->assertContains('participants', $match->matched_fields);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'uuid-minimal',
            'matched_at' => null,
            'matched_fields' => null,
        ]);

        $this->assertNull($match->matched_at);
        $this->assertNull($match->matched_fields);
    }

    /** @test */
    public function it_can_query_matches_by_keyword_id()
    {
        EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'notice-1',
            'matched_at' => now(),
        ]);

        EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'notice-2',
            'matched_at' => now(),
        ]);

        $keyword2 = EoglasnaKeyword::factory()->create();
        EoglasnaKeywordMatch::create([
            'keyword_id' => $keyword2->id,
            'notice_uuid' => 'notice-3',
            'matched_at' => now(),
        ]);

        $matches = EoglasnaKeywordMatch::where('keyword_id', $this->keyword->id)->get();

        $this->assertCount(2, $matches);
    }

    /** @test */
    public function it_can_query_matches_by_notice_uuid()
    {
        EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'target-notice',
            'matched_at' => now(),
        ]);

        $keyword2 = EoglasnaKeyword::factory()->create();
        EoglasnaKeywordMatch::create([
            'keyword_id' => $keyword2->id,
            'notice_uuid' => 'target-notice',
            'matched_at' => now(),
        ]);

        $keyword3 = EoglasnaKeyword::factory()->create();
        EoglasnaKeywordMatch::create([
            'keyword_id' => $keyword3->id,
            'notice_uuid' => 'other-notice',
            'matched_at' => now(),
        ]);

        $matches = EoglasnaKeywordMatch::where('notice_uuid', 'target-notice')->get();

        $this->assertCount(2, $matches);
    }

    /** @test */
    public function it_records_when_match_occurred()
    {
        $matchTime = now()->subHours(2);

        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'uuid-123',
            'matched_at' => $matchTime,
        ]);

        $this->assertEquals(
            $matchTime->format('Y-m-d H:i:s'),
            $match->matched_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_stores_croatian_legal_field_matches()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'stecaj-notice-001',
            'matched_at' => now(),
            'matched_fields' => [
                'naslov_oglasa',
                'broj_predmeta',
                'sudionici',
                'sud',
                'vrsta_predmeta',
            ],
        ]);

        $this->assertContains('naslov_oglasa', $match->matched_fields);
        $this->assertContains('broj_predmeta', $match->matched_fields);
        $this->assertContains('sudionici', $match->matched_fields);
    }

    /** @test */
    public function it_can_update_matched_fields()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'uuid-123',
            'matched_fields' => ['title'],
        ]);

        $this->assertCount(1, $match->matched_fields);

        $match->update([
            'matched_fields' => ['title', 'content', 'case_number'],
        ]);

        $this->assertCount(3, $match->fresh()->matched_fields);
    }

    /** @test */
    public function it_tracks_empty_matched_fields()
    {
        $match = EoglasnaKeywordMatch::create([
            'keyword_id' => $this->keyword->id,
            'notice_uuid' => 'uuid-empty',
            'matched_at' => now(),
            'matched_fields' => [],
        ]);

        $this->assertIsArray($match->matched_fields);
        $this->assertCount(0, $match->matched_fields);
    }
}
