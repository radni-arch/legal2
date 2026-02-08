<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\GraphException;
use App\Exceptions\Graph\GraphConnectionException;
use App\Exceptions\Graph\GraphQueryException;
use App\Exceptions\Graph\GraphValidationException;
use App\Exceptions\Graph\GraphTimeoutException;
use Tests\TestCase;

class GraphExceptionHierarchyTest extends TestCase
{
    /** @test */
    public function all_graph_exceptions_extend_base_graph_exception(): void
    {
        $exceptions = [
            GraphConnectionException::class,
            GraphQueryException::class,
            GraphValidationException::class,
            GraphTimeoutException::class,
        ];

        foreach ($exceptions as $exception) {
            $this->assertTrue(
                is_subclass_of($exception, GraphException::class),
                "$exception should extend GraphException"
            );
        }
    }

    /** @test */
    public function graph_query_exception_includes_query_context(): void
    {
        $query = "MATCH (n) RETURN n";
        $exception = GraphQueryException::forQuery($query, new \Exception('Neo4j error'));

        $this->assertStringContainsString('MATCH', $exception->getQuery());
        $this->assertNotNull($exception->getPrevious());
    }

    /** @test */
    public function graph_timeout_exception_includes_timeout_value(): void
    {
        $exception = GraphTimeoutException::afterSeconds(30, 'Long query');

        $this->assertEquals(30, $exception->getTimeoutSeconds());
        $this->assertStringContainsString('30', $exception->getMessage());
    }

    /** @test */
    public function graph_validation_exception_includes_field_errors(): void
    {
        $errors = ['node_id' => 'Invalid format', 'label' => 'Unknown label'];
        $exception = GraphValidationException::withErrors($errors);

        $this->assertEquals($errors, $exception->getValidationErrors());
    }
}
