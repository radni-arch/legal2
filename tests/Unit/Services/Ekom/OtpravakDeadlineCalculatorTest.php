<?php

namespace Tests\Unit\Services\Ekom;

use App\DTOs\Ekom\Otpravci\PagedOtpravakDTO;
use App\Models\Ekom\EkomProcedureType;
use App\Services\Ekom\OtpravakDeadlineCalculator;
use App\Services\Ekom\OtpravakService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OtpravakDeadlineCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private OtpravakService $mockOtpravakService;

    private OtpravakDeadlineCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOtpravakService = Mockery::mock(OtpravakService::class);
        $this->calculator = new OtpravakDeadlineCalculator($this->mockOtpravakService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---- Deadline Calculation ----

    public function test_criminal_case_gets_8_day_deadline(): void
    {
        // Create a criminal procedure type (oznaka starts with 'K')
        EkomProcedureType::create([
            'remote_id' => 100,
            'court_remote_id' => 1,
            'naziv' => 'Kazneni postupak',
            'oznaka' => 'K',
            'synced_at' => now(),
        ]);

        $dispatchDate = Carbon::parse('2026-02-01');
        $deadline = $this->calculator->calculateDeadline($dispatchDate, 100);

        $expectedDeadline = Carbon::parse('2026-02-09'); // 8 days later
        $this->assertTrue($deadline->isSameDay($expectedDeadline));
    }

    public function test_criminal_variants_get_8_day_deadline(): void
    {
        // Test various criminal procedure oznake
        $criminalOznake = ['K', 'Kv', 'Kzm', 'Km', 'Kov', 'Kr'];

        foreach ($criminalOznake as $index => $oznaka) {
            EkomProcedureType::create([
                'remote_id' => 200 + $index,
                'court_remote_id' => 1,
                'naziv' => "Kazneni {$oznaka}",
                'oznaka' => $oznaka,
                'synced_at' => now(),
            ]);

            $dispatchDate = Carbon::parse('2026-02-01');
            $deadline = $this->calculator->calculateDeadline($dispatchDate, 200 + $index);

            $this->assertEquals(
                8,
                $dispatchDate->diffInDays($deadline),
                "Criminal procedure with oznaka '{$oznaka}' should get 8-day deadline"
            );
        }
    }

    public function test_civil_case_gets_15_day_deadline(): void
    {
        // Create a civil procedure type (oznaka 'P' for parnični)
        EkomProcedureType::create([
            'remote_id' => 101,
            'court_remote_id' => 1,
            'naziv' => 'Parnični postupak',
            'oznaka' => 'P',
            'synced_at' => now(),
        ]);

        $dispatchDate = Carbon::parse('2026-02-01');
        $deadline = $this->calculator->calculateDeadline($dispatchDate, 101);

        $expectedDeadline = Carbon::parse('2026-02-16'); // 15 days later
        $this->assertTrue($deadline->isSameDay($expectedDeadline));
    }

    public function test_execution_case_gets_15_day_deadline(): void
    {
        // Create execution procedure type (ovršni)
        EkomProcedureType::create([
            'remote_id' => 102,
            'court_remote_id' => 1,
            'naziv' => 'Ovršni postupak',
            'oznaka' => 'Ovr',
            'synced_at' => now(),
        ]);

        $dispatchDate = Carbon::parse('2026-02-01');
        $deadline = $this->calculator->calculateDeadline($dispatchDate, 102);

        $this->assertEquals(15, $dispatchDate->diffInDays($deadline));
    }

    public function test_unknown_procedure_type_gets_15_day_deadline(): void
    {
        // Procedure type not in database - default to 15 days (safer option)
        $dispatchDate = Carbon::parse('2026-02-01');
        $deadline = $this->calculator->calculateDeadline($dispatchDate, 999);

        $this->assertEquals(15, $dispatchDate->diffInDays($deadline));
    }

    // ---- Is Criminal Procedure ----

    public function test_is_criminal_procedure_returns_true_for_criminal_types(): void
    {
        EkomProcedureType::create([
            'remote_id' => 100,
            'court_remote_id' => 1,
            'naziv' => 'Kazneni postupak',
            'oznaka' => 'K',
            'synced_at' => now(),
        ]);

        $this->assertTrue($this->calculator->isCriminalProcedure(100));
    }

    public function test_is_criminal_procedure_returns_false_for_non_criminal_types(): void
    {
        EkomProcedureType::create([
            'remote_id' => 101,
            'court_remote_id' => 1,
            'naziv' => 'Parnični postupak',
            'oznaka' => 'P',
            'synced_at' => now(),
        ]);

        $this->assertFalse($this->calculator->isCriminalProcedure(101));
    }

    public function test_is_criminal_procedure_returns_false_for_unknown_type(): void
    {
        $this->assertFalse($this->calculator->isCriminalProcedure(999));
    }

    // ---- Warning Detection ----

    public function test_three_day_warning_detection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-03 10:00:00'));

        // Dispatch with deadline in ~3 days (60 hours - within 48-72h window)
        $dto1 = $this->createOtpravakDTO(1, '2026-02-05 22:00:00', false);
        // Dispatch with deadline in 4 days (should not be included - beyond 72h)
        $dto2 = $this->createOtpravakDTO(2, '2026-02-07 10:00:00', false);
        // Dispatch with deadline in 1 day (should not be in 3-day warning - under 48h, more urgent)
        $dto3 = $this->createOtpravakDTO(3, '2026-02-04 08:00:00', false);

        $this->mockOtpravakService->shouldReceive('list')
            ->once()
            ->andReturn($this->createPaginatedResponse([$dto1, $dto2, $dto3]));

        $warnings = $this->calculator->getThreeDayWarnings();

        $this->assertCount(1, $warnings);
        $this->assertEquals(1, $warnings[0]->id);

        Carbon::setTestNow();
    }

    public function test_one_day_warning_detection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-03 10:00:00'));

        // Dispatch with deadline in 1 day
        $dto1 = $this->createOtpravakDTO(1, '2026-02-04 10:00:00', false);
        // Dispatch with deadline in 2 days (should not be included)
        $dto2 = $this->createOtpravakDTO(2, '2026-02-05 10:00:00', false);
        // Dispatch with deadline in 6 hours (more urgent)
        $dto3 = $this->createOtpravakDTO(3, '2026-02-03 16:00:00', false);

        $this->mockOtpravakService->shouldReceive('list')
            ->once()
            ->andReturn($this->createPaginatedResponse([$dto1, $dto2, $dto3]));

        $warnings = $this->calculator->getOneDayWarnings();

        $this->assertCount(1, $warnings);
        $this->assertEquals(1, $warnings[0]->id);

        Carbon::setTestNow();
    }

    public function test_auto_confirmed_dispatches_detection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-03 10:00:00'));

        // Dispatch past deadline (should be auto-confirmed)
        $dto1 = $this->createOtpravakDTO(1, '2026-02-02 10:00:00', false);
        // Dispatch with future deadline (should not be included)
        $dto2 = $this->createOtpravakDTO(2, '2026-02-04 10:00:00', false);
        // Another past deadline
        $dto3 = $this->createOtpravakDTO(3, '2026-02-01 10:00:00', false);

        $this->mockOtpravakService->shouldReceive('list')
            ->once()
            ->andReturn($this->createPaginatedResponse([$dto1, $dto2, $dto3]));

        $autoConfirmed = $this->calculator->getAutoConfirmedDispatches();

        $this->assertCount(2, $autoConfirmed);
        $ids = array_map(fn ($d) => $d->id, $autoConfirmed);
        $this->assertContains(1, $ids);
        $this->assertContains(3, $ids);

        Carbon::setTestNow();
    }

    // ---- Is Received By Expiry ----

    public function test_is_received_by_expiry_returns_true_when_flag_is_set(): void
    {
        $dto = $this->createOtpravakDTO(1, '2026-02-01 10:00:00', true);

        $this->assertTrue($this->calculator->isReceivedByExpiry($dto));
    }

    public function test_is_received_by_expiry_returns_false_when_flag_is_not_set(): void
    {
        $dto = $this->createOtpravakDTO(1, '2026-02-01 10:00:00', false);

        $this->assertFalse($this->calculator->isReceivedByExpiry($dto));
    }

    // ---- Helpers ----

    private function createOtpravakDTO(
        int $id,
        string $zadnjiTrenutakZaPotvrduPrimitka,
        bool $primljenZbogIstekaRoka
    ): PagedOtpravakDTO {
        return new PagedOtpravakDTO(
            id: $id,
            status: 'U_DOSTAVI',
            predmetOznaka: "P-{$id}/2026",
            predmetId: $id,
            sudNaziv: 'Test Sud',
            datumSlanjaSaSuda: '2026-01-15',
            datumPotvrdePrimitka: null,
            zadnjiTrenutakZaPotvrduPrimitka: $zadnjiTrenutakZaPotvrduPrimitka,
            primljenZbogIstekaRoka: $primljenZbogIstekaRoka,
        );
    }

    private function createPaginatedResponse(array $content): \App\DTOs\Ekom\PaginatedResponse
    {
        return new \App\DTOs\Ekom\PaginatedResponse(
            content: $content,
            totalElements: count($content),
            totalPages: 1,
            page: 0,
            size: 100,
            last: true,
            first: true
        );
    }
}
