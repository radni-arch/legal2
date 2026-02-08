<?php

namespace Tests\TestData\Builders;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Carbon\Carbon;

/**
 * MisconductScenarioBuilder
 *
 * Builds prosecutorial misconduct patterns detectable by the AI system.
 * Creates realistic scenarios of:
 * - Backdated documents
 * - Hidden/suppressed exculpatory evidence
 * - Fabricated probable cause
 *
 * Used for testing the system's ability to detect and flag misconduct patterns.
 */
class MisconductScenarioBuilder
{
    private bool $hasBackdatedDocuments = false;

    private bool $hasHiddenEvidence = false;

    private bool $hasFabricatedPC = false;

    /**
     * Create a new builder instance
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Add backdated documents (documents with dates before case filing)
     */
    public function withBackdatedDocuments(): self
    {
        $this->hasBackdatedDocuments = true;

        return $this;
    }

    /**
     * Add hidden/suppressed exculpatory evidence
     */
    public function withHiddenEvidence(): self
    {
        $this->hasHiddenEvidence = true;

        return $this;
    }

    /**
     * Add fabricated probable cause documentation
     */
    public function withFabricatedPC(): self
    {
        $this->hasFabricatedPC = true;

        return $this;
    }

    /**
     * Build and persist the misconduct case with all specified patterns
     */
    public function build(): LegalCase
    {
        // Create base criminal case with misconduct tag
        $tags = ['criminal', 'misconduct'];
        $case = LegalCase::factory()->criminal()->create([
            'tags' => $tags,
            'description' => 'Kazneni predmet s indikatorima postupovnih nepravilnosti',
        ]);

        // Add backdated documents if specified
        if ($this->hasBackdatedDocuments) {
            // Document dated 2 weeks before case filing
            $backdatedDate = Carbon::parse($case->filing_date)->subWeeks(2);

            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Sumnjiv zapisnik - Antedatirano',
                    'content' => 'Policijski zapisnik sastavljen dana '.$backdatedDate->format('d.m.Y').'. '
                        .'Međutim, datum ne odgovara kronologiji događaja. '
                        .'Dokaz pokazuje znakove naknadno dodanih informacija.',
                    'category' => 'evidence',
                    'author' => 'Policijski službenik',
                    'metadata' => [
                        'document_date' => $backdatedDate->format('Y-m-d'),
                        'filing_date' => $case->filing_date->format('Y-m-d'),
                        'misconduct_type' => 'backdated_document',
                        'suspicious' => true,
                        'date_discrepancy_days' => $backdatedDate->diffInDays($case->filing_date),
                    ],
                ]);

            // Second backdated document
            $anotherBackdatedDate = Carbon::parse($case->filing_date)->subDays(5);

            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Iskaz - Suma na antedatiranje',
                    'content' => 'Iskaz svjedoka navodno dan '.$anotherBackdatedDate->format('d.m.Y').'. '
                        .'Međutim, svjedok tvrdi da nije bio dostupan tog datuma.',
                    'category' => 'witness',
                    'metadata' => [
                        'document_date' => $anotherBackdatedDate->format('Y-m-d'),
                        'misconduct_type' => 'backdated_statement',
                        'suspicious' => true,
                    ],
                ]);
        }

        // Add hidden evidence if specified
        if ($this->hasHiddenEvidence) {
            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Prikriveni oslobađajući dokaz',
                    'content' => 'Dokaz koji potvrđuje alibi okrivljenika je bio zatajenpred sudskom odlukom. '
                        .'Video snimka s nadzornih kamera pokazuje da okrivljenik nije bio na mjestu događaja. '
                        .'Ovaj dokaz je bio dostupan tužiteljstvu ali nije priložen obrani. '
                        .'Povredalični čl. 63. st. 1. ZKP o dužnosti otkrivanja dokaza.',
                    'category' => 'evidence',
                    'author' => 'Obrana (naknadno otkriveno)',
                    'metadata' => [
                        'misconduct_type' => 'suppressed_exculpatory_evidence',
                        'suspicious' => true,
                        'brady_violation' => true, // Brady v. Maryland equivalent
                        'discovery_violation' => true,
                    ],
                ]);

            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Zatajeni vještački nalaz',
                    'content' => 'Vještački nalaz koji osporava tvrdnje tužiteljstva. '
                        .'Nalaz potvrđuje da  materijal ne pripada okrivljeniku. '
                        .'Dokument je prikriveno čuvan i nije priložen u spisu.',
                    'category' => 'expert',
                    'metadata' => [
                        'misconduct_type' => 'hidden_expert_report',
                        'suspicious' => true,
                        'exculpatory' => true,
                    ],
                ]);
        }

        // Add fabricated probable cause if specified
        if ($this->hasFabricatedPC) {
            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Fabrikovani razlog za uhićenje',
                    'content' => 'Policijski izvještaj navodi razumnu sumnju koja se ne može potvrditi. '
                        .'Navodni anonimni dojavljač nikada nije identificiran. '
                        .'Informacije u izvještaju sadrže lažne tvrdnje i izmišljene činjenice. '
                        .'Kasnije je utvrđeno da opisani događaji nisu mogli biti istiniti.',
                    'category' => 'evidence',
                    'author' => 'Policijski inspektor',
                    'metadata' => [
                        'misconduct_type' => 'fabricated_probable_cause',
                        'suspicious' => true,
                        'contains_falsehoods' => true,
                        'anonymous_tipster_unverified' => true,
                    ],
                ]);

            CaseDocument::factory()
                ->forCase($case)
                ->evidence()
                ->create([
                    'title' => 'Lažna izjava pod zakletvom',
                    'content' => 'Izjava policijskog službenika pod zakletvom sadrži lažne informacije. '
                        .'Tvrdi da je vidio okrivljenika na mjestu događaja, '
                        .'ali GPS podaci i drugi dokazi pokazuju da službenik nije bio u blizini. '
                        .'Ovo je klasičan primjer perjury (krivokletstva) radi dobivanja naloga.',
                    'category' => 'legal',
                    'author' => 'Policijski službenik (pod zakletvom)',
                    'metadata' => [
                        'misconduct_type' => 'perjury',
                        'suspicious' => true,
                        'contradicted_by_gps' => true,
                        'false_statement' => true,
                    ],
                ]);
        }

        return $case->fresh(['documents']);
    }
}
