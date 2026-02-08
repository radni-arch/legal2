<?php

namespace Tests\TestData\Providers;

class RegionalDataProvider
{
    private array $regions = [
        'Osijek-Baranja' => [
            'name' => 'Osijek-Baranja',
            'county' => 'Osječko-baranjska županija',
            'courts' => [
                'Županijski sud u Osijeku',
                'Općinski sud u Osijeku',
                'Općinski sud u Vukovaru',
            ],
            'prosecutors' => [
                'Županijsko državno odvjetništvo u Osijeku',
                'Općinsko državno odvjetništvo u Osijeku',
            ],
            'population' => 285002,
        ],
        'Zagreb' => [
            'name' => 'Zagreb',
            'county' => 'Grad Zagreb',
            'courts' => [
                'Vrhovni sud Republike Hrvatske',
                'Visoki kazneni sud Republike Hrvatske',
                'Visoki trgovački sud Republike Hrvatske',
                'Visoki upravni sud Republike Hrvatske',
                'Visoki prekršajni sud Republike Hrvatske',
                'Županijski sud u Zagrebu',
                'Općinski sud u Zagrebu',
            ],
            'prosecutors' => [
                'Državno odvjetništvo Republike Hrvatske',
                'Županijsko državno odvjetništvo u Zagrebu',
                'Općinsko državno odvjetništvo u Zagrebu',
            ],
            'population' => 806341,
        ],
        'Split-Dalmatia' => [
            'name' => 'Split-Dalmatia',
            'county' => 'Splitsko-dalmatinska županija',
            'courts' => [
                'Županijski sud u Splitu',
                'Općinski sud u Splitu',
            ],
            'prosecutors' => [
                'Županijsko državno odvjetništvo u Splitu',
                'Općinsko državno odvjetništvo u Splitu',
            ],
            'population' => 454798,
        ],
        'Zadar' => [
            'name' => 'Zadar',
            'county' => 'Zadarska županija',
            'courts' => [
                'Županijski sud u Zadru',
                'Općinski sud u Zadru',
            ],
            'prosecutors' => [
                'Županijsko državno odvjetništvo u Zadru',
                'Općinsko državno odvjetništvo u Zadru',
            ],
            'population' => 170017,
        ],
        'Rijeka' => [
            'name' => 'Rijeka',
            'county' => 'Primorsko-goranska županija',
            'courts' => [
                'Županijski sud u Rijeci',
                'Općinski sud u Rijeci',
            ],
            'prosecutors' => [
                'Županijsko državno odvjetništvo u Rijeci',
                'Općinsko državno odvjetništvo u Rijeci',
            ],
            'population' => 296195,
        ],
        'Varaždin' => [
            'name' => 'Varaždin',
            'county' => 'Varaždinska županija',
            'courts' => [
                'Županijski sud u Varaždinu',
                'Općinski sud u Varaždinu',
            ],
            'prosecutors' => [
                'Županijsko državno odvjetništvo u Varaždinu',
                'Općinsko državno odvjetništvo u Varaždinu',
            ],
            'population' => 175951,
        ],
        'Slavonski Brod' => [
            'name' => 'Slavonski Brod',
            'county' => 'Brodsko-posavska županija',
            'courts' => [
                'Općinski sud u Slavonskom Brodu',
            ],
            'prosecutors' => [
                'Općinsko državno odvjetništvo u Slavonskom Brodu',
            ],
            'population' => 158575,
        ],
        'Dubrovnik' => [
            'name' => 'Dubrovnik',
            'county' => 'Dubrovačko-neretvanska županija',
            'courts' => [
                'Županijski sud u Dubrovniku',
            ],
            'prosecutors' => [
                'Županijsko državno odvjetništvo u Dubrovniku',
            ],
            'population' => 122783,
        ],
    ];

    public function getAllRegions(): array
    {
        return array_keys($this->regions);
    }

    public function getRegionData(string $region): array
    {
        if (! isset($this->regions[$region])) {
            throw new \InvalidArgumentException("Unknown region: {$region}");
        }

        return $this->regions[$region];
    }

    public function getRegionalCourts(string $region): array
    {
        $data = $this->getRegionData($region);

        return $data['courts'];
    }

    public function getRegionalProsecutors(string $region): array
    {
        $data = $this->getRegionData($region);

        return $data['prosecutors'];
    }
}
