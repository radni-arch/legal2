<?php

namespace Tests\Unit\Repositories;

use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaKeywordMatch;
use App\Repositories\EoglasnaKeywordMatchRepository;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaKeywordMatchRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    private EoglasnaKeywordMatchRepository $repository;

    private EoglasnaKeyword $keyword1;

    private EoglasnaKeyword $keyword2;

    private EoglasnaKeyword $keyword3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EoglasnaKeywordMatchRepository;

        // Disable foreign key constraints for unit tests (these tests don't need full notice records)
        \DB::statement('SET session_replication_role = replica');

        // Create test keywords that can be referenced in tests
        $this->keyword1 = EoglasnaKeyword::factory()->create(['id' => 1]);
        $this->keyword2 = EoglasnaKeyword::factory()->create(['id' => 2]);
        $this->keyword3 = EoglasnaKeyword::factory()->create(['id' => 3]);
    }

    protected function tearDown(): void
    {
        // Re-enable foreign key constraints after tests
        \DB::statement('SET session_replication_role = origin');
        parent::tearDown();
    }

    /** @test */
    public function it_records_new_match()
    {
        Carbon::setTestNow('2024-01-20 10:00:00');

        $result = $this->repository->recordMatch(
            keywordId: 1,
            noticeUuid: 'notice-uuid-123',
            metadata: ['title', 'content']
        );

        $this->assertInstanceOf(EoglasnaKeywordMatch::class, $result);
        $this->assertEquals(1, $result->keyword_id);
        $this->assertEquals('notice-uuid-123', $result->notice_uuid);
        $this->assertEquals(['title', 'content'], $result->matched_fields);
        $this->assertEquals('2024-01-20 10:00:00', $result->matched_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_avoids_duplicate_matches()
    {
        $this->repository->recordMatch(1, 'notice-123', ['title']);
        $this->repository->recordMatch(1, 'notice-123', ['title', 'content']);

        $this->assertEquals(1, EoglasnaKeywordMatch::count());
    }

    /** @test */
    public function it_updates_matched_fields_on_duplicate()
    {
        $first = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $this->assertEquals(['title'], $first->matched_fields);

        $second = $this->repository->recordMatch(1, 'notice-123', ['title', 'content', 'participants']);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(['title', 'content', 'participants'], $second->matched_fields);
    }

    /** @test */
    public function it_does_not_update_matched_at_on_duplicate()
    {
        Carbon::setTestNow('2024-01-15 10:00:00');
        $first = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $originalMatchedAt = $first->matched_at;

        Carbon::setTestNow('2024-01-20 15:00:00');
        $second = $this->repository->recordMatch(1, 'notice-123', ['title', 'content']);

        $this->assertEquals(
            $originalMatchedAt->format('Y-m-d H:i:s'),
            $second->matched_at->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_handles_empty_metadata()
    {
        $result = $this->repository->recordMatch(1, 'notice-123', []);

        $this->assertEquals([], $result->matched_fields);
    }

    /** @test */
    public function it_handles_null_metadata_on_new_match()
    {
        $result = $this->repository->recordMatch(1, 'notice-123');

        $this->assertNull($result->matched_fields);
    }

    /** @test */
    public function it_preserves_existing_matched_fields_when_metadata_is_empty_array()
    {
        $first = $this->repository->recordMatch(1, 'notice-123', ['title', 'content']);
        $this->assertEquals(['title', 'content'], $first->matched_fields);

        $second = $this->repository->recordMatch(1, 'notice-123', []);

        // Empty array should preserve existing fields
        $this->assertEquals(['title', 'content'], $second->matched_fields);
    }

    /** @test */
    public function it_creates_separate_matches_for_different_keyword_ids()
    {
        $match1 = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $match2 = $this->repository->recordMatch(2, 'notice-123', ['content']);

        $this->assertNotEquals($match1->id, $match2->id);
        $this->assertEquals(2, EoglasnaKeywordMatch::count());
    }

    /** @test */
    public function it_creates_separate_matches_for_different_notice_uuids()
    {
        $match1 = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $match2 = $this->repository->recordMatch(1, 'notice-456', ['title']);

        $this->assertNotEquals($match1->id, $match2->id);
        $this->assertEquals(2, EoglasnaKeywordMatch::count());
    }

    /** @test */
    public function it_handles_complex_metadata()
    {
        $metadata = [
            'title',
            'content',
            'case_number',
            'participants',
            'court_name',
            'court_notice_details',
        ];

        $result = $this->repository->recordMatch(1, 'notice-123', $metadata);

        $this->assertEquals($metadata, $result->matched_fields);
        $this->assertCount(6, $result->matched_fields);
    }

    /** @test */
    public function it_persists_match_to_database()
    {
        $this->repository->recordMatch(1, 'notice-123', ['title']);

        $fromDb = EoglasnaKeywordMatch::where('keyword_id', 1)
            ->where('notice_uuid', 'notice-123')
            ->first();

        $this->assertNotNull($fromDb);
        $this->assertEquals(['title'], $fromDb->matched_fields);
    }

    /** @test */
    public function it_returns_existing_match_on_duplicate()
    {
        $first = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $second = $this->repository->recordMatch(1, 'notice-123', ['content']);

        $this->assertTrue($first->is($second));
    }

    /** @test */
    public function it_handles_multiple_matches_for_same_keyword()
    {
        $match1 = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $match2 = $this->repository->recordMatch(1, 'notice-456', ['content']);
        $match3 = $this->repository->recordMatch(1, 'notice-789', ['participants']);

        $matches = EoglasnaKeywordMatch::where('keyword_id', 1)->get();

        $this->assertCount(3, $matches);
    }

    /** @test */
    public function it_handles_multiple_keywords_matching_same_notice()
    {
        $match1 = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $match2 = $this->repository->recordMatch(2, 'notice-123', ['content']);
        $match3 = $this->repository->recordMatch(3, 'notice-123', ['case_number']);

        $matches = EoglasnaKeywordMatch::where('notice_uuid', 'notice-123')->get();

        $this->assertCount(3, $matches);
    }

    /** @test */
    public function it_sets_matched_at_only_for_new_matches()
    {
        Carbon::setTestNow('2024-01-15 10:00:00');

        $match1 = $this->repository->recordMatch(1, 'notice-123', ['title']);
        $this->assertNotNull($match1->matched_at);
        $this->assertEquals('2024-01-15 10:00:00', $match1->matched_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2024-01-20 15:00:00');

        $match2 = $this->repository->recordMatch(1, 'notice-123', ['content']);
        // Should keep original matched_at
        $this->assertEquals('2024-01-15 10:00:00', $match2->matched_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_croatian_field_names_in_metadata()
    {
        $metadata = [
            'naslov_oglasa',
            'broj_predmeta',
            'sudionici',
            'sud',
            'vrsta_predmeta',
        ];

        $result = $this->repository->recordMatch(1, 'notice-123', $metadata);

        $this->assertContains('naslov_oglasa', $result->matched_fields);
        $this->assertContains('sudionici', $result->matched_fields);
    }
}
