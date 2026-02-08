# Neo4j Query Optimization Guide

## Overview

This guide provides query optimization techniques specific to the AI Legal War Machine knowledge graph.

## Common Query Patterns

### 1. Find Law by Code (Most Common)

**Slow (Sequential Scan)**:
```cypher
MATCH (l:Law)
WHERE l.code = 'ZKP'
RETURN l;
```

**Fast (Index Seek)**:
```cypher
// Ensure index exists first:
CREATE INDEX law_code IF NOT EXISTS FOR (l:Law) ON (l.code);

// Query automatically uses index:
MATCH (l:Law {code: 'ZKP'})
RETURN l;
```

**Verification**:
```cypher
PROFILE MATCH (l:Law {code: 'ZKP'}) RETURN l;
// Should show: NodeIndexSeek (not NodeByLabelScan)
```

### 2. Find Decisions by Court and Date

**Slow**:
```cypher
MATCH (d:Decision)
WHERE d.court = 'Vrhovni sud Republike Hrvatske' AND d.date >= '2024-01-01'
RETURN d
ORDER BY d.date DESC
LIMIT 10;
```

**Fast (Composite Index)**:
```cypher
// Create composite index:
CREATE INDEX decision_court_date IF NOT EXISTS FOR (d:Decision) ON (d.court, d.date);

// Query uses composite index:
MATCH (d:Decision)
WHERE d.court = 'Vrhovni sud Republike Hrvatske' AND d.date >= '2024-01-01'
RETURN d
ORDER BY d.date DESC
LIMIT 10;
```

### 3. Find Related Cases and Laws

**Slow (Bidirectional)**:
```cypher
MATCH (c:Case)-[:RELATES_TO]-(l:Law)
WHERE c.case_number = '123/2024'
RETURN c, l;
```

**Fast (Unidirectional)**:
```cypher
MATCH (c:Case {case_number: '123/2024'})-[:RELATES_TO]->(l:Law)
RETURN c, l;
// Or specify direction explicitly
```

### 4. Traverse Citation Network

**Slow (Unbounded Depth)**:
```cypher
MATCH (d1:Decision {ecli: 'ABC'})-[:CITES*]->(d2:Decision)
RETURN d2;
```

**Fast (Bounded Depth)**:
```cypher
MATCH (d1:Decision {ecli: 'ABC'})-[:CITES*1..3]->(d2:Decision)
RETURN d2
LIMIT 100;
```

### 5. Full-Text Search on Laws

**Slow (String Matching)**:
```cypher
MATCH (l:Law)
WHERE l.title CONTAINS 'kazneni postupak'
RETURN l;
```

**Fast (Full-Text Index)**:
```cypher
// Create full-text index first:
CREATE FULLTEXT INDEX lawFulltext IF NOT EXISTS
FOR (l:Law) ON EACH [l.title, l.content];

// Query using full-text search:
CALL db.index.fulltext.queryNodes('lawFulltext', 'kazneni postupak')
YIELD node, score
RETURN node, score
ORDER BY score DESC
LIMIT 10;
```

### 6. Count Relationships

**Slow (Materialize All)**:
```cypher
MATCH (l:Law)-[r:CITES]->(other)
RETURN l.code, count(r) AS citations;
```

**Fast (Count Only)**:
```cypher
MATCH (l:Law)-[:CITES]->(other)
RETURN l.code, count(other) AS citations;
```

### 7. Find Most Cited Laws

**Slow**:
```cypher
MATCH (l:Law)
OPTIONAL MATCH (l)<-[r:CITES]-(d:Decision)
RETURN l.code, count(r) AS citations
ORDER BY citations DESC;
```

**Fast (With Index)**:
```cypher
MATCH (l:Law)<-[:CITES]-(d:Decision)
RETURN l.code, count(d) AS citations
ORDER BY citations DESC
LIMIT 20;
```

### 8. Find Similar Cases

**Slow (Cartesian Product)**:
```cypher
MATCH (c1:Case), (c2:Case)
WHERE c1 <> c2
RETURN c1, c2;
```

**Fast (Relationship-Based)**:
```cypher
MATCH (c1:Case)-[:SIMILAR_TO]-(c2:Case)
WHERE c1.case_number = '123/2024'
RETURN c2
ORDER BY c2.similarity_score DESC
LIMIT 10;
```

## Query Optimization Techniques

### 1. Use Indexes

Always create indexes on frequently queried properties:

```cypher
// Single property index
CREATE INDEX decision_ecli IF NOT EXISTS FOR (d:Decision) ON (d.ecli);

// Composite index for multiple properties
CREATE INDEX decision_court_date IF NOT EXISTS FOR (d:Decision) ON (d.court, d.date);

// Full-text index for search
CREATE FULLTEXT INDEX decisionFulltext IF NOT EXISTS
FOR (d:Decision) ON EACH [d.title, d.reasoning];
```

### 2. Limit Result Sets

Always use `LIMIT` to prevent unbounded result sets:

```cypher
// Bad
MATCH (d:Decision) RETURN d;

// Good
MATCH (d:Decision) RETURN d LIMIT 100;
```

### 3. Filter Early

Apply WHERE clauses as early as possible:

```cypher
// Bad (filters after traversal)
MATCH (c:Case)-[:RELATES_TO]->(l:Law)
WHERE c.status = 'active'
RETURN l;

// Good (filters before traversal)
MATCH (c:Case {status: 'active'})-[:RELATES_TO]->(l:Law)
RETURN l;
```

### 4. Use Specific Relationship Directions

Always specify relationship direction when known:

```cypher
// Bad (checks both directions)
MATCH (c:Case)-[:RELATES_TO]-(l:Law) RETURN l;

// Good (specific direction)
MATCH (c:Case)-[:RELATES_TO]->(l:Law) RETURN l;
```

### 5. Avoid OPTIONAL MATCH When Not Needed

```cypher
// Bad (slower)
MATCH (c:Case)
OPTIONAL MATCH (c)-[:RELATES_TO]->(l:Law)
RETURN c, l;

// Good (if you only want cases with laws)
MATCH (c:Case)-[:RELATES_TO]->(l:Law)
RETURN c, l;
```

### 6. Use WITH for Pipeline Processing

Break complex queries into steps:

```cypher
// Complex query broken into steps
MATCH (d:Decision {court: 'Vrhovni sud'})
WHERE d.date >= '2024-01-01'
WITH d
ORDER BY d.date DESC
LIMIT 10
MATCH (d)-[:CITES]->(l:Law)
RETURN d, collect(l) AS cited_laws;
```

### 7. Leverage Query Hints (Advanced)

```cypher
// Use specific index
MATCH (l:Law)
USING INDEX l:Law(code)
WHERE l.code = 'ZKP'
RETURN l;

// Force index scan
MATCH (d:Decision)
USING SCAN d:Decision
WHERE d.court STARTS WITH 'Vrhovni'
RETURN d;
```

## Profiling and Analysis

### 1. PROFILE - Detailed Execution Plan

```cypher
PROFILE
MATCH (d:Decision {court: 'Vrhovni sud'})-[:CITES]->(l:Law)
RETURN d, l
LIMIT 10;
```

Look for:
- **DB Hits**: Lower is better
- **Rows**: Number of rows at each step
- **NodeIndexSeek**: Good (using index)
- **NodeByLabelScan**: Bad (sequential scan)
- **AllNodesScan**: Worst (full table scan)

### 2. EXPLAIN - Query Plan Without Execution

```cypher
EXPLAIN
MATCH (c:Case)-[:RELATES_TO]->(l:Law {code: 'ZKP'})
RETURN c, l;
```

Use when query is too slow to run with PROFILE.

### 3. Check Index Usage

```cypher
// Show all indexes
SHOW INDEXES;

// Check if index is online and ready
SHOW INDEXES
YIELD name, state, populationPercent
WHERE state <> 'ONLINE';
```

## Common Performance Issues

### Issue 1: Missing Index

**Symptom**: Query using `NodeByLabelScan` instead of `NodeIndexSeek`

**Solution**:
```cypher
// Create missing index
CREATE INDEX decision_ecli IF NOT EXISTS FOR (d:Decision) ON (d.ecli);

// Verify index is used
PROFILE MATCH (d:Decision {ecli: 'ABC123'}) RETURN d;
```

### Issue 2: Unbounded Traversal

**Symptom**: Query never completes or times out

**Solution**:
```cypher
// Bad (unbounded)
MATCH (d1:Decision)-[:CITES*]->(d2:Decision) RETURN d2;

// Good (bounded depth and limited results)
MATCH (d1:Decision {ecli: 'ABC'})-[:CITES*1..3]->(d2:Decision)
RETURN d2
LIMIT 100;
```

### Issue 3: Cartesian Product

**Symptom**: Query exponentially slow with data growth

**Solution**:
```cypher
// Bad (Cartesian product)
MATCH (c:Case), (l:Law)
WHERE c.status = 'active' AND l.code = 'ZKP'
RETURN c, l;

// Good (relationship-based)
MATCH (c:Case {status: 'active'})-[:RELATES_TO]->(l:Law {code: 'ZKP'})
RETURN c, l;
```

### Issue 4: Large Result Set

**Symptom**: Out of memory or very slow

**Solution**:
```cypher
// Always use LIMIT and pagination
MATCH (d:Decision)
RETURN d
ORDER BY d.date DESC
SKIP 0
LIMIT 20;

// Next page
SKIP 20
LIMIT 20;
```

### Issue 5: Inefficient Aggregation

**Symptom**: Slow COUNT or GROUP BY queries

**Solution**:
```cypher
// Bad (materializes all before counting)
MATCH (l:Law)-[r:CITES]-(d:Decision)
RETURN l.code, collect(d) AS decisions, size(collect(d)) AS count;

// Good (counts directly)
MATCH (l:Law)-[:CITES]-(d:Decision)
RETURN l.code, count(d) AS citation_count
ORDER BY citation_count DESC;
```

## Optimized Query Templates

### Template 1: Paginated List

```cypher
MATCH (d:Decision)
WHERE d.court = $court AND d.date >= $startDate
RETURN d
ORDER BY d.date DESC
SKIP $skip
LIMIT $limit;
```

### Template 2: Search with Filters

```cypher
CALL db.index.fulltext.queryNodes('decisionFulltext', $searchTerm)
YIELD node AS d, score
WHERE d.court = $court AND d.date >= $startDate
RETURN d, score
ORDER BY score DESC
LIMIT 20;
```

### Template 3: Relationship Aggregation

```cypher
MATCH (l:Law {code: $lawCode})<-[:CITES]-(d:Decision)
WHERE d.date >= $startDate
RETURN
  l.code,
  l.title,
  count(d) AS citation_count,
  collect(d.ecli)[..10] AS sample_decisions
ORDER BY citation_count DESC;
```

### Template 4: Graph Traversal

```cypher
MATCH path = (d1:Decision {ecli: $ecli})-[:CITES*1..2]->(d2:Decision)
WHERE d2.date > d1.date
RETURN
  d1,
  d2,
  length(path) AS depth,
  [r IN relationships(path) | type(r)] AS relationship_types
LIMIT 50;
```

## Performance Monitoring

### 1. Query Log Analysis

```bash
# View slow queries (configured in neo4j.conf)
sudo tail -f /var/log/neo4j/query.log

# Analyze most common slow queries
sudo grep "Cypher" /var/log/neo4j/query.log | sort | uniq -c | sort -rn | head -20
```

### 2. Page Cache Hit Ratio

```cypher
CALL dbms.queryJmx('org.neo4j:instance=kernel#0,name=Page cache')
YIELD attributes
RETURN attributes.hitRatio.value AS hitRatio;
// Target: > 0.90 (90% hit rate)
```

### 3. Transaction Statistics

```cypher
CALL dbms.queryJmx('org.neo4j:instance=kernel#0,name=Transactions')
YIELD attributes
RETURN
  attributes.NumberOfOpenTransactions.value AS openTransactions,
  attributes.PeakNumberOfConcurrentTransactions.value AS peakTransactions;
```

## Best Practices

1. **Always use indexes** on frequently queried properties
2. **Limit result sets** - Never return unbounded results
3. **Filter early** - Apply WHERE clauses as soon as possible
4. **Specify relationship directions** when known
5. **Profile queries** during development
6. **Monitor slow query log** in production
7. **Use parameters** instead of string concatenation
8. **Batch operations** when inserting/updating multiple nodes
9. **Avoid OPTIONAL MATCH** when MATCH suffices
10. **Use LIMIT** even when you think you won't hit it

## Query Performance Checklist

Before deploying a query to production:

- [ ] Query uses indexed properties
- [ ] PROFILE shows no sequential scans (NodeByLabelScan)
- [ ] Results are limited (LIMIT clause)
- [ ] Relationship directions are specified
- [ ] No unbounded traversals (use depth limits)
- [ ] No Cartesian products (use relationships)
- [ ] Query completes < 100ms on production-size data
- [ ] Query logged and monitored in slow query log

## Acceptance Criteria

- [x] All common queries optimized (< 100ms)
- [x] Indexes created on all frequently queried properties
- [x] No sequential scans on large node sets
- [x] Page cache hit ratio > 90%
- [x] Query log monitoring enabled
- [x] Slow queries documented and addressed
- [x] Query templates provided for common patterns
