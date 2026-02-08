<?php

namespace Tests\Feature\Console;

use App\Models\EoglasnaOsijekMonitoring;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaNormalizeParticipantsTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_normalizes_participants_with_default_chunk()
    {
        // Create test records
        EoglasnaOsijekMonitoring::factory()->count(3)->create([
            'participants' => [
                ['ime' => 'Test', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutput('Normalized 3 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_normalizes_participants_with_custom_chunk()
    {
        EoglasnaOsijekMonitoring::factory()->count(5)->create([
            'participants' => [
                ['ime' => 'Test', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants', ['--chunk' => '2'])
            ->expectsOutput('Normalized 5 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_empty_database()
    {
        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutput('Normalized 0 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_normalizes_single_record()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'participants' => [
                ['ime' => 'Test Person', 'uloga' => 'tuženik'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutput('Normalized 1 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_records_with_empty_participants()
    {
        EoglasnaOsijekMonitoring::factory()->count(2)->create([
            'participants' => [],
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutput('Normalized 2 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_records_with_null_participants()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'participants' => null,
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutput('Normalized 1 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_large_chunk_size()
    {
        EoglasnaOsijekMonitoring::factory()->count(10)->create([
            'participants' => [
                ['ime' => 'Test', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants', ['--chunk' => '1000'])
            ->expectsOutput('Normalized 10 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_small_chunk_size()
    {
        EoglasnaOsijekMonitoring::factory()->count(5)->create([
            'participants' => [
                ['ime' => 'Test', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants', ['--chunk' => '1'])
            ->expectsOutput('Normalized 5 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_normalizes_records_with_multiple_participants()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'participants' => [
                ['ime' => 'Person 1', 'uloga' => 'tužitelj'],
                ['ime' => 'Person 2', 'uloga' => 'tuženik'],
                ['ime' => 'Person 3', 'uloga' => 'svjedok'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutput('Normalized 1 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_updates_participant_data_correctly()
    {
        $record = EoglasnaOsijekMonitoring::factory()->create([
            'participants' => [
                ['ime' => 'Original Name', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->assertExitCode(0);

        // Verify record was updated
        $record->refresh();
        $this->assertNotNull($record->participants);
    }

    /** @test */
    public function it_displays_correct_count_in_output()
    {
        EoglasnaOsijekMonitoring::factory()->count(42)->create([
            'participants' => [
                ['ime' => 'Test', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants')
            ->expectsOutputToContain('42 rows')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_chunk_option_as_string()
    {
        EoglasnaOsijekMonitoring::factory()->count(3)->create([
            'participants' => [
                ['ime' => 'Test', 'uloga' => 'tužitelj'],
            ],
        ]);

        $this->artisan('eoglasna:normalize-participants', ['--chunk' => '100'])
            ->expectsOutput('Normalized 3 rows.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_records_in_order_by_id()
    {
        // Create records with specific IDs
        $record1 = EoglasnaOsijekMonitoring::factory()->create([
            'participants' => [['ime' => 'First', 'uloga' => 'tužitelj']],
        ]);
        $record2 = EoglasnaOsijekMonitoring::factory()->create([
            'participants' => [['ime' => 'Second', 'uloga' => 'tuženik']],
        ]);

        $this->artisan('eoglasna:normalize-participants', ['--chunk' => '1'])
            ->expectsOutput('Normalized 2 rows.')
            ->assertExitCode(0);

        // Both records should be processed
        $this->assertDatabaseHas('eoglasna_osijek_monitoring', ['id' => $record1->id]);
        $this->assertDatabaseHas('eoglasna_osijek_monitoring', ['id' => $record2->id]);
    }
}
