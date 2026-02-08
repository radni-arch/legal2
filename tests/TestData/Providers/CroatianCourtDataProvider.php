<?php

namespace Tests\TestData\Providers;

class CroatianCourtDataProvider
{
    private array $courts = [
        // Supreme Court
        [
            'name' => 'Vrhovni sud Republike Hrvatske',
            'level' => 'supreme',
            'region' => 'Zagreb',
            'address' => 'Trg Nikole Šubića Zrinskog 3, 10000 Zagreb',
        ],

        // High Courts
        [
            'name' => 'Visoki kazneni sud Republike Hrvatske',
            'level' => 'high',
            'region' => 'Zagreb',
            'address' => 'Gajeva 1a, 10000 Zagreb',
        ],
        [
            'name' => 'Visoki trgovački sud Republike Hrvatske',
            'level' => 'high',
            'region' => 'Zagreb',
            'address' => 'Gajeva 1a, 10000 Zagreb',
        ],
        [
            'name' => 'Visoki upravni sud Republike Hrvatske',
            'level' => 'high',
            'region' => 'Zagreb',
            'address' => 'Gajeva 1a, 10000 Zagreb',
        ],
        [
            'name' => 'Visoki prekršajni sud Republike Hrvatske',
            'level' => 'high',
            'region' => 'Zagreb',
            'address' => 'Gajeva 1a, 10000 Zagreb',
        ],

        // County Courts
        [
            'name' => 'Županijski sud u Osijeku',
            'level' => 'county',
            'region' => 'Osijek',
            'address' => 'Europska avenija 4, 31000 Osijek',
        ],
        [
            'name' => 'Županijski sud u Zagrebu',
            'level' => 'county',
            'region' => 'Zagreb',
            'address' => 'Građanska 35, 10000 Zagreb',
        ],
        [
            'name' => 'Županijski sud u Splitu',
            'level' => 'county',
            'region' => 'Split',
            'address' => 'Grškovićeva 4, 21000 Split',
        ],
        [
            'name' => 'Županijski sud u Zadru',
            'level' => 'county',
            'region' => 'Zadar',
            'address' => 'Ivana Gundulića 2, 23000 Zadar',
        ],
        [
            'name' => 'Županijski sud u Rijeci',
            'level' => 'county',
            'region' => 'Rijeka',
            'address' => 'Žrtava fašizma 6, 51000 Rijeka',
        ],
        [
            'name' => 'Županijski sud u Varaždinu',
            'level' => 'county',
            'region' => 'Varaždin',
            'address' => 'Aleja kralja Zvonimira 10, 42000 Varaždin',
        ],
        [
            'name' => 'Županijski sud u Velikoj Gorici',
            'level' => 'county',
            'region' => 'Zagreb',
            'address' => 'Zagrebačka 33, 10410 Velika Gorica',
        ],
        [
            'name' => 'Županijski sud u Bjelovaru',
            'level' => 'county',
            'region' => 'Bjelovar',
            'address' => 'Trg Eugena Kvaternika 10, 43000 Bjelovar',
        ],
        [
            'name' => 'Županijski sud u Dubrovniku',
            'level' => 'county',
            'region' => 'Dubrovnik',
            'address' => 'Branitelja Dubrovnika 35, 20000 Dubrovnik',
        ],
        [
            'name' => 'Županijski sud u Koprivnici',
            'level' => 'county',
            'region' => 'Koprivnica',
            'address' => 'Trg bana Josipa Jelačića 6, 48000 Koprivnica',
        ],
        [
            'name' => 'Županijski sud u Puli',
            'level' => 'county',
            'region' => 'Pula',
            'address' => 'Flanatička 16, 52100 Pula',
        ],

        // Municipal Courts
        [
            'name' => 'Općinski sud u Osijeku',
            'level' => 'municipal',
            'region' => 'Osijek',
            'address' => 'Evropska avenija 2a, 31000 Osijek',
        ],
        [
            'name' => 'Općinski sud u Zagrebu',
            'level' => 'municipal',
            'region' => 'Zagreb',
            'address' => 'Gruška 7, 10000 Zagreb',
        ],
        [
            'name' => 'Općinski sud u Splitu',
            'level' => 'municipal',
            'region' => 'Split',
            'address' => 'Grškovićeva 6, 21000 Split',
        ],
        [
            'name' => 'Općinski sud u Rijeci',
            'level' => 'municipal',
            'region' => 'Rijeka',
            'address' => 'Žrtava fašizma 8, 51000 Rijeka',
        ],
        [
            'name' => 'Općinski sud u Zadru',
            'level' => 'municipal',
            'region' => 'Zadar',
            'address' => 'Obala kralja Petra Krešimira IV 1, 23000 Zadar',
        ],
        [
            'name' => 'Općinski sud u Varaždinu',
            'level' => 'municipal',
            'region' => 'Varaždin',
            'address' => 'Aleja kralja Zvonimira 8, 42000 Varaždin',
        ],
        [
            'name' => 'Općinski sud u Vukovaru',
            'level' => 'municipal',
            'region' => 'Vukovar',
            'address' => 'J.J. Strossmayera 33, 32000 Vukovar',
        ],
        [
            'name' => 'Općinski sud u Slavonskom Brodu',
            'level' => 'municipal',
            'region' => 'Slavonski Brod',
            'address' => 'Ante Starčevića 15, 35000 Slavonski Brod',
        ],
        [
            'name' => 'Općinski sud u Sisku',
            'level' => 'municipal',
            'region' => 'Sisak',
            'address' => 'Rimska 35, 44000 Sisak',
        ],
        [
            'name' => 'Općinski sud u Karlovcu',
            'level' => 'municipal',
            'region' => 'Karlovac',
            'address' => 'Trg bana Jelačića 7, 47000 Karlovac',
        ],
    ];

    public function getCourtsByLevel(string $level): array
    {
        return array_values(array_filter($this->courts, fn ($court) => $court['level'] === $level));
    }

    public function getCourtsByRegion(string $region): array
    {
        return array_values(array_filter($this->courts, function ($court) use ($region) {
            return stripos($court['region'], $region) !== false || stripos($court['name'], $region) !== false;
        }));
    }

    public function getRandomCourt(?string $level = null): array
    {
        $courts = $level ? $this->getCourtsByLevel($level) : $this->courts;

        if (empty($courts)) {
            throw new \InvalidArgumentException("No courts found for level: {$level}");
        }

        return $courts[array_rand($courts)];
    }

    public function getAllCourts(): array
    {
        return $this->courts;
    }
}
