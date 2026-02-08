<?php

namespace Tests\Unit\Services\Monitoring;

use App\Services\Monitoring\AlertManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sprint 6.7: Production Monitoring Setup - Alert Delivery Tests
 *
 * These tests verify that alerts are properly delivered via email and Slack
 * when critical thresholds are exceeded.
 */
class AlertDeliveryTest extends TestCase
{
    protected AlertManager $alertManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alertManager = app(AlertManager::class);

        // Clear any existing alerts
        $this->alertManager->clearAllAlerts();
    }

    /**
     * Test that critical alerts trigger email notifications
     */
    public function test_critical_alert_sends_email(): void
    {
        // Configure email recipients
        config(['monitoring.alert_email' => ['admin@example.com']]);

        Mail::fake();

        // Trigger a critical alert
        $this->alertManager->alert(
            'critical_exception',
            'Database connection failed',
            ['database' => 'postgres', 'error' => 'Connection refused']
        );

        // Assert email was sent
        Mail::assertSent(function ($mail) {
            return str_contains($mail->subject, 'critical')
                && str_contains($mail->subject, 'critical_exception');
        });
    }

    /**
     * Test that critical alerts trigger Slack notifications
     */
    public function test_critical_alert_sends_slack_notification(): void
    {
        // Configure Slack webhook
        config(['monitoring.slack_webhook' => 'https://hooks.slack.com/services/test']);

        Http::fake([
            'hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        // Trigger a critical alert
        $this->alertManager->alert(
            'database_connection_failed',
            'Cannot connect to database',
            ['host' => 'localhost', 'port' => 5432]
        );

        // Assert Slack webhook was called
        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/services/test'
                && $request->hasHeader('Content-Type', 'application/json')
                && str_contains($request->body(), 'database_connection_failed');
        });
    }

    /**
     * Test that error alerts send email but not Slack
     */
    public function test_error_alert_sends_email_only(): void
    {
        config([
            'monitoring.alert_email' => ['admin@example.com'],
            'monitoring.slack_webhook' => 'https://hooks.slack.com/services/test',
        ]);

        Mail::fake();
        Http::fake();

        // Trigger an error-level alert
        $this->alertManager->alert(
            'high_error_rate',
            'Error rate exceeded threshold',
            ['error_rate' => 15.5]
        );

        // Assert email was sent
        Mail::assertSent(function ($mail) {
            return str_contains($mail->subject, 'error');
        });

        // Assert Slack was NOT called (error alerts only go to email)
        Http::assertNothingSent();
    }

    /**
     * Test that warning alerts send to log only
     */
    public function test_warning_alert_logs_only(): void
    {
        config([
            'monitoring.alert_email' => ['admin@example.com'],
            'monitoring.slack_webhook' => 'https://hooks.slack.com/services/test',
        ]);

        Mail::fake();
        Http::fake();

        // Trigger a warning-level alert
        $this->alertManager->alert(
            'slow_request',
            'Request took longer than expected',
            ['duration_ms' => 2500]
        );

        // Assert no email sent
        Mail::assertNothingSent();

        // Assert no Slack message sent
        Http::assertNothingSent();
    }

    /**
     * Test alert rate limiting prevents spam
     */
    public function test_alert_rate_limiting_prevents_spam(): void
    {
        config(['monitoring.alert_email' => ['admin@example.com']]);

        Mail::fake();

        // Send first alert
        $this->alertManager->alert(
            'high_error_rate',
            'First alert',
            []
        );

        // Send second alert immediately (should be rate limited)
        $this->alertManager->alert(
            'high_error_rate',
            'Second alert',
            []
        );

        // Assert only one email was sent
        Mail::assertSentCount(1);
    }

    /**
     * Test alert contains proper context information
     */
    public function test_alert_contains_context_information(): void
    {
        config(['monitoring.alert_email' => ['admin@example.com']]);

        Mail::fake();

        $context = [
            'agent_name' => 'AutonomousResearchAgent',
            'failure_rate' => 15.5,
            'total_runs' => 100,
            'failed_runs' => 16,
        ];

        $this->alertManager->alert(
            'agent_high_failure_rate',
            'Agent failure rate exceeded threshold',
            $context
        );

        Mail::assertSent(function ($mail) use ($context) {
            $body = $mail->render();

            return str_contains($body, 'agent_high_failure_rate')
                && str_contains($body, (string) $context['failure_rate'])
                && str_contains($body, $context['agent_name']);
        });
    }

    /**
     * Test Slack alert has proper formatting
     */
    public function test_slack_alert_has_proper_formatting(): void
    {
        config(['monitoring.slack_webhook' => 'https://hooks.slack.com/services/test']);

        Http::fake([
            'hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $this->alertManager->alert(
            'service_unavailable',
            'OpenAI API is not responding',
            ['service' => 'OpenAI', 'status_code' => 503]
        );

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            // Check Slack message structure
            $this->assertArrayHasKey('text', $body);
            $this->assertArrayHasKey('attachments', $body);
            $this->assertIsArray($body['attachments']);

            $attachment = $body['attachments'][0];
            $this->assertArrayHasKey('color', $attachment);
            $this->assertEquals('danger', $attachment['color']); // Critical alert = danger color

            $fields = $attachment['fields'];
            $this->assertGreaterThan(0, count($fields));

            // Check required fields exist
            $fieldTitles = array_column($fields, 'title');
            $this->assertContains('Type', $fieldTitles);
            $this->assertContains('Message', $fieldTitles);

            return true;
        });
    }

    /**
     * Test alert history is maintained
     */
    public function test_alert_history_is_maintained(): void
    {
        // Trigger multiple alerts
        $this->alertManager->alert('test_alert_1', 'First alert', []);
        $this->alertManager->alert('test_alert_2', 'Second alert', []);
        $this->alertManager->alert('test_alert_3', 'Third alert', []);

        // Get alert history
        $history = $this->alertManager->getAlertHistory();

        // Assert history contains all alerts
        $this->assertCount(3, $history);
        $this->assertEquals('test_alert_1', $history[0]['type']);
        $this->assertEquals('test_alert_2', $history[1]['type']);
        $this->assertEquals('test_alert_3', $history[2]['type']);
    }

    /**
     * Test active alerts can be retrieved
     */
    public function test_active_alerts_can_be_retrieved(): void
    {
        // Trigger alerts
        $this->alertManager->alert('alert_1', 'Alert 1', []);
        $this->alertManager->alert('alert_2', 'Alert 2', []);

        // Get active alerts
        $active = $this->alertManager->getActiveAlerts();

        // Assert both alerts are active
        $this->assertArrayHasKey('alert_1', $active);
        $this->assertArrayHasKey('alert_2', $active);
        $this->assertEquals('Alert 1', $active['alert_1']['message']);
        $this->assertEquals('Alert 2', $active['alert_2']['message']);
    }

    /**
     * Test alerts can be cleared individually
     */
    public function test_alerts_can_be_cleared_individually(): void
    {
        // Trigger multiple alerts
        $this->alertManager->alert('alert_1', 'Alert 1', []);
        $this->alertManager->alert('alert_2', 'Alert 2', []);

        // Clear one alert
        $this->alertManager->clearAlert('alert_1');

        // Check remaining alerts
        $active = $this->alertManager->getActiveAlerts();

        $this->assertArrayNotHasKey('alert_1', $active);
        $this->assertArrayHasKey('alert_2', $active);
    }

    /**
     * Test all alerts can be cleared at once
     */
    public function test_all_alerts_can_be_cleared(): void
    {
        // Trigger multiple alerts
        $this->alertManager->alert('alert_1', 'Alert 1', []);
        $this->alertManager->alert('alert_2', 'Alert 2', []);
        $this->alertManager->alert('alert_3', 'Alert 3', []);

        // Clear all alerts
        $this->alertManager->clearAllAlerts();

        // Check no active alerts
        $active = $this->alertManager->getActiveAlerts();

        $this->assertEmpty($active);
    }

    /**
     * Test email alert formatting
     */
    public function test_email_alert_formatting(): void
    {
        config(['monitoring.alert_email' => ['admin@example.com']]);

        Mail::fake();

        $this->alertManager->alert(
            'high_api_error_rate',
            'API error rate is 7.5%',
            [
                'error_rate' => 7.5,
                'threshold' => 5.0,
                'total_requests' => 1000,
                'error_requests' => 75,
            ]
        );

        Mail::assertSent(function ($mail) {
            $body = $mail->render();

            // Check email contains all required information
            $this->assertStringContainsString('high_api_error_rate', $body);
            $this->assertStringContainsString('API error rate is 7.5%', $body);
            $this->assertStringContainsString('7.5', $body);
            $this->assertStringContainsString('error_rate', $body);

            return true;
        });
    }

    /**
     * Test that alert delivery handles network failures gracefully
     */
    public function test_alert_delivery_handles_network_failures(): void
    {
        config(['monitoring.slack_webhook' => 'https://hooks.slack.com/services/test']);

        // Simulate network failure
        Http::fake([
            'hooks.slack.com/*' => Http::response(null, 500),
        ]);

        // This should not throw an exception
        $this->alertManager->alert(
            'test_alert',
            'Test message',
            []
        );

        // Alert should still be stored even if notification fails
        $active = $this->alertManager->getActiveAlerts();
        $this->assertArrayHasKey('test_alert', $active);
    }

    /**
     * Test that alerts work without email configured
     */
    public function test_alerts_work_without_email_configured(): void
    {
        config(['monitoring.alert_email' => []]);

        Mail::fake();

        // Should not throw exception
        $this->alertManager->alert(
            'test_alert',
            'Test message',
            []
        );

        // No email should be sent
        Mail::assertNothingSent();

        // Alert should still be logged
        $active = $this->alertManager->getActiveAlerts();
        $this->assertArrayHasKey('test_alert', $active);
    }

    /**
     * Test that alerts work without Slack configured
     */
    public function test_alerts_work_without_slack_configured(): void
    {
        config(['monitoring.slack_webhook' => null]);

        Http::fake();

        // Should not throw exception
        $this->alertManager->alert(
            'critical_exception',
            'Test message',
            []
        );

        // No Slack request should be made
        Http::assertNothingSent();

        // Alert should still be logged
        $active = $this->alertManager->getActiveAlerts();
        $this->assertArrayHasKey('critical_exception', $active);
    }
}
