<?php

namespace Tests\Unit\Clients\Ekom;

use App\Clients\Ekom\EkomApiClient;
use App\Exceptions\EkomApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class EkomApiClientTest extends TestCase
{
    private function createClientWithMock(array $responses): EkomApiClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        $client = new EkomApiClient(
            baseUrl: 'https://api.test.com',
            token: 'test-token',
            timeout: 30,
            retries: 0,
            retryDelayMs: 100,
            userAgent: 'Test Agent'
        );

        $mockGuzzle = new Client(['handler' => $handlerStack]);

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($client, $mockGuzzle);

        return $client;
    }

    public function test_get_naselja_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Zagreb'],
            ['id' => 2, 'naziv' => 'Split'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getNaselja();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Zagreb', $result[0]['naziv']);
    }

    public function test_get_drzave_returns_array(): void
    {
        $expected = [
            ['id' => 'HR', 'naziv' => 'Hrvatska'],
            ['id' => 'DE', 'naziv' => 'Njemacka'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getDrzave();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Hrvatska', $result[0]['naziv']);
    }

    public function test_get_razlozi_neplacanja_pristojbe_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Razlog 1'],
            ['id' => 2, 'naziv' => 'Razlog 2'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getRazloziNeplacanjaPristojbe();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_get_osnove_oslobodjenja_pristojbe_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Osnova 1'],
            ['id' => 2, 'naziv' => 'Osnova 2'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getOsnoveOslobodjenjaPristojbe();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_get_vrste_postupaka_za_sud_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Parnični postupak'],
            ['id' => 2, 'naziv' => 'Izvanparnični postupak'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getVrstePostupakaZaSud(5001);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Parnični postupak', $result[0]['naziv']);
    }

    public function test_get_vrste_podnesaka_novi_postupak_returns_array(): void
    {
        $expected = [
            ['id' => 10, 'naziv' => 'Tužba'],
            ['id' => 11, 'naziv' => 'Prijedlog'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getVrstePodnesakaNoviPostupak(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Tužba', $result[0]['naziv']);
    }

    public function test_get_vrste_podnesaka_postojeci_predmet_returns_array(): void
    {
        $expected = [
            ['id' => 20, 'naziv' => 'Podnesak'],
            ['id' => 21, 'naziv' => 'Žalba'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getVrstePodnesakaPostojeciPredmet(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Podnesak', $result[0]['naziv']);
    }

    public function test_get_uloge_sudionika_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Tužitelj'],
            ['id' => 2, 'naziv' => 'Tuženik'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getUlogeSudionika(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Tužitelj', $result[0]['naziv']);
    }

    public function test_get_uloge_podnositelja_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Stranka'],
            ['id' => 2, 'naziv' => 'Punomoćnik'],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getUlogePodnositelja(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Stranka', $result[0]['naziv']);
    }

    public function test_get_pristojba_dodatne_opcije_novi_postupak_returns_array(): void
    {
        $expected = [
            ['id' => 1, 'naziv' => 'Opcija 1', 'iznos' => 100.00],
            ['id' => 2, 'naziv' => 'Opcija 2', 'iznos' => 200.00],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getPristojbaDodatneOpcijeNoviPostupak(1, 10);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(100.00, $result[0]['iznos']);
    }

    public function test_get_pristojba_dodatne_opcije_postojeci_predmet_returns_array(): void
    {
        $expected = [
            ['id' => 3, 'naziv' => 'Opcija 3', 'iznos' => 50.00],
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getPristojbaDodatneOpcijePostojeciPredmet(1, 20);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(50.00, $result[0]['iznos']);
    }

    public function test_delete_podnesak_sends_delete_request(): void
    {
        $client = $this->createClientWithMock([
            new Response(204), // No content on successful delete
        ]);

        // Should not throw
        $client->deletePodnesak(12345);

        $this->assertTrue(true); // If we get here, delete succeeded
    }

    public function test_delete_podnesak_throws_on_not_found(): void
    {
        $client = $this->createClientWithMock([
            new Response(404, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Podnesak not found',
            ])),
        ]);

        $this->expectException(EkomApiException::class);

        $client->deletePodnesak(99999);
    }

    public function test_get_pristojba_podneska_returns_array(): void
    {
        $expected = [
            'iznos' => 150.00,
            'status' => 'NEPLACENO',
            'nalogZaPlacanjeUrl' => '/download/nalog/123',
        ];

        $client = $this->createClientWithMock([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($expected)),
        ]);

        $result = $client->getPristojbaPodneska(12345);

        $this->assertIsArray($result);
        $this->assertEquals(150.00, $result['iznos']);
        $this->assertEquals('NEPLACENO', $result['status']);
    }
}
