<?php

namespace App\Listeners;

use App\Events\Neo4jUnavailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyNeo4jDowntime
{
    /**
     * Handle the event.
     */
    public function handle(Neo4jUnavailable $event): void
    {
        // Log the downtime with full context
        Log::critical('Neo4j database is unavailable', [
            'error' => $event->error,
            'health_status' => $event->healthStatus,
            'constraints_exist' => $event->constraintsExist,
            'indexes_exist' => $event->indexesExist,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Send Slack notification if configured
        $this->sendSlackNotification($event);

        // Send to Telescope if available
        $this->logToTelescope($event);
    }

    /**
     * Send notification to Slack if configured
     */
    protected function sendSlackNotification(Neo4jUnavailable $event): void
    {
        $slackWebhook = config('services.slack.neo4j_webhook');
        $slackToken = config('services.slack.notifications.bot_user_oauth_token');
        $slackChannel = config('services.slack.notifications.channel');

        // Support both webhook and bot token approaches
        if ($slackWebhook) {
            try {
                Http::post($slackWebhook, [
                    'text' => '🔴 *Neo4j Database Alert*',
                    'blocks' => [
                        [
                            'type' => 'header',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => '🔴 Neo4j Database Unavailable',
                            ],
                        ],
                        [
                            'type' => 'section',
                            'fields' => [
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Error:*\n{$event->error}",
                                ],
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Time:*\n".now()->format('Y-m-d H:i:s'),
                                ],
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Constraints:*\n".($event->constraintsExist ? '✅ Exist' : '❌ Missing'),
                                ],
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Indexes:*\n".($event->indexesExist ? '✅ Exist' : '❌ Missing'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'section',
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => "*Health Status:*\n```".json_encode($event->healthStatus, JSON_PRETTY_PRINT).'```',
                            ],
                        ],
                    ],
                ]);

                Log::info('Neo4j downtime notification sent to Slack webhook');
            } catch (\Exception $e) {
                Log::warning('Failed to send Slack webhook notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($slackToken && $slackChannel) {
            try {
                Http::withHeaders([
                    'Authorization' => "Bearer {$slackToken}",
                    'Content-Type' => 'application/json',
                ])->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $slackChannel,
                    'text' => '🔴 *Neo4j Database Alert*',
                    'blocks' => [
                        [
                            'type' => 'header',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => '🔴 Neo4j Database Unavailable',
                            ],
                        ],
                        [
                            'type' => 'section',
                            'fields' => [
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Error:*\n{$event->error}",
                                ],
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Time:*\n".now()->format('Y-m-d H:i:s'),
                                ],
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Constraints:*\n".($event->constraintsExist ? '✅ Exist' : '❌ Missing'),
                                ],
                                [
                                    'type' => 'mrkdwn',
                                    'text' => "*Indexes:*\n".($event->indexesExist ? '✅ Exist' : '❌ Missing'),
                                ],
                            ],
                        ],
                    ],
                ]);

                Log::info('Neo4j downtime notification sent to Slack via bot token');
            } catch (\Exception $e) {
                Log::warning('Failed to send Slack bot notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Log to Telescope for monitoring
     */
    protected function logToTelescope(Neo4jUnavailable $event): void
    {
        // Telescope will automatically capture this via the log entry
        // We can also add custom telescope entries if needed
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::recordException(
                new \RuntimeException('Neo4j database unavailable: '.$event->error)
            );
        }
    }
}
