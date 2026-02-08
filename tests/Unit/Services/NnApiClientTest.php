<?php

namespace Tests\Unit\Services;

use App\Services\NnApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class NnApiClientTest extends TestCase
{
    public function test_years_returns_data_on_success(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['year' => 2024]])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $api = new NnApiClient($client);

        $this->assertEquals([['year' => 2024]], $api->years());
    }

    public function test_years_returns_empty_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/api/index')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $api = new NnApiClient($client);

        $this->assertEmpty($api->years());
    }

    public function test_editions_returns_empty_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Timeout', new Request('POST', '/api/editions')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $api = new NnApiClient($client);

        $this->assertEmpty($api->editions(2024));
    }

    public function test_acts_returns_empty_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Timeout', new Request('POST', '/api/acts')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $api = new NnApiClient($client);

        $this->assertEmpty($api->acts(2024, 1));
    }

    public function test_act_json_ld_returns_empty_on_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException('Timeout', new Request('POST', '/api/act')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $api = new NnApiClient($client);

        $this->assertEmpty($api->actJsonLd(2024, 1, '1'));
    }
}
