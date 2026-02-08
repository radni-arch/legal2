<?php

namespace App\Services\LegalArtillery;

use App\Contracts\LegalArtillery\DevastatingArgumentBuilderInterface;
use App\DTOs\ArgumentChain;

/**
 * Builds devastating legal argument chains for prompt injection.
 *
 * Chains are pre-defined as logical progressions from premise to knockout,
 * aligned with specific document profiles.
 */
class DevastatingArgumentBuilder implements DevastatingArgumentBuilderInterface
{
    /**
     * Definirane devastirajuće lance argumenata.
     * Svaki lanac je niz koraka: premisa → zaključak → pojačanje → nokaut.
     */
    private array $chains = [
        // ================================================================
        // CHAIN 1: "Ne možete iscrpiti ono što ne postoji"
        // ================================================================
        'exhaustion_impossibility' => [
            'name' => 'Nemogućnost iscrpljivanja pravnog puta',
            'target_profiles' => ['ustavni_sud', 'echr_application'],
            'steps' => [
                [
                    'label' => 'PREMISA',
                    'argument' => 'Članak 18. st.1 Ustava jamči pravo na žalbu protiv pojedinačnih pravnih akata.',
                    'provisions' => ['Ustav čl.18 st.1'],
                ],
                [
                    'label' => 'ČINJENICA',
                    'argument' => 'Zahtjev za uvid u spis odbijen je neformalnim emailom, bez donošenja rješenja.',
                    'provisions' => [],
                ],
                [
                    'label' => 'LOGIČKI ZAKLJUČAK',
                    'argument' => 'Neformalni email NIJE pojedinačni pravni akt u smislu čl.18. Ne sadrži izreku, obrazloženje, ni pravnu pouku.',
                    'provisions' => ['Ustav čl.18 st.1', 'Ustav čl.29 st.1'],
                    'precedent' => 'U-III-3071/2006',
                ],
                [
                    'label' => 'POJAČANJE',
                    'argument' => 'Ustavni sud je u U-III-2258/2018 utvrdio da prava postaju "iluzorna i teorijska" kad odluke nisu obrazložene. Email bez obrazloženja = iluzorna odluka.',
                    'precedent' => 'U-III-2258/2018',
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Iscrpljivanje pravnog puta pretpostavlja POSTOJANJE pravnog puta. Kad ne postoji formalni akt, ne postoji ni akt protiv kojeg se podnosi žalba. Zahtjev za iscrpljivanje nepostojećeg puta je contradictio in adjecto. Stoga se primjenjuje čl.62(63) Ustavnog zakona — podnositelj nije dužan iscrpiti ono što ne postoji.',
                    'provisions' => ['UZUSRH čl.62 st.1'],
                ],
            ],
            'killer_summary' => 'Ne možete iscrpiti pravni put koji ne postoji. Neformalni email nije pravni akt. Tražiti od podnositelja da se žali na email = tražiti žalbu na ništa.',
        ],

        // ================================================================
        // CHAIN 2: "Tajnost istrage ne pokriva arhivirane spise"
        // ================================================================
        'investigation_secrecy_demolished' => [
            'name' => 'Rušenje argumenta tajnosti izvida',
            'target_profiles' => ['predsjednik_suda', 'ombudsman', 'ustavni_sud'],
            'steps' => [
                [
                    'label' => 'PROTIVNIČKA POZICIJA',
                    'argument' => 'Sud se poziva na čl.206.f ZKP — tajnost izvida — kao razlog uskrate pristupa.',
                    'provisions' => ['ZKP čl.206.f'],
                ],
                [
                    'label' => 'ČINJENICA 1',
                    'argument' => 'Spis je arhiviran 10. srpnja 2025. Arhiviranje znači da su izvidi završeni i predmet zaključen.',
                    'provisions' => [],
                ],
                [
                    'label' => 'LOGIČKI ZAKLJUČAK',
                    'argument' => 'Čl.206.f štiti tajnost TEKUĆIH izvida. Kad su izvidi završeni i spis arhiviran, nema što štititi — svrha tajnosti je ispunjena.',
                    'provisions' => ['ZKP čl.206.f'],
                ],
                [
                    'label' => 'POJAČANJE 1',
                    'argument' => 'Čak i tijekom tekućih izvida, čl.184 st.5 ZKP jamči pravo uvida u zapisnike o hitnim radnjama u roku 30 dana. Pretraga doma = hitna radnja.',
                    'provisions' => ['ZKP čl.184 st.5'],
                ],
                [
                    'label' => 'POJAČANJE 2',
                    'argument' => 'ESLJP u Garcia Alva v. Germany: tajnost istrage NE opravdava potpunu uskratu pristupa dokumentima bitnim za osporavanje zakonitosti postupanja.',
                    'precedent' => 'Garcia Alva v. Germany (23541/94)',
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Pozivanje na čl.206.f za arhivirani spis je ili: (a) nepoznavanje propisa — sud ne razlikuje tekuće od završenih izvida, ili (b) namjerna zlouporaba ovlasti — korištenje tajnosti kao izgovora za uskratu prava. U oba slučaja, radi se o povredi čl.29 Ustava.',
                    'provisions' => ['Ustav čl.29 st.1'],
                ],
            ],
            'killer_summary' => 'Tajnost izvida = tekući izvidi. Spis je arhiviran = izvidi završeni. Pozivanje na tajnost za zaključeni predmet je ili neznanje ili zlouporaba.',
        ],

        // ================================================================
        // CHAIN 3: "Čl.108 vs čl.150 — pogrešna odredba"
        // ================================================================
        'wrong_provision_applied' => [
            'name' => 'Sud primjenjuje pogrešnu odredbu',
            'target_profiles' => ['predsjednik_suda', 'ombudsman', 'ministarstvo_pravosudja', 'ustavni_sud'],
            'steps' => [
                [
                    'label' => 'PROTIVNIČKA POZICIJA',
                    'argument' => 'Sudac Bertok odbija pristup pozivajući se na čl.108 PZ — podnositelj nije stranka u prekršajnom postupku.',
                    'provisions' => ['PZ čl.108'],
                ],
                [
                    'label' => 'PROTUARGUMENT',
                    'argument' => 'Čl.108 definira stranke. Ali čl.150 st.1 IZRIJEKOM proširuje pristup spisu i na osobe koje NISU stranke: "svakomu drugom tko za to ima opravdani interes".',
                    'provisions' => ['PZ čl.150 st.1'],
                ],
                [
                    'label' => 'POJAČANJE 1',
                    'argument' => 'Nadalje, čl.150 st.4 predviđa da u završenom postupku o pristupu odlučuje PREDSJEDNIK SUDA, ne sudac koji je vodio postupak. Sudac Bertok nije nadležan.',
                    'provisions' => ['PZ čl.150 st.4'],
                ],
                [
                    'label' => 'POJAČANJE 2',
                    'argument' => 'Osoba čiji je dom pretražen temeljem naredbe ima inherentni opravdani interes — njezina su prava iz čl.34 Ustava direktno pogođena.',
                    'provisions' => ['Ustav čl.34'],
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Primjena čl.108 umjesto čl.150 na zahtjev za pristup spisu je fundamentalna pravna pogreška koja: (1) primjenjuje pogrešnu odredbu, (2) ignorira širu odredbu u istom zakonu, (3) donosi je nenadležna osoba. USRH U-III-3071/2006: sudovi su dužni poznavati propise koje primjenjuju.',
                    'provisions' => ['PZ čl.108', 'PZ čl.150 st.1', 'PZ čl.150 st.4'],
                    'precedent' => 'U-III-3071/2006',
                ],
            ],
            'killer_summary' => 'Sud primjenjuje čl.108 (definicija stranaka) umjesto čl.150 (pristup spisu). To je kao da citirate definiciju automobila kad vas netko pita za prometna pravila. Pogrešna odredba, pogrešna osoba, pogrešan zaključak.',
        ],

        // ================================================================
        // CHAIN 4: "Circulus vitiosus — začarani krug"
        // ================================================================
        'vicious_circle' => [
            'name' => 'Začarani krug uskrate',
            'target_profiles' => ['ustavni_sud', 'echr_application', 'ombudsman'],
            'steps' => [
                [
                    'label' => 'KORAK 1',
                    'argument' => 'Da bih osporio zakonitost pretrage, trebam vidjeti naredbu i spis.',
                ],
                [
                    'label' => 'KORAK 2',
                    'argument' => 'Da bih vidio spis, trebam podnijeti zahtjev.',
                ],
                [
                    'label' => 'KORAK 3',
                    'argument' => 'Zahtjev je odbijen — ali neformalno, bez rješenja.',
                ],
                [
                    'label' => 'KORAK 4',
                    'argument' => 'Da bih se žalio na odbijanje, trebam formalno rješenje.',
                ],
                [
                    'label' => 'KORAK 5',
                    'argument' => 'Rješenje neće biti doneseno — sud odgovara samo emailom.',
                ],
                [
                    'label' => 'ZAKLJUČAK',
                    'argument' => 'Situacija je circulus vitiosus — ne mogu pristupiti dokazima bez odluke, a ne mogu dobiti odluku bez pristupa postupku. ESLJP u Horvat v. Croatia: "neizvjesnost pravnog lijeka u praktičnom smislu" = povreda čl.13 EKLJP.',
                    'precedent' => 'Horvat v. Croatia',
                ],
            ],
            'killer_summary' => 'Sustav je dizajniran tako da blokira sam sebe. Trebam spis da bih se branio. Trebam odluku da bih dobio spis. Neću dobiti odluku jer se sud koristi emailom. To je Kafkina košmara pretočena u pravnu stvarnost.',
        ],

        // ================================================================
        // CHAIN 5: "Plod otrovnog drveta" (Fruit of poisonous tree)
        // ================================================================
        'fruit_of_poisonous_tree' => [
            'name' => 'Kontaminacija svih dokaza',
            'target_profiles' => ['kazneni_sud_motion', 'izdvajanje_dokaza', 'dorh_production'],
            'steps' => [
                [
                    'label' => 'PREMISA 1',
                    'argument' => 'Dokazi korišteni u kaznenom postupku prikupljeni su pretragom doma temeljem naredbe Pp Prz-74/2025-2.',
                ],
                [
                    'label' => 'PREMISA 2',
                    'argument' => 'Obrani je uskraćen pristup spisu pretrage — ne možemo provjeriti zakonitost naredbe.',
                ],
                [
                    'label' => 'PRAVNI ZAKLJUČAK',
                    'argument' => 'Uskrata pristupa spisu = povreda prava obrane (čl.10 st.2 toč.2 ZKP). Dokazi prikupljeni uz povredu prava obrane su nezakoniti.',
                    'provisions' => ['ZKP čl.10 st.2 toč.2'],
                ],
                [
                    'label' => 'POJAČANJE',
                    'argument' => 'Čak i ako se naredba pokaže zakonitom, sam postupak uskrate pristupa kontaminira dokaze. Dragojević v. Croatia: retroaktivno opravdanje deficijentnog naloga nije dopušteno.',
                    'precedent' => 'Dragojević v. Croatia (68955/11)',
                ],
                [
                    'label' => 'KASKADNI EFEKT',
                    'argument' => 'ZKP čl.10 st.2 toč.4: "Plod otrovnog drveta" — SVI dokazi izvedeni iz nezakonite pretrage su nezakoniti. To uključuje: fizičke dokaze, fotografije, svjedočenja policije o pronađenom, i sve što je iz toga proizašlo.',
                    'provisions' => ['ZKP čl.10 st.2 toč.4'],
                ],
                [
                    'label' => 'NOKAUT',
                    'argument' => 'Optužnica se temelji na dokazima iz pretrage. Pretraga se ne može verificirati. Neverificirana pretraga = sumnja u zakonitost. Sumnja + uskrata obrane = nezakoniti dokazi. Nezakoniti dokazi = urušavanje optužnice.',
                ],
            ],
            'killer_summary' => 'Ako ne mogu provjeriti zakonitost pretrage, ne mogu se braniti. Ako se ne mogu braniti, dokazi su nezakoniti. Ako su dokazi nezakoniti, sve izvedeno iz njih pada.',
        ],
    ];

    /**
     * Get all argument chains applicable to a specific profile.
     *
     * @param string $profileKey The document profile key
     * @return array<ArgumentChain> Array of argument chains for the profile
     */
    public function getChainsForProfile(string $profileKey): array
    {
        $matching = array_filter($this->chains, function (array $chain) use ($profileKey) {
            return in_array($profileKey, $chain['target_profiles'], true);
        });

        $chains = [];
        foreach ($matching as $key => $chain) {
            $chains[] = new ArgumentChain(
                name: $key,
                displayName: $chain['name'],
                steps: $chain['steps'],
                killerSummary: $chain['killer_summary'],
                profileKeys: $chain['target_profiles'],
            );
        }

        return $chains;
    }

    /**
     * Build a formatted argument injection string for LLM prompts.
     *
     * @param string $profileKey The document profile key
     * @return string Formatted argument chains for prompt injection
     */
    public function buildArgumentInjection(string $profileKey): string
    {
        $chains = $this->getChainsForProfile($profileKey);
        if (empty($chains)) {
            return '';
        }

        $text = "## DEVASTIRAJUĆI LANCI ARGUMENATA\n\n";
        $text .= "Koristi ove lance argumenata kao okosnicu dokumenta. ";
        $text .= "Svaki lanac vodi do neizbježnog zaključka. ";
        $text .= "NE preskači korake — svaki korak gradi na prethodnom.\n\n";

        foreach ($chains as $chain) {
            $text .= "### LANAC: {$chain->displayName}\n";
            foreach ($chain->steps as $step) {
                $text .= "**{$step['label']}**: {$step['argument']}\n";
                if (array_key_exists('provisions', $step)) {
                    $text .= "  Odredbe: " . implode(', ', $step['provisions']) . "\n";
                }
                if (array_key_exists('precedent', $step)) {
                    $text .= "  Presuda: {$step['precedent']}\n";
                }
            }
            $text .= "\n💀 ZAKLJUČAK: {$chain->killerSummary}\n\n";
            $text .= "---\n\n";
        }

        return $text;
    }

    /**
     * Get killer summaries for quick reference.
     *
     * @param string $profileKey The document profile key
     * @return array Array of ['name' => string, 'summary' => string]
     */
    public function getKillerSummaries(string $profileKey): array
    {
        $chains = $this->getChainsForProfile($profileKey);

        return array_map(fn(ArgumentChain $chain) => [
            'name' => $chain->displayName,
            'summary' => $chain->killerSummary,
        ], $chains);
    }
}
