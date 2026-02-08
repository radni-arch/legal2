<?php

namespace Tests\TestData\Builders;

use App\Models\Evidence;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * EvidenceChainBuilder
 *
 * Builds coherent evidence sequences with temporal consistency.
 * Creates chains of related evidence items (communications, physical evidence)
 * with proper timeline ordering for realistic test scenarios.
 */
class EvidenceChainBuilder
{
    private int $communicationCount = 0;
    private bool $hasPhysicalEvidence = false;
    private bool $enforceTimeline = false;
    private array $evidenceItems = [];

    /**
     * Create a new builder instance
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Add a chain of communication evidence (emails, messages, calls)
     */
    public function withCommunicationChain(int $count): self
    {
        $this->communicationCount = $count;
        return $this;
    }

    /**
     * Add physical evidence to the chain
     */
    public function withPhysicalEvidence(): self
    {
        $this->hasPhysicalEvidence = true;
        return $this;
    }

    /**
     * Enforce timeline ordering (evidence items will be chronologically ordered)
     */
    public function withTimeline(): self
    {
        $this->enforceTimeline = true;
        return $this;
    }

    /**
     * Build and persist the evidence chain
     */
    public function build(): Collection
    {
        $evidenceCollection = collect();
        $baseTime = Carbon::now()->subDays(30);
        $timeIncrement = 0;

        // Add communication chain
        if ($this->communicationCount > 0) {
            $communicationTypes = [
                'email' => 'Email poruka',
                'sms' => 'SMS poruka',
                'phone_call' => 'Telefonski poziv',
                'meeting' => 'Sastanak',
            ];

            for ($i = 0; $i < $this->communicationCount; $i++) {
                $type = array_rand($communicationTypes);
                $typeLabel = $communicationTypes[$type];

                $evidence = new Evidence([
                    'title' => "{$typeLabel} - Dokaz #{$i + 1}",
                    'description' => $this->generateCommunicationDescription($typeLabel, $i + 1),
                    'type' => 'communication',
                    'source' => $type,
                ]);

                // Set creation time for timeline
                if ($this->enforceTimeline) {
                    $evidence->created_at = $baseTime->copy()->addHours($timeIncrement);
                    $evidence->updated_at = $evidence->created_at;
                    $timeIncrement += rand(2, 8); // Random hours between evidence
                }

                $evidence->save();
                $evidenceCollection->push($evidence);
            }
        }

        // Add physical evidence
        if ($this->hasPhysicalEvidence) {
            $physicalTypes = [
                'Pronađena dokumentacija',
                'Fizički predmeti',
                'Uzorci DNA',
                'Otisci prstiju',
                'Fotografije s mjesta događaja',
            ];

            foreach ($physicalTypes as $index => $physicalType) {
                $evidence = new Evidence([
                    'title' => $physicalType,
                    'description' => "Fizički dokaz prikupljen tijekom istrage. {$physicalType} osiguran prema pravilima ZKP.",
                    'type' => 'physical',
                    'source' => 'crime_scene',
                ]);

                if ($this->enforceTimeline) {
                    $evidence->created_at = $baseTime->copy()->addHours($timeIncrement);
                    $evidence->updated_at = $evidence->created_at;
                    $timeIncrement += rand(3, 12);
                }

                $evidence->save();
                $evidenceCollection->push($evidence);

                // Only add one physical evidence item for now
                break;
            }
        }

        // Sort by timeline if enforced
        if ($this->enforceTimeline && $evidenceCollection->isNotEmpty()) {
            $evidenceCollection = $evidenceCollection->sortBy('created_at')->values();
        }

        return $evidenceCollection;
    }

    /**
     * Generate realistic communication description
     */
    private function generateCommunicationDescription(string $type, int $number): string
    {
        $templates = [
            'Email poruka' => "Email komunikacija između stranaka. Sadržaj #{$number} odnosi se na dogovor o sastanku i razmjenu dokumenata.",
            'SMS poruka' => "Tekstualna poruka #{$number}. Sadržaj potvrđuje dogovor i vremensku liniju događaja.",
            'Telefonski poziv' => "Zapis telefonskog poziva #{$number}. Trajanje 15 minuta. Razgovor o detaljima slučaja.",
            'Sastanak' => "Zapisnik sa sastanka #{$number}. Prisutne osobe: odvjetnik, klijent. Tema: strategija obrane.",
        ];

        return $templates[$type] ?? "Komunikacijski dokaz #{$number}";
    }
}
