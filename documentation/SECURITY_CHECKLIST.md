# Security Checklist

Comprehensive security checklist for the AI Legal War Machine.

## Table of Contents

- [Pre-Deployment Security](#pre-deployment-security)
- [Code Security](#code-security)
- [API Security](#api-security)
- [Data Security](#data-security)
- [Infrastructure Security](#infrastructure-security)
- [Monitoring & Response](#monitoring--response)

---

## Pre-Deployment Security

### Environment Configuration

- [ ] All `.env` variables are properly configured
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] Unique `APP_KEY` generated (`php artisan key:generate`)
- [ ] Strong database passwords (min 20 characters, alphanumeric + symbols)
- [ ] API tokens are cryptographically secure (min 32 bytes)
- [ ] OpenAI API key properly secured and restricted
- [ ] AWS credentials use IAM roles with least privilege
- [ ] No secrets committed to version control
- [ ] `.env.example` does not contain real credentials

### Dependency Security

- [ ] Run `composer audit` to check for vulnerable dependencies
- [ ] Run `npm audit` to check for vulnerable Node packages
- [ ] All dependencies are up-to-date with security patches
- [ ] No known CVEs in production dependencies
- [ ] Lock files (`composer.lock`, `package-lock.json`) are committed

### Configuration Security

- [ ] CORS configured properly (only allow necessary origins)
- [ ] Session configuration secure (`secure=true`, `httponly=true`, `samesite=strict`)
- [ ] Cookie settings secure
- [ ] File upload limits configured
- [ ] Maximum execution time set appropriately
- [ ] Memory limits configured

---

## Code Security

### Input Validation

- [ ] All user inputs validated with FormRequest classes
- [ ] Length limits enforced on all string inputs
- [ ] Type validation on all inputs (string, integer, boolean, etc.)
- [ ] Array inputs validated (both array structure and individual items)
- [ ] File uploads validated (type, size, extension)
- [ ] **Prompt injection protection implemented** (sanitize LLM inputs)
- [ ] SQL injection prevented (use Eloquent ORM, avoid raw queries)
- [ ] XSS prevention (API returns JSON, no HTML rendering from user input)

### Authentication & Authorization

- [ ] All API endpoints require authentication (except public ones)
- [ ] Bearer token authentication implemented
- [ ] Token comparison uses `hash_equals()` (constant-time comparison)
- [ ] Authorization policies defined for all models
- [ ] `$this->authorize()` called in all sensitive endpoints
- [ ] Role-based access control (RBAC) implemented
- [ ] Data ownership checked (users can only access their own data)
- [ ] Admin role has appropriate elevated permissions

### Prompt Injection Protection

- [ ] User inputs sanitized before LLM prompts
- [ ] Dangerous patterns removed/redacted
- [ ] Prompt hardening instructions added to system prompts
- [ ] LLM instructed to ignore injected instructions
- [ ] Length limits enforced to prevent context breaking
- [ ] Excessive newlines removed from inputs

### Rate Limiting

- [ ] Rate limiting configured for all API endpoints
- [ ] Tiered rate limits based on resource cost
- [ ] Per-user rate limiting (with IP fallback)
- [ ] Token budget limits to prevent excessive LLM usage
- [ ] Rate limit headers included in responses
- [ ] Custom error messages for rate limit exceeded

### Error Handling

- [ ] No sensitive data in error messages (production)
- [ ] Stack traces disabled in production (`APP_DEBUG=false`)
- [ ] Errors logged with request ID for debugging
- [ ] User-friendly error messages
- [ ] 500 errors return generic message in production
- [ ] Validation errors return helpful messages (422)

### Logging & Monitoring

- [ ] Security events logged (auth failures, authorization failures)
- [ ] Suspicious activity logged (injection attempts, rate limit violations)
- [ ] Logs include request ID, user ID, IP address
- [ ] Logs do not contain sensitive data (passwords, tokens, PII)
- [ ] Log rotation configured to prevent disk space exhaustion
- [ ] Monitoring alerts for critical errors

---

## API Security

### Endpoint Protection

- [ ] All sensitive endpoints require authentication
- [ ] Public endpoints have rate limiting
- [ ] CORS configured to allow only necessary origins
- [ ] OPTIONS requests handled correctly (preflight)
- [ ] API versioning implemented (if applicable)
- [ ] Deprecated endpoints documented and eventually removed

### Request Validation

- [ ] Content-Type validation
- [ ] Request size limits enforced
- [ ] JSON parsing errors handled gracefully
- [ ] Invalid UTF-8 rejected
- [ ] File upload validation (type, size, virus scanning if applicable)

### Response Security

- [ ] Security headers set on all responses:
  - `X-Frame-Options: SAMEORIGIN`
  - `X-Content-Type-Options: nosniff`
  - `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: no-referrer-when-downgrade`
  - `Content-Security-Policy`
- [ ] Sensitive data not included in responses (passwords, full tokens)
- [ ] Error responses don't leak internal information
- [ ] CORS headers properly configured

### Token Management

- [ ] API tokens stored hashed in database (if applicable)
- [ ] Token expiration implemented (if applicable)
- [ ] Token rotation mechanism available
- [ ] Revoked tokens cannot be used
- [ ] Token generation uses cryptographically secure random

---

## Data Security

### Database Security

- [ ] Database credentials strong and unique
- [ ] Database user has minimum necessary privileges
- [ ] Database accessible only from application server
- [ ] Database firewall configured (PostgreSQL pg_hba.conf)
- [ ] SSL/TLS required for database connections (production)
- [ ] Database backups encrypted
- [ ] Sensitive data encrypted at rest (if applicable)
- [ ] SQL injection prevented (Eloquent ORM used)

### Data Access Controls

- [ ] Users can only access their own data (policies enforced)
- [ ] Admin access properly audited
- [ ] Data access logged for sensitive operations
- [ ] Soft deletes used for user data (allows recovery)
- [ ] Data retention policy documented
- [ ] Data deletion is irreversible (when hard deleted)

### PII & GDPR Compliance

- [ ] PII identified and documented
- [ ] PII encrypted at rest (if required)
- [ ] User consent recorded for data processing
- [ ] Data subject access requests (DSAR) can be fulfilled
- [ ] Right to erasure (RTBF) can be honored
- [ ] Data processing agreements (DPA) in place with third parties
- [ ] Privacy policy published and up-to-date

### Vector Store Security

- [ ] Vector embeddings don't contain PII
- [ ] Embeddings properly attributed to cases/documents
- [ ] Access controls on vector search results
- [ ] Vector store backups secured

---

## Infrastructure Security

### Server Security

- [ ] Firewall configured (UFW, iptables, cloud firewall)
- [ ] Only necessary ports open (80, 443, 22)
- [ ] SSH key authentication (password auth disabled)
- [ ] Fail2Ban configured to block brute force attempts
- [ ] Automatic security updates enabled
- [ ] Server hardened (disable unnecessary services)
- [ ] File permissions correct (775 for storage, 644 for code)
- [ ] Web server user has minimal privileges (www-data)

### Web Server Security

- [ ] HTTPS enforced (HTTP redirects to HTTPS)
- [ ] SSL/TLS certificate valid and trusted
- [ ] SSL/TLS configuration strong (TLS 1.2+, strong ciphers)
- [ ] HSTS header set (`Strict-Transport-Security`)
- [ ] Certificate auto-renewal configured (Let's Encrypt)
- [ ] Web server version hidden
- [ ] Directory listing disabled
- [ ] Unnecessary HTTP methods disabled

### Application Security

- [ ] PHP version supported and patched (8.2+)
- [ ] PHP configuration secure (`php.ini`):
  - `display_errors = Off` (production)
  - `expose_php = Off`
  - `allow_url_fopen = Off` (if not needed)
  - `allow_url_include = Off`
  - Memory limits appropriate
- [ ] OpCache enabled (performance + security)
- [ ] Composer packages from trusted sources only
- [ ] Vendor directory not web-accessible

### Queue & Background Jobs

- [ ] Queue worker runs as non-privileged user
- [ ] Failed jobs monitored
- [ ] Job payloads don't contain sensitive data
- [ ] Job retry limits configured
- [ ] Queue worker auto-restart (Supervisor)

### Redis Security

- [ ] Redis requires authentication (`requirepass`)
- [ ] Redis only accessible from localhost
- [ ] Redis firewall rules configured
- [ ] Dangerous commands disabled (`FLUSHALL`, `FLUSHDB`, `CONFIG`)
- [ ] Redis persistence configured for important data

### Neo4j Security

- [ ] Neo4j requires authentication
- [ ] Strong password configured
- [ ] Neo4j only accessible from application server
- [ ] Bolt SSL/TLS enabled (production)
- [ ] Neo4j version patched and up-to-date

---

## Monitoring & Response

### Security Monitoring

- [ ] Failed authentication attempts logged
- [ ] Authorization failures logged
- [ ] Rate limit violations logged
- [ ] Suspicious patterns detected (injection attempts)
- [ ] Unusual API usage patterns monitored
- [ ] Database query errors logged
- [ ] LLM API errors logged

### Alerting

- [ ] Alerts configured for critical errors
- [ ] Alerts for high error rates
- [ ] Alerts for rate limit violations
- [ ] Alerts for authentication failures (brute force)
- [ ] Alerts for disk space low
- [ ] Alerts for high CPU/memory usage
- [ ] Alerts for SSL certificate expiration

### Incident Response

- [ ] Incident response plan documented
- [ ] Security contact designated
- [ ] Backup restoration tested
- [ ] Rollback procedure documented
- [ ] Communication plan for security incidents
- [ ] Post-incident review process defined

### Regular Security Tasks

- [ ] Weekly: Review failed login attempts
- [ ] Weekly: Review rate limit violations
- [ ] Monthly: Update dependencies (`composer update`, `npm update`)
- [ ] Monthly: Review access logs for suspicious activity
- [ ] Quarterly: Security audit
- [ ] Quarterly: Penetration testing (if applicable)
- [ ] Annually: Security training for developers
- [ ] Annually: Third-party security assessment

---

## Development Security

### Secure Coding Practices

- [ ] Code reviews include security checks
- [ ] Security vulnerabilities reported and fixed promptly
- [ ] Security patches applied immediately
- [ ] No hardcoded secrets in code
- [ ] Environment-specific secrets in `.env` only
- [ ] Secrets never logged
- [ ] Secrets never committed to version control

### Testing Security

- [ ] Unit tests for authorization policies
- [ ] Integration tests for authentication
- [ ] Tests for rate limiting
- [ ] Tests for input validation
- [ ] **Tests for prompt injection protection**
- [ ] Tests run in CI/CD pipeline
- [ ] Security tests automated

### Version Control Security

- [ ] `.env` in `.gitignore`
- [ ] Secrets scanning enabled (GitGuardian, TruffleHog)
- [ ] Branch protection rules (main/master requires review)
- [ ] Force push disabled on protected branches
- [ ] Commit signing enabled (GPG signatures)
- [ ] Access to repository restricted (need-to-know basis)

---

## Third-Party Security

### OpenAI API

- [ ] API key stored securely (env variable, secrets manager)
- [ ] API key has appropriate restrictions
- [ ] Rate limiting configured
- [ ] Token budget limits enforced
- [ ] API usage monitored
- [ ] Sensitive data not sent to OpenAI (PII redacted)

### AWS (S3, Textract)

- [ ] IAM role with least privilege
- [ ] S3 bucket not public (unless intentional)
- [ ] S3 bucket versioning enabled
- [ ] S3 bucket encryption enabled
- [ ] S3 access logging enabled
- [ ] AWS CloudTrail enabled for audit
- [ ] MFA required for AWS console access

### Google Drive (Textract Pipeline)

- [ ] Service account with minimal permissions
- [ ] Service account key stored securely
- [ ] Access only to specific folders
- [ ] Access logs reviewed periodically

### Odluke.sudovi.hr

- [ ] Circuit breaker configured (prevent overwhelming service)
- [ ] Rate limiting respected
- [ ] Errors handled gracefully
- [ ] Data properly attributed (copyright)

---

## Compliance Checklist

### GDPR / DSGVO

- [ ] Data processing legal basis documented
- [ ] User consent obtained where required
- [ ] Privacy policy published
- [ ] Data protection officer (DPO) designated (if required)
- [ ] Data processing agreement (DPA) with processors
- [ ] Data breach notification process defined (72-hour rule)
- [ ] Data subject rights can be fulfilled (access, erasure, portability)

### Croatian Legal Compliance

- [ ] System complies with Croatian data protection laws
- [ ] Legal documents generated follow Croatian legal format
- [ ] Court citations follow Croatian standards
- [ ] System deployed in EU (data residency)

### Industry Standards

- [ ] OWASP Top 10 vulnerabilities addressed
- [ ] CWE/SANS Top 25 vulnerabilities addressed
- [ ] ISO 27001 controls considered (if applicable)
- [ ] SOC 2 controls considered (if applicable)

---

## Production Deployment Checklist

### Before Deployment

- [ ] All items in this security checklist reviewed
- [ ] Security audit completed
- [ ] Critical vulnerabilities fixed
- [ ] Penetration testing completed (if applicable)
- [ ] Backup and restore tested
- [ ] Rollback procedure tested
- [ ] SSL certificate valid and configured
- [ ] DNS properly configured
- [ ] CDN configured (if applicable)

### Post-Deployment

- [ ] Monitor logs for errors
- [ ] Monitor security alerts
- [ ] Verify SSL certificate working
- [ ] Verify rate limiting working
- [ ] Verify authentication working
- [ ] Verify authorization working
- [ ] Run smoke tests
- [ ] Notify team of successful deployment

---

## Emergency Contacts

### Internal Contacts

- **Security Lead**: [Name] - [Email] - [Phone]
- **Backend Team Lead**: [Name] - [Email] - [Phone]
- **DevOps Lead**: [Name] - [Email] - [Phone]
- **On-Call Engineer**: [Rotation] - [Email] - [Phone]

### External Contacts

- **Security Consultant**: [Name/Firm] - [Email] - [Phone]
- **Hosting Provider Support**: [Email] - [Phone]
- **OpenAI Support**: support@openai.com
- **AWS Support**: [Support Plan]

---

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/11.x/security)
- [OWASP AI Security](https://owasp.org/www-project-top-10-for-large-language-model-applications/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [GDPR Compliance Guide](https://gdpr.eu/)

---

**Last Updated**: 2025-11-11
**Next Review**: 2025-12-11 (monthly)
**Owner**: Security Team
