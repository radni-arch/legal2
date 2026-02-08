<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Honeypot Alert Threshold
    |--------------------------------------------------------------------------
    |
    | Number of honeypot triggers from a single IP within one hour before
    | raising a critical alert. Default is 5 attempts.
    |
    */
    'alert_threshold' => env('HONEYPOT_ALERT_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Auto-Block Threshold
    |--------------------------------------------------------------------------
    |
    | Number of honeypot triggers from a single IP within one hour before
    | automatically marking it as blocked. Set to 0 to disable auto-blocking.
    |
    */
    'auto_block_threshold' => env('HONEYPOT_AUTO_BLOCK_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Log Retention Days
    |--------------------------------------------------------------------------
    |
    | Number of days to keep honeypot logs before they can be pruned.
    | Set to 0 to keep logs indefinitely.
    |
    */
    'log_retention_days' => env('HONEYPOT_LOG_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Channels to use for honeypot alerts. Options: 'log', 'mail', 'slack'
    | You can enable multiple channels.
    |
    */
    'notification_channels' => [
        'log' => true,
        'mail' => env('HONEYPOT_MAIL_NOTIFICATIONS', false),
        'slack' => env('HONEYPOT_SLACK_NOTIFICATIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Email
    |--------------------------------------------------------------------------
    |
    | Email address to send honeypot alerts to.
    |
    */
    'alert_email' => env('HONEYPOT_ALERT_EMAIL', 'admin@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Slack Webhook URL
    |--------------------------------------------------------------------------
    |
    | Slack webhook URL for sending honeypot alerts.
    |
    */
    'slack_webhook_url' => env('HONEYPOT_SLACK_WEBHOOK'),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the honeypot system entirely.
    |
    */
    'enabled' => env('HONEYPOT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Whitelist IPs
    |--------------------------------------------------------------------------
    |
    | IP addresses that should never be logged by the honeypot.
    | Useful for internal monitoring tools.
    |
    */
    'whitelist_ips' => [
        '127.0.0.1',
        '::1',
        // Add your monitoring IPs here
    ],

    /*
    |--------------------------------------------------------------------------
    | Severity Levels
    |--------------------------------------------------------------------------
    |
    | Map endpoint patterns to severity levels.
    | Higher severity triggers more aggressive alerts.
    |
    */
    'severity_levels' => [
        'critical' => ['admin/login', 'admin/users', '.env', 'database/dump'],
        'high' => ['admin/*', 'config/*', 'phpinfo', 'exec', 'cmd'],
        'medium' => ['debug/*', 'backup*', 'credentials'],
        'low' => ['wp-admin', 'phpmyadmin'],
    ],
];
