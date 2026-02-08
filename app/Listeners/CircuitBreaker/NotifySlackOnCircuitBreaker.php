<?php

namespace App\Listeners\CircuitBreaker;

use App\Events\CircuitBreaker\CircuitBreakerOpened;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send Slack notifications when circuit breaker opens
 *
 * This listener sends critical alerts to Slack when a circuit breaker opens,
 * enabling immediate response to service degradation.
 */
class NotifySlackOnCircuitBreaker
{
    /**
     * Handle the circuit breaker opened event
     */
    public function handle(CircuitBreakerOpened $event): void
    {
        try {
            $webhookUrl = config('services.slack.circuit_breaker_webhook');

            if (empty($webhookUrl)) {
                Log::debug('Slack webhook not configured, skipping notification', [
                    'service' => $event->serviceName,
                ]);

                return;
            }

            $payload = [
                'text' => '🚨 Circuit Breaker Alert',
                'attachments' => [
                    [
                        'color' => 'danger',
                        'title' => "Circuit Breaker OPENED: {$event->serviceName}",
                        'fields' => [
                            [
                                'title' => 'Service',
                                'value' => $event->serviceName,
                                'short' => true,
                            ],
                            [
                                'title' => 'Failure Count',
                                'value' => (string) $event->failureCount,
                                'short' => true,
                            ],
                            [
                                'title' => 'Opened At',
                                'value' => $event->openedAt->toDateTimeString(),
                                'short' => true,
                            ],
                            [
                                'title' => 'Retry After',
                                'value' => "{$event->retryAfterSeconds} seconds",
                                'short' => true,
                            ],
                            [
                                'title' => 'Last Error',
                                'value' => $event->lastError['message'] ?? 'Unknown error',
                                'short' => false,
                            ],
                        ],
                        'footer' => 'Circuit Breaker Monitoring',
                        'footer_icon' => 'https://platform.slack-edge.com/img/default_application_icon.png',
                        'ts' => $event->openedAt->timestamp,
                    ],
                ],
            ];

            $response = Http::timeout(5)->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Slack notification sent for circuit breaker opening', [
                    'service' => $event->serviceName,
                ]);
            } else {
                Log::warning('Failed to send Slack notification', [
                    'service' => $event->serviceName,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exception while sending Slack notification', [
                'service' => $event->serviceName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            // Don't throw - notification failure shouldn't block the application
        }
    }
}
