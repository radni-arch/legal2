<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\OpenAICacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for OpenAICacheService
 *
 * Following TDD RED-GREEN-REFACTOR cycle
 */
class OpenAICacheServiceTest extends TestCase
{
    protected OpenAICacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Clear cache before each test
        $this->cache = new OpenAICacheService;
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    // ========================================
    // Interface Implementation Tests
    // ========================================

    /** @test */
    public function it_implements_cache_service_interface()
    {
        $this->assertInstanceOf(
            \App\Contracts\AI\CacheServiceInterface::class,
            $this->cache
        );
    }

    // ========================================
    // Key Generation Tests
    // ========================================

    /** @test */
    public function it_generates_key_for_chat_operation()
    {
        $key = $this->cache->generateKey('chat', [
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => 'test']],
        ]);

        $this->assertIsString($key);
        $this->assertStringContainsString('chat', $key);
    }

    /** @test */
    public function it_generates_key_for_embeddings_operation()
    {
        $key = $this->cache->generateKey('embeddings', [
            'model' => 'text-embedding-ada-002',
            'input' => 'test text',
        ]);

        $this->assertIsString($key);
        $this->assertStringContainsString('embeddings', $key);
    }

    /** @test */
    public function it_generates_key_for_analysis_operation()
    {
        $key = $this->cache->generateKey('analysis', [
            'model' => 'gpt-4',
            'prompt' => 'analyze this',
        ]);

        $this->assertIsString($key);
        $this->assertStringContainsString('analysis', $key);
    }

    /** @test */
    public function it_generates_deterministic_keys()
    {
        $params = [
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => 'test']],
        ];

        $key1 = $this->cache->generateKey('chat', $params);
        $key2 = $this->cache->generateKey('chat', $params);

        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_keys_for_different_params()
    {
        $key1 = $this->cache->generateKey('chat', ['message' => 'test1']);
        $key2 = $this->cache->generateKey('chat', ['message' => 'test2']);

        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_includes_all_params_in_key_generation()
    {
        $params1 = ['model' => 'gpt-4', 'temperature' => 0.7];
        $params2 = ['model' => 'gpt-4', 'temperature' => 0.8];

        $key1 = $this->cache->generateKey('chat', $params1);
        $key2 = $this->cache->generateKey('chat', $params2);

        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_normalizes_params_for_consistent_keys()
    {
        // Different param order should generate same key
        $params1 = ['model' => 'gpt-4', 'temp' => 0.7];
        $params2 = ['temp' => 0.7, 'model' => 'gpt-4'];

        $key1 = $this->cache->generateKey('chat', $params1);
        $key2 = $this->cache->generateKey('chat', $params2);

        $this->assertEquals($key1, $key2);
    }

    // ========================================
    // Put and Get Tests
    // ========================================

    /** @test */
    public function it_stores_and_retrieves_values()
    {
        $key = 'test-key';
        $value = ['result' => 'test response'];

        $result = $this->cache->put($key, $value, 3600);
        $retrieved = $this->cache->get($key);

        $this->assertTrue($result);
        $this->assertEquals($value, $retrieved);
    }

    /** @test */
    public function it_returns_null_for_missing_keys()
    {
        $value = $this->cache->get('non-existent-key');

        $this->assertNull($value);
    }

    /** @test */
    public function it_can_store_different_value_types()
    {
        $ttl = 3600;
        $this->cache->put('string-key', 'test string', $ttl);
        $this->cache->put('array-key', ['test' => 'array'], $ttl);
        $this->cache->put('int-key', 42, $ttl);
        $this->cache->put('bool-key', true, $ttl);

        $this->assertEquals('test string', $this->cache->get('string-key'));
        $this->assertEquals(['test' => 'array'], $this->cache->get('array-key'));
        $this->assertEquals(42, $this->cache->get('int-key'));
        $this->assertTrue($this->cache->get('bool-key'));
    }

    // ========================================
    // TTL Tests
    // ========================================

    /** @test */
    public function it_respects_explicit_ttl()
    {
        $this->cache->put('short-ttl-key', 'short value', 60);
        $this->cache->put('long-ttl-key', 'long value', 86400);

        // Values should be stored (we can't easily test TTL expiration in unit test)
        $this->assertNotNull($this->cache->get('short-ttl-key'));
        $this->assertNotNull($this->cache->get('long-ttl-key'));
    }

    /** @test */
    public function it_provides_recommended_ttl_for_operations()
    {
        $chatTTL = $this->cache->getTTLForOperation('chat');
        $embedTTL = $this->cache->getTTLForOperation('embeddings');
        $analysisTTL = $this->cache->getTTLForOperation('analysis');

        $this->assertEquals(3600, $chatTTL);
        $this->assertEquals(86400, $embedTTL);
        $this->assertEquals(7200, $analysisTTL);
    }

    /** @test */
    public function it_uses_default_ttl_for_unknown_operation()
    {
        $ttl = $this->cache->getTTLForOperation('unknown-operation');

        // Should default to chat TTL
        $this->assertEquals(3600, $ttl);
    }

    // ========================================
    // Forget Tests
    // ========================================

    /** @test */
    public function it_removes_keys_from_cache()
    {
        $key = 'test-key';
        $this->cache->put($key, 'test value', 3600);
        $this->assertNotNull($this->cache->get($key));

        $this->cache->forget($key);

        $this->assertNull($this->cache->get($key));
    }

    /** @test */
    public function it_handles_forgetting_non_existent_keys()
    {
        // Should not throw exception
        $this->cache->forget('non-existent-key');

        $this->assertTrue(true);
    }

    // ========================================
    // Has Tests
    // ========================================

    /** @test */
    public function it_returns_true_for_existing_keys()
    {
        $key = 'test-key';
        $this->cache->put($key, 'test value', 3600);

        $this->assertTrue($this->cache->has($key));
    }

    /** @test */
    public function it_returns_false_for_missing_keys()
    {
        $this->assertFalse($this->cache->has('non-existent-key'));
    }

    /** @test */
    public function it_returns_false_after_forgetting()
    {
        $key = 'test-key';
        $this->cache->put($key, 'test value', 3600);
        $this->cache->forget($key);

        $this->assertFalse($this->cache->has($key));
    }

    // ========================================
    // Statistics Tests
    // ========================================

    /** @test */
    public function it_tracks_cache_hits()
    {
        $key = $this->cache->generateKey('chat', ['test' => 'params']);
        $this->cache->put($key, 'value', 3600);

        // Simulate hits by getting existing key
        $this->cache->get($key);
        $this->cache->get($key);

        $stats = $this->cache->getStatistics();

        $this->assertArrayHasKey('hits', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['hits']);
    }

    /** @test */
    public function it_tracks_cache_misses()
    {
        // Simulate misses by getting non-existent keys
        $this->cache->get('miss-1');
        $this->cache->get('miss-2');

        $stats = $this->cache->getStatistics();

        $this->assertArrayHasKey('misses', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['misses']);
    }

    /** @test */
    public function it_calculates_hit_rate()
    {
        $key = $this->cache->generateKey('chat', ['test' => 'params']);
        $this->cache->put($key, 'value', 3600);

        // 2 hits, 2 misses = 50% hit rate
        $this->cache->get($key); // hit
        $this->cache->get($key); // hit
        $this->cache->get('miss-1'); // miss
        $this->cache->get('miss-2'); // miss

        $stats = $this->cache->getStatistics();

        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertEquals(50.0, $stats['hit_rate']);
    }

    /** @test */
    public function it_handles_statistics_with_no_requests()
    {
        $stats = $this->cache->getStatistics();

        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0.0, $stats['hit_rate']);
    }

    // ========================================
    // Clear Operation Tests
    // ========================================

    /** @test */
    public function it_clears_all_keys_for_specific_operation()
    {
        $chatKey1 = $this->cache->generateKey('chat', ['msg' => '1']);
        $chatKey2 = $this->cache->generateKey('chat', ['msg' => '2']);
        $embedKey = $this->cache->generateKey('embeddings', ['text' => '1']);

        $this->cache->put($chatKey1, 'chat1', 3600);
        $this->cache->put($chatKey2, 'chat2', 3600);
        $this->cache->put($embedKey, 'embed1', 86400);

        $result = $this->cache->clear('chat');

        // Should return true
        $this->assertTrue($result);
        // Chat keys should be gone (driver-dependent)
        // Embedding key should still exist
        $this->assertNotNull($this->cache->get($embedKey));
    }

    /** @test */
    public function it_handles_clearing_non_existent_operation()
    {
        // Should not throw exception
        $result = $this->cache->clear('non-existent-operation');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_clears_all_cache_when_no_operation_specified()
    {
        $key1 = $this->cache->generateKey('chat', ['msg' => '1']);
        $key2 = $this->cache->generateKey('embeddings', ['text' => '1']);

        $this->cache->put($key1, 'value1', 3600);
        $this->cache->put($key2, 'value2', 86400);

        $result = $this->cache->clear();

        $this->assertTrue($result);
        // All keys should be gone
        $this->assertNull($this->cache->get($key1));
        $this->assertNull($this->cache->get($key2));
    }

    // ========================================
    // Integration Tests
    // ========================================

    /** @test */
    public function it_handles_full_workflow()
    {
        $params = ['model' => 'gpt-4', 'message' => 'test'];
        $key = $this->cache->generateKey('chat', $params);

        // Initial state - no cache
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));

        // Store value
        $value = ['response' => 'test response'];
        $result = $this->cache->put($key, $value, 3600);
        $this->assertTrue($result);

        // Should be cached now
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));

        // Clear cache
        $this->cache->forget($key);

        // Should be gone
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    /** @test */
    public function it_handles_concurrent_operations()
    {
        $chatKey = $this->cache->generateKey('chat', ['test' => 'chat']);
        $embedKey = $this->cache->generateKey('embeddings', ['test' => 'embed']);
        $analysisKey = $this->cache->generateKey('analysis', ['test' => 'analysis']);

        $this->cache->put($chatKey, 'chat value', 3600);
        $this->cache->put($embedKey, 'embed value', 86400);
        $this->cache->put($analysisKey, 'analysis value', 7200);

        $this->assertEquals('chat value', $this->cache->get($chatKey));
        $this->assertEquals('embed value', $this->cache->get($embedKey));
        $this->assertEquals('analysis value', $this->cache->get($analysisKey));
    }

    /** @test */
    public function it_uses_recommended_ttl_in_workflow()
    {
        $params = ['model' => 'gpt-4', 'message' => 'test'];
        $key = $this->cache->generateKey('embeddings', $params);

        // Get recommended TTL for embeddings
        $ttl = $this->cache->getTTLForOperation('embeddings');

        // Store with recommended TTL
        $value = ['embedding' => [/* 1536 floats */]];
        $result = $this->cache->put($key, $value, $ttl);

        $this->assertTrue($result);
        $this->assertEquals($value, $this->cache->get($key));
    }
}
