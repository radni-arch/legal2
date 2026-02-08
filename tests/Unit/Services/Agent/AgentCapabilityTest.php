<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\Contracts\AgentCapability;
use PHPUnit\Framework\TestCase;

class AgentCapabilityTest extends TestCase
{
    public function test_enum_has_required_capabilities(): void
    {
        // All required capabilities from the spec
        $this->assertEquals('file_read', AgentCapability::FILE_READ->value);
        $this->assertEquals('file_write', AgentCapability::FILE_WRITE->value);
        $this->assertEquals('bash', AgentCapability::BASH->value);
        $this->assertEquals('web_search', AgentCapability::WEB_SEARCH->value);
        $this->assertEquals('multi_turn', AgentCapability::MULTI_TURN->value);
        $this->assertEquals('extended_thinking', AgentCapability::EXTENDED_THINKING->value);
        $this->assertEquals('streaming', AgentCapability::STREAMING->value);
        $this->assertEquals('json_output', AgentCapability::JSON_OUTPUT->value);
    }

    public function test_capabilities_can_be_created_from_string(): void
    {
        $capability = AgentCapability::from('file_read');
        $this->assertEquals(AgentCapability::FILE_READ, $capability);

        $capability = AgentCapability::from('bash');
        $this->assertEquals(AgentCapability::BASH, $capability);
    }

    public function test_invalid_capability_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        AgentCapability::from('invalid_capability');
    }

    public function test_capabilities_array_can_be_mapped(): void
    {
        $capabilities = [
            AgentCapability::FILE_READ,
            AgentCapability::BASH,
            AgentCapability::JSON_OUTPUT,
        ];

        $values = array_map(fn($c) => $c->value, $capabilities);

        $this->assertEquals(['file_read', 'bash', 'json_output'], $values);
    }
}
