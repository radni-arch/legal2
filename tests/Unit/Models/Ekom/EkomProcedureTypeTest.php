<?php

namespace Tests\Unit\Models\Ekom;

use App\Models\Ekom\EkomCourt;
use App\Models\Ekom\EkomParticipantRole;
use App\Models\Ekom\EkomProcedureType;
use App\Models\Ekom\EkomSubmissionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EkomProcedureTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_procedure_type_has_correct_table_name(): void
    {
        $pt = new EkomProcedureType();
        $this->assertEquals('ekom_procedure_types', $pt->getTable());
    }

    public function test_procedure_type_belongs_to_court(): void
    {
        $court = EkomCourt::create([
            'remote_id' => 50,
            'naziv' => 'Test sud',
            'synced_at' => now(),
        ]);

        $pt = EkomProcedureType::create([
            'remote_id' => 500,
            'court_remote_id' => 50,
            'naziv' => 'Parnični',
            'synced_at' => now(),
        ]);

        $this->assertInstanceOf(EkomCourt::class, $pt->court);
        $this->assertEquals(50, $pt->court->remote_id);
    }

    public function test_procedure_type_has_many_submission_types(): void
    {
        $pt = EkomProcedureType::create([
            'remote_id' => 501,
            'court_remote_id' => null,
            'naziv' => 'Test postupak',
            'synced_at' => now(),
        ]);

        EkomSubmissionType::create([
            'remote_id' => 600,
            'procedure_type_remote_id' => 501,
            'naziv' => 'Tužba',
            'context' => 'novi_postupak',
            'synced_at' => now(),
        ]);

        EkomSubmissionType::create([
            'remote_id' => 601,
            'procedure_type_remote_id' => 501,
            'naziv' => 'Odgovor na tužbu',
            'context' => 'postojeci_predmet',
            'synced_at' => now(),
        ]);

        $this->assertCount(2, $pt->submissionTypes);
    }

    public function test_procedure_type_has_many_participant_roles(): void
    {
        $pt = EkomProcedureType::create([
            'remote_id' => 502,
            'court_remote_id' => null,
            'naziv' => 'Test postupak',
            'synced_at' => now(),
        ]);

        EkomParticipantRole::create([
            'remote_id' => 700,
            'procedure_type_remote_id' => 502,
            'naziv' => 'Tužitelj',
            'type' => 'sudionik',
            'synced_at' => now(),
        ]);

        EkomParticipantRole::create([
            'remote_id' => 701,
            'procedure_type_remote_id' => 502,
            'naziv' => 'Odvjetnik',
            'type' => 'podnositelj',
            'synced_at' => now(),
        ]);

        $this->assertCount(2, $pt->participantRoles);
    }

    public function test_scope_by_remote_id(): void
    {
        EkomProcedureType::create([
            'remote_id' => 503,
            'court_remote_id' => null,
            'naziv' => 'Found',
            'synced_at' => now(),
        ]);

        $result = EkomProcedureType::byRemoteId(503)->first();
        $this->assertNotNull($result);
        $this->assertEquals('Found', $result->naziv);
    }
}
