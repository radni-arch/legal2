<?php

namespace App\Services\Monitoring;

use App\Exceptions\MonitoringException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alert Management Service
 *
 * Manages alerts for critical failures and threshold violations.
 * Supports multiple notification channels and rate limiting.
 *
 * Hardened with comprehensive error handling and performance monitoring
 */
class AlertManager
{
    protected const CACHE_PREFIX = 'alerts:';

    protected const ALERT_TTL = 3600; // 1 hour

    protected const RATE_LIMIT_WINDOW = 300; // 5 minutes

    /**
     * Trigger an alert
     *
     * @throws MonitoringException
     */
    public function alert(string $alertType, string $message, array $context = []): void
    {
        $startTime = microtime(true);

        Log::info('Triggering alert', [
            'alert_type' => $alertType,
            'message' => substr($message, 0, 200),
            'has_context' => ! empty($context),
        ]);

        try {
            // Check if we should rate limit this alert
            $rateLimitCheckStart = microtime(true);

            try {
                $isRateLimited = $this->isRateLimited($alertType);
                $rateLimitCheckDuration = microtime(true) - $rateLimitCheckStart;

                if ($isRateLimited) {
                    Log::debug('Alert rate limited', [
                        'type' => $alertType,
                        'message' => substr($message, 0, 200),
                        'check_duration_ms' => round($rateLimitCheckDuration * 1000, 2),
                    ]);

                    return;
                }
            } catch (\Throwable $e) {
                $rateLimitCheckDuration = microtime(true) - $rateLimitCheckStart;

                Log::error('Rate limit check failed', [
                    'alert_type' => $alertType,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'check_duration_ms' => round($rateLimitCheckDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new MonitoringException(
                    "Failed to check rate limit for alert type {$alertType}: {$e->getMessage()}",
                    MonitoringException::RATE_LIMIT_CHECK_FAILED,
                    $e
                );
            }

            // Create alert record
            $createStart = microtime(true);

            try {
                $alert = [
                    'type' => $alertType,
                    'message' => $message,
                    'context' => $context,
                    'timestamp' => now()->toIso8601String(),
                    'level' => $this->determineLevel($alertType),
                ];
                $createDuration = microtime(true) - $createStart;

                Log::debug('Alert record created', [
                    'alert_type' => $alertType,
                    'level' => $alert['level'],
                    'duration_ms' => round($createDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $createDuration = microtime(true) - $createStart;

                Log::error('Alert creation failed', [
                    'alert_type' => $alertType,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($createDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new MonitoringException(
                    "Failed to create alert record for type {$alertType}: {$e->getMessage()}",
                    MonitoringException::ALERT_CREATION_FAILED,
                    $e
                );
            }

            // Store alert
            $storeStart = microtime(true);

            try {
                $this->storeAlert($alert);
                $storeDuration = microtime(true) - $storeStart;

                Log::debug('Alert stored successfully', [
                    'alert_type' => $alertType,
                    'duration_ms' => round($storeDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $storeDuration = microtime(true) - $storeStart;

                Log::error('Alert storage failed', [
                    'alert_type' => $alertType,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($storeDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new MonitoringException(
                    "Failed to store alert for type {$alertType}: {$e->getMessage()}",
                    MonitoringException::ALERT_STORAGE_FAILED,
                    $e
                );
            }

            // Log alert
            $this->logAlert($alert);

            // Send notifications
            $notifyStart = microtime(true);

            try {
                $this->sendNotifications($alert);
                $notifyDuration = microtime(true) - $notifyStart;

                Log::debug('Alert notifications sent', [
                    'alert_type' => $alertType,
                    'duration_ms' => round($notifyDuration * 1000, 2),
                ]);
            } catch (\Throwable $e) {
                $notifyDuration = microtime(true) - $notifyStart;

                Log::error('Alert notification sending failed', [
                    'alert_type' => $alertType,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'duration_ms' => round($notifyDuration * 1000, 2),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Don't throw here - alert was stored, notification failure is non-critical
            }

            // Mark as rate limited
            try {
                $this->markRateLimited($alertType);
            } catch (\Throwable $e) {
                Log::warning('Failed to mark alert as rate limited', [
                    'alert_type' => $alertType,
                    'error' => $e->getMessage(),
                ]);
            }

            $totalDuration = microtime(true) - $startTime;

            Log::info('Alert triggered successfully', [
                'alert_type' => $alertType,
                'level' => $alert['level'],
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

        } catch (MonitoringException $e) {
            // Re-throw custom exception
            $totalDuration = microtime(true) - $startTime;

            Log::error('Alert trigger failed', [
                'alert_type' => $alertType,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Alert trigger failed with unexpected error', [
                'alert_type' => $alertType,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Unexpected error triggering alert {$alertType}: {$e->getMessage()}",
                MonitoringException::UNEXPECTED_ERROR,
                $e
            );
        }
    }

    /**
     * Get all active alerts
     *
     * @throws MonitoringException
     */
    public function getActiveAlerts(): array
    {
        $startTime = microtime(true);

        Log::info('Retrieving active alerts');

        try {
            $alerts = Cache::get(self::CACHE_PREFIX.'active', []);
            $duration = microtime(true) - $startTime;

            Log::info('Active alerts retrieved', [
                'count' => count($alerts),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $alerts;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to retrieve active alerts', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to retrieve active alerts: {$e->getMessage()}",
                MonitoringException::ALERT_RETRIEVAL_FAILED,
                $e
            );
        }
    }

    /**
     * Get alert history
     *
     * @throws MonitoringException
     */
    public function getAlertHistory(int $limit = 100): array
    {
        $startTime = microtime(true);

        Log::info('Retrieving alert history', ['limit' => $limit]);

        try {
            $history = Cache::get(self::CACHE_PREFIX.'history', []);
            $results = array_slice($history, -$limit);
            $duration = microtime(true) - $startTime;

            Log::info('Alert history retrieved', [
                'total_count' => count($history),
                'returned_count' => count($results),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $results;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to retrieve alert history', [
                'limit' => $limit,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to retrieve alert history: {$e->getMessage()}",
                MonitoringException::ALERT_RETRIEVAL_FAILED,
                $e
            );
        }
    }

    /**
     * Clear an alert
     *
     * @throws MonitoringException
     */
    public function clearAlert(string $alertType): void
    {
        $startTime = microtime(true);

        Log::info('Clearing alert', ['alert_type' => $alertType]);

        try {
            $active = $this->getActiveAlerts();
            unset($active[$alertType]);
            Cache::put(self::CACHE_PREFIX.'active', $active, self::ALERT_TTL);

            $duration = microtime(true) - $startTime;

            Log::info('Alert cleared', [
                'alert_type' => $alertType,
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to clear alert', [
                'alert_type' => $alertType,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to clear alert {$alertType}: {$e->getMessage()}",
                MonitoringException::CACHE_ACCESS_FAILED,
                $e
            );
        }
    }

    /**
     * Clear all alerts
     *
     * @throws MonitoringException
     */
    public function clearAllAlerts(): void
    {
        $startTime = microtime(true);

        Log::info('Clearing all alerts');

        try {
            Cache::forget(self::CACHE_PREFIX.'active');
            $duration = microtime(true) - $startTime;

            Log::info('All alerts cleared', [
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to clear all alerts', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to clear all alerts: {$e->getMessage()}",
                MonitoringException::CACHE_ACCESS_FAILED,
                $e
            );
        }
    }

    /**
     * Check if alert type is rate limited
     *
     * @throws \Exception
     */
    protected function isRateLimited(string $alertType): bool
    {
        $key = self::CACHE_PREFIX.'ratelimit:'.$alertType;

        return Cache::has($key);
    }

    /**
     * Mark alert type as rate limited
     *
     * @throws \Exception
     */
    protected function markRateLimited(string $alertType): void
    {
        $key = self::CACHE_PREFIX.'ratelimit:'.$alertType;
        Cache::put($key, true, self::RATE_LIMIT_WINDOW);
    }

    /**
     * Store alert in active alerts and history
     *
     * @throws \Exception
     */
    protected function storeAlert(array $alert): void
    {
        // Add to active alerts
        $active = Cache::get(self::CACHE_PREFIX.'active', []);
        $active[$alert['type']] = $alert;
        Cache::put(self::CACHE_PREFIX.'active', $active, self::ALERT_TTL);

        // Add to history
        $history = Cache::get(self::CACHE_PREFIX.'history', []);
        $history[] = $alert;

        // Keep only last 1000 alerts in history
        if (count($history) > 1000) {
            $history = array_slice($history, -1000);
        }

        Cache::put(self::CACHE_PREFIX.'history', $history, self::ALERT_TTL * 24); // 24 hours
    }

    /**
     * Log alert
     */
    protected function logAlert(array $alert): void
    {
        $level = $alert['level'];
        $message = "[ALERT:{$alert['type']}] {$alert['message']}";

        match ($level) {
            'critical' => Log::critical($message, $alert['context']),
            'error' => Log::error($message, $alert['context']),
            'warning' => Log::warning($message, $alert['context']),
            default => Log::info($message, $alert['context']),
        };
    }

    /**
     * Send notifications for alert
     *
     * @throws MonitoringException
     */
    protected function sendNotifications(array $alert): void
    {
        $startTime = microtime(true);

        try {
            $channels = $this->getNotificationChannels($alert['level']);

            Log::debug('Sending notifications to channels', [
                'alert_type' => $alert['type'],
                'channels' => $channels,
            ]);

            foreach ($channels as $channel) {
                $channelStart = microtime(true);

                try {
                    $this->sendToChannel($channel, $alert);
                    $channelDuration = microtime(true) - $channelStart;

                    Log::debug('Notification sent to channel', [
                        'channel' => $channel,
                        'alert_type' => $alert['type'],
                        'duration_ms' => round($channelDuration * 1000, 2),
                    ]);
                } catch (\Throwable $e) {
                    $channelDuration = microtime(true) - $channelStart;

                    Log::error('Failed to send notification to channel', [
                        'channel' => $channel,
                        'alert_type' => $alert['type'],
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'duration_ms' => round($channelDuration * 1000, 2),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Continue with other channels
                }
            }

            $totalDuration = microtime(true) - $startTime;

            Log::debug('All notifications sent', [
                'alert_type' => $alert['type'],
                'channels_count' => count($channels),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;

            Log::error('Failed to send alert notification', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'alert' => $alert,
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to send notifications for alert {$alert['type']}: {$e->getMessage()}",
                MonitoringException::NOTIFICATION_SEND_FAILED,
                $e
            );
        }
    }

    /**
     * Send alert to specific channel
     *
     * @throws \Exception
     */
    protected function sendToChannel(string $channel, array $alert): void
    {
        match ($channel) {
            'log' => $this->sendToLog($alert),
            'email' => $this->sendToEmail($alert),
            'slack' => $this->sendToSlack($alert),
            default => null,
        };
    }

    /**
     * Send alert to log (already done in logAlert, but can be enhanced)
     */
    protected function sendToLog(array $alert): void
    {
        // Already logged in logAlert()
    }

    /**
     * Send alert via email
     *
     * @throws MonitoringException
     */
    protected function sendToEmail(array $alert): void
    {
        $recipients = config('monitoring.alert_email', []);

        if (empty($recipients)) {
            Log::debug('No email recipients configured, skipping email notification');

            return;
        }

        $startTime = microtime(true);

        try {
            Mail::raw(
                $this->formatAlertForEmail($alert),
                function ($message) use ($alert, $recipients) {
                    $message->to($recipients)
                        ->subject("[{$alert['level']}] Application Alert: {$alert['type']}");
                }
            );

            $duration = microtime(true) - $startTime;

            Log::info('Email alert sent successfully', [
                'alert_type' => $alert['type'],
                'recipients_count' => count($recipients),
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to send email alert', [
                'alert_type' => $alert['type'],
                'recipients' => $recipients,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to send email alert for {$alert['type']}: {$e->getMessage()}",
                MonitoringException::EMAIL_SEND_FAILED,
                $e
            );
        }
    }

    /**
     * Send alert to Slack
     *
     * @throws MonitoringException
     */
    protected function sendToSlack(array $alert): void
    {
        $webhookUrl = config('monitoring.slack_webhook');

        if (empty($webhookUrl)) {
            Log::debug('No Slack webhook configured, skipping Slack notification');

            return;
        }

        $startTime = microtime(true);

        try {
            $payload = [
                'text' => "*[{$alert['level']}] Application Alert*",
                'attachments' => [
                    [
                        'color' => $this->getSlackColor($alert['level']),
                        'fields' => [
                            [
                                'title' => 'Type',
                                'value' => $alert['type'],
                                'short' => true,
                            ],
                            [
                                'title' => 'Time',
                                'value' => $alert['timestamp'],
                                'short' => true,
                            ],
                            [
                                'title' => 'Message',
                                'value' => $alert['message'],
                                'short' => false,
                            ],
                        ],
                    ],
                ],
            ];

            Http::post($webhookUrl, $payload);

            $duration = microtime(true) - $startTime;

            Log::info('Slack alert sent successfully', [
                'alert_type' => $alert['type'],
                'duration_ms' => round($duration * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Failed to send Slack alert', [
                'alert_type' => $alert['type'],
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round($duration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MonitoringException(
                "Failed to send Slack alert for {$alert['type']}: {$e->getMessage()}",
                MonitoringException::SLACK_SEND_FAILED,
                $e
            );
        }
    }

    /**
     * Determine alert level based on type
     */
    protected function determineLevel(string $alertType): string
    {
        return match ($alertType) {
            'critical_exception',
            'database_connection_failed',
            'service_unavailable' => 'critical',

            'high_error_rate',
            'high_query_count',
            'high_job_failure_rate' => 'error',

            'slow_request',
            'slow_query',
            'cache_miss_rate_high' => 'warning',

            default => 'info',
        };
    }

    /**
     * Get notification channels for alert level
     */
    protected function getNotificationChannels(string $level): array
    {
        return match ($level) {
            'critical' => ['log', 'email', 'slack'],
            'error' => ['log', 'email'],
            'warning' => ['log'],
            default => ['log'],
        };
    }

    /**
     * Format alert for email
     */
    protected function formatAlertForEmail(array $alert): string
    {
        $context = json_encode($alert['context'], JSON_PRETTY_PRINT);

        return <<<EMAIL
Application Alert

Type: {$alert['type']}
Level: {$alert['level']}
Time: {$alert['timestamp']}

Message:
{$alert['message']}

Context:
{$context}

---
This is an automated alert from the AI Legal War Machine application.
EMAIL;
    }

    /**
     * Get Slack color for alert level
     */
    protected function getSlackColor(string $level): string
    {
        return match ($level) {
            'critical' => 'danger',
            'error' => 'warning',
            'warning' => '#ffcc00',
            default => 'good',
        };
    }
}
