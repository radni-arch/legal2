<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Tests\TestCase;

class GraphDatabaseServiceLoggingTest extends TestCase
{
    /** @test */
    public function query_preview_truncates_long_queries(): void
    {
        $longQuery = "MATCH (n:User {password: 'secret123'}) WHERE n.email = 'test@test.com' " . str_repeat('x', 200);

        $preview = GraphDatabaseService::getQueryPreview($longQuery);

        $this->assertLessThanOrEqual(100, strlen($preview));
        $this->assertStringEndsWith('...', $preview);
    }

    /** @test */
    public function query_preview_returns_short_queries_unchanged(): void
    {
        $shortQuery = "MATCH (n) RETURN n LIMIT 10";

        $preview = GraphDatabaseService::getQueryPreview($shortQuery);

        $this->assertEquals($shortQuery, $preview);
    }

    /** @test */
    public function query_preview_handles_empty_query(): void
    {
        $preview = GraphDatabaseService::getQueryPreview('');

        $this->assertEquals('', $preview);
    }
}
