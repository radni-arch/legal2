<?php

namespace Tests\Feature\Console;

use App\Actions\Textract\EnsureTextractJob;
use App\Actions\Textract\ListDrivePdfs;
use App\Actions\Textract\ProcessDrivePdf;
use App\Models\LegalCase;
use App\Models\TextractBatch;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TextractProcessDriveFolderCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the action classes
        $this->mock(ListDrivePdfs::class);
        $this->mock(EnsureTextractJob::class);
        $this->mock(ProcessDrivePdf::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requires_folder_id_or_env_variable()
    {
        $this->artisan('textract:process-drive-folder')
            ->expectsOutputToContain('Folder ID nije zadan')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_requires_valid_case_id()
    {
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $this->artisan('textract:process-drive-folder')
            ->expectsOutputToContain('Case ID is required')
            ->assertExitCode(1);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_validates_case_exists()
    {
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $this->artisan('textract:process-drive-folder', [
            '--case' => 'non-existent-case-id',
        ])
            ->expectsOutputToContain('Selected case not found')
            ->assertExitCode(1);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_handles_empty_drive_folder()
    {
        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $this->mock(ListDrivePdfs::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([]);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Nema PDF-ova u folderu')
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_processes_pdfs_from_drive_folder()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
            ['id' => 'file-2', 'name' => 'doc2.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->with('test-folder-id')
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Listing PDFs in Drive folder')
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_accepts_folder_id_as_argument()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();

        $this->mock(ListDrivePdfs::class, function ($mock) {
            $mock->shouldReceive('run')
                ->with('custom-folder-id')
                ->once()
                ->andReturn([]);
        });

        $this->artisan('textract:process-drive-folder', [
            'folderId' => 'custom-folder-id',
            '--case' => $case->id,
        ])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_synchronous_processing_mode()
    {
        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--sync' => true,
        ])
            ->expectsOutputToContain('Mode: SYNC')
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_supports_parallel_queue_processing()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--parallel' => true,
        ])
            ->expectsOutputToContain('Mode: PARALLEL QUEUE')
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_creates_batch_for_tracking()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
            ['id' => 'file-2', 'name' => 'doc2.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--batch' => true,
        ])
            ->expectsOutputToContain('Batch tracking: ENABLED')
            ->expectsOutputToContain('Created batch:')
            ->assertExitCode(0);

        $this->assertDatabaseHas('textract_batches', [
            'batch_type' => 'drive_folder',
            'source_identifier' => 'test-folder-id',
            'total_files' => 2,
            'status' => 'pending',
        ]);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_supports_table_extraction()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--extract-tables' => true,
        ])
            ->expectsOutputToContain('Table extraction: ENABLED')
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_supports_custom_queue_name()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--queue' => 'high-priority',
        ])
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_supports_job_priority()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--priority' => 10,
        ])
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_limits_number_of_files_processed()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
            ['id' => 'file-2', 'name' => 'doc2.pdf'],
            ['id' => 'file-3', 'name' => 'doc3.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--limit' => 2,
        ])
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_supports_force_reprocessing()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--force' => true,
        ])
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_stores_batch_configuration()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $files = [
            ['id' => 'file-1', 'name' => 'doc1.pdf'],
        ];

        $this->mock(ListDrivePdfs::class, function ($mock) use ($files) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn($files);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
            '--batch' => true,
            '--queue' => 'custom-queue',
            '--priority' => 5,
            '--parallel' => true,
            '--extract-tables' => true,
            '--force-textract' => true,
        ])
            ->assertExitCode(0);

        $batch = TextractBatch::first();
        $this->assertNotNull($batch);
        $this->assertEquals('custom-queue', $batch->configuration['queue_name']);
        $this->assertEquals(5, $batch->configuration['priority']);
        $this->assertTrue($batch->configuration['parallel']);
        $this->assertTrue($batch->configuration['extract_tables']);
        $this->assertTrue($batch->configuration['force_textract']);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }

    /** @test */
    public function it_displays_processing_mode()
    {
        Queue::fake();

        $case = LegalCase::factory()->create();
        putenv('GOOGLE_DRIVE_FOLDER_ID=test-folder-id');

        $this->mock(ListDrivePdfs::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([]);
        });

        $this->artisan('textract:process-drive-folder', [
            '--case' => $case->id,
        ])
            ->expectsOutputToContain('Mode: SEQUENTIAL QUEUE')
            ->assertExitCode(0);

        putenv('GOOGLE_DRIVE_FOLDER_ID=');
    }
}
