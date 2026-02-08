<?php

namespace App\Services;

/**
 * Resolves institution initials to full names
 * 
 * Croatian police and judicial institutions use standardized abbreviations
 * in court documents. This class translates them to full names.
 * 
 * Format patterns:
 * - P.P.X.Y. = Policijska postaja X Y
 * - P.U.X. = Policijska uprava X
 * - S.O.K.O. = Sektor za organizirani kriminalitet Osijek (ili druga lokacija)
 */
class InstitutionInitialsResolver
{
    /**
     * Known institution mappings (exact matches)
     * Key: normalized initials (uppercase, with dots)
     */
    const KNOWN_INSTITUTIONS = [
        // SOKO - Organized Crime Units
        'S.O.K.O.' => 'Sektor za organizirani kriminalitet Osijek',
        'SOKO' => 'Sektor za organizirani kriminalitet Osijek',
        'S.O.K.Z.' => 'Sektor za organizirani kriminalitet Zagreb',
        'SOKZ' => 'Sektor za organizirani kriminalitet Zagreb',
        'S.O.K.S.' => 'Sektor za organizirani kriminalitet Split',
        'SOKS' => 'Sektor za organizirani kriminalitet Split',
        'S.O.K.R.' => 'Sektor za organizirani kriminalitet Rijeka',
        'SOKR' => 'Sektor za organizirani kriminalitet Rijeka',
        
        // PNUSKOK
        'P.N.U.S.K.O.K.' => 'Policijski nacionalni ured za suzbijanje korupcije i organiziranog kriminaliteta',
        'PNUSKOK' => 'Policijski nacionalni ured za suzbijanje korupcije i organiziranog kriminaliteta',
        
        // USKOK
        'U.S.K.O.K.' => 'Ured za suzbijanje korupcije i organiziranog kriminaliteta',
        'USKOK' => 'Ured za suzbijanje korupcije i organiziranog kriminaliteta',
        
        // MUP
        'M.U.P.' => 'Ministarstvo unutarnjih poslova',
        'MUP' => 'Ministarstvo unutarnjih poslova',
        'M.U.P.R.H.' => 'Ministarstvo unutarnjih poslova Republike Hrvatske',
        'MUPRH' => 'Ministarstvo unutarnjih poslova Republike Hrvatske',
        
        // Carinska uprava
        'C.U.' => 'Carinska uprava',
        'C.U.R.H.' => 'Carinska uprava Republike Hrvatske',
        
        // Državno odvjetništvo
        'D.O.' => 'Državno odvjetništvo',
        'D.O.R.H.' => 'Državno odvjetništvo Republike Hrvatske',
        'Ž.D.O.' => 'Županijsko državno odvjetništvo',
        'O.D.O.' => 'Općinsko državno odvjetništvo',
        
        // Specijalne jedinice
        'S.J.P.' => 'Specijalna jedinica policije',
        'A.T.J.L.' => 'Antiteroristička jedinica Lučko',
        'ATJ' => 'Antiteroristička jedinica',
        
        // Granična policija
        'G.P.' => 'Granična policija',
        'G.K.P.' => 'Granična kriminalistička policija',
        
        // Prometna policija
        'P.P.P.' => 'Postaja prometne policije',
        
        // Interventna policija
        'I.P.' => 'Interventna policija',
        'I.J.P.' => 'Interventna jedinica policije',
    ];
    
    /**
     * Police headquarters (Policijske uprave) by region
     */
    const POLICE_HEADQUARTERS = [
        'P.U.O.S.' => 'Policijska uprava osječko-baranjska',
        'P.U.O.B.' => 'Policijska uprava osječko-baranjska',
        'PUOS' => 'Policijska uprava osječko-baranjska',
        'PUOB' => 'Policijska uprava osječko-baranjska',
        
        'P.U.Z.' => 'Policijska uprava zagrebačka',
        'P.U.ZG.' => 'Policijska uprava zagrebačka',
        'PUZ' => 'Policijska uprava zagrebačka',
        'PUZG' => 'Policijska uprava zagrebačka',
        
        'P.U.S.' => 'Policijska uprava splitsko-dalmatinska',
        'P.U.S.D.' => 'Policijska uprava splitsko-dalmatinska',
        'PUS' => 'Policijska uprava splitsko-dalmatinska',
        'PUSD' => 'Policijska uprava splitsko-dalmatinska',
        
        'P.U.R.' => 'Policijska uprava primorsko-goranska',
        'P.U.P.G.' => 'Policijska uprava primorsko-goranska',
        'PUR' => 'Policijska uprava primorsko-goranska',
        'PUPG' => 'Policijska uprava primorsko-goranska',
        
        'P.U.V.' => 'Policijska uprava varaždinska',
        'P.U.VŽ.' => 'Policijska uprava varaždinska',
        'PUV' => 'Policijska uprava varaždinska',
        'PUVŽ' => 'Policijska uprava varaždinska',
        
        'P.U.K.' => 'Policijska uprava karlovačka',
        'P.U.KA.' => 'Policijska uprava karlovačka',
        'PUK' => 'Policijska uprava karlovačka',
        
        'P.U.B.' => 'Policijska uprava bjelovarsko-bilogorska',
        'P.U.B.B.' => 'Policijska uprava bjelovarsko-bilogorska',
        'PUB' => 'Policijska uprava bjelovarsko-bilogorska',
        'PUBB' => 'Policijska uprava bjelovarsko-bilogorska',
        
        'P.U.V.P.' => 'Policijska uprava vukovarsko-srijemska',
        'P.U.V.S.' => 'Policijska uprava vukovarsko-srijemska',
        'PUVP' => 'Policijska uprava vukovarsko-srijemska',
        'PUVS' => 'Policijska uprava vukovarsko-srijemska',
        
        'P.U.D.' => 'Policijska uprava dubrovačko-neretvanska',
        'P.U.D.N.' => 'Policijska uprava dubrovačko-neretvanska',
        'PUD' => 'Policijska uprava dubrovačko-neretvanska',
        'PUDN' => 'Policijska uprava dubrovačko-neretvanska',
        
        'P.U.Š.' => 'Policijska uprava šibensko-kninska',
        'P.U.Š.K.' => 'Policijska uprava šibensko-kninska',
        'PUŠ' => 'Policijska uprava šibensko-kninska',
        'PUŠK' => 'Policijska uprava šibensko-kninska',
        
        'P.U.Z.D.' => 'Policijska uprava zadarska',
        'PUZD' => 'Policijska uprava zadarska',
        
        'P.U.I.' => 'Policijska uprava istarska',
        'PUI' => 'Policijska uprava istarska',
        
        'P.U.M.' => 'Policijska uprava međimurska',
        'PUM' => 'Policijska uprava međimurska',
        
        'P.U.S.B.' => 'Policijska uprava brodsko-posavska',
        'PUSB' => 'Policijska uprava brodsko-posavska',
        
        'P.U.P.Ž.' => 'Policijska uprava požeško-slavonska',
        'PUPŽ' => 'Policijska uprava požeško-slavonska',
        
        'P.U.V.T.' => 'Policijska uprava virovitičko-podravska',
        'PUVT' => 'Policijska uprava virovitičko-podravska',
        
        'P.U.K.Ž.' => 'Policijska uprava krapinsko-zagorska',
        'PUKŽ' => 'Policijska uprava krapinsko-zagorska',
        
        'P.U.S.M.' => 'Policijska uprava sisačko-moslavačka',
        'PUSM' => 'Policijska uprava sisačko-moslavačka',
        
        'P.U.L.S.' => 'Policijska uprava ličko-senjska',
        'PULS' => 'Policijska uprava ličko-senjska',
        
        'P.U.K.K.' => 'Policijska uprava koprivničko-križevačka',
        'PUKK' => 'Policijska uprava koprivničko-križevačka',
    ];
    
    /**
     * Police stations (Policijske postaje) - common ones
     */
    const POLICE_STATIONS = [
        // Osječko-baranjska županija
        'P.P.O.' => 'Policijska postaja Osijek',
        'PPO' => 'Policijska postaja Osijek',
        'P.P.B.M.' => 'Policijska postaja Beli Manastir',
        'PPBM' => 'Policijska postaja Beli Manastir',
        'P.P.Đ.' => 'Policijska postaja Đakovo',
        'PPĐ' => 'Policijska postaja Đakovo',
        'P.P.V.' => 'Policijska postaja Valpovo',
        'PPV' => 'Policijska postaja Valpovo',
        'P.P.N.' => 'Policijska postaja Našice',
        'PPN' => 'Policijska postaja Našice',
        'P.P.D.M.' => 'Policijska postaja Donji Miholjac',
        'PPDM' => 'Policijska postaja Donji Miholjac',
        
        // Zagreb
        'P.P.Z.' => 'Policijska postaja Zagreb',
        'PPZ' => 'Policijska postaja Zagreb',
        'I.P.P.Z.' => 'I. policijska postaja Zagreb',
        'II.P.P.Z.' => 'II. policijska postaja Zagreb',
        'III.P.P.Z.' => 'III. policijska postaja Zagreb',
        'IV.P.P.Z.' => 'IV. policijska postaja Zagreb',
        'V.P.P.Z.' => 'V. policijska postaja Zagreb',
        'VI.P.P.Z.' => 'VI. policijska postaja Zagreb',
        'VII.P.P.Z.' => 'VII. policijska postaja Zagreb',
        'VIII.P.P.Z.' => 'VIII. policijska postaja Zagreb',
        
        // Split
        'P.P.S.' => 'Policijska postaja Split',
        'PPS' => 'Policijska postaja Split',
        'I.P.P.S.' => 'I. policijska postaja Split',
        'II.P.P.S.' => 'II. policijska postaja Split',
        'III.P.P.S.' => 'III. policijska postaja Split',
        
        // Rijeka
        'P.P.R.' => 'Policijska postaja Rijeka',
        'PPR' => 'Policijska postaja Rijeka',
        'I.P.P.R.' => 'I. policijska postaja Rijeka',
        'II.P.P.R.' => 'II. policijska postaja Rijeka',
        
        // Ostali veći gradovi
        'P.P.VŽ.' => 'Policijska postaja Varaždin',
        'PPVŽ' => 'Policijska postaja Varaždin',
        'P.P.KA.' => 'Policijska postaja Karlovac',
        'PPKA' => 'Policijska postaja Karlovac',
        'P.P.SI.' => 'Policijska postaja Sisak',
        'PPSI' => 'Policijska postaja Sisak',
        'P.P.SB.' => 'Policijska postaja Slavonski Brod',
        'PPSB' => 'Policijska postaja Slavonski Brod',
        'P.P.VU.' => 'Policijska postaja Vukovar',
        'PPVU' => 'Policijska postaja Vukovar',
        'P.P.VK.' => 'Policijska postaja Vinkovci',
        'PPVK' => 'Policijska postaja Vinkovci',
        'P.P.ZD.' => 'Policijska postaja Zadar',
        'PPZD' => 'Policijska postaja Zadar',
        'P.P.ŠI.' => 'Policijska postaja Šibenik',
        'PPŠI' => 'Policijska postaja Šibenik',
        'P.P.DU.' => 'Policijska postaja Dubrovnik',
        'PPDU' => 'Policijska postaja Dubrovnik',
        'P.P.PU.' => 'Policijska postaja Pula',
        'PPPU' => 'Policijska postaja Pula',
        'P.P.BJ.' => 'Policijska postaja Bjelovar',
        'PPBJ' => 'Policijska postaja Bjelovar',
        'P.P.ČK.' => 'Policijska postaja Čakovec',
        'PPČK' => 'Policijska postaja Čakovec',
        'P.P.PŽ.' => 'Policijska postaja Požega',
        'PPPŽ' => 'Policijska postaja Požega',
        'P.P.GS.' => 'Policijska postaja Gospić',
        'PPGS' => 'Policijska postaja Gospić',
        'P.P.VT.' => 'Policijska postaja Virovitica',
        'PPVT' => 'Policijska postaja Virovitica',
        'P.P.KŽ.' => 'Policijska postaja Krapina',
        'PPKŽ' => 'Policijska postaja Krapina',
        'P.P.ZL.' => 'Policijska postaja Zlatar',
        'PPZL' => 'Policijska postaja Zlatar',
        'P.P.MA.' => 'Policijska postaja Makarska',
        'PPMA' => 'Policijska postaja Makarska',
        'P.P.ME.' => 'Policijska postaja Metković',
        'PPME' => 'Policijska postaja Metković',
    ];
    
    /**
     * City name mappings for constructing full names
     */
    const CITY_ABBREVIATIONS = [
        'O' => 'Osijek',
        'OS' => 'Osijek',
        'Z' => 'Zagreb',
        'ZG' => 'Zagreb',
        'S' => 'Split',
        'ST' => 'Split',
        'R' => 'Rijeka',
        'RI' => 'Rijeka',
        'V' => 'Varaždin',
        'VŽ' => 'Varaždin',
        'K' => 'Karlovac',
        'KA' => 'Karlovac',
        'B' => 'Bjelovar',
        'BJ' => 'Bjelovar',
        'D' => 'Dubrovnik',
        'DU' => 'Dubrovnik',
        'Š' => 'Šibenik',
        'ŠI' => 'Šibenik',
        'P' => 'Pula',
        'PU' => 'Pula',
        'I' => 'Istra',
        'M' => 'Međimurje',
        'ČK' => 'Čakovec',
        'SI' => 'Sisak',
        'SB' => 'Slavonski Brod',
        'VU' => 'Vukovar',
        'VK' => 'Vinkovci',
        'ZD' => 'Zadar',
        'PŽ' => 'Požega',
        'VT' => 'Virovitica',
        'GS' => 'Gospić',
        'KŽ' => 'Krapina',
        'ZL' => 'Zlatar',
        'N' => 'Našice',
        'Đ' => 'Đakovo',
        'BM' => 'Beli Manastir',
        'DM' => 'Donji Miholjac',
        'MA' => 'Makarska',
        'ME' => 'Metković',
    ];
    
    /**
     * Resolve initials to full institution name
     *
     * @param string $initials e.g., "P.P.B.M." or "PPBM"
     * @return array{name: string|null, type: string|null, confidence: string}
     */
    public function resolve(string $initials): array
    {
        $normalized = $this->normalize($initials);
        
        // Try exact match first
        $exactMatch = $this->findExactMatch($normalized);
        if ($exactMatch) {
            return [
                'name' => $exactMatch,
                'type' => $this->determineType($exactMatch),
                'confidence' => 'high',
            ];
        }
        
        // Try pattern-based resolution
        $patternMatch = $this->resolveByPattern($normalized, $initials);
        if ($patternMatch) {
            return $patternMatch;
        }
        
        // Check if it looks like institution initials
        if ($this->looksLikeInstitution($initials)) {
            return [
                'name' => null,
                'type' => 'unknown_institution',
                'confidence' => 'low',
            ];
        }
        
        // Probably a person's initials
        return [
            'name' => null,
            'type' => 'person',
            'confidence' => 'medium',
        ];
    }
    
    /**
     * Normalize initials for comparison
     */
    protected function normalize(string $initials): string
    {
        // Remove spaces
        $normalized = preg_replace('/\s+/', '', $initials);
        
        // Uppercase
        $normalized = mb_strtoupper($normalized);
        
        return $normalized;
    }
    
    /**
     * Find exact match in known institutions
     */
    protected function findExactMatch(string $normalized): ?string
    {
        // With dots
        $withDots = $normalized;
        
        // Without dots
        $withoutDots = str_replace('.', '', $normalized);
        
        // Check all mappings
        $allMappings = array_merge(
            self::KNOWN_INSTITUTIONS,
            self::POLICE_HEADQUARTERS,
            self::POLICE_STATIONS
        );
        
        foreach ($allMappings as $key => $value) {
            $normalizedKey = $this->normalize($key);
            if ($normalizedKey === $withDots || $normalizedKey === $withoutDots) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Resolve by analyzing pattern
     */
    protected function resolveByPattern(string $normalized, string $original): ?array
    {
        $withoutDots = str_replace('.', '', $normalized);
        
        // Pattern: P.P.X.Y. = Policijska postaja X Y
        if (preg_match('/^PP(.+)$/', $withoutDots, $match)) {
            $cityCode = $match[1];
            $city = $this->resolveCity($cityCode);
            
            if ($city) {
                return [
                    'name' => "Policijska postaja {$city}",
                    'type' => 'police_station',
                    'confidence' => 'medium',
                ];
            }
        }
        
        // Pattern: P.U.X. = Policijska uprava X
        if (preg_match('/^PU(.+)$/', $withoutDots, $match)) {
            $regionCode = $match[1];
            $region = $this->resolveRegion($regionCode);
            
            if ($region) {
                return [
                    'name' => "Policijska uprava {$region}",
                    'type' => 'police_headquarters',
                    'confidence' => 'medium',
                ];
            }
        }
        
        // Pattern: S.O.K.X. = SOKO X
        if (preg_match('/^SOK(.+)$/', $withoutDots, $match)) {
            $cityCode = $match[1];
            $city = $this->resolveCity($cityCode);
            
            if ($city) {
                return [
                    'name' => "Sektor za organizirani kriminalitet {$city}",
                    'type' => 'organized_crime_unit',
                    'confidence' => 'medium',
                ];
            }
        }
        
        // Pattern: K.P.X. = Kriminalistička policija X
        if (preg_match('/^KP(.*)$/', $withoutDots, $match)) {
            $cityCode = $match[1] ?? '';
            $city = $cityCode ? $this->resolveCity($cityCode) : null;
            
            return [
                'name' => $city ? "Kriminalistička policija {$city}" : "Kriminalistička policija",
                'type' => 'criminal_police',
                'confidence' => $city ? 'medium' : 'low',
            ];
        }
        
        return null;
    }
    
    /**
     * Resolve city code to city name
     */
    protected function resolveCity(string $code): ?string
    {
        $code = mb_strtoupper($code);
        return self::CITY_ABBREVIATIONS[$code] ?? null;
    }
    
    /**
     * Resolve region code to region name (for police headquarters)
     */
    protected function resolveRegion(string $code): ?string
    {
        $regionMappings = [
            'OS' => 'osječko-baranjska',
            'OB' => 'osječko-baranjska',
            'Z' => 'zagrebačka',
            'ZG' => 'zagrebačka',
            'S' => 'splitsko-dalmatinska',
            'SD' => 'splitsko-dalmatinska',
            'R' => 'primorsko-goranska',
            'PG' => 'primorsko-goranska',
            'V' => 'varaždinska',
            'VŽ' => 'varaždinska',
            'K' => 'karlovačka',
            'KA' => 'karlovačka',
            'B' => 'bjelovarsko-bilogorska',
            'BB' => 'bjelovarsko-bilogorska',
            'VP' => 'vukovarsko-srijemska',
            'VS' => 'vukovarsko-srijemska',
            'D' => 'dubrovačko-neretvanska',
            'DN' => 'dubrovačko-neretvanska',
            'Š' => 'šibensko-kninska',
            'ŠK' => 'šibensko-kninska',
            'ZD' => 'zadarska',
            'I' => 'istarska',
            'M' => 'međimurska',
            'SB' => 'brodsko-posavska',
            'PŽ' => 'požeško-slavonska',
            'VT' => 'virovitičko-podravska',
            'KŽ' => 'krapinsko-zagorska',
            'SM' => 'sisačko-moslavačka',
            'LS' => 'ličko-senjska',
            'KK' => 'koprivničko-križevačka',
        ];
        
        $code = mb_strtoupper($code);
        return $regionMappings[$code] ?? null;
    }
    
    /**
     * Determine institution type from full name
     */
    protected function determineType(string $name): string
    {
        if (stripos($name, 'postaja') !== false) {
            return 'police_station';
        }
        if (stripos($name, 'uprava') !== false) {
            return 'police_headquarters';
        }
        if (stripos($name, 'SOKO') !== false || stripos($name, 'organizirani kriminalitet') !== false) {
            return 'organized_crime_unit';
        }
        if (stripos($name, 'USKOK') !== false || stripos($name, 'korupcij') !== false) {
            return 'anti_corruption_unit';
        }
        if (stripos($name, 'PNUSKOK') !== false) {
            return 'national_police_unit';
        }
        if (stripos($name, 'odvjetništvo') !== false) {
            return 'prosecutors_office';
        }
        if (stripos($name, 'carinska') !== false) {
            return 'customs';
        }
        if (stripos($name, 'kriminalistička') !== false) {
            return 'criminal_police';
        }
        if (stripos($name, 'granična') !== false) {
            return 'border_police';
        }
        if (stripos($name, 'prometna') !== false) {
            return 'traffic_police';
        }
        if (stripos($name, 'interventna') !== false || stripos($name, 'specijalna') !== false) {
            return 'special_unit';
        }
        if (stripos($name, 'Ministarstvo') !== false) {
            return 'ministry';
        }
        
        return 'other';
    }
    
    /**
     * Check if initials look like an institution abbreviation
     */
    protected function looksLikeInstitution(string $initials): bool
    {
        // Institution patterns:
        // - Starts with P. (Policijska), S. (Sektor), M. (Ministarstvo), etc.
        // - Has 3+ letters/dots
        // - Doesn't look like a person's initials (usually 2-3 letters, no patterns)
        
        $cleaned = preg_replace('/[.\s]/', '', $initials);
        
        // Very short - likely person
        if (mb_strlen($cleaned) <= 2) {
            return false;
        }
        
        // Known institution prefixes
        $institutionPrefixes = ['PP', 'PU', 'SOK', 'KP', 'GP', 'MUP', 'DO', 'CU', 'SJ', 'IP'];
        
        foreach ($institutionPrefixes as $prefix) {
            if (stripos($cleaned, $prefix) === 0) {
                return true;
            }
        }
        
        // Has many dots - likely institution
        $dotCount = substr_count($initials, '.');
        if ($dotCount >= 3) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Resolve multiple initials from a case
     *
     * @param array $parties Array of party names
     * @return array
     */
    public function resolveAll(array $parties): array
    {
        $results = [];
        
        foreach ($parties as $party) {
            $resolution = $this->resolve($party);
            $results[$party] = $resolution;
        }
        
        return $results;
    }
    
    /**
     * Get all known institution mappings (for reference/debugging)
     */
    public function getAllMappings(): array
    {
        return [
            'known_institutions' => self::KNOWN_INSTITUTIONS,
            'police_headquarters' => self::POLICE_HEADQUARTERS,
            'police_stations' => self::POLICE_STATIONS,
            'city_abbreviations' => self::CITY_ABBREVIATIONS,
        ];
    }
    
    /**
     * Add custom mapping (for runtime additions)
     */
    protected array $customMappings = [];
    
    public function addMapping(string $initials, string $fullName): void
    {
        $this->customMappings[$this->normalize($initials)] = $fullName;
    }
    
    /**
     * Learn new mapping from context
     * 
     * If a document contains both initials and full name, this can learn the mapping
     */
    public function learnFromText(string $text): array
    {
        $learned = [];
        
        // Pattern: "P.P.X.Y." followed by "Policijska postaja X Y"
        preg_match_all(
            '/([A-ZČĆŽŠĐ]\.)+\s*[-–]\s*([A-ZČĆŽŠĐ][a-zčćžšđ]+(?:\s+[A-ZČĆŽŠĐA-Za-zčćžšđ]+)*)/u',
            $text,
            $matches,
            PREG_SET_ORDER
        );
        
        foreach ($matches as $match) {
            $initials = trim($match[0]);
            $parts = explode('-', $initials, 2);
            if (count($parts) === 2) {
                $abbrev = trim($parts[0]);
                $full = trim($parts[1]);
                
                if (mb_strlen($full) > 5) { // Reasonable full name length
                    $learned[$abbrev] = $full;
                    $this->addMapping($abbrev, $full);
                }
            }
        }
        
        return $learned;
    }
}
