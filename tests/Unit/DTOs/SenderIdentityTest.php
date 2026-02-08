<?php

namespace Tests\Unit\DTOs;

use App\DTOs\SenderIdentity;
use Tests\TestCase;

class SenderIdentityTest extends TestCase
{
    public function test_creates_sender_identity_from_config(): void
    {
        $sender = SenderIdentity::fromConfig();

        $this->assertInstanceOf(SenderIdentity::class, $sender);
        $this->assertNotEmpty($sender->name);
        $this->assertIsString($sender->oib);
        $this->assertIsString($sender->address);
        $this->assertIsString($sender->email);
        $this->assertIsString($sender->phone);
    }

    public function test_constructs_with_all_properties(): void
    {
        $sender = new SenderIdentity(
            name: 'Test Name',
            oib: '12345678901',
            address: 'Test Address 123',
            email: 'test@example.com',
            phone: '+385 91 123 4567',
        );

        $this->assertEquals('Test Name', $sender->name);
        $this->assertEquals('12345678901', $sender->oib);
        $this->assertEquals('Test Address 123', $sender->address);
        $this->assertEquals('test@example.com', $sender->email);
        $this->assertEquals('+385 91 123 4567', $sender->phone);
    }

    public function test_properties_are_readonly(): void
    {
        $sender = SenderIdentity::fromConfig();

        $reflection = new \ReflectionClass($sender);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
