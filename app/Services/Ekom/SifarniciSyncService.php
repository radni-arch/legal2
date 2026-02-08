<?php

namespace App\Services\Ekom;

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

class SifarniciSyncService
{
    public function __construct(
        private readonly EkomApiClientInterface $client
    ) {}

    /**
     * Sync all reference data in the correct cascade order.
     *
     * @param bool $force Force full re-sync even if recently synced
     * @return array<string, int> Stats with count of synced items per category
     */
    public function syncAll(bool $force = false): array
    {
        return [
            'courts' => $this->syncCourts(),
            'procedure_types' => $this->syncProcedureTypes(),
            'submission_types' => $this->syncSubmissionTypes(),
            'participant_roles' => $this->syncParticipantRoles(),
            'fee_options' => $this->syncFeeOptions(),
            'non_payment_reasons' => $this->syncNonPaymentReasons(),
            'fee_exemptions' => $this->syncFeeExemptions(),
            'settlements' => $this->syncSettlements(),
            'countries' => $this->syncCountries(),
        ];
    }

    /**
     * Sync courts (sudovi) from API.
     */
    public function syncCourts(): int
    {
        $courts = $this->client->getSudovi();
        $count = 0;

        foreach ($courts as $court) {
            EkomCourt::updateOrCreate(
                ['remote_id' => $court['id']],
                [
                    'naziv' => $court['naziv'],
                    'oznaka' => $court['oznaka'] ?? null,
                    'vrsta_suda_id' => $court['vrstaSuda']['id'] ?? null,
                    'vrsta_suda_naziv' => $court['vrstaSuda']['naziv'] ?? null,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync procedure types (vrste postupaka) for courts.
     *
     * @param int|null $courtRemoteId Optionally sync only for a specific court
     */
    public function syncProcedureTypes(?int $courtRemoteId = null): int
    {
        $courts = $courtRemoteId
            ? EkomCourt::where('remote_id', $courtRemoteId)->get()
            : EkomCourt::all();

        $count = 0;

        foreach ($courts as $court) {
            $procedureTypes = $this->client->getVrstePostupakaZaSud($court->remote_id);

            foreach ($procedureTypes as $pt) {
                EkomProcedureType::updateOrCreate(
                    ['remote_id' => $pt['id']],
                    [
                        'court_remote_id' => $court->remote_id,
                        'naziv' => $pt['naziv'],
                        'oznaka' => $pt['oznaka'] ?? null,
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync submission types (vrste podnesaka) for procedure types.
     * Syncs both contexts: novi_postupak and postojeci_predmet.
     *
     * @param int|null $procedureTypeRemoteId Optionally sync only for a specific procedure type
     */
    public function syncSubmissionTypes(?int $procedureTypeRemoteId = null): int
    {
        $procedureTypes = $procedureTypeRemoteId
            ? EkomProcedureType::where('remote_id', $procedureTypeRemoteId)->get()
            : EkomProcedureType::all();

        $count = 0;

        foreach ($procedureTypes as $pt) {
            // Sync novi_postupak context
            $noviPostupakTypes = $this->client->getVrstePodnesakaNoviPostupak($pt->remote_id);
            foreach ($noviPostupakTypes as $st) {
                EkomSubmissionType::updateOrCreate(
                    ['remote_id' => $st['id'], 'context' => 'novi_postupak'],
                    [
                        'procedure_type_remote_id' => $pt->remote_id,
                        'naziv' => $st['naziv'],
                        'oznaka' => $st['oznaka'] ?? null,
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }

            // Sync postojeci_predmet context
            $postojeciPredmetTypes = $this->client->getVrstePodnesakaPostojeciPredmet($pt->remote_id);
            foreach ($postojeciPredmetTypes as $st) {
                EkomSubmissionType::updateOrCreate(
                    ['remote_id' => $st['id'], 'context' => 'postojeci_predmet'],
                    [
                        'procedure_type_remote_id' => $pt->remote_id,
                        'naziv' => $st['naziv'],
                        'oznaka' => $st['oznaka'] ?? null,
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync participant roles (uloge sudionika/podnositelja) for procedure types.
     * Syncs both types: sudionik and podnositelj.
     *
     * @param int|null $procedureTypeRemoteId Optionally sync only for a specific procedure type
     */
    public function syncParticipantRoles(?int $procedureTypeRemoteId = null): int
    {
        $procedureTypes = $procedureTypeRemoteId
            ? EkomProcedureType::where('remote_id', $procedureTypeRemoteId)->get()
            : EkomProcedureType::all();

        $count = 0;

        foreach ($procedureTypes as $pt) {
            // Sync sudionik roles
            $sudionikRoles = $this->client->getUlogeSudionika($pt->remote_id);
            foreach ($sudionikRoles as $role) {
                EkomParticipantRole::updateOrCreate(
                    ['remote_id' => $role['id'], 'type' => 'sudionik'],
                    [
                        'procedure_type_remote_id' => $pt->remote_id,
                        'naziv' => $role['naziv'],
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }

            // Sync podnositelj roles
            $podnoditeljRoles = $this->client->getUlogePodnositelja($pt->remote_id);
            foreach ($podnoditeljRoles as $role) {
                EkomParticipantRole::updateOrCreate(
                    ['remote_id' => $role['id'], 'type' => 'podnositelj'],
                    [
                        'procedure_type_remote_id' => $pt->remote_id,
                        'naziv' => $role['naziv'],
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync fee options (pristojba dodatne opcije) for submission types.
     * Syncs both contexts based on submission type context.
     */
    public function syncFeeOptions(): int
    {
        $submissionTypes = EkomSubmissionType::all();
        $count = 0;

        foreach ($submissionTypes as $st) {
            $options = $st->context === 'novi_postupak'
                ? $this->client->getPristojbaDodatneOpcijeNoviPostupak($st->procedure_type_remote_id, $st->remote_id)
                : $this->client->getPristojbaDodatneOpcijePostojeciPredmet($st->procedure_type_remote_id, $st->remote_id);

            foreach ($options as $option) {
                EkomFeeOption::updateOrCreate(
                    ['remote_id' => $option['id'], 'context' => $st->context],
                    [
                        'procedure_type_remote_id' => $st->procedure_type_remote_id,
                        'submission_type_remote_id' => $st->remote_id,
                        'naziv' => $option['naziv'],
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sync non-payment reasons (razlozi neplacanja pristojbe).
     */
    public function syncNonPaymentReasons(): int
    {
        $reasons = $this->client->getRazloziNeplacanjaPristojbe();
        $count = 0;

        foreach ($reasons as $reason) {
            EkomNonPaymentReason::updateOrCreate(
                ['remote_id' => $reason['id']],
                [
                    'naziv' => $reason['naziv'],
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync fee exemptions (osnove oslobodjenja pristojbe).
     */
    public function syncFeeExemptions(): int
    {
        $exemptions = $this->client->getOsnoveOslobodjenjaPristojbe();
        $count = 0;

        foreach ($exemptions as $exemption) {
            EkomFeeExemption::updateOrCreate(
                ['remote_id' => $exemption['id']],
                [
                    'naziv' => $exemption['naziv'],
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync settlements (naselja).
     */
    public function syncSettlements(): int
    {
        $settlements = $this->client->getNaselja();
        $count = 0;

        foreach ($settlements as $settlement) {
            EkomSettlement::updateOrCreate(
                ['remote_id' => $settlement['id']],
                [
                    'naziv' => $settlement['naziv'],
                    'postanski_broj' => $settlement['postanskiBroj'] ?? null,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync countries (države).
     */
    public function syncCountries(): int
    {
        $countries = $this->client->getDrzave();
        $count = 0;

        foreach ($countries as $country) {
            EkomCountry::updateOrCreate(
                ['remote_id' => $country['id']],
                [
                    'naziv' => $country['naziv'],
                    'oznaka' => $country['oznaka'] ?? null,
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }
}
