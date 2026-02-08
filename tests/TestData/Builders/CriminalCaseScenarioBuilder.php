<?php

namespace Tests\TestData\Builders;

use App\Models\CaseDocument;
use App\Models\LegalCase;

/**
 * CriminalCaseScenarioBuilder
 *
 * Builds complete criminal case workflows with related documents, evidence, and agents.
 * Uses existing Laravel factories to create realistic test scenarios for Croatian legal system.
 */
class CriminalCaseScenarioBuilder
{
    private bool $hasDrugCharges = false;

    private bool $hasHomeSearch = false;

    private bool $hasFabricatedEvidence = false;

    private int $witnessCount = 0;

    /**
     * Create a new builder instance
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Add drug charges to the criminal case
     */
    public function withDrugCharges(): self
    {
        $this->hasDrugCharges = true;

        return $this;
    }

    /**
     * Add home search warrant and execution documents
     */
    public function withHomeSearch(): self
    {
        $this->hasHomeSearch = true;

        return $this;
    }

    /**
     * Add fabricated evidence markers (for misconduct detection testing)
     */
    public function withFabricatedEvidence(): self
    {
        $this->hasFabricatedEvidence = true;

        return $this;
    }

    /**
     * Add witness statements to the case
     */
    public function withWitnesses(int $count): self
    {
        $this->witnessCount = $count;

        return $this;
    }

    /**
     * Build and persist the criminal case with all specified components
     */
    public function build(): LegalCase
    {
        // Create base criminal case
        $case = LegalCase::factory()->criminal()->create();

        // Add drug charges if specified
        if ($this->hasDrugCharges) {
            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Optužnica - Prekršaj posjedovanja droge',
                    'content' => 'Optužnica prema članku 173. Zakona o suzbijanju zlouporabe droga. '
                        .'Okrivljenik je dana 15.03.2024. uhvaćen u posjedu 5 grama droge (marihuana). '
                        .'Policijski zapisnik potvrđuje pronalazak droge prilikom legitimacije.',
                    'category' => 'evidence',
                    'author' => 'Državni odvjetnik',
                ]);
        }

        // Add home search documentation if specified
        if ($this->hasHomeSearch) {
            CaseDocument::factory()
                ->forCase($case)
                ->courtOrder()
                ->create([
                    'title' => 'Nalog za pretres stana',
                    'content' => 'Nalog za pretres stana izdan od strane suca istrage. '
                        .'Temeljem razumne sumnje o počinjenju kaznenog djela, '
                        .'odobrava se pretres stambenog prostora na adresi Ilica 42, Zagreb. '
                        .'Pretres će se provesti u prisutnosti dva svjedoka prema ZKP.',
                    'category' => 'court-order',
                    'author' => 'Sudac istrage',
                ]);

            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Zapisnik o pretresu stana',
                    'content' => 'Zapisnik o pretresu stana izvršenom dana 16.03.2024. '
                        .'Tijekom pretresa pronađeni su  dokazi navedeni u prilogu. '
                        .'Pretres je proveden uz prisutnost svjedoka i u skladu sa ZKP.',
                    'category' => 'evidence',
                    'author' => 'Policijski službenik',
                ]);
        }

        // Add fabricated evidence if specified
        if ($this->hasFabricatedEvidence) {
            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Sporni dokaz - Fabrikovano svjedočenje',
                    'content' => 'Dokaz koji pokazuje znakove falsificiranja. '
                        .'Svjedok navodi događaje koji se nisu mogli dogoditi u navedeno vrijeme. '
                        .'Krivotvoreno svjedočanstvo prema analizi. '
                        .'Uočene nedosljednosti s drugim dokazima u spisu.',
                    'category' => 'evidence',
                    'metadata' => [
                        'suspicious' => true,
                        'fabrication_indicators' => ['timeline_inconsistency', 'contradicts_physical_evidence'],
                    ],
                ]);
        }

        // Add witness statements if specified
        if ($this->witnessCount > 0) {
            $witnessNames = ['Marko Horvat', 'Ana Kovačević', 'Petar Babić', 'Maja Jurić', 'Ivan Novak'];

            for ($i = 0; $i < $this->witnessCount; $i++) {
                $witnessName = $witnessNames[$i % count($witnessNames)];

                CaseDocument::factory()
                    ->forCase($case)
                    ->witness()
                    ->create([
                        'title' => "Iskaz svjedoka - {$witnessName}",
                        'content' => "Iskaz svjedoka {$witnessName} dan dana ".now()->subDays($i + 1)->format('d.m.Y').'. '
                            .'Svjedok potvrđuje okolnosti događaja i identificira okrivljenika. '
                            .'Iskaz je dan dobrovoljno i zabilježen prema članku 236. ZKP.',
                        'category' => 'witness',
                        'author' => $witnessName,
                    ]);
            }
        }

        return $case->fresh(['documents']);
    }
}
