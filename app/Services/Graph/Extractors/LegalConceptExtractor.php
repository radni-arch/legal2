<?php

namespace App\Services\Graph\Extractors;

class LegalConceptExtractor
{
    /**
     * Croatian legal concepts with definitions and categories
     */
    protected array $concepts = [
        // Procedural concepts
        'tužba' => ['category' => 'procedural', 'definition' => 'Pismeni zahtjev kojim se pokreće sudski postupak'],
        'presuda' => ['category' => 'procedural', 'definition' => 'Sudska odluka o meritumu predmeta'],
        'rješenje' => ['category' => 'procedural', 'definition' => 'Sudska odluka procesne prirode'],
        'žalba' => ['category' => 'procedural', 'definition' => 'Pravni lijek protiv prvostupanjske odluke'],
        'revizija' => ['category' => 'procedural', 'definition' => 'Izvanredni pravni lijek protiv drugostupanjske presude'],
        'ovrha' => ['category' => 'procedural', 'definition' => 'Prisilno izvršenje sudske odluke'],
        'prijedlog' => ['category' => 'procedural', 'definition' => 'Zahtjev stranke u postupku'],
        'dokaz' => ['category' => 'procedural', 'definition' => 'Sredstvo utvrđivanja činjenica'],
        'svjedok' => ['category' => 'procedural', 'definition' => 'Osoba koja iznosi saznanja o činjenicama'],
        'vještak' => ['category' => 'procedural', 'definition' => 'Stručna osoba koja daje mišljenje'],
        'ročište' => ['category' => 'procedural', 'definition' => 'Zakazani termin za sudsku radnju'],
        'zastara' => ['category' => 'procedural', 'definition' => 'Gubitak prava zbog proteka vremena'],
        'pravomoćnost' => ['category' => 'procedural', 'definition' => 'Svojstvo odluke protiv koje nema redovnog pravnog lijeka'],
        'tuženik' => ['category' => 'procedural', 'definition' => 'Stranka protiv koje je podnesena tužba'],
        'tužitelj' => ['category' => 'procedural', 'definition' => 'Stranka koja podnosi tužbu'],
        'stranka' => ['category' => 'procedural', 'definition' => 'Sudionik u sudskom postupku'],
        'nadležnost' => ['category' => 'procedural', 'definition' => 'Ovlast suda da sudi u određenom predmetu'],
        'pretres' => ['category' => 'procedural', 'definition' => 'Raspravljanje pred sudom'],
        'dostava' => ['category' => 'procedural', 'definition' => 'Uručenje sudskog akta stranci'],
        'pobijanje' => ['category' => 'procedural', 'definition' => 'Osporavanje pravnog akta'],
        'povrat' => ['category' => 'procedural', 'definition' => 'Vraćanje predmeta u prethodno stanje'],
        'procesna_radnja' => ['category' => 'procedural', 'definition' => 'Radnja u sudskom postupku'],
        'zapisnik' => ['category' => 'procedural', 'definition' => 'Pismeni dokument o tijeku ročišta'],
        'tumačenje' => ['category' => 'procedural', 'definition' => 'Prevođenje stranoga jezika'],

        // Substantive civil law concepts
        'ugovor' => ['category' => 'substantive', 'definition' => 'Suglasnost volja dviju ili više strana'],
        'obveza' => ['category' => 'substantive', 'definition' => 'Dužnost ispunjenja određene činidbe'],
        'naknada_štete' => ['category' => 'substantive', 'definition' => 'Popravljanje prouzročene štete'],
        'šteta' => ['category' => 'substantive', 'definition' => 'Umanjenje imovine ili prava'],
        'vlasništvo' => ['category' => 'substantive', 'definition' => 'Najšire pravo na stvari'],
        'posjed' => ['category' => 'substantive', 'definition' => 'Faktična vlast nad stvari'],
        'hipoteka' => ['category' => 'substantive', 'definition' => 'Založno pravo na nekretnini'],
        'zalog' => ['category' => 'substantive', 'definition' => 'Osiguranje tražbine na stvari'],
        'služnost' => ['category' => 'substantive', 'definition' => 'Pravo korištenja tuđe stvari'],
        'najam' => ['category' => 'substantive', 'definition' => 'Ugovor o korištenju stvari uz naknadu'],
        'zakup' => ['category' => 'substantive', 'definition' => 'Ugovor o korištenju i ubiranju plodova'],
        'kupoprodaja' => ['category' => 'substantive', 'definition' => 'Ugovor o prijenosu vlasništva uz naknadu'],
        'darovanje' => ['category' => 'substantive', 'definition' => 'Besplatni prijenos imovine'],
        'nasljedstvo' => ['category' => 'substantive', 'definition' => 'Prijenos imovine nakon smrti'],
        'oporuka' => ['category' => 'substantive', 'definition' => 'Jednostrana izjava volje za slučaj smrti'],
        'jamstvo' => ['category' => 'substantive', 'definition' => 'Odgovornost za ispunjenje tuđe obveze'],
        'zakašnjenje' => ['category' => 'substantive', 'definition' => 'Neispunjenje obveze u određenom roku'],
        'neispunjenje' => ['category' => 'substantive', 'definition' => 'Neizvršenje ugovorne obveze'],
        'raskid' => ['category' => 'substantive', 'definition' => 'Prestanak ugovora prije roka'],
        'protivusluga' => ['category' => 'substantive', 'definition' => 'Uzajamna obveza u dvostrano obveznom ugovoru'],
        'pristanak' => ['category' => 'substantive', 'definition' => 'Suglasnost s pravnom radnjom'],
        'punopravnost' => ['category' => 'substantive', 'definition' => 'Potpuna poslovna sposobnost'],
        'poslovna_sposobnost' => ['category' => 'substantive', 'definition' => 'Sposobnost sklapanja pravnih poslova'],
        'ništavost' => ['category' => 'substantive', 'definition' => 'Potpuna pravna nevaljanost pravnog posla'],
        'pobojnost' => ['category' => 'substantive', 'definition' => 'Osporiva pravna valjanost'],

        // Criminal law concepts
        'kazneno_djelo' => ['category' => 'criminal', 'definition' => 'Protupravna radnja kažnjiva zakonom'],
        'krivnja' => ['category' => 'criminal', 'definition' => 'Prijekornost počinitelja'],
        'kazna' => ['category' => 'criminal', 'definition' => 'Sankcija za kazneno djelo'],
        'zatvor' => ['category' => 'criminal', 'definition' => 'Kazna oduzimanja slobode'],
        'uvjetna_osuda' => ['category' => 'criminal', 'definition' => 'Odgoda izvršenja kazne'],
        'ubojstvo' => ['category' => 'criminal', 'definition' => 'Protupravno oduzimanje života'],
        'krađa' => ['category' => 'criminal', 'definition' => 'Protupravno oduzimanje tuđe stvari'],
        'prijevara' => ['category' => 'criminal', 'definition' => 'Obmana radi stjecanja koristi'],
        'oštećenik' => ['category' => 'criminal', 'definition' => 'Osoba kojoj je kaznenim djelom povrijeđeno pravo'],
        'optužnica' => ['category' => 'criminal', 'definition' => 'Akt kojim se pokreće kazneni progon'],
        'okrivljenik' => ['category' => 'criminal', 'definition' => 'Osoba protiv koje se vodi kazneni postupak'],
        'svjedočenje' => ['category' => 'criminal', 'definition' => 'Izjava svjedoka o činjenicama'],
        'dokazna_radnja' => ['category' => 'criminal', 'definition' => 'Radnja prikupljanja dokaza'],
        'oslobađajuća_presuda' => ['category' => 'criminal', 'definition' => 'Presuda kojom se optuženi oslobađa'],
        'novčana_kazna' => ['category' => 'criminal', 'definition' => 'Kazna plaćanja određenog iznosa'],
        'pokušaj' => ['category' => 'criminal', 'definition' => 'Nesvršeno kazneno djelo'],
        'suučesništvo' => ['category' => 'criminal', 'definition' => 'Zajedničko počinjenje kaznenog djela'],

        // Labor law concepts
        'radni_odnos' => ['category' => 'labor', 'definition' => 'Pravni odnos radnika i poslodavca'],
        'ugovor_o_radu' => ['category' => 'labor', 'definition' => 'Ugovor kojim se zasniva radni odnos'],
        'otkaz' => ['category' => 'labor', 'definition' => 'Raskid ugovora o radu'],
        'plaća' => ['category' => 'labor', 'definition' => 'Naknada za rad'],
        'otpremnina' => ['category' => 'labor', 'definition' => 'Naknada pri prestanku radnog odnosa'],
        'radno_vrijeme' => ['category' => 'labor', 'definition' => 'Vrijeme u kojem radnik obavlja rad'],
        'godišnji_odmor' => ['category' => 'labor', 'definition' => 'Plaćeno odsustvo s rada'],
        'bolovanje' => ['category' => 'labor', 'definition' => 'Odsustvo s rada zbog bolesti'],
        'radnik' => ['category' => 'labor', 'definition' => 'Osoba koja radi za poslodavca'],
        'poslodavac' => ['category' => 'labor', 'definition' => 'Osoba koja zapošljava radnike'],

        // Family law concepts
        'brak' => ['category' => 'family', 'definition' => 'Zakonom uređena zajednica muškarca i žene'],
        'razvod' => ['category' => 'family', 'definition' => 'Prestanak braka odlukom suda'],
        'uzdržavanje' => ['category' => 'family', 'definition' => 'Obveza osiguranja životnih potreba'],
        'skrbništvo' => ['category' => 'family', 'definition' => 'Zaštita osoba nesposobnih brinuti se same za sebe'],
        'roditeljska_skrb' => ['category' => 'family', 'definition' => 'Prava i dužnosti roditelja prema djetetu'],
        'bračna_stečevina' => ['category' => 'family', 'definition' => 'Imovina stečena za vrijeme braka'],
        'alimentacija' => ['category' => 'family', 'definition' => 'Novčana obveza uzdržavanja'],
        'posvojenje' => ['category' => 'family', 'definition' => 'Uspostavljanje roditeljskog odnosa'],
        'starateljstvo' => ['category' => 'family', 'definition' => 'Briga o maloljetniku ili lišeniku'],

        // Commercial law concepts
        'trgovačko_društvo' => ['category' => 'commercial', 'definition' => 'Pravna osoba za obavljanje gospodarske djelatnosti'],
        'dionice' => ['category' => 'commercial', 'definition' => 'Vrijednosni papiri koji predstavljaju udio u d.d.'],
        'stečaj' => ['category' => 'commercial', 'definition' => 'Postupak namirenja vjerovnika insolventnog dužnika'],
        'likvidacija' => ['category' => 'commercial', 'definition' => 'Postupak prestanka pravne osobe'],
        'dioničar' => ['category' => 'commercial', 'definition' => 'Vlasnik dionica društva'],
        'nadzorni_odbor' => ['category' => 'commercial', 'definition' => 'Tijelo nadzora nad upravljanjem društvom'],
        'uprava_društva' => ['category' => 'commercial', 'definition' => 'Tijelo koje vodi poslove društva'],
        'trgovački_registar' => ['category' => 'commercial', 'definition' => 'Javna knjiga trgovačkih subjekata'],
        'temeljni_kapital' => ['category' => 'commercial', 'definition' => 'Kapitalni ulog osnivača društva'],
        'mjenica' => ['category' => 'commercial', 'definition' => 'Vrijednosni papir plaćanja'],

        // Administrative law concepts
        'upravni_akt' => ['category' => 'administrative', 'definition' => 'Pojedinačni akt javne uprave'],
        'rješenje_upravno' => ['category' => 'administrative', 'definition' => 'Upravni akt kojim se odlučuje o pravima'],
        'inspekcijski_nadzor' => ['category' => 'administrative', 'definition' => 'Nadzor nad primjenom propisa'],
        'upravni_postupak' => ['category' => 'administrative', 'definition' => 'Postupak pred upravnim tijelom'],
        'žalba_upravna' => ['category' => 'administrative', 'definition' => 'Pravni lijek protiv upravnog akta'],
        'upravni_spor' => ['category' => 'administrative', 'definition' => 'Sudski postupak kontrole zakonitosti upravnih akata'],
        'dozvola' => ['category' => 'administrative', 'definition' => 'Akt kojim se dopušta određena djelatnost'],
        'koncesija' => ['category' => 'administrative', 'definition' => 'Odobrenje korištenja dobara od javnog interesa'],
    ];

    /**
     * Extract legal concepts from text
     *
     * @param string $text Text to analyze
     * @return array Array of ['concept_id' => string, 'name' => string, 'frequency' => int, ...]
     */
    public function extract(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $normalizedText = mb_strtolower($text);
        $found = [];

        foreach ($this->concepts as $conceptKey => $conceptData) {
            // Create base stem for matching (handle underscores as spaces)
            $baseTerm = str_replace('_', ' ', $conceptKey);

            // Create stem for Croatian inflection matching
            $stem = $this->createStem($baseTerm);

            // Create regex pattern that matches the stem with inflections
            $pattern = $this->createStemPattern($stem);

            // Count occurrences using regex for stem matching
            $frequency = preg_match_all($pattern, $normalizedText, $matches);

            if ($frequency > 0) {
                $name = $this->mbUcfirst($baseTerm);
                $found[] = [
                    'concept_id' => 'concept_' . md5($name),
                    'name' => $name,
                    'definition' => $conceptData['definition'],
                    'category' => $conceptData['category'],
                    'frequency' => $frequency,
                ];
            }
        }

        // Sort by frequency descending
        usort($found, fn($a, $b) => $b['frequency'] <=> $a['frequency']);

        return $found;
    }

    /**
     * Create a stem from a Croatian term by removing common endings
     */
    protected function createStem(string $term): string
    {
        // For multi-word terms, stem each word separately
        $words = explode(' ', $term);
        $stemmedWords = [];

        foreach ($words as $word) {
            // Remove common Croatian nominal endings to get the stem
            // This allows matching across all cases
            $stemmed = preg_replace('/[aoeiuAOEIU]$/', '', $word);

            // If we removed nothing and word ends in consonant + 'a', try removing last vowel
            if ($stemmed === $word && mb_strlen($word) > 3) {
                // For words like "kupoprodaja", create stem "kupoprodaj"
                $stemmed = preg_replace('/a$/', '', $word);
            }

            $stemmedWords[] = $stemmed;
        }

        return implode(' ', $stemmedWords);
    }

    /**
     * Create a regex pattern for matching Croatian word stems
     */
    protected function createStemPattern(string $stem): string
    {
        // Handle multi-word terms
        $words = explode(' ', $stem);
        $patterns = [];

        foreach ($words as $word) {
            // Escape special regex characters
            $escaped = preg_quote($word, '/');

            // Match the stem followed by any Croatian characters (inflection suffixes)
            // This allows "krađ" to match "krađa", "krađe", "krađi", "krađom", etc.
            $patterns[] = $escaped . '[a-zčćđšžA-ZČĆĐŠŽ]*';
        }

        // Use case-insensitive matching (i flag) for proper multibyte handling
        return '/\b' . implode('\s+', $patterns) . '\b/ui';
    }

    /**
     * Multibyte-safe ucfirst for Croatian characters
     */
    protected function mbUcfirst(string $string): string
    {
        $firstChar = mb_substr($string, 0, 1);
        $rest = mb_substr($string, 1);
        return mb_strtoupper($firstChar) . $rest;
    }

    /**
     * Get all concepts in the dictionary
     */
    public function getAllConcepts(): array
    {
        return $this->concepts;
    }

    /**
     * Get concepts by category
     */
    public function getConceptsByCategory(string $category): array
    {
        return array_filter(
            $this->concepts,
            fn($data) => $data['category'] === $category
        );
    }
}
