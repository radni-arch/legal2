// ============================================
// Neo4j Index Creation Script
// AI Legal War Machine - Knowledge Graph
// ============================================
//
// This script creates all necessary indexes for optimal query performance.
// Run this script after initial Neo4j setup.
//
// Usage:
//   cypher-shell -u neo4j -p your-password < create-neo4j-indexes.cypher
//
// Or run manually via cypher-shell:
//   cypher-shell -u neo4j -p your-password
//   Then paste commands one by one
//
// ============================================

// --------------------------------------------
// NODE PROPERTY INDEXES
// --------------------------------------------

// Case nodes
CREATE INDEX case_number IF NOT EXISTS FOR (c:Case) ON (c.case_number);
CREATE INDEX case_id IF NOT EXISTS FOR (c:Case) ON (c.id);
CREATE INDEX case_created_at IF NOT EXISTS FOR (c:Case) ON (c.created_at);
CREATE INDEX case_status IF NOT EXISTS FOR (c:Case) ON (c.status);

// Law nodes
CREATE INDEX law_code IF NOT EXISTS FOR (l:Law) ON (l.code);
CREATE INDEX law_id IF NOT EXISTS FOR (l:Law) ON (l.id);
CREATE INDEX law_title IF NOT EXISTS FOR (l:Law) ON (l.title);

// Court Decision nodes
CREATE INDEX decision_ecli IF NOT EXISTS FOR (d:Decision) ON (d.ecli);
CREATE INDEX decision_id IF NOT EXISTS FOR (d:Decision) ON (d.id);
CREATE INDEX decision_court IF NOT EXISTS FOR (d:Decision) ON (d.court);
CREATE INDEX decision_date IF NOT EXISTS FOR (d:Decision) ON (d.date);
CREATE INDEX decision_created_at IF NOT EXISTS FOR (d:Decision) ON (d.created_at);

// Keyword nodes
CREATE INDEX keyword_term IF NOT EXISTS FOR (k:Keyword) ON (k.term);
CREATE INDEX keyword_weight IF NOT EXISTS FOR (k:Keyword) ON (k.weight);

// Topic nodes
CREATE INDEX topic_name IF NOT EXISTS FOR (t:Topic) ON (t.name);
CREATE INDEX topic_category IF NOT EXISTS FOR (t:Topic) ON (t.category);

// Court nodes
CREATE INDEX court_name IF NOT EXISTS FOR (c:Court) ON (c.name);
CREATE INDEX court_type IF NOT EXISTS FOR (c:Court) ON (c.type);

// Legal Concept nodes
CREATE INDEX concept_name IF NOT EXISTS FOR (lc:LegalConcept) ON (lc.name);
CREATE INDEX concept_category IF NOT EXISTS FOR (lc:LegalConcept) ON (lc.category);

// Article nodes (Law Articles)
CREATE INDEX article_number IF NOT EXISTS FOR (a:Article) ON (a.number);
CREATE INDEX article_law_id IF NOT EXISTS FOR (a:Article) ON (a.law_id);

// Document nodes
CREATE INDEX document_type IF NOT EXISTS FOR (doc:Document) ON (doc.type);
CREATE INDEX document_created_at IF NOT EXISTS FOR (doc:Document) ON (doc.created_at);

// --------------------------------------------
// COMPOSITE INDEXES (for complex queries)
// --------------------------------------------

// Decisions by court and date (most common query)
CREATE INDEX decision_court_date IF NOT EXISTS FOR (d:Decision) ON (d.court, d.date);

// Cases by status and date
CREATE INDEX case_status_created IF NOT EXISTS FOR (c:Case) ON (c.status, d.created_at);

// Articles by law and number
CREATE INDEX article_law_number IF NOT EXISTS FOR (a:Article) ON (a.law_id, a.number);

// --------------------------------------------
// FULL-TEXT SEARCH INDEXES
// --------------------------------------------

// Full-text search on Law titles and content
CREATE FULLTEXT INDEX lawFulltext IF NOT EXISTS
FOR (l:Law)
ON EACH [l.title, l.content];

// Full-text search on Decision reasoning and summary
CREATE FULLTEXT INDEX decisionFulltext IF NOT EXISTS
FOR (d:Decision)
ON EACH [l.title, d.reasoning, d.summary];

// Full-text search on Case descriptions
CREATE FULLTEXT INDEX caseFulltext IF NOT EXISTS
FOR (c:Case)
ON EACH [c.description, c.notes];

// Full-text search on Legal Concepts
CREATE FULLTEXT INDEX conceptFulltext IF NOT EXISTS
FOR (lc:LegalConcept)
ON EACH [lc.name, lc.description];

// Full-text search on Keywords
CREATE FULLTEXT INDEX keywordFulltext IF NOT EXISTS
FOR (k:Keyword)
ON EACH [k.term];

// --------------------------------------------
// RELATIONSHIP INDEXES (Neo4j 5.x)
// --------------------------------------------

// Index on CITES relationships (frequently queried)
// CREATE INDEX cites_weight IF NOT EXISTS FOR ()-[r:CITES]-() ON (r.weight);

// Index on RELATES_TO relationships
// CREATE INDEX relates_confidence IF NOT EXISTS FOR ()-[r:RELATES_TO]-() ON (r.confidence);

// Index on SIMILAR_TO relationships
// CREATE INDEX similar_score IF NOT EXISTS FOR ()-[r:SIMILAR_TO]-() ON (r.similarity_score);

// --------------------------------------------
// CONSTRAINT (Uniqueness)
// --------------------------------------------

// Ensure unique case numbers
CREATE CONSTRAINT case_number_unique IF NOT EXISTS
FOR (c:Case) REQUIRE c.case_number IS UNIQUE;

// Ensure unique law codes
CREATE CONSTRAINT law_code_unique IF NOT EXISTS
FOR (l:Law) REQUIRE l.code IS UNIQUE;

// Ensure unique ECLI identifiers for decisions
CREATE CONSTRAINT decision_ecli_unique IF NOT EXISTS
FOR (d:Decision) REQUIRE d.ecli IS UNIQUE;

// Ensure unique keyword terms
CREATE CONSTRAINT keyword_term_unique IF NOT EXISTS
FOR (k:Keyword) REQUIRE k.term IS UNIQUE;

// Ensure unique topic names
CREATE CONSTRAINT topic_name_unique IF NOT EXISTS
FOR (t:Topic) REQUIRE t.name IS UNIQUE;

// Ensure unique court names
CREATE CONSTRAINT court_name_unique IF NOT EXISTS
FOR (c:Court) REQUIRE c.name IS UNIQUE;

// --------------------------------------------
// VERIFICATION
// --------------------------------------------

// Show all indexes
SHOW INDEXES;

// Show all constraints
SHOW CONSTRAINTS;

// ============================================
// NOTES
// ============================================
//
// Index Creation Time:
// - Simple property indexes: Instant (or near-instant for existing data)
// - Composite indexes: Depends on data size (< 1 minute for 100k nodes)
// - Full-text indexes: May take longer (1-5 minutes for large datasets)
//
// Index Usage:
// - Neo4j automatically uses indexes when appropriate
// - Use PROFILE or EXPLAIN to verify index usage:
//   PROFILE MATCH (l:Law {code: 'ZKP'}) RETURN l;
//   Should show "NodeIndexSeek" not "NodeByLabelScan"
//
// Index Maintenance:
// - Indexes are automatically maintained by Neo4j
// - No manual rebuilding required (unlike some databases)
// - Indexes are updated atomically with data changes
//
// Performance Impact:
// - Indexes speed up read queries significantly (10x-1000x)
// - Small write overhead (usually < 10%)
// - Disk space: ~10-20% of node/relationship data size
//
// Monitoring Index Performance:
//   CALL db.index.fulltext.queryNodes('lawFulltext', 'kazneni postupak');
//   PROFILE MATCH (l:Law {code: 'ZKP'}) RETURN l;
//
// ============================================
