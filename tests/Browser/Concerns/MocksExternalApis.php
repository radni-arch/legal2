<?php

namespace Tests\Browser\Concerns;

use Illuminate\Support\Facades\Http;

trait MocksExternalApis
{
    protected function mockOpenAIApis(): void
    {
        // Mock OpenAI embeddings API
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'total_tokens' => 10,
                ],
            ], 200),

            // Mock OpenAI chat completions API
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'severity_score' => 75,
                                'misconduct_types' => ['evidence_suppression'],
                                'analysis' => 'Test analysis result',
                                'legal_basis' => 'ZKP Članak 9',
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ], 200),
        ]);
    }

    protected function mockOdlukeApi(): void
    {
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response([
                'results' => [],
                'total' => 0,
            ], 200),
        ]);
    }

    protected function mockGraphQLApi(?array $predmetData = null): void
    {
        // Default mock case data if not provided
        $defaultPredmetData = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'upisnikNaziv' => 'Prekršajni postupak',
            'upisnikOznaka' => 'Pp Prz',
            'vrstaPredmeta' => 'Prekršajni predmet',
            'vrstaOdluke' => 'Rješenje',
            'spisNaVisemSudu' => false,
            'spisIzvanSuda' => false,
            'lastUpdateTime' => '2025-01-15 10:30:00',
            'datumDodjele' => '2025-01-10 09:00:00',
            'datumDonosenjaOdluke' => null,
            'datumOtpreme' => null,
            'datumOvrsnosti' => null,
            'datumZalbe' => null,
            'datumArhiviranja' => null,
            'rocista' => [
                [
                    'vrstaRadnje' => 'Glavna rasprava',
                    'stPocetak' => '2025-02-15 10:00:00',
                    'stZavrsetak' => '2025-02-15 11:30:00',
                    'plPocetak' => '2025-02-15 10:00:00',
                    'plZavrsetak' => '2025-02-15 11:30:00',
                    'sobanaziv' => 'Sudnica 1',
                    'sobaoznaka' => 'S1',
                    'odgoda' => false,
                ],
            ],
            'stranke' => [
                [
                    'naziv' => 'Ivana Horvat',
                    'nazivuloge' => 'Okrivljenik',
                ],
                [
                    'naziv' => 'Republika Hrvatska',
                    'nazivuloge' => 'Podnositelj zahtjeva',
                ],
            ],
            'vjecnici' => [
                [
                    'ime' => 'Marko Kovačević, sudac',
                    'vrsta' => 'Sudac pojedinac',
                ],
            ],
            'pismena' => [
                [
                    'datum' => '2025-01-15 14:30:00',
                    'vrsta' => 'Zahtjev',
                    'tip' => 'Podnesak',
                    'podnositelj' => 'Odvjetnik Petar Novak',
                    'prilozi' => '3',
                ],
            ],
            'povezaniPredmeti' => [
                [
                    'vezaniOznakaBroj' => 'Pp Prz-73/2025',
                    'vezaniOznaka' => 'Pp Prz-73/2025',
                    'opis' => 'Povezan predmet',
                    'datumVeze' => '2025-01-12',
                    'tip' => 'Sličan',
                ],
            ],
        ];

        $responseData = $predmetData ?? $defaultPredmetData;

        // Mock GraphQL endpoint (pattern matches any GraphQL endpoint)
        Http::fake([
            '*/graphql*' => Http::response([
                'data' => [
                    'predmet' => $responseData,
                ],
            ], 200),
        ]);
    }

    protected function mockGraphQLApiError(string $errorMessage = 'GraphQL query failed'): void
    {
        Http::fake([
            '*/graphql*' => Http::response([
                'errors' => [
                    [
                        'message' => $errorMessage,
                        'path' => ['predmet'],
                    ],
                ],
            ], 200),
        ]);
    }

    protected function mockGraphQLApiTimeout(): void
    {
        Http::fake([
            '*/graphql*' => Http::response('', 504),
        ]);
    }

    protected function mockAllExternalApis(): void
    {
        $this->mockOpenAIApis();
        $this->mockOdlukeApi();
        $this->mockGraphQLApi();
    }
}
