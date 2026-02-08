<?php

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class TextractManagerTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();

        // Ensure required tables exist
        if (! Schema::hasTable('users') || ! Schema::hasTable('legal_cases') || ! Schema::hasTable('textract_jobs')) {
            $this->markTestSkipped('Database schema not initialized');
        }
    }

    public function test_can_access_textract_manager(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('Textract', 10)
                ->assertSee('Textract')
                ->assertSee('Google Drive')
                ->assertSee('Manual Processing');
        });
    }

    public function test_can_view_textract_jobs_list(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'test-document.pdf',
            'status' => 'succeeded',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('test-document.pdf', 10)
                ->assertSee('test-document.pdf')
                ->assertSee('succeeded');
        });
    }

    public function test_can_filter_jobs_by_status(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'completed.pdf',
            'status' => 'succeeded',
        ]);

        TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'failed.pdf',
            'status' => 'failed',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('completed.pdf', 10)

                // Filter by succeeded
                ->select('@status-filter', 'succeeded')
                ->pause(1000)
                ->assertSee('completed.pdf')
                ->assertDontSee('failed.pdf')

                // Filter by failed
                ->select('@status-filter', 'failed')
                ->pause(1000)
                ->assertSee('failed.pdf')
                ->assertDontSee('completed.pdf');
        });
    }

    public function test_can_view_job_content(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'viewable.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'This is extracted OCR content from the PDF.',
        ]);

        $this->browse(function (Browser $browser) use ($user, $job) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('viewable.pdf', 10)

                // Click view content button
                ->press("@view-content-{$job->id}")
                ->waitFor('@content-modal', 5)

                // Verify modal shows content
                ->assertSee('This is extracted OCR content')
                ->assertPresent('@content-tabs');
        });
    }

    public function test_can_edit_and_save_job_content(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'editable.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Original content',
            'manual_content' => null,
        ]);

        $this->browse(function (Browser $browser) use ($user, $job) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('editable.pdf', 10)

                // Click edit content button
                ->press("@edit-content-{$job->id}")
                ->waitFor('@edit-content-modal', 5)

                // Edit content
                ->type('@content-editor', 'Edited manual content')

                // Save
                ->press('Save')
                ->waitForText('Content saved', 5)
                ->assertSee('Content saved');
        });
    }

    public function test_can_process_manual_drive_file(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('Manual Processing', 10)

                // Enter Drive File ID
                ->type('@manual-drive-file-id', '1ABC123xyz-test-file-id')
                ->type('@manual-drive-file-name', 'manual-test.pdf')

                // Select case
                ->select('@manual-case-selector', $case->id)
                ->pause(500)

                // Click process button
                ->press('@process-manual-button')
                ->waitForText('Processing', 10)
                ->assertSee('Processing')
                ->assertSee('manual-test.pdf');
        });
    }

    public function test_can_regenerate_embeddings(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'embed-test.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content to embed',
            'embedding_status' => 'completed',
        ]);

        $this->browse(function (Browser $browser) use ($user, $job) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('embed-test.pdf', 10)

                // Click regenerate embeddings
                ->press("@regenerate-embeddings-{$job->id}")
                ->waitForText('Queued', 5)
                ->assertSee('embedding');
        });
    }

    public function test_can_sync_to_neo4j_graph(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'drive_file_name' => 'graph-sync-test.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content with legal references',
            'graph_sync_status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) use ($user, $job) {
            $this->loginAs($browser, $user);

            $browser->visit('/textract')
                ->waitForText('graph-sync-test.pdf', 10)

                // Click sync to graph
                ->press("@sync-to-graph-{$job->id}")
                ->waitForText('Syncing', 5)
                ->assertSee('graph');
        });
    }
}
