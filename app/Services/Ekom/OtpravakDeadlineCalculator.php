<?php

namespace App\Services\Ekom;

use App\DTOs\Ekom\Otpravci\PagedOtpravakDTO;
use App\Models\Ekom\EkomProcedureType;
use Carbon\Carbon;

/**
 * Calculator for dispatch (otpravak) deadlines per Croatian law.
 *
 * Legal deadlines:
 * - Criminal cases (kazneni predmeti): 8 days from dispatch date (ZKP cl. 172.a)
 * - All other procedure types: 15 days from dispatch date (ZPP cl. 143.c, ZUS cl. 110)
 *
 * After deadline expiry, the system auto-confirms receipt.
 */
class OtpravakDeadlineCalculator
{
    /**
     * Criminal procedure deadline in days per ZKP (Zakon o kaznenom postupku).
     */
    private const CRIMINAL_DEADLINE_DAYS = 8;

    /**
     * Default deadline in days for non-criminal procedures per ZPP/ZUS.
     */
    private const DEFAULT_DEADLINE_DAYS = 15;

    public function __construct(
        private readonly OtpravakService $otpravakService
    ) {}

    /**
     * Calculate deadline for a dispatch based on procedure type.
     *
     * @param  Carbon  $dispatchDate  Date the dispatch was sent from court
     * @param  int  $vrstaPostupkaId  Remote ID of the procedure type (from sifrarnici)
     * @return Carbon Deadline datetime
     */
    public function calculateDeadline(Carbon $dispatchDate, int $vrstaPostupkaId): Carbon
    {
        $days = $this->isCriminalProcedure($vrstaPostupkaId)
            ? self::CRIMINAL_DEADLINE_DAYS
            : self::DEFAULT_DEADLINE_DAYS;

        return $dispatchDate->copy()->addDays($days);
    }

    /**
     * Check if a procedure type is criminal (kazneni postupak).
     *
     * Criminal procedures in Croatian legal system have 'oznaka' starting with 'K':
     * - K (kazneni)
     * - Kv (kazneni vijecni)
     * - Kzm (kazneni za mladeze)
     * - Km (kazneni maloljetnici)
     * - Kov (kazneni ovrsni)
     * - Kr (kazneni registar)
     * - etc.
     */
    public function isCriminalProcedure(int $vrstaPostupkaId): bool
    {
        $procedureType = EkomProcedureType::where('remote_id', $vrstaPostupkaId)->first();

        if (! $procedureType || ! $procedureType->oznaka) {
            return false;
        }

        // Croatian criminal procedure types have oznaka starting with 'K'
        return str_starts_with(strtoupper($procedureType->oznaka), 'K');
    }

    /**
     * Get dispatches that have passed their deadline and will be auto-confirmed.
     *
     * These are dispatches where zadnjiTrenutakZaPotvrduPrimitka is in the past.
     *
     * @return array<PagedOtpravakDTO>
     */
    public function getAutoConfirmedDispatches(): array
    {
        $dispatches = $this->getUnconfirmedDispatches();
        $now = now();

        return array_filter($dispatches, function (PagedOtpravakDTO $dto) use ($now) {
            if (! $dto->zadnjiTrenutakZaPotvrduPrimitka) {
                return false;
            }

            $deadline = Carbon::parse($dto->zadnjiTrenutakZaPotvrduPrimitka);

            return $deadline->lessThan($now);
        });
    }

    /**
     * Get dispatches with deadline in approximately 3 days (48-72 hours).
     *
     * @return array<PagedOtpravakDTO>
     */
    public function getThreeDayWarnings(): array
    {
        $now = now();
        $threeDayStart = $now->copy()->addHours(48);
        $threeDayEnd = $now->copy()->addHours(72);

        return $this->getDispatchesInDeadlineWindow($threeDayStart, $threeDayEnd);
    }

    /**
     * Get dispatches with deadline in approximately 1 day (12-24 hours).
     *
     * @return array<PagedOtpravakDTO>
     */
    public function getOneDayWarnings(): array
    {
        $now = now();
        $oneDayStart = $now->copy()->addHours(12);
        $oneDayEnd = $now->copy()->addHours(24);

        return $this->getDispatchesInDeadlineWindow($oneDayStart, $oneDayEnd);
    }

    /**
     * Check if a dispatch was received due to deadline expiry (automatic confirmation).
     *
     * @param  PagedOtpravakDTO  $otpravak  The dispatch to check
     */
    public function isReceivedByExpiry(PagedOtpravakDTO $otpravak): bool
    {
        return $otpravak->primljenZbogIstekaRoka;
    }

    /**
     * Get dispatches with deadline within the specified time window.
     *
     * @return array<PagedOtpravakDTO>
     */
    private function getDispatchesInDeadlineWindow(Carbon $start, Carbon $end): array
    {
        $dispatches = $this->getUnconfirmedDispatches();

        return array_filter($dispatches, function (PagedOtpravakDTO $dto) use ($start, $end) {
            if (! $dto->zadnjiTrenutakZaPotvrduPrimitka) {
                return false;
            }

            $deadline = Carbon::parse($dto->zadnjiTrenutakZaPotvrduPrimitka);

            return $deadline->greaterThanOrEqualTo($start) && $deadline->lessThanOrEqualTo($end);
        });
    }

    /**
     * Get unconfirmed dispatches (status = U_DOSTAVI).
     *
     * @return array<PagedOtpravakDTO>
     */
    private function getUnconfirmedDispatches(): array
    {
        $response = $this->otpravakService->list([
            'status' => 'U_DOSTAVI',
        ], page: 0, size: 100);

        return array_filter(
            $response->content,
            fn ($item) => $item instanceof PagedOtpravakDTO
        );
    }
}
