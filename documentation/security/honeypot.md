# Honeypot Security System Documentation

## Overview

The API Honeypot is a creative security system designed to detect, log, and monitor unauthorized access attempts to your application. It works by creating fake "attractive" endpoints that legitimate users would never access, but attackers commonly target.

## What is a Honeypot?

A honeypot is a cybersecurity mechanism that sets traps to detect, deflect, or study hacking attempts. Our implementation creates fake API endpoints that:
- Look like real sensitive data or vulnerabilities
- Log all access attempts with full details
- Alert administrators of potential threats
- Help identify attack patterns and techniques

## Features

### 🎯 Creative Fake Endpoints

The honeypot includes 30+ fake endpoints designed to attract different types of attackers:

**Admin Endpoints** (High Priority Targets):
- `/api/admin/login` - Fake admin authentication
- `/api/admin/users` - Fake user list with credentials
- `/api/admin/config` - Fake configuration with secrets
- `/api/admin/backup` - Fake database backup
- `/api/admin/keys` - Fake API keys

**Configuration Files** (Extremely Attractive):
- `/api/.env` - Fake environment file with credentials
- `/api/config/database` - Fake database configuration
- `/api/.git/config` - Fake git configuration with tokens

**Debug Endpoints** (Common Scanner Targets):
- `/api/debug/info` - Fake debug information
- `/api/phpinfo` - Fake PHP configuration
- `/api/info.php` - Alternate PHP info endpoint

**Database Endpoints**:
- `/api/database/dump` - Fake SQL dump
- `/api/db/backup` - Fake database backup
- `/api/backup.sql` - Fake SQL file

**Vulnerable Endpoints** (SQL Injection/RCE):
- `/api/user?user_id=1` - Fake SQL injection vulnerability
- `/api/exec?command=whoami` - Fake command execution
- `/api/cmd` - Fake command endpoint

**Cloud/Infrastructure**:
- `/api/aws/credentials` - Fake AWS credentials
- `/api/s3/config` - Fake S3 configuration

**Common CMS Paths** (WordPress, phpMyAdmin):
- `/api/wp-admin` - Fake WordPress admin
- `/api/wp-login.php` - Fake WordPress login
- `/api/phpmyadmin` - Fake phpMyAdmin
- `/api/pma` - Fake phpMyAdmin shorthand

### 📊 Comprehensive Logging

Every honeypot access is logged with:
- **IP Address** - Attacker's IP
- **User Agent** - Browser/tool information
- **HTTP Method** - GET, POST, PUT, DELETE, etc.
- **Full URL** - Complete request URL
- **Headers** - All HTTP headers
- **Query Parameters** - URL parameters
- **Request Body** - POST data
- **Attempted Authentication** - Bearer tokens, API keys, basic auth
- **Timestamp** - Exact time of attempt
- **Referer** - Where the request came from

### 🎨 Monitoring Dashboard

Access the honeypot dashboard at `/honeypot` to view:

**Statistics Cards**:
- Total attack attempts
- Unique attacker IPs
- Attempts in last 24 hours
- Attempts in last 7 days
- Number of blocked IPs
- Average attempts per IP

**Top Targeted Paths**:
- Most frequently accessed fake endpoints
- Attack pattern identification

**Top Attacking IPs**:
- Most aggressive attackers
- Quick block functionality
- Link to detailed IP analysis

**Recent Activity Feed**:
- Live stream of honeypot triggers
- Detailed request information
- Export functionality (JSON)

### 🚨 Alerting System

The system can alert administrators when suspicious activity is detected:

**Alert Triggers**:
- Multiple attempts from same IP (default: 5+ per hour)
- Critical endpoint access (.env, database dumps)
- Auto-blocking threshold reached (default: 10+ per hour)

**Alert Channels** (Configurable):
- Laravel log files (`storage/logs/honeypot.log`)
- Email notifications
- Slack webhooks

### 🔒 Auto-Blocking

When an IP exceeds the auto-block threshold:
1. All attempts from that IP are marked as `is_blocked = true`
2. Critical alerts are logged
3. Can be integrated with firewall rules

## Installation & Setup

### 1. Run the Migration

```bash
php artisan migrate
```

This creates the `honeypot_logs` table for storing attack attempts.

### 2. Configure Environment Variables

Add to your `.env` file:

```env
# Honeypot Configuration
HONEYPOT_ENABLED=true
HONEYPOT_ALERT_THRESHOLD=5
HONEYPOT_AUTO_BLOCK_THRESHOLD=10
HONEYPOT_LOG_RETENTION_DAYS=90

# Notifications (Optional)
HONEYPOT_MAIL_NOTIFICATIONS=false
HONEYPOT_ALERT_EMAIL=security@your-domain.com
HONEYPOT_SLACK_NOTIFICATIONS=false
HONEYPOT_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### 3. Test the Honeypot

Try accessing a fake endpoint (use curl or browser):

```bash
# This will trigger the honeypot and log the attempt
curl http://your-domain.com/api/.env

# Check the honeypot dashboard
# Visit: http://your-domain.com/honeypot
```

## Configuration

Edit `config/honeypot.php` to customize behavior:

```php
return [
    // Number of triggers before raising critical alert
    'alert_threshold' => 5,

    // Number of triggers before auto-blocking IP
    'auto_block_threshold' => 10,

    // Days to keep logs (0 = forever)
    'log_retention_days' => 90,

    // Notification channels
    'notification_channels' => [
        'log' => true,
        'mail' => false,
        'slack' => false,
    ],

    // IPs to never log (internal monitoring)
    'whitelist_ips' => [
        '127.0.0.1',
        '::1',
    ],

    // Endpoint severity levels
    'severity_levels' => [
        'critical' => ['admin/login', '.env'],
        'high' => ['admin/*', 'config/*'],
        'medium' => ['debug/*', 'backup*'],
        'low' => ['wp-admin'],
    ],
];
```

## Honeypot Endpoints Reference

### Admin Endpoints
| Endpoint | Method | Fake Response |
|----------|--------|---------------|
| `/api/admin/login` | POST | Invalid credentials error |
| `/api/admin/users` | GET | Fake user list with hashes |
| `/api/admin/config` | GET | Fake config with "passwords" |
| `/api/admin/backup` | GET/POST | Fake backup file info |
| `/api/admin/keys` | GET | Fake API keys |

### Configuration Files
| Endpoint | Method | Fake Response |
|----------|--------|---------------|
| `/api/.env` | GET | Fake .env file content |
| `/api/env` | GET | Fake environment variables |
| `/api/config/database` | GET | Fake DB credentials |
| `/api/.git/config` | GET | Fake git config with tokens |

### Debug Endpoints
| Endpoint | Method | Fake Response |
|----------|--------|---------------|
| `/api/debug` | GET | Fake debug info |
| `/api/phpinfo` | GET | Fake PHP info page |
| `/api/phpinfo.php` | GET | Fake PHP info page |

### Database Endpoints
| Endpoint | Method | Fake Response |
|----------|--------|---------------|
| `/api/database/dump` | GET | Fake SQL dump |
| `/api/db/backup` | GET | Fake backup metadata |
| `/api/backup.sql` | GET | Fake SQL file |

### Vulnerable Endpoints
| Endpoint | Method | Fake Response |
|----------|--------|---------------|
| `/api/user` | GET/POST | Fake SQL query result |
| `/api/exec` | GET/POST | Fake command output |
| `/api/cmd` | POST | Fake command execution |

### WordPress/CMS
| Endpoint | Method | Fake Response |
|----------|--------|---------------|
| `/api/wp-admin` | ANY | Fake WP login |
| `/api/wp-login.php` | ANY | Fake WP login |
| `/api/phpmyadmin` | ANY | Fake phpMyAdmin |
| `/api/pma` | ANY | Fake phpMyAdmin |

## Dashboard Features

### Main Dashboard (`/honeypot`)

**Statistics Overview**:
- Real-time attack metrics
- Trend analysis
- Threat assessment

**Top Targeted Paths**:
- Identify which fake endpoints attract most attacks
- Understand attacker objectives

**Top Attacking IPs**:
- Identify persistent attackers
- One-click IP blocking
- View detailed IP history

**Recent Activity**:
- Live feed of attempts
- Detailed request inspection
- Export data for analysis

### IP Detail View (`/honeypot/ip/{ip}`)

View detailed information about a specific IP:
- Total attempts
- First and last seen dates
- All paths attempted
- HTTP methods used
- User agents (tools/browsers)
- Block status
- Full request history

### Export Functionality (`/honeypot/export`)

Export honeypot logs as JSON:
```bash
# Last 24 hours
curl http://your-domain.com/honeypot/export?hours=24

# Last week
curl http://your-domain.com/honeypot/export?hours=168
```

## Security Best Practices

### 1. Monitor Regularly

Check the honeypot dashboard daily to:
- Identify new attack patterns
- Block persistent attackers
- Update security measures

### 2. Act on Alerts

When the honeypot triggers:
1. Review the attempt details
2. Check if it's a legitimate mistake or attack
3. Block the IP if necessary
4. Update firewall rules if seeing patterns

### 3. Analyze Patterns

Look for:
- Geographic patterns (IP ranges)
- Time-based patterns (attack schedules)
- Tool signatures (user agents)
- Target patterns (which endpoints are most popular)

### 4. Don't Link to Honeypot Endpoints

Never:
- Link to honeypot endpoints from your real application
- Document honeypot endpoints in public docs
- Use honeypot endpoints in examples

Legitimate users should never see these endpoints.

### 5. Keep Responses Convincing

The fake responses are designed to look real:
- They return appropriate HTTP status codes
- Include realistic error messages
- Provide fake but believable data

This encourages attackers to spend time analyzing fake data.

### 6. Integrate with Firewall

For production systems:
1. Export blocked IPs regularly
2. Add to firewall rules (iptables, AWS Security Groups, etc.)
3. Consider automatic integration

Example script:
```bash
#!/bin/bash
# Get blocked IPs from honeypot
curl -s http://localhost/honeypot/export | \
  jq -r '.logs[] | select(.is_blocked==true) | .ip_address' | \
  sort -u | \
  while read ip; do
    # Add to iptables (requires root)
    iptables -A INPUT -s $ip -j DROP
  done
```

## Maintenance

### Pruning Old Logs

To keep the database size manageable, periodically delete old logs:

```php
// In tinker or a scheduled command
HoneypotLog::where('created_at', '<', now()->subDays(90))->delete();
```

Or create a scheduled task in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $days = config('honeypot.log_retention_days', 90);
        if ($days > 0) {
            HoneypotLog::where('created_at', '<', now()->subDays($days))->delete();
        }
    })->daily();
}
```

### Database Indexes

The migration includes indexes on:
- `ip_address` - Fast IP lookups
- `path` - Fast endpoint analysis
- `created_at` - Fast time-range queries
- `is_blocked` - Fast blocked IP queries
- Composite indexes for common queries

## Attack Pattern Examples

### Example 1: Automated Scanner

```
IP: 192.168.1.100
Attempts: 45
Time: 2 minutes
Paths: wp-admin, phpmyadmin, .env, admin/login

Analysis: Automated vulnerability scanner
Action: Auto-blocked after 10 attempts
```

### Example 2: Targeted Attack

```
IP: 203.0.113.50
Attempts: 8
Time: 30 minutes
Paths: .env, config/database, admin/config
User-Agent: curl/7.68.0

Analysis: Manual reconnaissance
Action: Monitoring, potential targeted attack
```

### Example 3: SQL Injection Attempt

```
IP: 198.51.100.25
Attempts: 15
Paths: user?user_id=1' OR '1'='1
Body: Contains SQL injection payloads

Analysis: Attempting SQL injection
Action: Blocked and logged payload for analysis
```

## API Endpoints

### Get Statistics (JSON)

```bash
GET /honeypot/stats

Response:
{
  "statistics": {
    "total_attempts": 1234,
    "unique_ips": 56,
    "attempts_last_24h": 89,
    "attempts_last_7days": 432,
    "blocked_ips": 12,
    "avg_attempts_per_ip": 22.04
  },
  "top_targeted_paths": [...],
  "top_attacking_ips": [...],
  "recent_activity": [...]
}
```

### Export Logs

```bash
GET /honeypot/export?hours=24

Response:
{
  "export_date": "2025-10-28T10:30:00Z",
  "period_hours": 24,
  "total_records": 89,
  "logs": [...]
}
```

### Block IP

```bash
POST /honeypot/block/{ip}

Response: Redirects back with success message
```

## Troubleshooting

### No Logs Appearing

1. Check if honeypot is enabled:
```env
HONEYPOT_ENABLED=true
```

2. Verify middleware is registered in `bootstrap/app.php`:
```php
'honeypot' => \App\Http\Middleware\HoneypotMiddleware::class,
```

3. Test a honeypot endpoint:
```bash
curl http://your-domain.com/api/.env
```

4. Check database table exists:
```bash
php artisan migrate
```

### Dashboard Shows No Data

1. Access a honeypot endpoint first
2. Check database for entries:
```sql
SELECT * FROM honeypot_logs LIMIT 10;
```

3. Clear cache:
```bash
php artisan cache:clear
php artisan config:clear
```

### Alerts Not Working

1. Check configuration in `config/honeypot.php`
2. Verify email/Slack settings in `.env`
3. Test Laravel mail configuration:
```bash
php artisan tinker
Mail::raw('Test', function($msg) { $msg->to('your@email.com'); });
```

## Legal & Ethical Considerations

### Is This Legal?

Yes, honeypots are legal when:
- Deployed on systems you own or manage
- Used for defensive purposes only
- Not used to entrap legitimate users
- Properly configured to avoid false positives

### Ethical Use

✅ **Do**:
- Use for security monitoring
- Analyze attack patterns
- Share anonymized data with security community
- Improve your defenses

❌ **Don't**:
- Use data for commercial purposes without consent
- Hack back or retaliate against attackers
- Publicly shame individual attackers
- Share personal information

### Privacy Concerns

The honeypot logs IP addresses and request details. Ensure:
- You have a privacy policy covering security logs
- Logs are stored securely
- Access is restricted to security team
- Data retention follows regulations (GDPR, etc.)

## Advanced Usage

### Custom Fake Responses

Edit `HoneypotController.php` to customize responses:

```php
public function customEndpoint()
{
    return response()->json([
        'data' => 'Your custom fake data',
        'looks' => 'very realistic',
    ]);
}
```

### Integration with WAF

Forward blocked IPs to your Web Application Firewall:

```php
// In HoneypotMiddleware::alertIfNeeded()
if ($shouldBlock) {
    event(new BlockIPEvent($request->ip()));
}
```

### Slack Notifications

Configure Slack webhook in `.env`:

```env
HONEYPOT_SLACK_NOTIFICATIONS=true
HONEYPOT_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK
```

Customize notification in `HoneypotMiddleware.php`.

## Contributing

Have ideas for more creative honeypot endpoints? Want to improve the detection logic? Contributions are welcome!

## Conclusion

The Honeypot system provides valuable intelligence about threats to your application. Regular monitoring and analysis helps you:
- Stay ahead of attackers
- Understand current threat landscape
- Improve your security posture
- Detect breaches early

Remember: **A honeypot is not a replacement for proper security** - it's an additional layer of defense and detection.

---

**Questions?** Check the dashboard at `/honeypot` or review the code in:
- `app/Http/Middleware/HoneypotMiddleware.php`
- `app/Http/Controllers/HoneypotController.php`
- `app/Models/HoneypotLog.php`
- `config/honeypot.php`
