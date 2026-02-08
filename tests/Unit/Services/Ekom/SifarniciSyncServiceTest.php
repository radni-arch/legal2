<?php

namespace Tests\Unit\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\Models\Ekom\EkomCourt;
use App\Models\Ekom\EkomCountry;
use App\Models\Ekom\EkomFeeExemption;
use App\Models\Ekom\EkomFeeOption;
use App\Models\Ekom\EkomNonPaymentReason;
use App\Models\Ekom\EkomParticipantRole;
use App\Models\Ekom\EkomProcedureType;
use App\Models\Ekom\EkomSettlement;
use App\Models\Ekom\EkomSubmissionType;
use App\Services\Ekom\SifarniciSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SifarniciSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private EkomApiClientInterface $mockClient;

    private SifarniciSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(EkomApiClientInterface::class);
        $this->service = new SifarniciSyncService($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---- syncCourts ----

    public function test_sync_courts_calls_get_sudovi_and_upserts(): void
    {
        $this->mockClient->shouldReceive('getSudovi')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Opcinski sud u Zagrebu', 'oznaka' => 'OSZG', 'vrstaSuda' => ['id' => 10, 'naziv' => 'Opcinski']],
            ['id' => 2, 'naziv' => 'Trgovacki sud u Zagrebu', 'oznaka' => 'TSZG', 'vrstaSuda' => ['id' => 20, 'naziv' => 'Trgovacki']],
        ]);

        $count = $this->service->syncCourts();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_courts', [
            'remote_id' => 1,
            'naziv' => 'Opcinski sud u Zagrebu',
            'oznaka' => 'OSZG',
            'vrsta_suda_id' => 10,
            'vrsta_suda_naziv' => 'Opcinski',
        ]);
        $this->assertDatabaseHas('ekom_courts', [
            'remote_id' => 2,
            'naziv' => 'Trgovacki sud u Zagrebu',
        ]);
    }

    public function test_sync_courts_is_idempotent(): void
    {
        $apiData = [
            ['id' => 1, 'naziv' => 'Sud A', 'oznaka' => 'SA', 'vrstaSuda' => ['id' => 10, 'naziv' => 'Tip']],
        ];

        $this->mockClient->shouldReceive('getSudovi')->twice()->andReturn($apiData);

        $this->service->syncCourts();
        $this->service->syncCourts();

        $this->assertEquals(1, EkomCourt::count());
    }

    // ---- syncProcedureTypes ----

    public function test_sync_procedure_types_calls_api_for_each_court(): void
    {
        // Pre-seed courts
        EkomCourt::create(['remote_id' => 1, 'naziv' => 'Sud A', 'synced_at' => now()]);
        EkomCourt::create(['remote_id' => 2, 'naziv' => 'Sud B', 'synced_at' => now()]);

        $this->mockClient->shouldReceive('getVrstePostupakaZaSud')
            ->with(1)->once()->andReturn([
                ['id' => 100, 'naziv' => 'Parnični', 'oznaka' => 'P'],
            ]);

        $this->mockClient->shouldReceive('getVrstePostupakaZaSud')
            ->with(2)->once()->andReturn([
                ['id' => 200, 'naziv' => 'Ovršni', 'oznaka' => 'Ovr'],
            ]);

        $count = $this->service->syncProcedureTypes();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_procedure_types', [
            'remote_id' => 100,
            'court_remote_id' => 1,
            'naziv' => 'Parnični',
        ]);
    }

    public function test_sync_procedure_types_for_specific_court(): void
    {
        EkomCourt::create(['remote_id' => 1, 'naziv' => 'Sud A', 'synced_at' => now()]);
        EkomCourt::create(['remote_id' => 2, 'naziv' => 'Sud B', 'synced_at' => now()]);

        $this->mockClient->shouldReceive('getVrstePostupakaZaSud')
            ->with(1)->once()->andReturn([
                ['id' => 100, 'naziv' => 'Parnični', 'oznaka' => 'P'],
            ]);

        // Should NOT call for court 2
        $this->mockClient->shouldNotReceive('getVrstePostupakaZaSud')->with(2);

        $count = $this->service->syncProcedureTypes(1);

        $this->assertEquals(1, $count);
    }

    // ---- syncSubmissionTypes ----

    public function test_sync_submission_types_calls_both_contexts(): void
    {
        EkomProcedureType::create(['remote_id' => 100, 'court_remote_id' => null, 'naziv' => 'Test', 'synced_at' => now()]);

        $this->mockClient->shouldReceive('getVrstePodnesakaNoviPostupak')
            ->with(100)->once()->andReturn([
                ['id' => 300, 'naziv' => 'Tužba', 'oznaka' => 'TZ'],
            ]);

        $this->mockClient->shouldReceive('getVrstePodnesakaPostojeciPredmet')
            ->with(100)->once()->andReturn([
                ['id' => 301, 'naziv' => 'Odgovor', 'oznaka' => 'OD'],
            ]);

        $count = $this->service->syncSubmissionTypes();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_submission_types', [
            'remote_id' => 300,
            'context' => 'novi_postupak',
        ]);
        $this->assertDatabaseHas('ekom_submission_types', [
            'remote_id' => 301,
            'context' => 'postojeci_predmet',
        ]);
    }

    // ---- syncParticipantRoles ----

    public function test_sync_participant_roles_calls_both_types(): void
    {
        EkomProcedureType::create(['remote_id' => 100, 'court_remote_id' => null, 'naziv' => 'Test', 'synced_at' => now()]);

        $this->mockClient->shouldReceive('getUlogeSudionika')
            ->with(100)->once()->andReturn([
                ['id' => 400, 'naziv' => 'Tužitelj'],
            ]);

        $this->mockClient->shouldReceive('getUlogePodnositelja')
            ->with(100)->once()->andReturn([
                ['id' => 401, 'naziv' => 'Odvjetnik'],
            ]);

        $count = $this->service->syncParticipantRoles();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_participant_roles', [
            'remote_id' => 400,
            'type' => 'sudionik',
        ]);
        $this->assertDatabaseHas('ekom_participant_roles', [
            'remote_id' => 401,
            'type' => 'podnositelj',
        ]);
    }

    // ---- syncFeeOptions ----

    public function test_sync_fee_options_calls_both_contexts(): void
    {
        EkomProcedureType::create(['remote_id' => 100, 'court_remote_id' => null, 'naziv' => 'Test', 'synced_at' => now()]);

        EkomSubmissionType::create([
            'remote_id' => 300,
            'procedure_type_remote_id' => 100,
            'naziv' => 'Tužba',
            'context' => 'novi_postupak',
            'synced_at' => now(),
        ]);

        EkomSubmissionType::create([
            'remote_id' => 301,
            'procedure_type_remote_id' => 100,
            'naziv' => 'Odgovor',
            'context' => 'postojeci_predmet',
            'synced_at' => now(),
        ]);

        $this->mockClient->shouldReceive('getPristojbaDodatneOpcijeNoviPostupak')
            ->with(100, 300)->once()->andReturn([
                ['id' => 500, 'naziv' => 'Opcija A'],
            ]);

        $this->mockClient->shouldReceive('getPristojbaDodatneOpcijePostojeciPredmet')
            ->with(100, 301)->once()->andReturn([
                ['id' => 501, 'naziv' => 'Opcija B'],
            ]);

        $count = $this->service->syncFeeOptions();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_fee_options', [
            'remote_id' => 500,
            'context' => 'novi_postupak',
        ]);
        $this->assertDatabaseHas('ekom_fee_options', [
            'remote_id' => 501,
            'context' => 'postojeci_predmet',
        ]);
    }

    // ---- standalone sync methods ----

    public function test_sync_non_payment_reasons(): void
    {
        $this->mockClient->shouldReceive('getRazloziNeplacanjaPristojbe')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Razlog 1'],
            ['id' => 2, 'naziv' => 'Razlog 2'],
        ]);

        $count = $this->service->syncNonPaymentReasons();

        $this->assertEquals(2, $count);
        $this->assertEquals(2, EkomNonPaymentReason::count());
    }

    public function test_sync_fee_exemptions(): void
    {
        $this->mockClient->shouldReceive('getOsnoveOslobodjenjaPristojbe')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Oslobodjenje 1'],
        ]);

        $count = $this->service->syncFeeExemptions();

        $this->assertEquals(1, $count);
        $this->assertEquals(1, EkomFeeExemption::count());
    }

    public function test_sync_settlements(): void
    {
        $this->mockClient->shouldReceive('getNaselja')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Zagreb', 'postanskiBroj' => '10000'],
            ['id' => 2, 'naziv' => 'Split', 'postanskiBroj' => '21000'],
        ]);

        $count = $this->service->syncSettlements();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_settlements', [
            'remote_id' => 1,
            'naziv' => 'Zagreb',
            'postanski_broj' => '10000',
        ]);
    }

    public function test_sync_countries(): void
    {
        $this->mockClient->shouldReceive('getDrzave')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Hrvatska', 'oznaka' => 'HR'],
            ['id' => 2, 'naziv' => 'Slovenija', 'oznaka' => 'SI'],
        ]);

        $count = $this->service->syncCountries();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('ekom_countries', [
            'remote_id' => 1,
            'naziv' => 'Hrvatska',
            'oznaka' => 'HR',
        ]);
    }

    // ---- syncAll ----

    public function test_sync_all_cascades_correctly(): void
    {
        // Mock all API calls for a minimal cascade
        $this->mockClient->shouldReceive('getSudovi')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Sud', 'oznaka' => 'S', 'vrstaSuda' => ['id' => 1, 'naziv' => 'Tip']],
        ]);

        $this->mockClient->shouldReceive('getVrstePostupakaZaSud')
            ->with(1)->once()->andReturn([
                ['id' => 10, 'naziv' => 'Postupak', 'oznaka' => 'P'],
            ]);

        $this->mockClient->shouldReceive('getVrstePodnesakaNoviPostupak')
            ->with(10)->once()->andReturn([
                ['id' => 100, 'naziv' => 'Podnesak NP', 'oznaka' => 'PNP'],
            ]);

        $this->mockClient->shouldReceive('getVrstePodnesakaPostojeciPredmet')
            ->with(10)->once()->andReturn([
                ['id' => 101, 'naziv' => 'Podnesak PP', 'oznaka' => 'PPP'],
            ]);

        $this->mockClient->shouldReceive('getUlogeSudionika')
            ->with(10)->once()->andReturn([
                ['id' => 200, 'naziv' => 'Sudionik'],
            ]);

        $this->mockClient->shouldReceive('getUlogePodnositelja')
            ->with(10)->once()->andReturn([
                ['id' => 201, 'naziv' => 'Podnositelj'],
            ]);

        $this->mockClient->shouldReceive('getPristojbaDodatneOpcijeNoviPostupak')
            ->with(10, 100)->once()->andReturn([
                ['id' => 300, 'naziv' => 'Fee NP'],
            ]);

        $this->mockClient->shouldReceive('getPristojbaDodatneOpcijePostojeciPredmet')
            ->with(10, 101)->once()->andReturn([
                ['id' => 301, 'naziv' => 'Fee PP'],
            ]);

        $this->mockClient->shouldReceive('getRazloziNeplacanjaPristojbe')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Razlog'],
        ]);

        $this->mockClient->shouldReceive('getOsnoveOslobodjenjaPristojbe')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Osnova'],
        ]);

        $this->mockClient->shouldReceive('getNaselja')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Zagreb', 'postanskiBroj' => '10000'],
        ]);

        $this->mockClient->shouldReceive('getDrzave')->once()->andReturn([
            ['id' => 1, 'naziv' => 'Hrvatska', 'oznaka' => 'HR'],
        ]);

        $stats = $this->service->syncAll();

        $this->assertArrayHasKey('courts', $stats);
        $this->assertArrayHasKey('procedure_types', $stats);
        $this->assertArrayHasKey('submission_types', $stats);
        $this->assertArrayHasKey('participant_roles', $stats);
        $this->assertArrayHasKey('fee_options', $stats);
        $this->assertArrayHasKey('non_payment_reasons', $stats);
        $this->assertArrayHasKey('fee_exemptions', $stats);
        $this->assertArrayHasKey('settlements', $stats);
        $this->assertArrayHasKey('countries', $stats);

        $this->assertEquals(1, $stats['courts']);
        $this->assertEquals(1, $stats['procedure_types']);
        $this->assertEquals(2, $stats['submission_types']);
        $this->assertEquals(2, $stats['participant_roles']);
        $this->assertEquals(2, $stats['fee_options']);
    }
}
