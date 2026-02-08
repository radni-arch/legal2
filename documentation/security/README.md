# Security Documentation

This document outlines the security measures, authentication mechanisms, and best practices implemented in the AI Legal War Machine application.

## Table of Contents

- [Security Overview](#security-overview)
- [Authentication Guide](#authentication-guide)
- [Security Headers](#security-headers)
- [XSS Prevention Strategy](#xss-prevention-strategy)
- [SQL Injection Protection](#sql-injection-protection)
- [API Token Management](#api-token-management)
- [CSRF Protection](#csrf-protection)
- [Rate Limiting](#rate-limiting)
- [Security Best Practices](#security-best-practices)
- [Security Testing](#security-testing)
- [Reporting Security Issues](#reporting-security-issues)

---

## Security Overview

The AI Legal War Machine implements multiple layers of security to protect against common web vulnerabilities:

- **Authentication**: API token-based authentication for all protected endpoints
- **Authorization**: Role-based access control for different API scopes
- **Input Sanitization**: HTML Purifier for XSS prevention
- **Security Headers**: Comprehensive HTTP security headers on all responses
- **Rate Limiting**: Protection against brute force and DoS attacks
- **CSRF Protection**: Token-based CSRF protection for web routes
- **SQL Injection Protection**: Parameterized queries via Laravel Eloquent ORM
- **Honeypot System**: Decoy endpoints to detect and monitor attackers

---

## Authentication Guide

### API Token Authentication

All protected API endpoints require authentication using Bearer tokens.

#### Setting Up API Tokens

1. **Configure your API token** in `.env`:
   ```env
   API_TOKEN=your-secret-token-here
   ```

2. **Make authenticated requests** using the Authorization header:
   ```bash
   curl -X POST https://api.example.com/api/ingest/text \
     -H "Authorization: Bearer your-secret-token-here" \
     -H "Content-Type: application/json" \
     -d '{"content": "Legal text to ingest"}'
   ```

#### MCP Authentication

MCP (Model Context Protocol) endpoints use a separate authentication token:

1. **Configure MCP token** in `.env`:
   ```env
   MCP_API_TOKEN=your-mcp-token-here
   ```

2. **Make MCP requests** using the `X-MCP-Token` header:
   ```bash
   curl -X POST https://api.example.com/api/mcp/law.search \
     -H "X-MCP-Token: your-mcp-token-here" \
     -H "Content-Type: application/json" \
     -d '{"query": "criminal law"}'
   ```

#### Timing-Safe Token Comparison

All token comparisons use `hash_equals()` to prevent timing attacks. This ensures that attackers cannot determine the correctness of token characters by measuring response times.

**Implementation in `McpApiTokenAuth` middleware:**
```php
if (!hash_equals($expectedToken, $providedToken)) {
    return response()->json(['error' => 'Unauthorized'], 401);
}
```

### Protected Endpoints

The following endpoint categories require authentication:

- `/api/openai/*` - OpenAI API proxy (requires `api.token`)
- `/api/ingest/*` - Content ingestion (requires `api.token`)
- `/api/uploads/*` - File uploads (requires `api.token`)
- `/api/mcp/*` - MCP tools (requires `mcp.auth`)
- `/api/mcp-openai/*` - MCP-OpenAI bridge (requires `mcp.auth`)
- `/api/agent/*` - Autonomous agents (requires `api.token`)
- `/api/search/*` - Search API (requires `api.token`)
- `/api/reasoning/*` - Legal reasoning (requires `api.token`)
- `/api/analytics/*` - Predictive analytics (requires `api.token`)
- `/api/strategy/*` - Legal strategy (requires `api.token`)
- `/api/topics/*` - Topic analysis (requires `api.token`)
- `/api/collaboration/*` - Multi-agent collaboration (requires `api.token`)
- `/api/monitoring/*` - Agent monitoring (requires `api.token`)
- `/api/graph/*` - Graph visualization (requires `api.token`)

### Public Endpoints

Some endpoints are intentionally public:

- `/api/mcp-openai/info` - Public info endpoint (rate-limited)
- `/api/admin/*` - Honeypot endpoints (no auth required by design)
- `/api/.env` - Honeypot endpoint (no auth required by design)
- `/api/phpinfo` - Honeypot endpoint (no auth required by design)

---

## Security Headers

The application automatically adds comprehensive security headers to all HTTP responses via the `SecurityHeaders` middleware.

### Implemented Headers

#### X-Frame-Options: DENY
**Purpose**: Prevents clickjacking attacks

Prevents the application from being embedded in `<iframe>`, `<frame>`, `<embed>`, or `<object>` tags on other websites.

```http
X-Frame-Options: DENY
```

#### X-Content-Type-Options: nosniff
**Purpose**: Prevents MIME type sniffing

Forces browsers to respect the declared Content-Type and prevents them from trying to "sniff" the content type.

```http
X-Content-Type-Options: nosniff
```

#### X-XSS-Protection: 1; mode=block
**Purpose**: Enables XSS filter in browsers

Activates the browser's built-in XSS protection and instructs it to block the page if an attack is detected.

```http
X-XSS-Protection: 1; mode=block
```

#### Strict-Transport-Security
**Purpose**: Enforces HTTPS connections

Instructs browsers to only access the application over HTTPS for the next year, including all subdomains.

```http
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

**Best Practice**: Enable this only after confirming HTTPS is properly configured.

#### Content-Security-Policy
**Purpose**: Controls resource loading

Restricts which resources the browser can load, reducing XSS attack surface.

```http
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'
```

**Policy Breakdown**:
- `default-src 'self'`: Only load resources from the same origin
- `script-src 'self' 'unsafe-inline'`: Allow scripts from same origin and inline scripts
- `style-src 'self' 'unsafe-inline'`: Allow styles from same origin and inline styles

**Note**: `'unsafe-inline'` is enabled for compatibility. For stricter security, use nonces or hashes for inline scripts/styles.

#### Referrer-Policy
**Purpose**: Controls referrer information

Limits the information sent in the `Referer` header to prevent leaking sensitive data.

```http
Referrer-Policy: strict-origin-when-cross-origin
```

#### Permissions-Policy
**Purpose**: Controls browser features

Disables potentially sensitive browser features that the application doesn't need.

```http
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

### Verifying Security Headers

Test security headers using:

```bash
curl -I https://api.example.com/
```

Or use online tools:
- [SecurityHeaders.com](https://securityheaders.com)
- [Mozilla Observatory](https://observatory.mozilla.org)

---

## XSS Prevention Strategy

Cross-Site Scripting (XSS) prevention is implemented through multiple layers:

### 1. HTML Purifier Integration

We use the `mews/purifier` package with the `clean()` helper function to sanitize all user-generated HTML content.

**Installation**:
```bash
composer require mews/purifier
```

**Configuration**: `config/purifier.php`

**Allowed HTML Tags**:
- Formatting: `p`, `strong`, `em`, `u`, `b`, `i`
- Structure: `div`, `span`, `br`, `h1`-`h6`
- Lists: `ul`, `ol`, `li`
- Links: `a[href|title]`
- Code: `pre`, `code`, `blockquote`
- Highlighting: `mark` (for search results)

**Blocked Elements**:
- Scripts: `<script>` tags
- Event handlers: `onclick`, `onerror`, `onload`, etc.
- Dangerous protocols: `javascript:`, `data:` URIs
- Embedded content: `<iframe>`, `<object>`, `<embed>`

### 2. Blade Template Protection

**Safe Output** (automatically escaped):
```blade
{{ $userInput }}  <!-- Automatically escaped -->
```

**Raw HTML Output** (use clean()):
```blade
{!! clean($userGeneratedHtml) !!}  <!-- Sanitized with HTML Purifier -->
```

**JavaScript Context** (use @json directive):
```blade
<script>
const data = @json($dataArray);  <!-- Safely encoded as JSON -->
</script>
```

### 3. Protected Templates

The following templates have been audited and protected:

1. **resources/views/pdf/article.blade.php:30**
   ```blade
   {!! clean($article_html ?? '') !!}
   ```

2. **resources/views/livewire/timeline-page.blade.php:17**
   ```blade
   const timeline_json = @json($this->timelineJs);
   ```

3. **resources/views/livewire/comparative-timeline-page.blade.php:142-143**
   ```blade
   const dataTop = @json($this->dataTopJs);
   const dataBottom = @json($this->dataBottomJs);
   ```

4. **resources/views/livewire/gup-timeline.blade.php:81,86**
   ```blade
   {!! clean($currentItem['detailsHtml']) !!}
   {!! clean($currentAsset['html'] ?? '') !!}
   ```

5. **resources/views/livewire/transcript-previewer.blade.php:134**
   ```blade
   {!! clean(nl2br($html)) !!}
   ```

6. **resources/views/agent/run.blade.php:103**
   ```blade
   {!! clean(Str::markdown($run->final_output)) !!}
   ```

### 4. XSS Testing

Comprehensive XSS tests are in `tests/Feature/XssProtectionTest.php`:

- Tests 15+ common XSS attack vectors
- Verifies `clean()` function sanitizes malicious input
- Ensures safe HTML tags are preserved
- Tests markdown, nl2br, and search highlighting

**Run XSS tests:**
```bash
php artisan test --filter=XssProtectionTest
```

---

## SQL Injection Protection

SQL injection protection is achieved through Laravel's built-in security features:

### 1. Eloquent ORM

All database queries use Eloquent ORM or Query Builder, which automatically use parameterized queries:

```php
// Safe - parameterized query
$laws = Law::where('title', 'like', '%' . $search . '%')->get();

// Safe - query builder with bindings
DB::table('laws')->where('id', $id)->first();
```

### 2. Never Use Raw Queries with User Input

**❌ Dangerous** (vulnerable to SQL injection):
```php
DB::select("SELECT * FROM laws WHERE id = {$id}");
```

**✅ Safe** (parameterized query):
```php
DB::select("SELECT * FROM laws WHERE id = ?", [$id]);
```

### 3. SQL Injection Testing

SQL injection tests are in `tests/Feature/Security/SecurityAuditTest.php`:

- Tests common SQL injection vectors
- Verifies honeypot endpoints handle malicious input safely
- Ensures protected endpoints don't leak database errors

**Common SQL injection attempts blocked:**
```sql
' OR '1'='1
1; DROP TABLE users--
' UNION SELECT * FROM users--
admin'--
```

---

## API Token Management

### Generating Secure Tokens

Use cryptographically secure random tokens:

```bash
# Generate a 32-byte random token
php -r "echo bin2hex(random_bytes(32));"
```

Or use Laravel's string helper:

```php
use Illuminate\Support\Str;

$token = Str::random(64);
```

### Token Storage

**Environment Variables** (`.env`):
```env
API_TOKEN=your-generated-token-here
MCP_API_TOKEN=your-mcp-token-here
```

**Never commit tokens** to version control:
- Tokens should only exist in `.env` files
- Add `.env` to `.gitignore`
- Use `.env.example` for documentation

### Token Rotation

**Best practice**: Rotate tokens periodically

1. Generate a new token
2. Update `.env` with new token
3. Update client applications
4. Revoke old token

### Multiple Tokens

For multiple clients, consider implementing a token database:

```php
// Migration
Schema::create('api_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('token', 64)->unique();
    $table->string('name');
    $table->json('scopes')->nullable();
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});
```

---

## CSRF Protection

Laravel provides built-in CSRF (Cross-Site Request Forgery) protection for web routes.

### How CSRF Protection Works

1. Laravel generates a CSRF token for each user session
2. The token is included in forms and AJAX requests
3. Laravel verifies the token on POST, PUT, PATCH, DELETE requests
4. Requests without valid tokens are rejected with 419 status

### Web Routes (CSRF Required)

All web routes automatically have CSRF protection via the `web` middleware group.

**Blade forms**:
```blade
<form method="POST" action="/login">
    @csrf
    <!-- form fields -->
</form>
```

**AJAX requests**:
```javascript
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data)
})
```

### API Routes (CSRF Exempt)

API routes under `/api/*` do NOT require CSRF tokens. They use API token authentication instead.

### Verifying CSRF Protection

```php
// Test CSRF protection
$response = $this->post('/login', [
    'email' => 'test@example.com',
    'password' => 'password',
]);

// Should return 419 (CSRF token mismatch)
$response->assertStatus(419);
```

---

## Rate Limiting

Rate limiting protects against brute force attacks and API abuse.

### Default Rate Limits

- **Public Info Endpoint**: 60 requests/minute
- **API Endpoints**: 60 requests/minute per user
- **MCP Endpoints**: 60 requests/minute per user
- **OdlukeAgent**: 30 requests/minute per user

### Implementation

Rate limiting is applied via the `throttle` middleware:

```php
Route::middleware(['api.token', 'throttle:60,1'])->group(function () {
    Route::post('/search', [SearchController::class, 'search']);
});
```

### Handling Rate Limit Responses

When rate limited, the API returns:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 60
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
```

**Client implementation**:
```javascript
async function makeRequest() {
    const response = await fetch('/api/search', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: JSON.stringify(data)
    });

    if (response.status === 429) {
        const retryAfter = response.headers.get('Retry-After');
        console.log(`Rate limited. Retry after ${retryAfter} seconds`);
        await sleep(retryAfter * 1000);
        return makeRequest(); // Retry
    }

    return response.json();
}
```

### Custom Rate Limits

Adjust rate limits in `app/Providers/AppServiceProvider.php` or route definitions:

```php
// Custom rate limit for sensitive endpoints
Route::post('/admin/users', [UserController::class, 'create'])
    ->middleware('throttle:10,1'); // 10 requests per minute
```

---

## Security Best Practices

### For Developers

1. **Never Trust User Input**
   - Always validate and sanitize input
   - Use `clean()` for HTML content
   - Use parameterized queries for database operations

2. **Use HTTPS Everywhere**
   - Enable HSTS (Strict-Transport-Security)
   - Redirect HTTP to HTTPS
   - Use secure cookies: `SESSION_SECURE_COOKIE=true`

3. **Keep Dependencies Updated**
   ```bash
   composer update
   npm update
   ```
   - Monitor for security advisories
   - Use `composer audit` to check for vulnerabilities

4. **Validate on Server-Side**
   - Never rely solely on client-side validation
   - Use Laravel Form Requests for validation

5. **Implement Logging**
   - Log authentication failures
   - Log suspicious activity
   - Monitor honeypot triggers

6. **Principle of Least Privilege**
   - Grant minimum necessary permissions
   - Use role-based access control
   - Separate API tokens by scope

### For Deployment

1. **Environment Configuration**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=[generate with: php artisan key:generate]
   ```

2. **Disable Debug Mode**
   - Never run `APP_DEBUG=true` in production
   - Debug mode leaks sensitive information

3. **File Permissions**
   ```bash
   chmod 755 storage bootstrap/cache
   chmod 644 .env
   ```

4. **Database Security**
   - Use strong database passwords
   - Restrict database user permissions
   - Enable SSL for database connections

5. **Backup Strategy**
   - Regular automated backups
   - Encrypt backups
   - Store backups off-site

6. **Monitoring**
   - Set up error monitoring (Sentry, Bugsnag)
   - Monitor failed login attempts
   - Track API usage patterns

### For API Clients

1. **Store Tokens Securely**
   - Never expose tokens in client-side code
   - Use environment variables
   - Rotate tokens periodically

2. **Use HTTPS**
   - Always make requests over HTTPS
   - Verify SSL certificates

3. **Handle Rate Limiting**
   - Implement exponential backoff
   - Respect `Retry-After` headers

4. **Validate Responses**
   - Check response status codes
   - Validate response structure
   - Handle errors gracefully

---

## Security Testing

### Running Security Tests

**All security tests**:
```bash
php artisan test tests/Feature/Security
```

**XSS protection tests**:
```bash
php artisan test tests/Feature/XssProtectionTest
```

**Security headers tests**:
```bash
php artisan test tests/Feature/SecurityHeadersTest
```

### Test Coverage

Our security test suite covers:

- ✅ API endpoint authentication (30+ endpoints)
- ✅ Security headers on all responses
- ✅ XSS injection attempts (15+ vectors)
- ✅ SQL injection attempts
- ✅ CSRF protection
- ✅ Rate limiting
- ✅ Token validation
- ✅ Error response safety

### Manual Security Testing

**Test security headers**:
```bash
curl -I https://your-domain.com/
```

**Test authentication**:
```bash
# Should return 401
curl https://your-domain.com/api/ingest/text

# Should succeed
curl -H "Authorization: Bearer your-token" https://your-domain.com/api/ingest/text
```

**Test XSS protection**:
```bash
curl "https://your-domain.com/api/search?q=<script>alert('XSS')</script>"
```

### External Security Scanning

Use external tools to validate security:

- [OWASP ZAP](https://www.zaproxy.org/) - Automated security scanner
- [Burp Suite](https://portswigger.net/burp) - Web security testing
- [SecurityHeaders.com](https://securityheaders.com) - Header analysis
- [Mozilla Observatory](https://observatory.mozilla.org) - Security assessment

---

## Reporting Security Issues

If you discover a security vulnerability, please report it responsibly:

### DO NOT

- ❌ Open a public GitHub issue
- ❌ Post on social media
- ❌ Exploit the vulnerability

### DO

1. **Email security@example.com** with:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if applicable)

2. **Wait for acknowledgment** (within 48 hours)

3. **Allow time for a fix** before public disclosure

### Response Process

1. **Acknowledgment**: Within 48 hours
2. **Investigation**: Within 1 week
3. **Fix Development**: As quickly as possible
4. **Deployment**: Emergency deployment for critical issues
5. **Public Disclosure**: After fix is deployed (with credit to reporter)

### Bug Bounty

We appreciate security researchers who responsibly disclose vulnerabilities. While we don't currently have a formal bug bounty program, we recognize and credit researchers in our security advisories.

---

## Security Checklist

### Before Deployment

- [ ] `APP_DEBUG=false` in production
- [ ] Strong, unique `APP_KEY` generated
- [ ] Secure API tokens configured
- [ ] HTTPS enabled with valid SSL certificate
- [ ] HSTS header enabled
- [ ] Database credentials secure and limited
- [ ] File permissions correctly set
- [ ] All dependencies updated
- [ ] Security tests passing
- [ ] Error monitoring configured
- [ ] Backup system in place
- [ ] Rate limiting configured

### Regular Maintenance

- [ ] Weekly: Review application logs
- [ ] Weekly: Check honeypot activity
- [ ] Monthly: Update dependencies
- [ ] Monthly: Rotate API tokens (if policy requires)
- [ ] Monthly: Review security advisories
- [ ] Quarterly: Security audit
- [ ] Quarterly: Penetration testing
- [ ] Annually: Comprehensive security review

---

## Resources

### Documentation

- [Laravel Security](https://laravel.com/docs/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Cheat Sheets](https://cheatsheetseries.owasp.org/)

### Tools

- [Laravel Security Checker](https://github.com/enlightn/security-checker)
- [Roave Security Advisories](https://github.com/Roave/SecurityAdvisories)
- [SensioLabs Security Checker](https://github.com/sensiolabs/security-checker)

### Best Practices

- [OWASP Secure Coding Practices](https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/)
- [Laravel Security Best Practices](https://www.stackhawk.com/blog/laravel-security-best-practices/)

---

## Changelog

### Version 1.0.0 (2025-01-XX)

- ✅ Implemented SecurityHeaders middleware
- ✅ Added HTML Purifier for XSS protection
- ✅ Created comprehensive security test suite
- ✅ Fixed XSS vulnerabilities in 6 Blade templates
- ✅ Implemented MCP API token authentication with timing-safe comparison
- ✅ Added honeypot system for attacker detection
- ✅ Configured rate limiting on all API endpoints
- ✅ Added security documentation

---

**Last Updated**: January 2025
**Maintained By**: Security Team
**Version**: 1.0.0
