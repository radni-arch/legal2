# Go-Live Checklist

## AI Legal War Machine - Production Go-Live

**Version**: 1.0
**Last Updated**: 2025-11-09
**Go-Live Date**: _____________
**Go-Live Time**: _____________
**Duration**: 4-6 hours

---

## Table of Contents

1. [Phase 1: Pre-Deployment (T-24 hours)](#phase-1-pre-deployment-t-24-hours)
2. [Phase 2: Deployment (T-0 to T+2 hours)](#phase-2-deployment-t-0-to-t2-hours)
3. [Phase 3: Verification (T+2 to T+3 hours)](#phase-3-verification-t2-to-t3-hours)
4. [Phase 4: Monitoring (T+3 to T+24 hours)](#phase-4-monitoring-t3-to-t24-hours)
5. [Phase 5: Post-Deployment (T+24 hours)](#phase-5-post-deployment-t24-hours)
6. [Rollback Procedure](#rollback-procedure)
7. [Emergency Contacts](#emergency-contacts)

---

## Pre-Go-Live Summary

### Readiness Status

**Date**: _____________

- [ ] All production testing completed successfully
- [ ] All critical bugs fixed
- [ ] Documentation complete and reviewed
- [ ] Team trained on operations and troubleshooting
- [ ] Backups verified and tested
- [ ] Rollback plan tested
- [ ] External monitoring configured
- [ ] Communication plan established
- [ ] Stakeholders notified of go-live schedule

**Sign-off**:
- DevOps Lead: _____________ Date: _______
- Technical Lead: _____________ Date: _______
- Project Manager: _____________ Date: _______

---

## Phase 1: Pre-Deployment (T-24 hours)

**Timeline**: 24 hours before go-live
**Duration**: 2-3 hours
**Responsible**: DevOps Team

### 1.1 Final Backups (T-24 hours)

- [ ] **Database backup**
  ```bash
  # TODO: Create backup-database.sh script
  # Manual backup for now:
  pg_dump -U postgres ai_legal_war_machine | gzip > /var/backups/ai-legal-war-machine/db_$(date +%Y%m%d_%H%M%S).sql.gz

  # Verify backup
  ls -lh /var/backups/ai-legal-war-machine/db_*.sql.gz | tail -1
  # Expected: Recent backup > 1MB
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Neo4j backup**
  ```bash
  # TODO: Create backup-neo4j.sh script
  # Manual backup for now:
  sudo systemctl stop neo4j
  sudo tar czf /var/backups/neo4j/neo4j_$(date +%Y%m%d_%H%M%S).tar.gz /var/lib/neo4j/data
  sudo systemctl start neo4j

  # Verify backup
  ls -lh /var/backups/neo4j/*.tar.gz | tail -1
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Application files backup**
  ```bash
  # TODO: Create backup-app.sh script
  # Manual backup for now:
  cd /var/www/ai-legal-war-machine
  tar czf /var/backups/ai-legal-war-machine/storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/ .env

  # Verify backup
  ls -lh /var/backups/ai-legal-war-machine/storage_*.tar.gz | tail -1
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Download backups to local machine** (optional but recommended)
  ```bash
  # From local machine
  scp deploy@server:/var/backups/ai-legal-war-machine/db_*.sql.gz ./backups/
  ```
  **Status**: ⬜ Completed at: _______

### 1.2 Code Freeze (T-24 hours)

- [ ] **Freeze main branch**
  - No new commits to main branch
  - All changes must go through emergency review process

  **Status**: ⬜ Completed at: _______

- [ ] **Tag release version**
  ```bash
  cd /var/www/ai-legal-war-machine
  git tag -a v1.0.0 -m "Production go-live release"
  git push origin v1.0.0
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Document current commit hash**
  ```bash
  cd /var/www/ai-legal-war-machine
  git rev-parse HEAD > /tmp/golive-commit-hash.txt
  echo "Go-live commit: $(cat /tmp/golive-commit-hash.txt)"
  ```
  **Commit Hash**: ___________________________
  **Status**: ⬜ Completed at: _______

### 1.3 Team Readiness (T-24 hours)

- [ ] **Confirm team availability**
  - [ ] DevOps Lead: _______________
  - [ ] System Administrator: _______________
  - [ ] Backend Developer: _______________
  - [ ] On-Call Engineer: _______________

  **Status**: ⬜ Completed at: _______

- [ ] **Review emergency procedures**
  - [ ] Team reviewed rollback procedure
  - [ ] Team reviewed troubleshooting guide
  - [ ] Team has access to all systems
  - [ ] Communication channels tested (Slack, phone)

  **Status**: ⬜ Completed at: _______

- [ ] **Prepare war room** (physical or virtual)
  - Slack channel: #go-live
  - Video call: _______________
  - Screen sharing ready

  **Status**: ⬜ Completed at: _______

### 1.4 External Services (T-24 hours)

- [ ] **Notify stakeholders**
  - [ ] Users notified of maintenance window
  - [ ] Support team notified
  - [ ] Management notified
  - [ ] Legal team notified

  **Status**: ⬜ Completed at: _______

- [ ] **Prepare external monitoring**
  - [ ] UptimeRobot/Pingdom paused (to avoid false alarms during deployment)
  - [ ] Slack alerts configured
  - [ ] Email alerts configured

  **Status**: ⬜ Completed at: _______

- [ ] **Prepare status page** (if available)
  - Update with "Scheduled Maintenance"

  **Status**: ⬜ Completed at: _______

### 1.5 Pre-Deployment Checks (T-12 hours)

- [ ] **Server health check**
  ```bash
  # Check all services
  curl -s https://your-domain.com/api/health | jq

  # Verify all services healthy
  systemctl status nginx php8.2-fpm postgresql redis-server neo4j
  sudo supervisorctl status
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Resource check**
  ```bash
  # Disk space
  df -h  # Expected: < 70%

  # Memory
  free -h  # Expected: < 70%

  # CPU
  uptime  # Expected: Load < CPU cores
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **SSL certificate check**
  ```bash
  # Verify certificate valid for > 30 days
  sudo certbot certificates
  ```
  **Status**: ⬜ Completed at: _______

### 1.6 Final Testing (T-6 hours)

- [ ] **Run production testing checklist**
  - See: `docs/production-testing-checklist.md`
  - Focus on critical tests only

  **Test Results**: PASS ⬜ / FAIL ⬜
  **Status**: ⬜ Completed at: _______

- [ ] **Performance baseline**
  ```bash
  # Record baseline metrics
  curl -s https://your-domain.com/api/health | jq '.metrics' > /tmp/baseline-metrics.json

  # Record queue sizes
  curl -s https://your-domain.com/api/health | jq '.services.queue.queue_sizes' > /tmp/baseline-queues.json
  ```
  **Status**: ⬜ Completed at: _______

---

## Phase 2: Deployment (T-0 to T+2 hours)

**Timeline**: Go-live window start
**Duration**: 1-2 hours
**Responsible**: DevOps Lead

### 2.1 Pre-Deployment (T-0 to T+15 minutes)

- [ ] **GO/NO-GO decision**
  - All team members present: YES ⬜ / NO ⬜
  - All pre-checks passed: YES ⬜ / NO ⬜
  - **Final GO/NO-GO**: GO ⬜ / NO-GO ⬜

  **Decision By**: _______________
  **Time**: _______

- [ ] **Announce start of deployment**
  - Post in Slack: "🚀 Go-live deployment starting now"
  - Update status page: "Maintenance in progress"

  **Status**: ⬜ Completed at: _______

- [ ] **Enable maintenance mode**
  ```bash
  cd /var/www/ai-legal-war-machine
  php artisan down --render="errors::503" --retry=60
  ```

  **Verification**:
  ```bash
  curl -I https://your-domain.com
  # Expected: HTTP/2 503
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Stop queue workers**
  ```bash
  sudo supervisorctl stop ai-legal-queue-worker:*

  # Verify stopped
  sudo supervisorctl status | grep ai-legal-queue-worker
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Pause external monitoring**
  - UptimeRobot: Paused ⬜
  - Pingdom: Paused ⬜

  **Status**: ⬜ Completed at: _______

### 2.2 Deployment Execution (T+15 to T+60 minutes)

- [ ] **Pull latest code**
  ```bash
  cd /var/www/ai-legal-war-machine

  # Record current commit (for rollback)
  git rev-parse HEAD > /tmp/rollback-commit.txt

  # Pull latest
  git fetch origin main
  git checkout main
  git reset --hard origin/main

  # Verify commit
  git log -1 --oneline
  ```
  **New Commit**: ___________________________
  **Status**: ⬜ Completed at: _______

- [ ] **Install dependencies**
  ```bash
  # Composer
  composer install --no-dev --optimize-autoloader --no-interaction

  # NPM
  npm ci --no-audit
  npm run build
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Run database migrations**
  ```bash
  # Check for pending migrations
  php artisan migrate:status

  # Run migrations
  php artisan migrate --force
  ```
  **Migrations Run**: _____
  **Status**: ⬜ Completed at: _______

- [ ] **Clear and rebuild caches**
  ```bash
  # Clear caches
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
  php artisan cache:clear

  # Rebuild caches
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache

  # Optimize autoloader
  composer dump-autoload --optimize
  ```
  **Status**: ⬜ Completed at: _______

### 2.3 Service Restart (T+60 to T+75 minutes)

- [ ] **Restart PHP-FPM**
  ```bash
  sudo systemctl restart php8.2-fpm

  # Verify
  systemctl status php8.2-fpm
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Reload Nginx**
  ```bash
  # Test config first
  sudo nginx -t

  # Reload
  sudo systemctl reload nginx

  # Verify
  systemctl status nginx
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Start queue workers**
  ```bash
  # Start workers
  sudo supervisorctl start ai-legal-queue-worker:*

  # Verify all running
  sudo supervisorctl status | grep ai-legal-queue-worker
  ```
  **Status**: ⬜ Completed at: _______

### 2.4 Smoke Tests (T+75 to T+90 minutes)

- [ ] **Health check**
  ```bash
  # Test health endpoint (still in maintenance mode - test from server)
  curl -s http://localhost/api/health | jq

  # Verify all services healthy
  curl -s http://localhost/api/health | jq '.status'
  # Expected: "healthy"
  ```
  **Status**: ⬜ PASS / FAIL
  **Completed at**: _______

- [ ] **Database connectivity**
  ```bash
  php artisan db:show
  php artisan tinker
  >>> DB::select('SELECT 1 as test');
  >>> exit
  ```
  **Status**: ⬜ PASS / FAIL
  **Completed at**: _______

- [ ] **Queue processing**
  ```bash
  # Dispatch test job
  php artisan tinker
  >>> dispatch(function() { \Log::info('Go-live test job'); });
  >>> exit

  # Wait 5 seconds, check logs
  tail -20 storage/logs/queue.log | grep "Go-live test job"
  ```
  **Status**: ⬜ PASS / FAIL
  **Completed at**: _______

- [ ] **Neo4j connectivity**
  ```bash
  php artisan graph:stats
  ```
  **Status**: ⬜ PASS / FAIL
  **Completed at**: _______

### 2.5 Go Live (T+90 to T+120 minutes)

- [ ] **Disable maintenance mode**
  ```bash
  php artisan up

  # Verify
  curl -I https://your-domain.com
  # Expected: HTTP/2 200
  ```
  **Status**: ⬜ Completed at: _______

- [ ] **Resume external monitoring**
  - UptimeRobot: Resumed ⬜
  - Pingdom: Resumed ⬜

  **Status**: ⬜ Completed at: _______

- [ ] **Announce completion**
  - Post in Slack: "✅ Go-live deployment completed successfully"
  - Update status page: "All systems operational"

  **Status**: ⬜ Completed at: _______

---

## Phase 3: Verification (T+2 to T+3 hours)

**Timeline**: Immediately after going live
**Duration**: 1 hour
**Responsible**: Full Team

### 3.1 Functional Verification

- [ ] **Homepage accessible**
  ```bash
  curl -I https://your-domain.com
  # Expected: HTTP/2 200
  ```
  **Status**: ⬜ PASS / FAIL

- [ ] **User authentication working**
  - Manual test: Login via browser
  - Expected: Successful login

  **Status**: ⬜ PASS / FAIL

- [ ] **Search functionality working**
  - Manual test: Perform search query
  - Expected: Results displayed

  **Status**: ⬜ PASS / FAIL

- [ ] **Document upload working**
  - Manual test: Upload test PDF
  - Expected: Processing starts, job queued

  **Status**: ⬜ PASS / FAIL

- [ ] **AI features working**
  - Manual test: Use evidence analysis or misconduct detection
  - Expected: Analysis completes successfully

  **Status**: ⬜ PASS / FAIL

- [ ] **Graph viewer working**
  - Navigate to /graph
  - Expected: Neo4j visualization displays

  **Status**: ⬜ PASS / FAIL

### 3.2 API Verification

- [ ] **Health endpoint**
  ```bash
  curl -s https://your-domain.com/api/health | jq '.status'
  # Expected: "healthy"
  ```
  **Status**: ⬜ PASS / FAIL

- [ ] **Authenticated endpoints**
  ```bash
  # Test with valid token
  curl -X POST https://your-domain.com/api/search \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"query": "test"}' \
    | jq
  ```
  **Status**: ⬜ PASS / FAIL

- [ ] **Rate limiting**
  ```bash
  # Test rate limiting (should get 429 after 60 requests)
  for i in {1..61}; do
    curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.com/api/health
  done
  ```
  **Status**: ⬜ PASS / FAIL

### 3.3 Performance Verification

- [ ] **Response times acceptable**
  ```bash
  # Homepage < 500ms
  curl -o /dev/null -s -w 'Total: %{time_total}s\n' https://your-domain.com

  # Health endpoint < 100ms
  curl -o /dev/null -s -w 'Total: %{time_total}s\n' https://your-domain.com/api/health
  ```
  **Homepage**: _______ seconds (< 0.5s) ⬜ PASS / FAIL
  **Health**: _______ seconds (< 0.1s) ⬜ PASS / FAIL

- [ ] **Queue processing normally**
  ```bash
  curl -s https://your-domain.com/api/health | jq '.services.queue.queue_sizes'
  # Expected: All queues < 50 pending jobs
  ```
  **Status**: ⬜ PASS / FAIL

- [ ] **Database performance normal**
  ```bash
  curl -s https://your-domain.com/api/health | jq '.services.database.response_time_ms'
  # Expected: < 10ms
  ```
  **Response Time**: _______ ms (< 10ms) ⬜ PASS / FAIL

### 3.4 Monitoring Verification

- [ ] **External monitoring reporting healthy**
  - UptimeRobot: Status Up ⬜
  - Pingdom: Status Up ⬜

  **Status**: ⬜ PASS / FAIL

- [ ] **Logs clean (no critical errors)**
  ```bash
  php artisan logs:analyze --errors --since="1 hour ago"
  # Expected: 0 critical errors
  ```
  **Error Count**: _______
  **Status**: ⬜ PASS / FAIL

- [ ] **Slack notifications working**
  ```bash
  # Test notification
  php artisan tinker
  >>> \Log::channel('slack')->info('Go-live verification test');
  >>> exit
  ```
  **Status**: ⬜ PASS / FAIL

---

## Phase 4: Monitoring (T+3 to T+24 hours)

**Timeline**: First 24 hours after go-live
**Duration**: 24 hours
**Responsible**: On-Call Team

### 4.1 Immediate Monitoring (T+3 to T+6 hours)

**Check every 30 minutes**:

- [ ] **Health status**
  ```bash
  curl -s https://your-domain.com/api/health | jq '.status'
  # Expected: "healthy"
  ```

  **Checks**:
  - T+3h30m: ⬜ Healthy
  - T+4h00m: ⬜ Healthy
  - T+4h30m: ⬜ Healthy
  - T+5h00m: ⬜ Healthy
  - T+5h30m: ⬜ Healthy
  - T+6h00m: ⬜ Healthy

- [ ] **Error rate**
  ```bash
  php artisan logs:analyze --stats
  # Expected: Error rate < 1%
  ```

  **Error Rates**:
  - T+3h30m: _____% ⬜ < 1%
  - T+4h00m: _____% ⬜ < 1%
  - T+4h30m: _____% ⬜ < 1%
  - T+5h00m: _____% ⬜ < 1%
  - T+5h30m: _____% ⬜ < 1%
  - T+6h00m: _____% ⬜ < 1%

- [ ] **Queue sizes**
  ```bash
  curl -s https://your-domain.com/api/health | jq '.services.queue.queue_sizes'
  # Expected: All queues < 100 pending
  ```

  **Queue Sizes**:
  - T+3h30m: Total: _____ ⬜ < 100
  - T+4h00m: Total: _____ ⬜ < 100
  - T+4h30m: Total: _____ ⬜ < 100
  - T+5h00m: Total: _____ ⬜ < 100
  - T+5h30m: Total: _____ ⬜ < 100
  - T+6h00m: Total: _____ ⬜ < 100

- [ ] **System resources**
  ```bash
  df -h  # Disk
  free -h  # Memory
  uptime  # CPU load
  ```

  **Resource Usage**:
  - T+3h30m: Disk: ____% Memory: ____% Load: _____
  - T+4h00m: Disk: ____% Memory: ____% Load: _____
  - T+4h30m: Disk: ____% Memory: ____% Load: _____
  - T+5h00m: Disk: ____% Memory: ____% Load: _____
  - T+5h30m: Disk: ____% Memory: ____% Load: _____
  - T+6h00m: Disk: ____% Memory: ____% Load: _____

### 4.2 Extended Monitoring (T+6 to T+24 hours)

**Check every 2 hours**:

- [ ] **T+8h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+10h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+12h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+14h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+16h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+18h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+20h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+22h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜
- [ ] **T+24h**: Health ⬜ Errors ⬜ Queue ⬜ Resources ⬜

### 4.3 Issue Tracking

**Log any issues encountered**:

| Time | Issue | Severity | Action Taken | Resolved |
|------|-------|----------|--------------|----------|
| T+___ | _________________ | P1/P2/P3/P4 | _________________ | Yes/No |
| T+___ | _________________ | P1/P2/P3/P4 | _________________ | Yes/No |
| T+___ | _________________ | P1/P2/P3/P4 | _________________ | Yes/No |

### 4.4 User Feedback

**Monitor user reports**:

- [ ] Support tickets: Count: _____ (Expected: < 5)
- [ ] User complaints: Count: _____ (Expected: 0)
- [ ] Performance complaints: Count: _____ (Expected: 0)

---

## Phase 5: Post-Deployment (T+24 hours)

**Timeline**: 24 hours after go-live
**Duration**: 2-3 hours
**Responsible**: Full Team

### 5.1 24-Hour Review

- [ ] **Review metrics**
  ```bash
  # Error rate
  php artisan logs:analyze --stats --since="24 hours ago"

  # Performance
  php artisan logs:analyze --channel=performance --stats --since="24 hours ago"

  # Queue stats
  php artisan logs:analyze --channel=queue --stats --since="24 hours ago"
  ```

  **Metrics**:
  - Error Rate: _____% (Expected: < 1%)
  - Slow Requests: _____% (Expected: < 5%)
  - Failed Jobs: _____ (Expected: < 10)

- [ ] **External monitoring review**
  - Uptime: _____% (Expected: 100%)
  - Downtime incidents: _____ (Expected: 0)
  - Average response time: _____ ms (Expected: < 200ms)

- [ ] **User feedback review**
  - Total support tickets: _____
  - Critical issues: _____
  - User satisfaction: Positive ⬜ / Negative ⬜

### 5.2 Performance Comparison

- [ ] **Compare to baseline**
  ```bash
  # Current metrics
  curl -s https://your-domain.com/api/health | jq '.metrics' > /tmp/current-metrics.json

  # Compare to baseline
  diff /tmp/baseline-metrics.json /tmp/current-metrics.json
  ```

  **Comparison**:
  - Response time: Baseline: _____ ms, Current: _____ ms
  - Memory usage: Baseline: _____ MB, Current: _____ MB
  - Degradation: ⬜ None / ⬜ Minor / ⬜ Significant

### 5.3 Issue Summary

- [ ] **Document all issues**
  - Create ticket for each issue
  - Assign priority and owner
  - Set resolution timeline

  **Issues**:
  1. _________________________________________ Priority: P___ Owner: _______
  2. _________________________________________ Priority: P___ Owner: _______
  3. _________________________________________ Priority: P___ Owner: _______

### 5.4 Post-Mortem Meeting

- [ ] **Schedule post-mortem** (within 48 hours of go-live)
  - Date: _____________
  - Time: _____________
  - Attendees: _________________________

- [ ] **Prepare post-mortem agenda**:
  1. What went well?
  2. What could be improved?
  3. What issues occurred?
  4. Action items for future deployments

### 5.5 Documentation Updates

- [ ] **Update documentation** based on learnings
  - [ ] Deployment runbook
  - [ ] Operations manual
  - [ ] Troubleshooting guide
  - [ ] This go-live checklist

- [ ] **Archive go-live records**
  - [ ] This completed checklist
  - [ ] Deployment logs
  - [ ] Incident reports
  - [ ] Performance metrics

### 5.6 Return to Normal Operations

- [ ] **Reduce monitoring frequency**
  - Change from hourly to daily checks
  - Review monitoring schedule in operations manual

- [ ] **Resume code deployments**
  - Lift code freeze
  - Resume normal development workflow

- [ ] **Notify stakeholders**
  - Send go-live success email
  - Highlight key metrics
  - Thank team members

- [ ] **Celebrate! 🎉**
  - Team successfully deployed to production
  - Application is live and stable

---

## Rollback Procedure

**Use this if critical issues are encountered during deployment**

### When to Rollback

Rollback immediately if:
- Health endpoint returns "unhealthy" and cannot be fixed in 15 minutes
- Critical feature completely broken
- Data loss or corruption detected
- Error rate > 10%
- Security breach detected

### Rollback Steps

1. **Announce rollback decision**
   ```
   Post in Slack: "🚨 ROLLBACK in progress - Critical issue detected"
   ```

2. **Enable maintenance mode**
   ```bash
   php artisan down
   ```

3. **Stop queue workers**
   ```bash
   sudo supervisorctl stop ai-legal-queue-worker:*
   ```

4. **Restore previous code**
   ```bash
   cd /var/www/ai-legal-war-machine

   # Get rollback commit
   ROLLBACK_COMMIT=$(cat /tmp/rollback-commit.txt)

   # Checkout previous commit
   git checkout $ROLLBACK_COMMIT

   # Reinstall dependencies
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

5. **Rollback database** (if migrations were run)
   ```bash
   # Rollback migrations
   php artisan migrate:rollback --step=1

   # Or restore database backup
   # See deployment-runbook.md Section 15.2
   ```

6. **Clear caches**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear

   php artisan config:cache
   php artisan route:cache
   ```

7. **Restart services**
   ```bash
   sudo systemctl restart php8.2-fpm
   sudo systemctl reload nginx
   sudo supervisorctl start ai-legal-queue-worker:*
   ```

8. **Verify health**
   ```bash
   curl -s http://localhost/api/health | jq '.status'
   # Expected: "healthy"
   ```

9. **Disable maintenance mode**
   ```bash
   php artisan up
   ```

10. **Verify external access**
    ```bash
    curl -I https://your-domain.com
    # Expected: HTTP/2 200
    ```

11. **Announce rollback complete**
    ```
    Post in Slack: "✅ ROLLBACK completed - Application restored to previous version"
    ```

12. **Schedule post-mortem**
    - Investigate root cause
    - Fix issue
    - Test fix thoroughly
    - Schedule new deployment

---

## Emergency Contacts

### On-Call Team

| Role | Name | Phone | Email | Backup |
|------|------|-------|-------|--------|
| DevOps Lead | ____________ | ____________ | ____________ | ____________ |
| System Admin | ____________ | ____________ | ____________ | ____________ |
| Backend Dev | ____________ | ____________ | ____________ | ____________ |
| On-Call Engineer | ____________ | ____________ | ____________ | ____________ |

### Escalation Path

1. **Level 1**: On-Call Engineer (respond within 15 minutes)
2. **Level 2**: DevOps Lead (respond within 30 minutes)
3. **Level 3**: Technical Lead (respond within 1 hour)
4. **Level 4**: CTO/VP Engineering (respond within 2 hours)

### Communication Channels

- **Primary**: Slack #go-live
- **Secondary**: Phone/SMS (numbers above)
- **Video**: Zoom/Meet link: _______________
- **Status Updates**: Status page / Twitter / Email

---

## Sign-Off

### Deployment Completion

**Go-Live Completed**: _____________ at _____________
**Rollback Required**: YES ⬜ / NO ⬜

**Final Status**: SUCCESS ⬜ / PARTIAL SUCCESS ⬜ / FAILURE ⬜

### Team Sign-Off

- DevOps Lead: _____________ Date: _______
- System Admin: _____________ Date: _______
- Technical Lead: _____________ Date: _______
- Project Manager: _____________ Date: _______

### Notes

_____________________________________________________________________
_____________________________________________________________________
_____________________________________________________________________
_____________________________________________________________________
_____________________________________________________________________

---

## Post-Mortem Template

**Meeting Date**: _____________
**Attendees**: _________________________________________________

### What Went Well

1. _____________________________________________________________
2. _____________________________________________________________
3. _____________________________________________________________

### What Could Be Improved

1. _____________________________________________________________
2. _____________________________________________________________
3. _____________________________________________________________

### Issues Encountered

| Issue | Impact | Root Cause | Resolution |
|-------|--------|------------|------------|
| _________________ | High/Med/Low | _________________ | _________________ |
| _________________ | High/Med/Low | _________________ | _________________ |
| _________________ | High/Med/Low | _________________ | _________________ |

### Action Items

| Action | Owner | Deadline | Status |
|--------|-------|----------|--------|
| _______________________________ | _______ | _______ | Open/Closed |
| _______________________________ | _______ | _______ | Open/Closed |
| _______________________________ | _______ | _______ | Open/Closed |

### Lessons Learned

1. _____________________________________________________________
2. _____________________________________________________________
3. _____________________________________________________________

### Recommendations for Future Deployments

1. _____________________________________________________________
2. _____________________________________________________________
3. _____________________________________________________________

---

**End of Go-Live Checklist**

**Good luck with the deployment! 🚀**
