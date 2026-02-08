<?php

namespace Tests\TestData\Providers;

class LegalCitationProvider
{
    private array $zkpArticles = [
        [
            'law' => 'ZKP',
            'article' => '12',
            'citation' => 'ZKP Čl. 12',
            'description' => 'Pravo na branitelja - Osumnjičenik i optuženik imaju pravo uzeti branitelja od početka kaznenog postupka.',
        ],
        [
            'law' => 'ZKP',
            'article' => '177',
            'citation' => 'ZKP Čl. 177',
            'description' => 'Dokazi - Utvrđivanje odlučnih činjenica na temelju dokaza izvedenih na glavnoj raspravi.',
        ],
        [
            'law' => 'ZKP',
            'article' => '1',
            'citation' => 'ZKP Čl. 1',
            'description' => 'Primjena Zakona o kaznenom postupku - Određuje se postupak za kaznena djela.',
        ],
        [
            'law' => 'ZKP',
            'article' => '191',
            'citation' => 'ZKP Čl. 191',
            'description' => 'Ispitivanje svjedoka - Postupak ispitivanja svjedoka na glavnoj raspravi.',
        ],
        [
            'law' => 'ZKP',
            'article' => '206',
            'citation' => 'ZKP Čl. 206',
            'description' => 'Vještačenje - Nalaz i mišljenje vještaka kao dokaz u kaznenom postupku.',
        ],
        [
            'law' => 'ZKP',
            'article' => '64',
            'citation' => 'ZKP Čl. 64',
            'description' => 'Pritvor - Mjera osiguranja prisutnosti okrivljenika u kaznenom postupku.',
        ],
        [
            'law' => 'ZKP',
            'article' => '157',
            'citation' => 'ZKP Čl. 157',
            'description' => 'Obustava istrage - Državni odvjetnik obustavlja istragu ako ne postoje osnovi sumnje.',
        ],
        [
            'law' => 'ZKP',
            'article' => '43',
            'citation' => 'ZKP Čl. 43',
            'description' => 'Isključenje sudaca - Razlozi za isključenje sudaca iz postupka.',
        ],
    ];

    private array $kzArticles = [
        [
            'law' => 'KZ',
            'article' => '87',
            'citation' => 'KZ Čl. 87',
            'description' => 'Nedjela protiv života - Kaznena djela koja se odnose na ugrozavanje i oduzimanje ljudskog života.',
        ],
        [
            'law' => 'KZ',
            'article' => '190',
            'citation' => 'KZ Čl. 190',
            'description' => 'Krađa - Oduzimanje tuđe pokretne stvari s ciljem protupravnog prisvajanja.',
        ],
        [
            'law' => 'KZ',
            'article' => '105',
            'citation' => 'KZ Čl. 105',
            'description' => 'Ubojstvo - Oduzimanje života drugom čovjeku kazneno djelo s kaznom zatvora od pet godina.',
        ],
        [
            'law' => 'KZ',
            'article' => '106',
            'citation' => 'KZ Čl. 106',
            'description' => 'Teško ubojstvo - Kvalificirani oblik ubojstva s težom kaznom zatvora.',
        ],
        [
            'law' => 'KZ',
            'article' => '228',
            'citation' => 'KZ Čl. 228',
            'description' => 'Razbojništvo - Protupravno oduzimanje tuđe pokretne stvari silom ili prijetnjom napada.',
        ],
        [
            'law' => 'KZ',
            'article' => '117',
            'citation' => 'KZ Čl. 117',
            'description' => 'Teška tjelesna ozljeda - Nanošenje teške ozljede koja ugrožava život ili narušava zdravlje.',
        ],
        [
            'law' => 'KZ',
            'article' => '140',
            'citation' => 'KZ Čl. 140',
            'description' => 'Silovanje - Prisiljavanje na spolni odnošaj silom ili prijetnjom napada.',
        ],
        [
            'law' => 'KZ',
            'article' => '31',
            'citation' => 'KZ Čl. 31',
            'description' => 'Nužna obrana - Isključenje protupravnosti djela počinjenog u obrani.',
        ],
    ];

    private array $ustavArticles = [
        [
            'law' => 'Ustav RH',
            'article' => '29',
            'citation' => 'Ustav RH Čl. 29',
            'description' => 'Pravo na pravično suđenje - Svakome se jamči pravo na pravično i javno suđenje pred neovisnim sudom.',
        ],
        [
            'law' => 'Ustav RH',
            'article' => '21',
            'citation' => 'Ustav RH Čl. 21',
            'description' => 'Nezavisnost sudova - Sudovi su neovisni i samostalni u svom radu.',
        ],
        [
            'law' => 'Ustav RH',
            'article' => '25',
            'citation' => 'Ustav RH Čl. 25',
            'description' => 'Sloboda i prava čovjeka - Jamstvo sloboda i prava čovjeka i građanina.',
        ],
        [
            'law' => 'Ustav RH',
            'article' => '26',
            'citation' => 'Ustav RH Čl. 26',
            'description' => 'Zaštita osobnih podataka - Pravo na zaštitu osobnih podataka.',
        ],
        [
            'law' => 'Ustav RH',
            'article' => '31',
            'citation' => 'Ustav RH Čl. 31',
            'description' => 'Prezumpcija nevinosti - Svatko se smatra nevinim dok se mu sudskom presudom ne utvrdi krivnja.',
        ],
        [
            'law' => 'Ustav RH',
            'article' => '23',
            'citation' => 'Ustav RH Čl. 23',
            'description' => 'Zabrana mučenja - Nitko ne smije biti podvrgnut mučenju ili ponižavajućem postupanju.',
        ],
        [
            'law' => 'Ustav RH',
            'article' => '35',
            'citation' => 'Ustav RH Čl. 35',
            'description' => 'Sloboda kretanja - Svakome se jamči sloboda kretanja i prebivališta.',
        ],
    ];

    public function getZKPArticles(): array
    {
        return $this->zkpArticles;
    }

    public function getKZArticles(): array
    {
        return $this->kzArticles;
    }

    public function getUstavArticles(): array
    {
        return $this->ustavArticles;
    }

    public function getRandomCitation(?string $law = null): array
    {
        if ($law === null) {
            $allArticles = array_merge($this->zkpArticles, $this->kzArticles, $this->ustavArticles);

            return $allArticles[array_rand($allArticles)];
        }

        $articles = match ($law) {
            'ZKP' => $this->zkpArticles,
            'KZ' => $this->kzArticles,
            'Ustav RH' => $this->ustavArticles,
            default => throw new \InvalidArgumentException("Unknown law: {$law}"),
        };

        if (empty($articles)) {
            throw new \InvalidArgumentException("No articles found for law: {$law}");
        }

        return $articles[array_rand($articles)];
    }
}
