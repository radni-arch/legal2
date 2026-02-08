<?php

namespace Tests\Unit\Services\Agent\Contracts;

use App\Services\Agent\Contracts\AgentCapability;
use PHPUnit\Framework\TestCase;

class AgentCapabilityTest extends TestCase
{
    /** @test */
    public function it_has_all_expected_capability_values(): void
    {
        $this->assertEquals('file_read', AgentCapability::FILE_READ->value);
        $this->assertEquals('file_write', AgentCapability::FILE_WRITE->value);
        $this->assertEquals('bash', AgentCapability::BASH->value);
        $this->assertEquals('web_search', AgentCapability::WEB_SEARCH->value);
        $this->assertEquals('multi_turn', AgentCapability::MULTI_TURN->value);
        $this->assertEquals('extended_thinking', AgentCapability::EXTENDED_THINKING->value);
        $this->assertEquals('streaming', AgentCapability::STREAMING->value);
        $this->assertEquals('json_output', AgentCapability::JSON_OUTPUT->value);
    }

    /** @test */
    public function it_has_exactly_eight_capabilities(): void
    {
        $this->assertCount(8, AgentCapability::cases());
    }

    /** @test */
    public function it_can_create_from_valid_string(): void
    {
        $capability = AgentCapability::from('file_read');
        $this->assertEquals(AgentCapability::FILE_READ, $capability);

        $capability = AgentCapability::from('json_output');
        $this->assertEquals(AgentCapability::JSON_OUTPUT, $capability);
    }

    /** @test */
    public function it_returns_null_for_invalid_string_with_tryFrom(): void
    {
        $capability = AgentCapability::tryFrom('invalid_capability');
        $this->assertNull($capability);
    }

    /** @test */
    public function it_is_backed_by_string(): void
    {
        foreach (AgentCapability::cases() as $capability) {
            $this->assertIsString($capability->value);
        }
    }
}
