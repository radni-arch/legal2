# Textract Node Cleanup Documentation

## Overview

The `textract:clean-orphaned-nodes` command finds and removes orphaned `TextractDocument` nodes in Neo4j that no longer have corresponding database records. This handles cases where documents were deleted from the database but not properly removed from the graph.

## Problem Statement

When TextractDocument records are deleted from PostgreSQL without proper graph cleanup:
- 🔴 **Data Integrity:** Orphaned graph nodes accumulate
- 🔴 **Graph Pollution:** Relationships point to non-existent documents
- 🔴 **Search Confusion:** Deleted documents may still appear in graph queries
- 🔴 **Storage Waste:** Neo4j database grows unnecessarily

## Command Usage

### Basic Usage

```bash
# Find and delete all orphaned nodes
php artisan textract:clean-orphaned-nodes

# Preview what would be deleted (dry-run)
php artisan textract:clean-orphaned-nodes --dry-run

# Custom batch size for large databases
php artisan textract:clean-orphaned-nodes --batch=50
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--dry-run` | Preview orphaned nodes without deleting | false |
| `--batch` | Batch size for database lookups | 100 |

## How It Works

### Step 1: Query Neo4j
```cypher
MATCH (n:TextractDocument) RETURN n.id as id
```
Retrieves all TextractDocument node IDs from the graph.

### Step 2: Check Database
Queries the `textract_documents` table in batches to find which nodes have corresponding DB records.

### Step 3: Identify Orphans
Compares Neo4j node IDs with database IDs to identify orphans:
```
orphaned_nodes = neo4j_ids - database_ids
```

### Step 4: Delete (if not dry-run)
```cypher
MATCH (n:TextractDocument {id: $id})
DETACH DELETE n
```
Deletes each orphaned node and all its relationships.

## Example Output

### Dry Run Mode

```
🔍 DRY RUN MODE - No nodes will be deleted

🧹 Cleaning Orphaned TextractDocument Nodes...

Step 1: Finding orphaned nodes...
  Found 250 TextractDocument nodes in Neo4j
  Found 235 corresponding records in database
Found 15 orphaned TextractDocument node(s)

Sample of orphaned node IDs:
  1. doc-abc123
  2. doc-def456
  3. doc-ghi789
  4. doc-jkl012
  5. doc-mno345
  6. doc-pqr678
  7. doc-stu901
  8. doc-vwx234
  9. doc-yza567
  10. doc-bcd890
  ... and 5 more

DRY RUN: No nodes deleted. Run without --dry-run to delete orphaned nodes.
```

### Actual Deletion

```
🧹 Cleaning Orphaned TextractDocument Nodes...

Step 1: Finding orphaned nodes...
  Found 250 TextractDocument nodes in Neo4j
  Found 235 corresponding records in database
Found 15 orphaned TextractDocument node(s)

Sample of orphaned node IDs:
  1. doc-abc123
  2. doc-def456
  3. doc-ghi789
  4. doc-jkl012
  5. doc-mno345
  6. doc-pqr678
  7. doc-stu901
  8. doc-vwx234
  9. doc-yza567
  10. doc-bcd890
  ... and 5 more

Step 2: Deleting orphaned nodes...
 15/15 [████████████████████████████] 100%

═══════════════════════════════════════════════════════
  CLEANUP SUMMARY
═══════════════════════════════════════════════════════
  Total orphaned nodes found: 15
  Deleted successfully: 15
  Errors: 0
═══════════════════════════════════════════════════════

✅ Cleanup completed in 2.34s
```

### With Errors

```
Step 2: Deleting orphaned nodes...
 15/15 [████████████████████████████] 100%

═══════════════════════════════════════════════════════
  CLEANUP SUMMARY
═══════════════════════════════════════════════════════
  Total orphaned nodes found: 15
  Deleted successfully: 13
  Errors encountered: 2

Error details:
  1. Node: doc-error1 - Connection timeout
  2. Node: doc-error2 - Node not found
═══════════════════════════════════════════════════════

✅ Cleanup completed in 3.12s
```

### No Orphans Found

```
🧹 Cleaning Orphaned TextractDocument Nodes...

Step 1: Finding orphaned nodes...
  Found 235 TextractDocument nodes in Neo4j
  Found 235 corresponding records in database
✅ No orphaned nodes found. Graph is clean!
```

### Neo4j Unavailable

```
🧹 Cleaning Orphaned TextractDocument Nodes...

❌ Neo4j is not available. Please check the connection.
```

## Scheduling

### Recommended Schedule

Add to `app/Console/Kernel.php`:

```php
// Textract node cleanup - monthly on first Sunday at 6:00 AM
$schedule->command('textract:clean-orphaned-nodes')
    ->monthlyOn(1, '06:00')
    ->sundays()
    ->name('textract-node-cleanup-monthly')
    ->onOneServer()
    ->withoutOverlapping(60)
    ->emailOutputOnFailure('admin@example.com');
```

### Alternative Schedules

```php
// Weekly cleanup (for high-volume systems)
$schedule->command('textract:clean-orphaned-nodes')
    ->weekly()
    ->sundays()
    ->at('06:00');

// Daily cleanup (for systems with frequent deletions)
$schedule->command('textract:clean-orphaned-nodes')
    ->daily()
    ->at('03:00');
```

## Testing

### Unit Tests

Located in: `tests/Unit/Console/CleanOrphanedTextractNodesTest.php`

Tests cover:
- ✅ Detecting orphaned nodes when DB record missing
- ✅ Returning empty array when no orphans found
- ✅ Handling Neo4j unavailable gracefully
- ✅ Processing nodes in batches

Run unit tests:
```bash
php artisan test --filter=CleanOrphanedTextractNodesTest
```

### Feature Tests

Located in: `tests/Feature/CleanOrphanedTextractNodesCommandTest.php`

Tests cover:
- ✅ Dry-run mode doesn't delete
- ✅ Actual deletion removes nodes
- ✅ Neo4j unavailable returns error
- ✅ Respects batch size option
- ✅ Handles deletion errors gracefully
- ✅ Displays progress bar
- ✅ Shows success message when no orphans

Run feature tests:
```bash
php artisan test --filter=CleanOrphanedTextractNodesCommandTest
```

### Integration Testing

```bash
# 1. Create orphaned nodes manually in Neo4j
CREATE (n:TextractDocument {id: 'orphan-test-1', created_at: '2025-11-15'})

# 2. Run cleanup in dry-run mode
php artisan textract:clean-orphaned-nodes --dry-run

# 3. Verify orphan detected
# Expected: "Found 1 orphaned TextractDocument node(s)"

# 4. Run actual cleanup
php artisan textract:clean-orphaned-nodes

# 5. Verify node deleted in Neo4j
MATCH (n:TextractDocument {id: 'orphan-test-1'}) RETURN n
# Expected: No results
```

## Performance Considerations

### Batch Processing

The command processes nodes in batches (default 100) to avoid memory issues:

```php
// For 10,000 Neo4j nodes with batch size 100:
// - 100 database queries (one per batch)
// - Memory efficient (processes in chunks)
// - Estimated time: 2-5 minutes
```

### Large Databases

For databases with thousands of nodes:

```bash
# Use smaller batch size to reduce memory
php artisan textract:clean-orphaned-nodes --batch=50

# Or run during off-peak hours
0 3 * * 0 cd /path/to/app && php artisan textract:clean-orphaned-nodes
```

### Progress Bar

Progress bar shows deletion progress for better visibility:
```
Deleting orphaned nodes...
 156/500 [███████░░░░░░░░░░░░░░░░░░░] 31%
```

## Error Handling

### Neo4j Unavailable

Command exits with failure code (1) and error message:
```
❌ Neo4j is not available. Please check the connection.
```

### Deletion Errors

Individual deletion errors are logged but don't stop the process:
```
Deleted successfully: 13
Errors encountered: 2

Error details:
  1. Node: doc-error1 - Connection timeout
```

Errors are also logged to `storage/logs/laravel.log`:
```
[2025-11-15 10:30:45] local.ERROR: Failed to delete orphaned TextractDocument node
{"node_id":"doc-error1","error":"Connection timeout"}
```

## Monitoring

### Success Indicators

- Exit code: `0`
- Output: "✅ Cleanup completed"
- Log: INFO level messages for deleted nodes

### Failure Indicators

- Exit code: `1`
- Output: "❌ Error during cleanup"
- Log: ERROR level messages
- Email alert (if configured in schedule)

### Grafana Metrics

Track cleanup operations:
```
textract_cleanup_runs_total
textract_orphaned_nodes_found
textract_orphaned_nodes_deleted
textract_cleanup_errors
textract_cleanup_duration_seconds
```

## Troubleshooting

### "Neo4j is not available"

**Cause:** Neo4j connection failed

**Solutions:**
1. Check Neo4j is running: `docker ps | grep neo4j`
2. Verify connection in `.env`: `NEO4J_URI`, `NEO4J_PASSWORD`
3. Test connection: `php artisan graph:stats`
4. Check Neo4j logs: `docker logs neo4j`

### "Connection timeout" errors

**Cause:** Neo4j under heavy load

**Solutions:**
1. Run during off-peak hours
2. Reduce batch size: `--batch=25`
3. Increase timeout in `config/neo4j.php`

### No orphans found but graph seems large

**Cause:** Nodes exist for valid documents

**Solutions:**
1. Check if documents are in database: `SELECT COUNT(*) FROM textract_documents`
2. Check if nodes are in Neo4j: `MATCH (n:TextractDocument) RETURN COUNT(n)`
3. Verify node IDs match: `MATCH (n:TextractDocument) RETURN n.id LIMIT 10`

### Memory errors with large datasets

**Cause:** Too many nodes processed at once

**Solutions:**
1. Use smaller batch size: `--batch=50`
2. Increase PHP memory limit: `php -d memory_limit=512M artisan textract:clean-orphaned-nodes`

## Best Practices

### 1. Always Test with Dry-Run First

```bash
# Preview before deleting
php artisan textract:clean-orphaned-nodes --dry-run

# Review the output carefully
# Then run actual deletion
php artisan textract:clean-orphaned-nodes
```

### 2. Monitor First Run

After deploying the command, monitor the first execution:
```bash
# Watch logs in real-time
php artisan pail --filter=CleanOrphanedTextractNodes

# Run cleanup
php artisan textract:clean-orphaned-nodes
```

### 3. Schedule During Off-Peak Hours

Run when database load is low:
- Weekends (Sunday 3-6 AM)
- Weekdays early morning (3-5 AM)

### 4. Alert on High Orphan Counts

If cleanup finds > 100 orphaned nodes, investigate:
- Check if deletion sync is working properly
- Review recent database operations
- Verify `TextractDocumentObserver` is registered

## Related Documentation

- [TEXTRACT_MANAGER_ASSESSMENT.md](../TEXTRACT_MANAGER_ASSESSMENT.md) - Sprint plan and assessment
- [CLAUDE.md](../CLAUDE.md) - Project setup and commands
- [API_DOCUMENTATION.md](../API_DOCUMENTATION.md) - API reference

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Run diagnostics: `php artisan graph:stats`
3. Review test suite: `php artisan test --filter=CleanOrphanedTextractNodes`
4. Contact: development team

---

**Last Updated:** 2025-11-15
**Version:** 1.0.0
**Status:** Production Ready
