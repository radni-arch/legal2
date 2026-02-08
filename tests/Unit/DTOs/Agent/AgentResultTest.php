<?php

namespace Tests\Unit\DTOs\Agent;

use App\DTOs\Agent\AgentResult;
use PHPUnit\Framework\TestCase;

class AgentResultTest extends TestCase
{
    /** @test */
    public function it_creates_an_agent_result_with_all_properties(): void
    {
        $result = new AgentResult(
            success: true,
            driver: 'claude',
            results: ['analysis' => 'test data'],
            rawOutput: 'Raw stdout output',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 12.5,
            sessionId: 'session-123',
            outputFile: '/tmp/output.json',
            metadata: ['model' => 'claude-3-opus'],
        );

        $this->assertTrue($result->success);
        $this->assertEquals('claude', $result->driver);
        $this->assertEquals(['analysis' => 'test data'], $result->results);
        $this->assertEquals('Raw stdout output', $result->rawOutput);
        $this->assertEquals('', $result->rawError);
        $this->assertEquals(0, $result->exitCode);
        $this->assertEquals(12.5, $result->elapsedSeconds);
        $this->assertEquals('session-123', $result->sessionId);
        $this->assertEquals('/tmp/output.json', $result->outputFile);
        $this->assertEquals(['model' => 'claude-3-opus'], $result->metadata);
    }

    /** @test */
    public function it_creates_a_failed_result(): void
    {
        $result = new AgentResult(
            success: false,
            driver: 'gemini',
            results: null,
            rawOutput: '',
            rawError: 'Error: API rate limit exceeded',
            exitCode: 1,
            elapsedSeconds: 0.5,
            sessionId: 'session-456',
            outputFile: null,
            metadata: ['error_type' => 'rate_limit'],
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->results);
        $this->assertEquals('Error: API rate limit exceeded', $result->rawError);
        $this->assertEquals(1, $result->exitCode);
        $this->assertNull($result->outputFile);
    }

    /** @test */
    public function it_has_default_empty_metadata(): void
    {
        $result = new AgentResult(
            success: true,
            driver: 'test',
            results: [],
            rawOutput: 'output',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 1.0,
            sessionId: 'test-session',
            outputFile: null,
        );

        $this->assertEquals([], $result->metadata);
    }

    /** @test */
    public function it_converts_to_array_with_all_fields(): void
    {
        $result = new AgentResult(
            success: true,
            driver: 'claude',
            results: ['findings' => ['a', 'b', 'c']],
            rawOutput: 'Full raw output text here',
            rawError: 'Some warning messages',
            exitCode: 0,
            elapsedSeconds: 45.75,
            sessionId: 'sess-abc123',
            outputFile: '/path/to/output.json',
            metadata: ['tokens_used' => 5000],
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['success']);
        $this->assertEquals('claude', $array['driver']);
        $this->assertEquals(['findings' => ['a', 'b', 'c']], $array['results']);
        $this->assertEquals('Full raw output text here', $array['raw_output']);
        $this->assertEquals('Some warning messages', $array['raw_error']);
        $this->assertEquals(0, $array['exit_code']);
        $this->assertEquals(45.75, $array['elapsed_seconds']);
        $this->assertEquals('sess-abc123', $array['session_id']);
        $this->assertEquals('/path/to/output.json', $array['output_file']);
        $this->assertEquals(['tokens_used' => 5000], $array['metadata']);
    }

    /** @test */
    public function it_includes_all_expected_array_keys(): void
    {
        $result = new AgentResult(
            success: false,
            driver: 'test',
            results: null,
            rawOutput: '',
            rawError: 'error',
            exitCode: 1,
            elapsedSeconds: 0.0,
            sessionId: 'test',
            outputFile: null,
        );

        $array = $result->toArray();

        $expectedKeys = [
            'success',
            'driver',
            'results',
            'raw_output',
            'raw_error',
            'exit_code',
            'elapsed_seconds',
            'session_id',
            'output_file',
            'metadata',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, "Missing key: {$key}");
        }
    }

    /** @test */
    public function it_preserves_nested_results_structure(): void
    {
        $nestedResults = [
            'phase1' => [
                'documents' => [
                    ['id' => 1, 'findings' => ['a', 'b']],
                    ['id' => 2, 'findings' => ['c']],
                ],
            ],
            'phase2' => [
                'summary' => 'Cross-document analysis complete',
            ],
        ];

        $result = new AgentResult(
            success: true,
            driver: 'claude',
            results: $nestedResults,
            rawOutput: '{}',
            rawError: '',
            exitCode: 0,
            elapsedSeconds: 120.0,
            sessionId: 'bulk-session',
            outputFile: '/tmp/bulk-output.json',
        );

        $this->assertEquals($nestedResults, $result->results);
        $this->assertEquals($nestedResults, $result->toArray()['results']);
    }
}
