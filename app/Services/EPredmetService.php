<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class EPredmetService
{
    protected string $apiUrl;
    protected const TIMEOUT = 30;
    protected const BATCH_SIZE = 20;

    public function __construct()
    {
        $this->apiUrl = config('services.epredmet.url') ?? 'https://e-predmet.pravosudje.hr/api';
    }

    /**
     * Execute a GraphQL query
     */
    public function query(string $query): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl, ['query' => $query]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['errors'])) {
                    Log::warning('GraphQL errors', ['errors' => $data['errors']]);
                }

                return $data['data'] ?? null;
            }

            Log::error('EPredmet API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (RequestException $e) {
            Log::error('EPredmet API request failed', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all courts from the API
     */
    public function getCourts(): array
    {
        $data = $this->query('{ sudovi { id sudNaziv sudOznaka razina } }');
        return $data['sudovi'] ?? [];
    }

    /**
     * Get a single case with all details
     */
    public function getCase(int $courtId, string $caseNumber): ?array
    {
        $query = sprintf(
            '{ predmet(sud: %d, oznakaBroj: "%s") { %s } }',
            $courtId,
            $caseNumber,
            $this->getCaseFields()
        );

        $data = $this->query($query);
        return $data['predmet'] ?? null;
    }

    /**
     * Get multiple cases in a batch
     */
    public function getCasesBatch(int $courtId, string $register, int $year, array $numbers): array
    {
        $queries = [];
        foreach ($numbers as $i => $num) {
            $caseNumber = sprintf('%s-%d/%d', $register, $num, $year);
            $queries[] = sprintf(
                'c%d: predmet(sud: %d, oznakaBroj: "%s") { %s }',
                $i,
                $courtId,
                $caseNumber,
                $this->getCaseFields()
            );
        }

        $query = '{ ' . implode(' ', $queries) . ' }';
        $data = $this->query($query);

        if (!$data) {
            return [];
        }

        // Filter out null results
        return array_filter($data, fn($item) => $item !== null);
    }

    /**
     * Fetch all cases for a court/register/year
     *
     * @param int $courtId
     * @param string $register
     * @param int $year
     * @param callable|null $onProgress Callback for progress updates: fn(int $fetched, int $total)
     * @param int $startFrom Start from this case number (for resuming)
     * @param int $maxCases Maximum cases to fetch (0 = unlimited)
     * @return \Generator Yields case data arrays
     */
    public function fetchAllCases(
        int $courtId,
        string $register,
        int $year,
        ?callable $onProgress = null,
        int $startFrom = 1,
        int $maxCases = 0
    ): \Generator {
        $current = $startFrom;
        $fetched = 0;
        $consecutiveEmpty = 0;
        $maxConsecutiveEmpty = 3; // Stop after 3 empty batches

        while (true) {
            // Check max cases limit
            if ($maxCases > 0 && $fetched >= $maxCases) {
                break;
            }

            // Calculate batch range
            $batchEnd = min($current + self::BATCH_SIZE - 1, $maxCases > 0 ? $startFrom + $maxCases - 1 : $current + self::BATCH_SIZE - 1);
            $numbers = range($current, $batchEnd);

            // Fetch batch
            $cases = $this->getCasesBatch($courtId, $register, $year, $numbers);

            if (empty($cases)) {
                $consecutiveEmpty++;
                if ($consecutiveEmpty >= $maxConsecutiveEmpty) {
                    break;
                }
            } else {
                $consecutiveEmpty = 0;

                foreach ($cases as $case) {
                    yield $case;
                    $fetched++;
                }
            }

            // Progress callback
            if ($onProgress) {
                $onProgress($fetched, $current + self::BATCH_SIZE - 1);
            }

            $current += self::BATCH_SIZE;

            // Rate limiting
            usleep(100000); // 100ms delay between batches
        }
    }

    /**
     * Get all available registers
     */
    public function getRegisters(): array
    {
        $data = $this->query('{ upisnici { upisnikOznaka upisnikNaziv } }');
        return $data['upisnici'] ?? [];
    }

    /**
     * Get GraphQL fields for case query
     */
    protected function getCaseFields(): string
    {
        return <<<FIELDS
            id
            oznakaBroj
            broj
            sudId
            upisnikId
            upisnikOznaka
            upisnikNaziv
            sudac
            vrstaPredmeta
            vrstaOdluke
            datumOsnivanja
            datumDodjele
            datumDonosenjaOdluke
            datumOtpreme
            datumPravomocnosti
            datumOvrsnosti
            datumArhiviranja
            datumZalbe
            datumRokaCuvanja
            datumPocetkaProcesa
            lastUpdateTime
            spisNaVisemSudu
            spisIzvanSuda
            pogresnoUpisani
            stalnaSluzba
            stranke {
                naziv
                nazivuloge
            }
            pismena {
                tip
                vrsta
                datum
                podnositelj
                prilozi
            }
            rocista {
                vrstaRadnje
                plPocetak
                plZavrsetak
                stPocetak
                stZavrsetak
                sobaoznaka
                sobanaziv
                odgoda
            }
FIELDS;
    }

    /**
     * Map county name from court name
     */
    public static function mapCountyFromCourtName(string $courtName): ?string
    {
        $mappings = [
            'Zagreb' => 'Grad Zagreb',
            'Velika Gorica' => 'Zagrebačka',
            'Samobor' => 'Zagrebačka',
            'Zaprešić' => 'Zagrebačka',
            'Split' => 'Splitsko-dalmatinska',
            'Makarska' => 'Splitsko-dalmatinska',
            'Sinj' => 'Splitsko-dalmatinska',
            'Trogir' => 'Splitsko-dalmatinska',
            'Rijeka' => 'Primorsko-goranska',
            'Osijek' => 'Osječko-baranjska',
            'Đakovo' => 'Osječko-baranjska',
            'Našice' => 'Osječko-baranjska',
            'Beli Manastir' => 'Osječko-baranjska',
            'Zadar' => 'Zadarska',
            'Šibenik' => 'Šibensko-kninska',
            'Dubrovnik' => 'Dubrovačko-neretvanska',
            'Pula' => 'Istarska',
            'Varaždin' => 'Varaždinska',
            'Karlovac' => 'Karlovačka',
            'Sisak' => 'Sisačko-moslavačka',
            'Bjelovar' => 'Bjelovarsko-bilogorska',
            'Koprivnica' => 'Koprivničko-križevačka',
            'Čakovec' => 'Međimurska',
            'Virovitica' => 'Virovitičko-podravska',
            'Požega' => 'Požeško-slavonska',
            'Slavonski Brod' => 'Brodsko-posavska',
            'Vukovar' => 'Vukovarsko-srijemska',
            'Vinkovci' => 'Vukovarsko-srijemska',
            'Gospić' => 'Ličko-senjska',
            'Krapina' => 'Krapinsko-zagorska',
            'Zlatar' => 'Krapinsko-zagorska',
        ];

        foreach ($mappings as $city => $county) {
            if (str_contains($courtName, $city)) {
                return $county;
            }
        }

        return null;
    }
}
