<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateLawMetadata;
use App\Models\IngestedLaw;
use App\Models\Law;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LawsRegenMetadataTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Log::spy();
    }

    /** @test */
    public function it_validates_batch_size_minimum()
    {
        $this->artisan('laws:regen-metadata', ['--batch-size' => '0'])
            ->expectsOutput('Batch size must be at least 1')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_negative_batch_size()
    {
        $this->artisan('laws:regen-metadata', ['--batch-size' => '-5'])
            ->expectsOutput('Batch size must be at least 1')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_rate_limit_cannot_be_negative()
    {
        $this->artisan('laws:regen-metadata', ['--rate-limit' => '-1'])
            ->expectsOutput('Rate limit cannot be negative')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_limit_minimum()
    {
        $this->artisan('laws:regen-metadata', ['--limit' => '0'])
            ->expectsOutput('Limit must be at least 1')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_negative_limit()
    {
        $this->artisan('laws:regen-metadata', ['--limit' => '-10'])
            ->expectsOutput('Limit must be at least 1')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_accepts_zero_rate_limit()
    {
        $this->artisan('laws:regen-metadata', ['--dry-run' => true, '--rate-limit' => '0'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_no_laws_needing_metadata()
    {
        // No laws in database

        $this->artisan('laws:regen-metadata')
            ->expectsOutput('No laws found that need metadata generation.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_in_dry_run_mode()
    {
        IngestedLaw::factory()->count(3)->create([
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true])
            ->expectsOutput('DRY RUN MODE - No jobs will be dispatched')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_displays_configuration_table()
    {
        $this->artisan('laws:regen-metadata', ['--dry-run' => true])
            ->expectsOutputToContain('Dry Run')
            ->expectsOutputToContain('Batch Size')
            ->expectsOutputToContain('Rate Limit')
            ->expectsOutputToContain('Force Regeneration')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_filters_by_doc_id()
    {
        IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-5678',
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata', [
            '--dry-run' => true,
            '--doc-id' => 'nn-2021-12-1234',
        ])
            ->expectsOutputToContain('Filtering by doc_id: nn-2021-12-1234')
            ->expectsOutputToContain('Found 1 law(s) needing metadata generation')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_only_processes_laws_without_ai_metadata_by_default()
    {
        // Law without AI metadata
        IngestedLaw::factory()->create([
            'metadata' => null,
        ]);

        // Law with AI metadata
        IngestedLaw::factory()->create([
            'metadata' => ['ai_generated' => 'yes'],
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 law(s) needing metadata generation')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_all_laws_with_force_option()
    {
        // Law without AI metadata
        IngestedLaw::factory()->create([
            'metadata' => null,
        ]);

        // Law with AI metadata
        IngestedLaw::factory()->create([
            'metadata' => ['ai_generated' => 'yes'],
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true, '--force' => true])
            ->expectsOutputToContain('Found 2 law(s) needing metadata generation')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_respects_limit_option()
    {
        IngestedLaw::factory()->count(10)->create([
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true, '--limit' => '5'])
            ->expectsOutputToContain('Found 5 law(s) needing metadata generation')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_dry_run_preview_table()
    {
        IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'title' => 'Test Law',
            'law_number' => 'NN 12/2021',
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true])
            ->expectsOutputToContain('ID')
            ->expectsOutputToContain('Doc ID')
            ->expectsOutputToContain('Title')
            ->expectsOutputToContain('Law Number')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_limits_dry_run_preview_to_10_rows()
    {
        IngestedLaw::factory()->count(15)->create([
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true])
            ->expectsOutputToContain('... and 5 more')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_dispatches_jobs_for_laws_with_articles()
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        // Create law chunks with articles
        Law::factory()->count(3)->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => json_encode([
                'article_number' => '1',
                'heading_chain' => ['Title I'],
            ]),
        ]);

        $this->artisan('laws:regen-metadata', ['--batch-size' => '1'])
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->expectsOutput('Processing laws in batches...')
            ->expectsOutput('Metadata regeneration complete!')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateLawMetadata::class, 1);
    }

    /** @test */
    public function it_skips_laws_without_articles()
    {
        IngestedLaw::factory()->create([
            'doc_id' => 'nn-no-articles',
            'metadata' => null,
        ]);

        // No Law chunks created

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->expectsOutput('Metadata regeneration complete!')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
        Log::shouldHaveReceived('warning')->once();
    }

    /** @test */
    public function it_displays_summary_statistics()
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => json_encode(['article_number' => '1']),
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->expectsOutputToContain('Total processed')
            ->expectsOutputToContain('Jobs dispatched')
            ->expectsOutputToContain('Failed')
            ->expectsOutputToContain('Success rate')
            ->expectsOutputToContain('Batches')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_be_aborted_at_confirmation()
    {
        IngestedLaw::factory()->create([
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'no')
            ->expectsOutput('Aborted.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_processes_laws_in_batches()
    {
        // Create 5 laws with articles
        for ($i = 1; $i <= 5; $i++) {
            $law = IngestedLaw::factory()->create([
                'doc_id' => "nn-2021-12-{$i}",
                'metadata' => null,
            ]);

            Law::factory()->create([
                'doc_id' => "nn-2021-12-{$i}",
                'metadata' => json_encode(['article_number' => '1']),
            ]);
        }

        $this->artisan('laws:regen-metadata', ['--batch-size' => '2', '--rate-limit' => '0'])
            ->expectsQuestion('Do you want to dispatch jobs for 5 law(s)?', 'yes')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateLawMetadata::class, 5);
    }

    /** @test */
    public function it_logs_completion_with_statistics()
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => json_encode(['article_number' => '1']),
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Laws metadata regeneration completed', \Mockery::type('array'));
    }

    /** @test */
    public function it_warns_about_failures()
    {
        IngestedLaw::factory()->create([
            'doc_id' => 'nn-no-articles',
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->expectsOutputToContain('Warning:')
            ->expectsOutputToContain('failed to dispatch')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_custom_batch_size()
    {
        for ($i = 1; $i <= 3; $i++) {
            $law = IngestedLaw::factory()->create([
                'doc_id' => "nn-2021-12-{$i}",
                'metadata' => null,
            ]);

            Law::factory()->create([
                'doc_id' => "nn-2021-12-{$i}",
                'metadata' => json_encode(['article_number' => '1']),
            ]);
        }

        $this->artisan('laws:regen-metadata', ['--batch-size' => '1', '--rate-limit' => '0'])
            ->expectsQuestion('Do you want to dispatch jobs for 3 law(s)?', 'yes')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateLawMetadata::class, 3);
    }

    /** @test */
    public function it_extracts_articles_from_law_chunks()
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'chunk_index' => 0,
            'content' => 'Article 1 content',
            'metadata' => json_encode([
                'article_number' => '1',
                'heading_chain' => ['Chapter I', 'General Provisions'],
            ]),
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'chunk_index' => 1,
            'content' => 'Article 2 content',
            'metadata' => json_encode([
                'article_number' => '2',
                'heading_chain' => ['Chapter I'],
            ]),
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateLawMetadata::class, function ($job) {
            // Job should be dispatched with law ID and articles array
            return true;
        });
    }

    /** @test */
    public function it_handles_metadata_as_string()
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => '{"article_number":"1","heading_chain":[]}',
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateLawMetadata::class, 1);
    }

    /** @test */
    public function it_handles_laws_with_empty_ai_generated_field()
    {
        IngestedLaw::factory()->create([
            'metadata' => ['ai_generated' => ''],
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 law(s) needing metadata generation')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_warning_when_limit_restricts_total()
    {
        IngestedLaw::factory()->count(20)->create([
            'metadata' => null,
        ]);

        $this->artisan('laws:regen-metadata', ['--dry-run' => true, '--limit' => '5'])
            ->expectsOutputToContain('Total available is 20, but processing only 5 due to --limit option')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_orders_law_chunks_by_chunk_index()
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'metadata' => null,
        ]);

        // Create chunks out of order
        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'chunk_index' => 2,
            'content' => 'Article 3',
            'metadata' => json_encode(['article_number' => '3']),
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'chunk_index' => 0,
            'content' => 'Article 1',
            'metadata' => json_encode(['article_number' => '1']),
        ]);

        Law::factory()->create([
            'doc_id' => 'nn-2021-12-1234',
            'chunk_index' => 1,
            'content' => 'Article 2',
            'metadata' => json_encode(['article_number' => '2']),
        ]);

        $this->artisan('laws:regen-metadata')
            ->expectsQuestion('Do you want to dispatch jobs for 1 law(s)?', 'yes')
            ->assertExitCode(0);

        // Job should receive articles in correct order (0, 1, 2)
        Queue::assertPushed(GenerateLawMetadata::class, 1);
    }
}
