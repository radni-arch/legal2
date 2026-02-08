<?php

namespace Tests\Unit\Services;

use App\Services\EPredmetService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EPredmetServiceTest extends TestCase
{
    public function test_query_returns_null_on_http_failure(): void
    {
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $service = new EPredmetService();
        $result = $service->query('{ sudovi { id } }');

        $this->assertNull($result);
    }

    public function test_get_courts_returns_empty_array_on_failure(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $service = new EPredmetService();
        $result = $service->getCourts();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_uses_configured_url(): void
    {
        config(['services.epredmet.url' => 'https://custom.example.com/api']);

        Http::fake([
            'custom.example.com/*' => Http::response([
                'data' => ['sudovi' => [['id' => 1, 'sudNaziv' => 'Test']]]
            ], 200),
        ]);

        $service = new EPredmetService();
        $result = $service->getCourts();

        $this->assertNotEmpty($result);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'custom.example.com'));
    }

    public function test_defaults_to_production_url(): void
    {
        // Clear any test config
        config(['services.epredmet.url' => null]);

        $service = new EPredmetService();

        Http::fake(['e-predmet.pravosudje.hr/*' => Http::response(['data' => ['sudovi' => []]], 200)]);

        $service->getCourts();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'e-predmet.pravosudje.hr'));
    }
}
