#!/bin/bash
# ============================================
# Queue Monitoring Script
# AI Legal War Machine
# ============================================
#
# This script monitors the size of all priority queues in Redis.
#
# Usage:
#   ./scripts/monitor-queues.sh
#
# Cron example (log queue sizes every hour):
#   0 * * * * /var/www/ai-legal-war-machine/scripts/monitor-queues.sh >> /var/log/queue-monitor.log
#
# ============================================

# Get Redis password from environment or config
if [ -z "$REDIS_PASSWORD" ]; then
    # Try to read from .env file
    if [ -f /var/www/ai-legal-war-machine/.env ]; then
        REDIS_PASSWORD=$(grep REDIS_PASSWORD /var/www/ai-legal-war-machine/.env | cut -d '=' -f2)
    fi
fi

# Get queue sizes from Redis (database 2)
HIGH=$(redis-cli -a "$REDIS_PASSWORD" -n 2 LLEN queues:high 2>/dev/null || echo 0)
AGENTS=$(redis-cli -a "$REDIS_PASSWORD" -n 2 LLEN queues:agents 2>/dev/null || echo 0)
TEXTRACT=$(redis-cli -a "$REDIS_PASSWORD" -n 2 LLEN queues:textract 2>/dev/null || echo 0)
DEFAULT=$(redis-cli -a "$REDIS_PASSWORD" -n 2 LLEN queues:default 2>/dev/null || echo 0)
LOW=$(redis-cli -a "$REDIS_PASSWORD" -n 2 LLEN queues:low 2>/dev/null || echo 0)

# Calculate total
TOTAL=$((HIGH + AGENTS + TEXTRACT + DEFAULT + LOW))

# Display results
echo "=== Queue Monitor - $(date '+%Y-%m-%d %H:%M:%S') ==="
echo ""
echo "Queue Sizes:"
echo "  High:     $HIGH"
echo "  Agents:   $AGENTS"
echo "  Textract: $TEXTRACT"
echo "  Default:  $DEFAULT"
echo "  Low:      $LOW"
echo "  ─────────────────"
echo "  Total:    $TOTAL"
echo ""

# Alert if high priority queue is backed up
if [ $HIGH -gt 100 ]; then
    echo "⚠️  WARNING: High priority queue has $HIGH jobs!"
fi

# Alert if any queue is severely backed up
if [ $TOTAL -gt 1000 ]; then
    echo "⚠️  WARNING: Total queue size is $TOTAL jobs!"
fi

# Exit with status code based on queue health
if [ $HIGH -gt 500 ] || [ $TOTAL -gt 5000 ]; then
    exit 1
else
    exit 0
fi
