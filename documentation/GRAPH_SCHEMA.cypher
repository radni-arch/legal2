// ============================================================================
// AI Legal War Machine - Neo4j Graph Schema
// ============================================================================
// This file documents the complete graph schema for the legal knowledge graph.
// It includes node types, properties, relationships, constraints, and indexes.
//
// Sprint: D2.1 - Graph Schema Design
// Date: 2025-10-26
// ============================================================================

// ============================================================================
// 1. NODE TYPES AND PROPERTIES
// ============================================================================

// ----------------------------------------------------------------------------
// 1.1 Legal Document Nodes
// ----------------------------------------------------------------------------

// Law Documents (statutory law, regulations)
(:LawDocument {
  id: String,                    // ULID primary key (unique)
  doc_id: String,                // Document identifier
  title: String,                 // Law title
  law_number: String,            // NN number (e.g., "123/20")
  jurisdiction: String,          // Jurisdiction name
  country: String,               // Country code
  language: String,              // Language code (e.g., "hr")
  chunk_index: Integer,          // Chunk index for large documents
  content_hash: String,          // SHA-256 hash of content
  effective_date: String,        // ISO8601 date when law becomes effective
  promulgation_date: String,     // ISO8601 date when law was promulgated

  // Temporal fields (Sprint 4.1)
  valid_from: String,            // ISO8601 date when version becomes valid
  valid_until: String,           // ISO8601 date when version ends (null if current)
  version: String,               // Version identifier

  // Amendment Tracking Properties (NEW - Phase 4 Enhancement)
  amendments: [String],          // Array of amendment NN numbers (e.g., ["NN 123/21", "NN 45/22"])
  repeal_date: String,           // ISO8601 date when law was repealed (null if active)
  repealed_by: String,           // Law number that repealed this law (e.g., "NN 100/25")
  parent_law_number: String,     // If this is an amendment, reference to original law
  consolidation_date: String,    // ISO8601 date of last consolidation (pročišćeni tekst)

  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Case Documents (generic case law, legacy)
(:CaseDocument {
  id: String,                    // ULID primary key (unique)
  case_id: String,               // Parent case ID
  doc_id: String,                // Document identifier
  title: String,                 // Case title
  category: String,              // Case category
  language: String,              // Language code
  chunk_index: Integer,          // Chunk index
  content_hash: String,          // SHA-256 hash of content
  source: String,                // Source system
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Court Decision Documents (primary node for court decisions)
// NOTE: This is the main node type for representing court decisions in the graph.
// Each CourtDecisionDocument represents a chunk of a court decision.
(:CourtDecisionDocument {
  id: String,                    // ULID primary key (unique)
  decision_id: String,           // Parent court_decisions.id (ULID)
  doc_id: String,                // Document identifier
  title: String,                 // Decision title

  // Core Decision Metadata
  case_number: String,           // Case number (e.g., "Rev 123/2020") - INDEXED
  court: String,                 // Court name - INDEXED
  jurisdiction: String,          // Jurisdiction (e.g., "Croatia", "HR")
  judge: String,                 // Judge name(s)
  decision_date: String,         // ISO8601 date - INDEXED
  publication_date: String,      // ISO8601 date when published
  decision_type: String,         // Type: presuda, rješenje, odluka, etc.
  register: String,              // Court register (upisnik)
  finality: String,              // Finality status (pravomocnost)
  ecli: String,                  // European Case Law Identifier - INDEXED & UNIQUE

  // Document Metadata
  chunk_index: Integer,          // Chunk index for large decisions
  content_hash: String,          // SHA-256 hash of content
  source: String,                // Source (e.g., "odluke.hr", "upload")

  // Outcome Properties (NEW - Phase 4 Enhancement)
  outcome: String,               // "affirmed", "reversed", "remanded", "dismissed"
  holding: String,               // Brief statement of holding
  precedential_value: String,    // "binding", "persuasive", "informational"
  dissent_count: Integer,        // Number of dissenting judges
  concurrence_count: Integer,    // Number of concurring opinions

  // Timestamps
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// ----------------------------------------------------------------------------
// 1.2 Entity Nodes
// ----------------------------------------------------------------------------

// Courts
(:Court {
  id: String,                    // Unique ID: "court_" + MD5(name)
  name: String,                  // Court name (unique)
  jurisdiction: String,          // Jurisdiction
  court_type: String,            // Type: supreme, appellate, first_instance, etc.
  location: String,              // Physical location
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Jurisdictions
(:Jurisdiction {
  id: String,                    // Unique ID: "jurisdiction_" + name
  name: String,                  // Jurisdiction name (unique)
  country: String,               // Country code
  level: String,                 // Level: national, regional, local
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Keywords (extracted from content)
(:Keyword {
  id: String,                    // Unique ID: "keyword_" + MD5(name)
  name: String,                  // Keyword text (unique)
  normalized: String,            // Normalized lowercase version
  category: String,              // Category for grouping
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Tags (manually or automatically assigned)
(:Tag {
  id: String,                    // Unique ID
  name: String,                  // Tag name (unique)
  category: String,              // Category for grouping
  description: String,           // Tag description
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Topics (higher-level legal topics)
(:Topic {
  id: String,                    // Unique ID
  name: String,                  // Topic name (unique)
  description: String,           // Topic description
  parent_topic_id: String,       // Parent topic for hierarchy
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Legal Concepts (abstract legal concepts)
(:LegalConcept {
  id: String,                    // Unique ID
  name: String,                  // Concept name (unique)
  definition: String,            // Concept definition
  category: String,              // Concept category
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// ----------------------------------------------------------------------------
// 1.3 People Nodes (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

// Judges (Presiding Judges)
(:Judge {
  id: String,                    // Unique ID: "judge_" + MD5(name + court)
  name: String,                  // Judge full name (indexed)
  court: String,                 // Primary court affiliation
  title: String,                 // Title (e.g., "Sudac", "Predsjednik suda")
  specializations: [String],     // Areas of specialization
  active: Boolean,               // Currently active
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Legal Principle (Court-established doctrines) - NEW Phase 2 Enhancement
(:LegalPrinciple {
  id: String,                    // ULID
  name: String,                  // Principle name (unique, indexed)
  description: String,           // Full description
  category: String,              // Category (procedural, substantive, evidentiary)
  landmark_case_id: String,      // Decision that established it
  established_date: String,      // ISO8601 when first established
  status: String,                // "active", "overruled", "modified"
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// Party (Case participants) - NEW Phase 2 Enhancement
(:Party {
  id: String,                    // "party_" + MD5(name + role)
  name: String,                  // Party name (indexed)
  party_type: String,            // "natural_person", "legal_entity", "state_body"
  role: String,                  // "plaintiff", "defendant", "intervener", "appellant"
  normalized_name: String,       // Normalized for matching
  created_at: String,            // ISO8601 timestamp
  updated_at: String             // ISO8601 timestamp
})

// ============================================================================
// 2. RELATIONSHIPS
// ============================================================================

// ----------------------------------------------------------------------------
// 2.1 Citation Relationships
// ----------------------------------------------------------------------------

// Court Decision cites a Law (with article-level precision)
(:CourtDecisionDocument)-[:CITES {
  citation_type: String,         // Type: "statute", "article_reference", "nn_reference"
  law_abbreviation: String,      // Law abbreviation (e.g., "ZPP", "ZOO")
  article: String,               // Article number (e.g., "110")
  paragraph: String,             // Paragraph number (stavak)
  item: String,                  // Item number (točka)
  alineja: String,               // Alinea number
  created_at: String             // ISO8601 timestamp
}]->(:LawDocument)

// Law cites another Law
(:LawDocument)-[:CITES {
  citation_type: String,         // Type of citation
  article: String,               // Article number if specific
  created_at: String             // ISO8601 timestamp
}]->(:LawDocument)

// Case cites a Law (legacy)
(:CaseDocument)-[:CITES {
  citation_type: String,
  article: String,
  created_at: String
}]->(:LawDocument)

// ----------------------------------------------------------------------------
// 2.2 Case Reference Relationships
// ----------------------------------------------------------------------------

// Court Decision references another Court Decision (general reference)
(:CourtDecisionDocument)-[:REFERENCES {
  citation_type: String,         // Type: "ecli", "case_number"
  ecli: String,                  // ECLI if available
  case_number: String,           // Case number if available
  prefix: String,                // Case number prefix (e.g., "Rev")
  created_at: String             // ISO8601 timestamp
}]->(:CourtDecisionDocument)

// Court Decision overrules another Court Decision
// Used when a higher court explicitly overturns a lower court's decision
(:CourtDecisionDocument)-[:OVERRULES {
  reason: String,                // Reason for overruling
  decision_date: String,         // Date of the overruling decision
  created_at: String             // ISO8601 timestamp
}]->(:CourtDecisionDocument)

// Court Decision confirms/upholds another Court Decision
// Used when an appellate court confirms a lower court's decision
(:CourtDecisionDocument)-[:CONFIRMS {
  decision_date: String,         // Date of confirmation
  created_at: String             // ISO8601 timestamp
}]->(:CourtDecisionDocument)

// Court Decision modifies another Court Decision
// Used when an appellate court partially changes a lower court's decision
(:CourtDecisionDocument)-[:MODIFIES {
  modification_details: String,  // Description of modifications
  decision_date: String,         // Date of modification
  created_at: String             // ISO8601 timestamp
}]->(:CourtDecisionDocument)

// Court Decision follows/applies precedent from another Decision
// Used to track precedential relationships
(:CourtDecisionDocument)-[:FOLLOWS {
  reasoning: String,             // How the precedent was applied
  created_at: String             // ISO8601 timestamp
}]->(:CourtDecisionDocument)

// Court Decision distinguishes itself from another Decision
// Used when a court explains why a precedent doesn't apply
(:CourtDecisionDocument)-[:DISTINGUISHES {
  reasoning: String,             // Reasoning for distinction
  created_at: String             // ISO8601 timestamp
}]->(:CourtDecisionDocument)

// Legacy case references
(:CaseDocument)-[:REFERENCES {
  citation_type: String,
  created_at: String
}]->(:CaseDocument)

// ----------------------------------------------------------------------------
// 2.3 Entity Relationships
// ----------------------------------------------------------------------------

// Court Decision decided by Court
(:CourtDecisionDocument)-[:DECIDED_BY {
  decision_date: String,         // Date of decision
  created_at: String             // ISO8601 timestamp
}]->(:Court)

// Document belongs to Jurisdiction
(:CourtDecisionDocument)-[:BELONGS_TO_JURISDICTION {
  created_at: String             // ISO8601 timestamp
}]->(:Jurisdiction)

(:LawDocument)-[:BELONGS_TO_JURISDICTION {
  created_at: String
}]->(:Jurisdiction)

(:CaseDocument)-[:BELONGS_TO_JURISDICTION {
  created_at: String
}]->(:Jurisdiction)

// Court belongs to Jurisdiction
(:Court)-[:LOCATED_IN {
  created_at: String
}]->(:Jurisdiction)

// ----------------------------------------------------------------------------
// 2.3.1 Judge Relationships (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

// Court Decision presided by Judge
(:CourtDecisionDocument)-[:PRESIDED_BY {
  role: String,                  // "presiding", "member", "reporting"
  created_at: String             // ISO8601 timestamp
}]->(:Judge)

// Judge affiliated with Court
(:Judge)-[:AFFILIATED_WITH {
  start_date: String,            // ISO8601
  end_date: String,              // ISO8601 or null if current
  role: String,                  // Judge role at court
  created_at: String             // ISO8601 timestamp
}]->(:Court)

// ----------------------------------------------------------------------------
// 2.3.2 Legal Principle Relationships (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

// Decision establishes a legal principle (landmark case)
(:CourtDecisionDocument)-[:ESTABLISHES {
  reasoning: String,             // Brief reasoning
  created_at: String             // ISO8601 timestamp
}]->(:LegalPrinciple)

// Decision applies an existing legal principle
(:CourtDecisionDocument)-[:APPLIES {
  how_applied: String,           // How the principle was applied
  created_at: String             // ISO8601 timestamp
}]->(:LegalPrinciple)

// ----------------------------------------------------------------------------
// 2.3.3 Party Relationships (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

// Court Decision involves a Party
(:CourtDecisionDocument)-[:HAS_PARTY {
  role: String,                  // "plaintiff", "defendant", "appellant", "respondent"
  outcome: String,               // "prevailed", "lost", "partial", "settled"
  created_at: String             // ISO8601 timestamp
}]->(:Party)

// ----------------------------------------------------------------------------
// 2.4 Metadata Relationships
// ----------------------------------------------------------------------------

// Document has Keyword
(:CourtDecisionDocument)-[:HAS_KEYWORD {
  weight: Float,                 // Keyword weight/importance (0.0-1.0)
  created_at: String
}]->(:Keyword)

(:LawDocument)-[:HAS_KEYWORD {
  weight: Float,
  created_at: String
}]->(:Keyword)

(:CaseDocument)-[:HAS_KEYWORD {
  weight: Float,
  created_at: String
}]->(:Keyword)

// Document has Tag
(:CourtDecisionDocument)-[:HAS_TAG {
  created_at: String
}]->(:Tag)

(:LawDocument)-[:HAS_TAG {
  created_at: String
}]->(:Tag)

(:CaseDocument)-[:HAS_TAG {
  created_at: String
}]->(:Tag)

// Document relates to Topic
(:CourtDecisionDocument)-[:RELATES_TO {
  relevance: Float,              // Relevance score (0.0-1.0)
  created_at: String
}]->(:Topic)

(:LawDocument)-[:RELATES_TO {
  relevance: Float,
  created_at: String
}]->(:Topic)

// Document mentions Legal Concept
(:CourtDecisionDocument)-[:MENTIONS {
  frequency: Integer,            // How many times concept is mentioned
  created_at: String
}]->(:LegalConcept)

(:LawDocument)-[:MENTIONS {
  frequency: Integer,
  created_at: String
}]->(:LegalConcept)

// ----------------------------------------------------------------------------
// 2.5 Similarity Relationships
// ----------------------------------------------------------------------------

// Document similar to another Document (based on embeddings)
(:CourtDecisionDocument)-[:SIMILAR_TO {
  similarity: Float,             // Cosine similarity score (0.0-1.0)
  computed_at: String            // ISO8601 timestamp
}]->(:CourtDecisionDocument)

(:LawDocument)-[:SIMILAR_TO {
  similarity: Float,
  computed_at: String
}]->(:LawDocument)

(:CaseDocument)-[:SIMILAR_TO {
  similarity: Float,
  computed_at: String
}]->(:CaseDocument)

// ============================================================================
// 3. CONSTRAINTS
// ============================================================================

// ----------------------------------------------------------------------------
// 3.1 Uniqueness Constraints
// ----------------------------------------------------------------------------

// Law Document Constraints
CREATE CONSTRAINT law_doc_id IF NOT EXISTS
FOR (ld:LawDocument) REQUIRE ld.id IS UNIQUE;

// Case Document Constraints
CREATE CONSTRAINT case_doc_id IF NOT EXISTS
FOR (cd:CaseDocument) REQUIRE cd.id IS UNIQUE;

// Court Decision Document Constraints
CREATE CONSTRAINT court_decision_doc_id IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) REQUIRE cdd.id IS UNIQUE;

// NOTE: ECLI uniqueness constraint intentionally omitted because ECLI can be nullable.
// Multiple nodes with null ECLI are allowed. Use the ECLI index for fast lookups when present.

// Court Constraints
CREATE CONSTRAINT court_name IF NOT EXISTS
FOR (c:Court) REQUIRE c.name IS UNIQUE;

// Jurisdiction Constraints
CREATE CONSTRAINT jurisdiction_name IF NOT EXISTS
FOR (j:Jurisdiction) REQUIRE j.name IS UNIQUE;

// Keyword Constraints
CREATE CONSTRAINT keyword_name IF NOT EXISTS
FOR (k:Keyword) REQUIRE k.name IS UNIQUE;

// Tag Constraints
CREATE CONSTRAINT tag_name IF NOT EXISTS
FOR (t:Tag) REQUIRE t.name IS UNIQUE;

// Topic Constraints
CREATE CONSTRAINT topic_name IF NOT EXISTS
FOR (t:Topic) REQUIRE t.name IS UNIQUE;

// Legal Concept Constraints
CREATE CONSTRAINT legal_concept_name IF NOT EXISTS
FOR (lc:LegalConcept) REQUIRE lc.name IS UNIQUE;

// Judge Constraints (NEW - Phase 2 Enhancement)
CREATE CONSTRAINT judge_id IF NOT EXISTS
FOR (j:Judge) REQUIRE j.id IS UNIQUE;

// Legal Principle Constraints (NEW - Phase 2 Enhancement)
CREATE CONSTRAINT legal_principle_id IF NOT EXISTS
FOR (lp:LegalPrinciple) REQUIRE lp.id IS UNIQUE;

// Party Constraints (NEW - Phase 2 Enhancement)
CREATE CONSTRAINT party_id IF NOT EXISTS
FOR (p:Party) REQUIRE p.id IS UNIQUE;

// ============================================================================
// 4. INDEXES
// ============================================================================

// ----------------------------------------------------------------------------
// 4.1 Court Decision Indexes (NEW - Sprint D2.1)
// ----------------------------------------------------------------------------

// ECLI Index (for fast lookup by European Case Law Identifier)
CREATE INDEX court_decision_ecli_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.ecli);

// Case Number Index (for lookup by case number)
CREATE INDEX court_decision_case_number_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.case_number);

// Court Index (for filtering by court)
CREATE INDEX court_decision_court_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.court);

// Decision Date Index (for temporal queries)
CREATE INDEX court_decision_date_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.decision_date);

// Decision Type Index (for filtering by type)
CREATE INDEX court_decision_type_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.decision_type);

// Composite Index (case_number + court for precise lookups)
CREATE INDEX court_decision_case_court_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.case_number, cdd.court);

// Decision ID Index (for joining with parent court_decisions table)
CREATE INDEX court_decision_parent_idx IF NOT EXISTS
FOR (cdd:CourtDecisionDocument) ON (cdd.decision_id);

// ----------------------------------------------------------------------------
// 4.2 Law Document Indexes
// ----------------------------------------------------------------------------

CREATE INDEX law_title_idx IF NOT EXISTS
FOR (l:LawDocument) ON (l.title);

CREATE INDEX law_number_idx IF NOT EXISTS
FOR (l:LawDocument) ON (l.law_number);

CREATE INDEX law_effective_date_idx IF NOT EXISTS
FOR (l:LawDocument) ON (l.effective_date);

CREATE INDEX law_jurisdiction_idx IF NOT EXISTS
FOR (l:LawDocument) ON (l.jurisdiction);

// ----------------------------------------------------------------------------
// 4.3 Case Document Indexes
// ----------------------------------------------------------------------------

CREATE INDEX case_title_idx IF NOT EXISTS
FOR (c:CaseDocument) ON (c.title);

// ----------------------------------------------------------------------------
// 4.4 Keyword and Tag Indexes
// ----------------------------------------------------------------------------

CREATE INDEX keyword_category_idx IF NOT EXISTS
FOR (k:Keyword) ON (k.category);

CREATE INDEX tag_category_idx IF NOT EXISTS
FOR (t:Tag) ON (t.category);

// ----------------------------------------------------------------------------
// 4.5 Judge Indexes (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

CREATE INDEX judge_name_idx IF NOT EXISTS
FOR (j:Judge) ON (j.name);

CREATE INDEX judge_court_idx IF NOT EXISTS
FOR (j:Judge) ON (j.court);

// ----------------------------------------------------------------------------
// 4.6 Legal Principle Indexes (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

CREATE INDEX legal_principle_name_idx IF NOT EXISTS
FOR (lp:LegalPrinciple) ON (lp.name);

CREATE INDEX legal_principle_category_idx IF NOT EXISTS
FOR (lp:LegalPrinciple) ON (lp.category);

// ----------------------------------------------------------------------------
// 4.7 Party Indexes (NEW - Phase 2 Enhancement)
// ----------------------------------------------------------------------------

CREATE INDEX party_name_idx IF NOT EXISTS
FOR (p:Party) ON (p.name);

CREATE INDEX party_type_idx IF NOT EXISTS
FOR (p:Party) ON (p.party_type);

// ============================================================================
// 5. EXAMPLE QUERIES
// ============================================================================

// ----------------------------------------------------------------------------
// 5.1 Find Court Decisions by ECLI
// ----------------------------------------------------------------------------
// MATCH (d:CourtDecisionDocument {ecli: "ECLI:HR:VSRH:2020:123"})
// RETURN d;

// ----------------------------------------------------------------------------
// 5.2 Find all Laws cited by a Court Decision
// ----------------------------------------------------------------------------
// MATCH (d:CourtDecisionDocument {ecli: "ECLI:HR:VSRH:2020:123"})-[c:CITES]->(l:LawDocument)
// RETURN l.title, c.article, c.paragraph
// ORDER BY l.law_number;

// ----------------------------------------------------------------------------
// 5.3 Find Court Decisions that cite a specific Law Article
// ----------------------------------------------------------------------------
// MATCH (d:CourtDecisionDocument)-[c:CITES {article: "110"}]->(l:LawDocument {law_number: "53/91"})
// RETURN d.case_number, d.court, d.decision_date, c.paragraph
// ORDER BY d.decision_date DESC;

// ----------------------------------------------------------------------------
// 5.4 Find precedent chain (decisions that follow each other)
// ----------------------------------------------------------------------------
// MATCH path = (d1:CourtDecisionDocument)-[:FOLLOWS*1..5]->(d2:CourtDecisionDocument)
// WHERE d1.case_number = "Rev 123/2020"
// RETURN path;

// ----------------------------------------------------------------------------
// 5.5 Find overruled decisions
// ----------------------------------------------------------------------------
// MATCH (newer:CourtDecisionDocument)-[o:OVERRULES]->(older:CourtDecisionDocument)
// RETURN newer.case_number as overruling_case,
//        older.case_number as overruled_case,
//        o.reason,
//        newer.decision_date
// ORDER BY newer.decision_date DESC;

// ----------------------------------------------------------------------------
// 5.6 Find similar court decisions
// ----------------------------------------------------------------------------
// MATCH (d:CourtDecisionDocument {ecli: "ECLI:HR:VSRH:2020:123"})-[s:SIMILAR_TO]->(similar:CourtDecisionDocument)
// WHERE s.similarity > 0.85
// RETURN similar.case_number, similar.title, s.similarity
// ORDER BY s.similarity DESC
// LIMIT 10;

// ----------------------------------------------------------------------------
// 5.7 Find all decisions by a specific court
// ----------------------------------------------------------------------------
// MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court {name: "Vrhovni sud Republike Hrvatske"})
// RETURN d.case_number, d.decision_date, d.title
// ORDER BY d.decision_date DESC
// LIMIT 50;

// ----------------------------------------------------------------------------
// 5.8 Find most cited laws in court decisions
// ----------------------------------------------------------------------------
// MATCH (d:CourtDecisionDocument)-[:CITES]->(l:LawDocument)
// WITH l, count(d) as citation_count
// RETURN l.title, l.law_number, citation_count
// ORDER BY citation_count DESC
// LIMIT 20;

// ----------------------------------------------------------------------------
// 5.9 Find citation network for a law
// ----------------------------------------------------------------------------
// MATCH (l:LawDocument {law_number: "53/91"})<-[c:CITES]-(d:CourtDecisionDocument)
// WITH l, collect({decision: d, article: c.article}) as citing_decisions
// RETURN l.title, l.law_number, citing_decisions
// LIMIT 1;

// ----------------------------------------------------------------------------
// 5.10 Find decisions with common keywords
// ----------------------------------------------------------------------------
// MATCH (d1:CourtDecisionDocument {case_number: "Rev 123/2020"})-[:HAS_KEYWORD]->(k:Keyword)<-[:HAS_KEYWORD]-(d2:CourtDecisionDocument)
// WHERE d1 <> d2
// WITH d2, count(k) as shared_keywords
// RETURN d2.case_number, d2.title, shared_keywords
// ORDER BY shared_keywords DESC
// LIMIT 10;

// ============================================================================
// 6. MIGRATION NOTES
// ============================================================================
//
// To apply this schema:
// 1. Run constraints first (ensures data integrity)
// 2. Run indexes second (improves query performance)
// 3. Use the provided migration script: database/migrations/add_court_decision_graph_schema.php
//
// Performance Considerations:
// - ECLI lookups: O(1) with unique constraint + index
// - Case number lookups: O(log n) with index
// - Citation queries: O(log n) with relationship indexes
// - Similarity queries: O(n) but limited by threshold
//
// ============================================================================
