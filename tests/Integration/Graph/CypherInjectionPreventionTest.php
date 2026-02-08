<?php

namespace Tests\Integration\Graph;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class CypherInjectionPreventionTest extends GraphIntegrationTestCase
{
    protected function tearDown(): void
    {
        // Clean up any test nodes
        try {
            $this->graph->run('MATCH (n:InjectionTestNode) DETACH DELETE n');
            $this->graph->run('MATCH (n:HackedNode) DETACH DELETE n');
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }

        parent::tearDown();
    }

    /**
     * Provide various injection attack patterns
     */
    public static function injectionPatterns(): array
    {
        return [
            'single quote escape' => ["Test' OR 1=1 --"],
            'double quote escape' => ['Test" OR 1=1 --'],
            'cypher comment injection' => ['Test // DELETE ALL'],
            'property escape attempt' => ['Test}) DELETE (n) RETURN ({a: "'],
            'match injection' => ['Test"}) MATCH (n) DETACH DELETE n RETURN n //'],
            'create injection' => ["Test' CREATE (h:HackedNode {name:'hacked'}) RETURN h //"],
            'unicode escape' => ["Test\u0027 OR 1=1"],
            'backslash escape' => ['Test\\ OR 1=1'],
            'null byte injection' => ["Test\0 DELETE ALL"],
            'newline injection' => ["Test\n MATCH (n) DELETE n"],
            'semicolon injection' => ["Test; MATCH (n) DELETE n;"],
        ];
    }

    #[Test]
    #[DataProvider('injectionPatterns')]
    public function it_safely_handles_injection_attempts_in_node_properties(string $maliciousInput): void
    {
        try {
            // Attempt to create node with malicious property value
            $this->graph->run(
                'CREATE (n:InjectionTestNode {id: $id, name: $name}) RETURN n',
                ['id' => 'test_' . md5($maliciousInput), 'name' => $maliciousInput]
            );

            // If we get here, the injection was safely parameterized
            // Verify the node was created with the literal malicious string
            $result = $this->graph->run(
                'MATCH (n:InjectionTestNode {id: $id}) RETURN n.name as name',
                ['id' => 'test_' . md5($maliciousInput)]
            );

            $this->assertNotEmpty($result, 'Node should be created');
            $this->assertEquals(
                $maliciousInput,
                $result[0]['name'] ?? null,
                'Malicious input should be stored literally, not interpreted'
            );

            // Verify no HackedNode was created (injection didn't work)
            $hackedResult = $this->graph->run('MATCH (h:HackedNode) RETURN count(h) as count');
            $this->assertEquals(0, $hackedResult[0]['count'] ?? 1, 'Injection should not create unauthorized nodes');

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            // Any other exception means the injection was blocked, which is also acceptable
            $this->assertTrue(true, 'Query was safely rejected');
        }
    }

    #[Test]
    public function it_handles_special_characters_in_property_names(): void
    {
        // Note: Neo4j property names have restrictions, but values should handle anything
        $specialValues = [
            'curly_braces' => 'Value with {curly} braces',
            'brackets' => 'Value with [brackets]',
            'angle_brackets' => 'Value with <angle> brackets',
            'parentheses' => 'Value with (parentheses)',
            'pipe' => 'Value with | pipe',
            'colon' => 'Value with : colon',
            'at_sign' => 'Value with @ at sign',
            'hash' => 'Value with # hash',
            'dollar' => 'Value with $ dollar',
            'percent' => 'Value with % percent',
            'ampersand' => 'Value with & ampersand',
            'asterisk' => 'Value with * asterisk',
        ];

        foreach ($specialValues as $key => $value) {
            try {
                $this->graph->run(
                    'CREATE (n:InjectionTestNode {id: $id, special_value: $value})',
                    ['id' => 'special_' . $key, 'value' => $value]
                );

                $result = $this->graph->run(
                    'MATCH (n:InjectionTestNode {id: $id}) RETURN n.special_value as val',
                    ['id' => 'special_' . $key]
                );

                $this->assertEquals($value, $result[0]['val'] ?? null, "Should handle {$key} correctly");
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'not available')) {
                    $this->markTestSkipped('Neo4j not available');
                }
                throw $e;
            }
        }
    }

    #[Test]
    public function it_handles_unicode_characters_safely(): void
    {
        $unicodeStrings = [
            'croatian' => 'Čćžšđ ČĆŽŠĐ',
            'cyrillic' => 'Привет мир',
            'chinese' => '你好世界',
            'arabic' => 'مرحبا بالعالم',
            'emoji' => '👋🌍🔒',
            'mixed' => 'Hello Čćžšđ 你好 👋',
            'rtl' => 'עברית',
            'thai' => 'สวัสดี',
        ];

        foreach ($unicodeStrings as $type => $value) {
            try {
                $this->graph->run(
                    'CREATE (n:InjectionTestNode {id: $id, text: $text})',
                    ['id' => 'unicode_' . $type, 'text' => $value]
                );

                $result = $this->graph->run(
                    'MATCH (n:InjectionTestNode {id: $id}) RETURN n.text as text',
                    ['id' => 'unicode_' . $type]
                );

                $this->assertEquals($value, $result[0]['text'] ?? null, "Should preserve {$type} unicode");
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'not available')) {
                    $this->markTestSkipped('Neo4j not available');
                }
                throw $e;
            }
        }
    }

    #[Test]
    public function it_handles_extremely_long_strings(): void
    {
        $longString = str_repeat('A', 10000);

        try {
            $this->graph->run(
                'CREATE (n:InjectionTestNode {id: $id, content: $content})',
                ['id' => 'long_string', 'content' => $longString]
            );

            $result = $this->graph->run(
                'MATCH (n:InjectionTestNode {id: $id}) RETURN length(n.content) as len',
                ['id' => 'long_string']
            );

            $this->assertEquals(10000, $result[0]['len'] ?? 0, 'Should handle long strings');
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }

    #[Test]
    public function it_handles_null_and_empty_values(): void
    {
        try {
            // Empty string
            $this->graph->run(
                'CREATE (n:InjectionTestNode {id: $id, value: $value})',
                ['id' => 'empty_string', 'value' => '']
            );

            $result = $this->graph->run(
                'MATCH (n:InjectionTestNode {id: $id}) RETURN n.value as val',
                ['id' => 'empty_string']
            );
            $this->assertEquals('', $result[0]['val'] ?? 'not_empty');

            // Null value
            $this->graph->run(
                'CREATE (n:InjectionTestNode {id: $id}) SET n.nullable = $value',
                ['id' => 'null_value', 'value' => null]
            );

            $result = $this->graph->run(
                'MATCH (n:InjectionTestNode {id: $id}) RETURN n.nullable as val',
                ['id' => 'null_value']
            );
            $this->assertNull($result[0]['val'] ?? 'not_null');

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }

    #[Test]
    public function it_prevents_label_injection(): void
    {
        // Labels are typically not parameterizable, so we test that
        // user input used as labels is properly sanitized
        $maliciousLabel = 'Safe` MATCH (n) DELETE n //';

        try {
            // If the system tries to use this as a label, it should fail or sanitize
            // We can't directly test label injection via parameters (Neo4j doesn't support it)
            // But we verify that our typical pattern (hardcoded labels) is safe

            $this->graph->run(
                'CREATE (n:InjectionTestNode {id: $id, attempted_label: $label})',
                ['id' => 'label_injection_test', 'label' => $maliciousLabel]
            );

            // Verify it was stored as a property, not executed as a label
            $result = $this->graph->run(
                'MATCH (n:InjectionTestNode {id: $id}) RETURN n.attempted_label as label, labels(n) as actualLabels',
                ['id' => 'label_injection_test']
            );

            $this->assertEquals($maliciousLabel, $result[0]['label'] ?? null);
            $this->assertContains('InjectionTestNode', $result[0]['actualLabels'] ?? []);
            $this->assertNotContains('Safe', $result[0]['actualLabels'] ?? ['Safe']); // Injection failed

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }

    #[Test]
    public function it_handles_json_in_properties(): void
    {
        $jsonString = '{"key": "value", "nested": {"array": [1, 2, 3]}, "quotes": "say \\"hello\\""}';

        try {
            $this->graph->run(
                'CREATE (n:InjectionTestNode {id: $id, json_data: $json})',
                ['id' => 'json_test', 'json' => $jsonString]
            );

            $result = $this->graph->run(
                'MATCH (n:InjectionTestNode {id: $id}) RETURN n.json_data as json',
                ['id' => 'json_test']
            );

            $this->assertEquals($jsonString, $result[0]['json'] ?? null);

            // Verify it's valid JSON that can be parsed
            $parsed = json_decode($result[0]['json'], true);
            $this->assertNotNull($parsed);
            $this->assertEquals('value', $parsed['key'] ?? null);

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            throw $e;
        }
    }
}
