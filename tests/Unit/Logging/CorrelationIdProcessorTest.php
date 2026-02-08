<?php

namespace Tests\Unit\Logging;

use App\Logging\CorrelationIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Tests for CorrelationIdProcessor
 *
 * Sprint 1.7: Observability Setup
 */
class CorrelationIdProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset correlation ID before each test
        CorrelationIdProcessor::resetCorrelationId();
    }

    protected function tearDown(): void
    {
        // Reset correlation ID after each test
        CorrelationIdProcessor::resetCorrelationId();

        parent::tearDown();
    }

    /** @test */
    public function it_generates_correlation_id_if_not_set(): void
    {
        $processor = new CorrelationIdProcessor;

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertArrayHasKey('correlation_id', $processedRecord->context);
        $this->assertNotEmpty($processedRecord->context['correlation_id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $processedRecord->context['correlation_id']
        );
    }

    /** @test */
    public function it_uses_existing_correlation_id(): void
    {
        $customId = 'custom-correlation-id-123';
        CorrelationIdProcessor::setCorrelationId($customId);

        $processor = new CorrelationIdProcessor;

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertEquals($customId, $processedRecord->context['correlation_id']);
    }

    /** @test */
    public function it_preserves_existing_context(): void
    {
        $processor = new CorrelationIdProcessor;

        $originalContext = [
            'user_id' => 123,
            'action' => 'test_action',
        ];

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: $originalContext,
            extra: []
        );

        $processedRecord = $processor($record);

        // Original context should be preserved
        $this->assertEquals(123, $processedRecord->context['user_id']);
        $this->assertEquals('test_action', $processedRecord->context['action']);

        // Correlation ID should be added
        $this->assertArrayHasKey('correlation_id', $processedRecord->context);
    }

    /** @test */
    public function it_adds_additional_context(): void
    {
        $additionalContext = [
            'environment' => 'testing',
            'app_version' => '1.0.0',
        ];

        $processor = new CorrelationIdProcessor($additionalContext);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertEquals('testing', $processedRecord->context['environment']);
        $this->assertEquals('1.0.0', $processedRecord->context['app_version']);
        $this->assertArrayHasKey('correlation_id', $processedRecord->context);
    }

    /** @test */
    public function it_can_set_and_get_correlation_id(): void
    {
        $testId = 'test-correlation-id-456';

        $returnedId = CorrelationIdProcessor::setCorrelationId($testId);

        $this->assertEquals($testId, $returnedId);
        $this->assertEquals($testId, CorrelationIdProcessor::getCorrelationId());
    }

    /** @test */
    public function it_can_reset_correlation_id(): void
    {
        $testId = 'test-correlation-id-789';
        CorrelationIdProcessor::setCorrelationId($testId);

        $this->assertEquals($testId, CorrelationIdProcessor::getCorrelationId());

        CorrelationIdProcessor::resetCorrelationId();

        // After reset, should generate new UUID
        $newId = CorrelationIdProcessor::getCorrelationId();
        $this->assertNotEquals($testId, $newId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $newId
        );
    }

    /** @test */
    public function it_returns_same_correlation_id_across_multiple_calls(): void
    {
        $processor = new CorrelationIdProcessor;

        $record1 = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'First message',
            context: [],
            extra: []
        );

        $record2 = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Second message',
            context: [],
            extra: []
        );

        $processedRecord1 = $processor($record1);
        $processedRecord2 = $processor($record2);

        $this->assertEquals(
            $processedRecord1->context['correlation_id'],
            $processedRecord2->context['correlation_id']
        );
    }

    /** @test */
    public function it_can_get_and_set_additional_context(): void
    {
        $initialContext = ['key1' => 'value1'];
        $processor = new CorrelationIdProcessor($initialContext);

        $this->assertEquals($initialContext, $processor->getAdditionalContext());

        $newContext = ['key2' => 'value2'];
        $processor->setAdditionalContext($newContext);

        $this->assertEquals($newContext, $processor->getAdditionalContext());
    }

    /** @test */
    public function it_can_merge_additional_context(): void
    {
        $initialContext = ['key1' => 'value1'];
        $processor = new CorrelationIdProcessor($initialContext);

        $mergeContext = ['key2' => 'value2', 'key3' => 'value3'];
        $processor->mergeAdditionalContext($mergeContext);

        $expectedContext = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->assertEquals($expectedContext, $processor->getAdditionalContext());
    }

    /** @test */
    public function it_uses_correlation_id_from_request_header_if_available(): void
    {
        $headerCorrelationId = 'header-correlation-id-999';

        // Simulate HTTP request with correlation ID header
        $this->app['request']->headers->set(
            CorrelationIdProcessor::HEADER_NAME,
            $headerCorrelationId
        );

        $processor = new CorrelationIdProcessor;

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        $processedRecord = $processor($record);

        $this->assertEquals($headerCorrelationId, $processedRecord->context['correlation_id']);
    }
}
