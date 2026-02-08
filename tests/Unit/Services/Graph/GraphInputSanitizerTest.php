<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphInputSanitizer;
use Tests\TestCase;

class GraphInputSanitizerTest extends TestCase
{
    private GraphInputSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new GraphInputSanitizer();
    }

    /** @test */
    public function it_sanitizes_node_labels(): void
    {
        $this->assertEquals('LawDocument', $this->sanitizer->sanitizeLabel('LawDocument'));
        $this->assertNull($this->sanitizer->sanitizeLabel('Invalid-Label'));
        $this->assertNull($this->sanitizer->sanitizeLabel('DROP TABLE'));
        $this->assertNull($this->sanitizer->sanitizeLabel(''));
    }

    /** @test */
    public function it_sanitizes_relationship_types(): void
    {
        $this->assertEquals('CITES', $this->sanitizer->sanitizeRelationType('CITES'));
        $this->assertEquals('RELATES_TO', $this->sanitizer->sanitizeRelationType('RELATES_TO'));
        $this->assertNull($this->sanitizer->sanitizeRelationType('invalid-type'));
    }

    /** @test */
    public function it_sanitizes_property_names(): void
    {
        $this->assertEquals('title', $this->sanitizer->sanitizePropertyName('title'));
        $this->assertEquals('created_at', $this->sanitizer->sanitizePropertyName('created_at'));
        $this->assertNull($this->sanitizer->sanitizePropertyName('1invalid'));
        $this->assertNull($this->sanitizer->sanitizePropertyName('prop;DROP'));
    }

    /** @test */
    public function it_sanitizes_node_ids(): void
    {
        $this->assertEquals('law-123', $this->sanitizer->sanitizeNodeId('law-123'));
        $this->assertEquals('uuid-abc-def', $this->sanitizer->sanitizeNodeId('uuid-abc-def'));
        $this->assertNull($this->sanitizer->sanitizeNodeId('<script>'));
        $this->assertNull($this->sanitizer->sanitizeNodeId("id'; DROP"));
    }

    /** @test */
    public function it_sanitizes_search_queries(): void
    {
        $this->assertEquals('legal research', $this->sanitizer->sanitizeSearchQuery('legal research'));
        $this->assertEquals('test query', $this->sanitizer->sanitizeSearchQuery('test<script>query'));
        $this->assertNull($this->sanitizer->sanitizeSearchQuery(str_repeat('a', 1001)));
    }
}
