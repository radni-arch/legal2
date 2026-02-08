#!/bin/bash
# ============================================
# Queue Worker Health Check Script
# AI Legal War Machine
# ============================================
#
# This script checks if the expected number of queue workers are running.
# Returns exit code 0 if healthy, 1 if unhealthy.
#
# Usage:
#   ./scripts/check-queue-workers.sh
#
# Cron example (check every 5 minutes):
#   */5 * * * * /var/www/ai-legal-war-machine/scripts/check-queue-workers.sh
#
# ============================================

# Expected number of workers
EXPECTED=4

# Count running workers
RUNNING=$(supervisorctl status ai-legal-queue-worker:* 2>/dev/null | grep RUNNING | wc -l)

# Check if we can connect to supervisor
if [ $? -ne 0 ]; then
    echo "ERROR: Cannot connect to Supervisor"
    exit 1
fi

# Check if enough workers are running
if [ $RUNNING -lt $EXPECTED ]; then
    echo "WARNING: Only $RUNNING/$EXPECTED workers running!"
    echo "Workers status:"
    supervisorctl status ai-legal-queue-worker:*
    exit 1
else
    echo "OK: $RUNNING/$EXPECTED workers running"
    exit 0
fi
