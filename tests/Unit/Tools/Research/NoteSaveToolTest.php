<?php

namespace Tests\Unit\Tools\Research;

use App\Tools\Research\NoteSaveTool;
use App\Services\AgentToolbox;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class NoteSaveToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $tool = new NoteSaveTool($mockToolbox);

        $definition = $tool->definition();

        $this->assertEquals('note_save', $definition['name']);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('properties', $definition['parameters']);
        $this->assertArrayHasKey('content', $definition['parameters']['properties']);
        $this->assertContains('content', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_note_save(): void
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('noteSave')
            ->once()
            ->with(
                'research_orchestrator',
                'Article 8 of ZKP establishes proportionality requirements for home searches',
                Mockery::type('array')
            )
            ->andReturn([
                'success' => true,
                'id' => 'mem-12345',
                'status' => 'created',
            ]);

        $tool = new NoteSaveTool($mockToolbox);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            [
                'content' => 'Article 8 of ZKP establishes proportionality requirements for home searches',
                'agent_name' => 'research_orchestrator',
                'namespace' => 'insights',
            ],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
        $this->assertEquals('mem-12345', $decoded['id']);
        $this->assertEquals('created', $decoded['status']);
    }

    #[Test]
    public function it_uses_default_agent_name_if_not_provided(): void
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('noteSave')
            ->once()
            ->with(
                'autonomous_agent',
                'Test note content',
                Mockery::type('array')
            )
            ->andReturn([
                'success' => true,
                'id' => 'mem-67890',
                'status' => 'created',
            ]);

        $tool = new NoteSaveTool($mockToolbox);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['content' => 'Test note content'],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockToolbox = Mockery::mock(AgentToolbox::class);
        $mockToolbox->shouldReceive('noteSave')
            ->once()
            ->andThrow(new \Exception('Failed to save note'));

        $tool = new NoteSaveTool($mockToolbox);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['content' => 'test content'],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('Failed to save note', $decoded['error']);
    }
}
