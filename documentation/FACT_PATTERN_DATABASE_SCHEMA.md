# Fact Pattern Extractor - Database Schema Documentation

This document provides comprehensive documentation for all database tables related to the Fact Pattern Extractor system.

## Table of Contents

1. [Overview](#overview)
2. [Tables](#tables)
   - [legal_fact_patterns](#legal_fact_patterns)
   - [agent_collaborations](#agent_collaborations)
   - [legal_memos](#legal_memos)
   - [discovery_packages](#discovery_packages)
   - [case_chronologies](#case_chronologies)
3. [Relationships](#relationships)
4. [JSON Structure Reference](#json-structure-reference)
5. [Indexes](#indexes)

---

## Overview

The Fact Pattern Extractor system uses 5 main database tables to store extracted legal fact patterns and their derived analyses:

- **legal_fact_patterns**: Core table storing extracted structured facts from legal narratives
- **agent_collaborations**: Multi-agent analysis results using 4 specialist AI agents
- **legal_memos**: Auto-generated legal memoranda in IRAC format
- **discovery_packages**: Generated discovery requests (interrogatories, document requests, etc.)
- **case_chronologies**: Timeline visualizations with gap analysis

All tables use UUID primary keys and are linked to users and fact patterns via foreign key relationships.

---

## Tables

### legal_fact_patterns

**Migration**: `2025_10_29_115736_create_legal_fact_patterns_table.php`

Stores structured legal facts extracted from raw client narratives using AI.

#### Columns

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | uuid | NO | Primary key (UUID) |
| `user_id` | bigint unsigned | NO | Foreign key to users table |
| `raw_narrative` | text | NO | Original unstructured text from client |
| `structured_facts` | json | NO | Extracted structured facts (see JSON structure below) |
| `legal_area` | varchar(255) | NO | Legal area (contract, tort, property, employment, family, criminal) |
| `extraction_confidence` | float | NO | Confidence score 0.0-1.0 of extraction quality |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record update timestamp |

#### Indexes

- Primary key: `id`
- Index: `user_id` (for user filtering)
- Index: `legal_area` (for area filtering)
- Index: `extraction_confidence` (for quality filtering)
- Index: `created_at` (for chronological sorting)

#### Relationships

- **Belongs to**: `users` (user_id)
- **Has many**: `agent_collaborations`
- **Has many**: `legal_memos`
- **Has many**: `discovery_packages`
- **Has many**: `case_chronologies`

#### structured_facts JSON Structure

```json
{
  "parties": [
    {
      "name": "John Smith",
      "role": "plaintiff|defendant|witness|third_party",
      "type": "individual|corporation|government",
      "contact_info": "Optional contact information"
    }
  ],
  "events": [
    {
      "description": "Contract was signed",
      "date": "2024-01-15",
      "significance": "Formation of agreement",
      "location": "Zagreb, Croatia"
    }
  ],
  "legal_issues": [
    {
      "issue": "Breach of contract",
      "area_of_law": "contract",
      "elements": ["Valid contract", "Breach", "Damages"],
      "potential_claims": ["Breach of contract", "Specific performance"]
    }
  ],
  "disputed_facts": [
    "Whether notice was properly given",
    "Amount of actual damages"
  ],
  "undisputed_facts": [
    "Contract was signed on January 15, 2024",
    "Payment was due on February 15, 2024"
  ],
  "facts_favorable_to_plaintiff": [
    "Written contract with clear terms",
    "Documented breach"
  ],
  "facts_favorable_to_defendant": [
    "Force majeure clause in contract",
    "Notice was sent"
  ],
  "evidence": [
    {
      "type": "documentary|testimonial|expert|physical|electronic",
      "description": "Signed contract",
      "strength": "strong|moderate|weak",
      "availability": "available|needs_discovery|unknown"
    }
  ],
  "damages_or_relief_sought": {
    "type": "monetary|injunctive|declaratory|specific_performance",
    "amount": "500,000 HRK",
    "description": "Contract damages and lost profits"
  },
  "timeline": [
    {
      "date": "2024-01-15",
      "event": "Contract signed"
    }
  ],
  "procedural_posture": {
    "stage": "pre-filing|filed|discovery|trial|appeal",
    "deadlines": [
      {
        "description": "Statute of limitations",
        "date": "2025-01-15"
      }
    ]
  },
  "summary": "Brief summary of the case"
}
```

---

### agent_collaborations

**Migration**: `2025_10_28_000005_create_agent_collaborations_table.php`

Stores results from multi-agent case analysis using 4 specialist AI agents.

#### Columns

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | uuid | NO | Primary key (UUID) |
| `user_id` | bigint unsigned | NO | Foreign key to users table |
| `fact_pattern_id` | uuid | NO | Foreign key to legal_fact_patterns table |
| `agent_results` | json | NO | Results from all 4 agents (see JSON structure below) |
| `synthesis` | json | NO | Synthesized analysis across all agents |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record update timestamp |

#### Indexes

- Primary key: `id`
- Index: `user_id`
- Index: `fact_pattern_id`
- Index: `created_at`

#### Relationships

- **Belongs to**: `users` (user_id)
- **Belongs to**: `legal_fact_patterns` (fact_pattern_id)

#### agent_results JSON Structure

```json
{
  "research_agent": {
    "precedents_found": 15,
    "relevant_laws": ["Article 123 of Obligations Act", "..."],
    "key_precedents": [
      {
        "case_number": "HIGH-COURT-2023-045",
        "summary": "Similar contract breach case",
        "holding": "Material breach justifies termination",
        "relevance_score": 0.92
      }
    ],
    "legal_principles": ["Pacta sunt servanda", "..."],
    "jurisdiction_analysis": "Croatian law applies"
  },
  "strategy_agent": {
    "recommended_approach": "Pursue breach of contract claim with emphasis on...",
    "key_arguments": [
      "Clear breach of contractual obligation",
      "Substantial damages incurred"
    ],
    "potential_defenses": [
      "Force majeure",
      "Impossibility"
    ],
    "settlement_considerations": {
      "strength": "Strong case for settlement",
      "recommended_range": "70-85% of claimed damages"
    },
    "litigation_strategy": "Recommend early mediation followed by litigation if unsuccessful"
  },
  "risk_agent": {
    "risk_level": "low|medium|high",
    "risk_score": 0.35,
    "key_risks": [
      {
        "risk": "Statute of limitations may be approaching",
        "severity": "high",
        "mitigation": "File complaint within 30 days"
      }
    ],
    "mitigation_strategies": ["Immediate filing", "..."],
    "case_weaknesses": ["Limited corroborating evidence for damages"],
    "case_strengths": ["Written contract", "Clear breach"]
  },
  "evidence_agent": {
    "evidence_strength_score": 0.75,
    "evidence_gaps": [
      "Need contemporaneous records of damages",
      "Missing email communications"
    ],
    "discovery_needs": [
      "Defendant's financial records",
      "Internal communications"
    ],
    "corroboration_needs": [
      "Expert witness for damages calculation",
      "Additional witnesses to breach"
    ],
    "evidence_strategy": "Focus on documentary evidence, supplement with expert testimony"
  }
}
```

#### synthesis JSON Structure

```json
{
  "unified_analysis": "Comprehensive narrative combining all agent insights...",
  "key_insights": [
    "Strong case for breach with good precedent support",
    "Main risk is approaching statute of limitations",
    "Need additional discovery for damages proof"
  ],
  "success_probability": 0.72,
  "final_recommendations": [
    "File complaint within 30 days",
    "Begin discovery immediately",
    "Prepare for mediation in 60-90 days"
  ],
  "next_steps": [
    {
      "action": "Draft and file complaint",
      "priority": "urgent",
      "timeline": "Within 7 days"
    }
  ]
}
```

---

### legal_memos

**Migration**: `2025_10_29_120000_create_legal_memos_table.php`

Stores auto-generated legal memoranda using IRAC (Issue, Rule, Application, Conclusion) methodology.

**Note**: As of 2025-12-20, the migration exists but the `LegalMemo` model has not yet been created. The model should be created at `app/Models/LegalMemo.php` to match this migration.

#### Columns

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | uuid | NO | Primary key (UUID) |
| `user_id` | bigint unsigned | NO | Foreign key to users table |
| `fact_pattern_id` | uuid | NO | Foreign key to legal_fact_patterns table |
| `sections` | json | NO | Memo sections (see JSON structure below) |
| `metadata` | json | YES | Generation metadata and precedents used |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record update timestamp |

#### Indexes

- Primary key: `id`
- Index: `user_id`
- Index: `fact_pattern_id`
- Index: `created_at`

#### Relationships

- **Belongs to**: `users` (user_id)
- **Belongs to**: `legal_fact_patterns` (fact_pattern_id)

#### sections JSON Structure

```json
{
  "header": {
    "to": "Senior Partner",
    "from": "Associate Attorney",
    "date": "2024-10-29",
    "re": "Legal Analysis of Contract Breach Case",
    "subject": "Breach of Contract - XYZ Corp v. ABC Inc"
  },
  "issue": "Whether defendant's failure to complete the contracted work constitutes a material breach of contract entitling plaintiff to damages.",
  "brief_answer": "Yes. Defendant's complete cessation of work after two weeks, combined with demand for additional unauthorized payment, constitutes a material breach of contract. Plaintiff is likely entitled to damages including the contract amount and consequential lost business revenue.",
  "facts": "Chronological narrative of relevant facts extracted from fact pattern...",
  "analysis": [
    {
      "issue": "Whether a valid contract existed",
      "rule": "Under Croatian law, a valid contract requires offer, acceptance, and consideration. Article 247 of the Obligations Act...",
      "application": "In this case, the written contract signed on January 15, 2024, clearly establishes all elements of a valid contract...",
      "conclusion": "A valid and enforceable contract existed between the parties."
    },
    {
      "issue": "Whether defendant breached the contract",
      "rule": "A material breach occurs when a party fails to perform a substantial part of their contractual obligations...",
      "application": "Defendant's complete cessation of work after only two weeks, leaving the project substantially incomplete...",
      "conclusion": "Defendant materially breached the contract."
    }
  ],
  "conclusion": "Plaintiff has a strong case for breach of contract with substantial damages. The evidence supports both liability and damages claims.",
  "recommendations": [
    "File complaint within statute of limitations",
    "Conduct discovery to document lost business revenue",
    "Consider early mediation to avoid trial costs",
    "Prepare expert witness for damages calculation"
  ]
}
```

#### metadata JSON Structure

```json
{
  "precedents_used": [
    {
      "case_number": "SUPREME-COURT-2023-045",
      "citation": "Supreme Court of Croatia, Case No. Rev-45/2023",
      "relevance": "Contract interpretation standards"
    }
  ],
  "generation_options": {
    "recipient": "Senior Partner",
    "purpose": "Case evaluation",
    "format": "formal",
    "include_citations": true
  },
  "generated_at": "2024-10-29T12:00:00Z",
  "word_count": 2450,
  "estimated_reading_time": 10
}
```

---

### discovery_packages

**Migration**: `2025_10_29_120100_create_discovery_packages_table.php`

Stores auto-generated discovery request packages including interrogatories, document requests, admissions, and deposition notices.

#### Columns

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | uuid | NO | Primary key (UUID) |
| `user_id` | bigint unsigned | NO | Foreign key to users table |
| `fact_pattern_id` | uuid | NO | Foreign key to legal_fact_patterns table |
| `requests` | json | NO | All discovery requests (see JSON structure below) |
| `metadata` | json | YES | Cost estimates, timeline, complexity metrics |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record update timestamp |

#### Indexes

- Primary key: `id`
- Index: `user_id`
- Index: `fact_pattern_id`
- Index: `created_at`

#### Relationships

- **Belongs to**: `users` (user_id)
- **Belongs to**: `legal_fact_patterns` (fact_pattern_id)

#### requests JSON Structure

```json
{
  "interrogatories": {
    "total": 25,
    "items": [
      {
        "number": 1,
        "text": "State your full legal name and all names under which you have conducted business.",
        "category": "identification"
      },
      {
        "number": 2,
        "text": "Describe in detail your knowledge of the events of March 15, 2024.",
        "category": "facts"
      },
      {
        "number": 3,
        "text": "Identify all documents that support your claim of force majeure.",
        "category": "documents"
      }
    ]
  },
  "document_requests": {
    "total": 15,
    "items": [
      {
        "number": 1,
        "description": "All contracts, agreements, and amendments between plaintiff and defendant",
        "category": "contracts",
        "relevance": "Establishes contractual obligations"
      },
      {
        "number": 2,
        "description": "All emails, letters, and communications regarding the project",
        "category": "communications",
        "relevance": "Shows notice and breach"
      }
    ]
  },
  "requests_for_admission": {
    "total": 10,
    "items": [
      {
        "number": 1,
        "statement": "Admit that you signed the contract dated January 15, 2024.",
        "purpose": "Establish undisputed facts"
      },
      {
        "number": 2,
        "statement": "Admit that work ceased on April 3, 2024.",
        "purpose": "Establish timeline"
      }
    ]
  },
  "deposition_notices": {
    "total": 3,
    "notices": [
      {
        "deponent": "John Smith",
        "role": "Defendant's CEO",
        "topics": [
          "Decision to stop work",
          "Basis for additional payment demand",
          "Knowledge of contract terms"
        ],
        "estimated_duration": "4 hours"
      },
      {
        "deponent": "Jane Doe",
        "role": "Project Manager",
        "topics": [
          "Work performed",
          "Reasons for cessation",
          "Communications with plaintiff"
        ],
        "estimated_duration": "3 hours"
      }
    ]
  }
}
```

#### metadata JSON Structure

```json
{
  "estimated_cost": {
    "total_min": 8000,
    "total_max": 18000,
    "currency": "HRK",
    "breakdown": {
      "interrogatories": {
        "preparation": 1500,
        "review_of_responses": 1000
      },
      "document_production": {
        "preparation": 2000,
        "review": 3000
      },
      "depositions": {
        "attorney_time": 8000,
        "court_reporter": 2500,
        "transcripts": 1500
      },
      "expert_witnesses": {
        "estimated": 5000
      }
    }
  },
  "timeline": {
    "phase_1": {
      "days": 30,
      "description": "Serve interrogatories and document requests",
      "activities": [
        "Draft and serve discovery requests",
        "Await responses (30 days)"
      ]
    },
    "phase_2": {
      "days": 60,
      "description": "Review responses and conduct depositions",
      "activities": [
        "Review document production",
        "Schedule and conduct depositions",
        "Follow-up discovery if needed"
      ]
    },
    "phase_3": {
      "days": 90,
      "description": "Complete discovery and prepare for trial",
      "activities": [
        "Expert witness depositions",
        "Motion practice if needed",
        "Trial preparation"
      ]
    }
  },
  "generated_at": "2024-10-29T12:00:00Z",
  "complexity_score": 0.75
}
```

---

### case_chronologies

**Migration**: `2025_10_29_120200_create_case_chronologies_table.php`

Stores timeline visualizations of case events with gap analysis and critical date identification.

#### Columns

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | uuid | NO | Primary key (UUID) |
| `user_id` | bigint unsigned | NO | Foreign key to users table |
| `fact_pattern_id` | uuid | NO | Foreign key to legal_fact_patterns table |
| `events` | json | NO | All events in chronological order (see JSON structure below) |
| `analysis` | json | NO | Time gap analysis, critical dates, summary |
| `visualization_data` | json | YES | Chart-ready data for timeline visualization |
| `created_at` | timestamp | YES | Record creation timestamp |
| `updated_at` | timestamp | YES | Record update timestamp |

#### Indexes

- Primary key: `id`
- Index: `user_id`
- Index: `fact_pattern_id`
- Index: `created_at`

#### Relationships

- **Belongs to**: `users` (user_id)
- **Belongs to**: `legal_fact_patterns` (fact_pattern_id)

#### events JSON Structure

```json
[
  {
    "date": "2024-01-15",
    "description": "Contract signed between parties",
    "sequence": 1,
    "category": "event",
    "significance": "Formation of contractual relationship",
    "source": "events"
  },
  {
    "date": "2024-02-01",
    "description": "Email sent regarding project timeline",
    "sequence": 2,
    "category": "evidence",
    "significance": "Shows communication about deadlines",
    "source": "evidence"
  },
  {
    "date": "2024-03-20",
    "description": "Work ceased without notice",
    "sequence": 3,
    "category": "event",
    "significance": "Alleged breach of contract",
    "source": "events"
  },
  {
    "date": "2024-04-01",
    "description": "Complaint filed in court",
    "sequence": 4,
    "category": "procedural",
    "significance": "Initiation of litigation",
    "source": "procedural_posture"
  }
]
```

#### analysis JSON Structure

```json
{
  "time_gaps": [
    {
      "after_event": "Email sent regarding project timeline",
      "before_event": "Work ceased without notice",
      "days": 48,
      "date_range": "2024-02-01 to 2024-03-20",
      "questions": [
        "What happened during this 48-day period?",
        "Were there additional communications?",
        "What events led to cessation of work?"
      ]
    }
  ],
  "critical_dates": [
    {
      "date": "2024-01-15",
      "description": "Contract signed",
      "reason": "Establishes contractual relationship and start of obligations"
    },
    {
      "date": "2024-03-20",
      "description": "Work ceased",
      "reason": "Date of alleged breach - crucial for statute of limitations"
    },
    {
      "date": "2025-03-20",
      "description": "Statute of limitations deadline",
      "reason": "One year from breach - must file by this date"
    }
  ],
  "timeline_summary": "The case spans a 6-month period from contract signing on January 15, 2024, to the filing of the complaint on April 1, 2024. The critical breach occurred on March 20, 2024, when defendant ceased work without justification. There is a notable 48-day gap between the last documented communication and the breach, which requires investigation.",
  "total_duration": {
    "days": 76,
    "description": "2.5 months from contract to filing"
  }
}
```

#### visualization_data JSON Structure

```json
{
  "timeline_data": [
    {
      "x": "2024-01-15",
      "y": 1,
      "label": "Contract signed",
      "category": "event",
      "color": "#4CAF50"
    },
    {
      "x": "2024-02-01",
      "y": 2,
      "label": "Email sent",
      "category": "evidence",
      "color": "#2196F3"
    }
  ],
  "gap_visualization": [
    {
      "start": "2024-02-01",
      "end": "2024-03-20",
      "duration": 48,
      "severity": "moderate"
    }
  ],
  "category_breakdown": {
    "event": 10,
    "procedural": 5,
    "evidence": 8,
    "timeline": 3
  }
}
```

---

## Relationships

### Entity Relationship Diagram

```
users (1) ──────< (N) legal_fact_patterns
                        │
                        ├──< (N) agent_collaborations
                        │
                        ├──< (N) legal_memos
                        │
                        ├──< (N) discovery_packages
                        │
                        └──< (N) case_chronologies
```

### Relationship Details

1. **users → legal_fact_patterns**: One-to-Many
   - A user can have multiple fact patterns
   - Each fact pattern belongs to one user
   - Cascade delete: Deleting user deletes all their fact patterns

2. **legal_fact_patterns → agent_collaborations**: One-to-Many
   - A fact pattern can have multiple agent collaboration analyses
   - Each collaboration belongs to one fact pattern
   - Cascade delete: Deleting fact pattern deletes all related collaborations

3. **legal_fact_patterns → legal_memos**: One-to-Many
   - A fact pattern can have multiple generated memos
   - Each memo belongs to one fact pattern
   - Cascade delete: Deleting fact pattern deletes all related memos

4. **legal_fact_patterns → discovery_packages**: One-to-Many
   - A fact pattern can have multiple discovery packages
   - Each package belongs to one fact pattern
   - Cascade delete: Deleting fact pattern deletes all related packages

5. **legal_fact_patterns → case_chronologies**: One-to-Many
   - A fact pattern can have multiple chronologies (regenerated with updates)
   - Each chronology belongs to one fact pattern
   - Cascade delete: Deleting fact pattern deletes all related chronologies

---

## JSON Structure Reference

### Legal Areas (legal_fact_patterns.legal_area)

Valid values:
- `contract` - Contract disputes
- `tort` - Tort claims (negligence, defamation, etc.)
- `property` - Property disputes
- `employment` - Employment issues
- `family` - Family law matters
- `criminal` - Criminal defense

### Evidence Types (structured_facts.evidence[].type)

Valid values:
- `documentary` - Written documents, contracts, emails
- `testimonial` - Witness testimony
- `expert` - Expert witness opinions
- `physical` - Physical evidence
- `electronic` - Electronic/digital evidence

### Evidence Strength (structured_facts.evidence[].strength)

Valid values:
- `strong` - Highly probative and admissible
- `moderate` - Somewhat probative, admissibility uncertain
- `weak` - Low probative value or admissibility issues

### Evidence Availability (structured_facts.evidence[].availability)

Valid values:
- `available` - Already in possession
- `needs_discovery` - Requires discovery process
- `unknown` - Availability uncertain

### Party Roles (structured_facts.parties[].role)

Valid values:
- `plaintiff` - Person bringing the claim
- `defendant` - Person defending against claim
- `witness` - Witness to events
- `third_party` - Other involved parties

### Procedural Stages (structured_facts.procedural_posture.stage)

Valid values:
- `pre-filing` - Before complaint filed
- `filed` - Complaint filed, awaiting response
- `discovery` - Discovery phase
- `trial` - Trial phase
- `appeal` - Appeal phase

### Risk Levels (agent_results.risk_agent.risk_level)

Valid values:
- `low` - Low risk, strong case (risk_score < 0.3)
- `medium` - Moderate risk (risk_score 0.3-0.6)
- `high` - High risk, weak case (risk_score > 0.6)

---

## Indexes

### Performance Optimization

All tables include indexes on commonly queried columns:

1. **User filtering**: `user_id` indexes allow fast retrieval of user's records
2. **Fact pattern filtering**: `fact_pattern_id` indexes enable quick lookups of related analyses
3. **Chronological sorting**: `created_at` indexes support efficient date-based queries
4. **Quality filtering**: `extraction_confidence` index (legal_fact_patterns only) for filtering by quality

### Query Examples

```sql
-- Find all high-confidence fact patterns for a user
SELECT * FROM legal_fact_patterns
WHERE user_id = 123
AND extraction_confidence >= 0.7
ORDER BY created_at DESC;

-- Get all analyses for a fact pattern
SELECT
  fp.id,
  fp.legal_area,
  ac.id as collaboration_id,
  lm.id as memo_id,
  dp.id as discovery_package_id,
  cc.id as chronology_id
FROM legal_fact_patterns fp
LEFT JOIN agent_collaborations ac ON fp.id = ac.fact_pattern_id
LEFT JOIN legal_memos lm ON fp.id = lm.fact_pattern_id
LEFT JOIN discovery_packages dp ON fp.id = dp.fact_pattern_id
LEFT JOIN case_chronologies cc ON fp.id = cc.fact_pattern_id
WHERE fp.id = 'uuid-here';

-- Find contract cases with high success probability
SELECT
  fp.*,
  ac.synthesis->>'$.success_probability' as success_prob
FROM legal_fact_patterns fp
INNER JOIN agent_collaborations ac ON fp.id = ac.fact_pattern_id
WHERE fp.legal_area = 'contract'
AND JSON_EXTRACT(ac.synthesis, '$.success_probability') > 0.7
ORDER BY JSON_EXTRACT(ac.synthesis, '$.success_probability') DESC;
```

---

## Storage Considerations

### JSON Column Sizes

Approximate storage requirements:

- `legal_fact_patterns.structured_facts`: 5-50 KB (depends on case complexity)
- `agent_collaborations.agent_results`: 10-30 KB
- `agent_collaborations.synthesis`: 2-10 KB
- `legal_memos.sections`: 20-100 KB (depends on memo length)
- `discovery_packages.requests`: 15-50 KB
- `case_chronologies.events`: 5-30 KB (depends on event count)
- `case_chronologies.analysis`: 3-15 KB
- `case_chronologies.visualization_data`: 5-20 KB

### Cleanup and Archiving

Consider implementing:

1. **Soft deletes**: Add `deleted_at` column for archiving instead of hard deletion
2. **Archival policy**: Move old records (>2 years) to archive tables
3. **Compression**: Use MySQL's JSON compression for large documents
4. **Partitioning**: Partition tables by `created_at` for better query performance

---

## Migration Order

When setting up a new database, run migrations in this order:

1. `create_users_table.php` (base Laravel migration)
2. `create_legal_fact_patterns_table.php`
3. `create_agent_collaborations_table.php`
4. `create_legal_memos_table.php`
5. `create_discovery_packages_table.php`
6. `create_case_chronologies_table.php`

All fact pattern-related tables depend on `legal_fact_patterns`, which depends on `users`.

---

## Backup Strategy

### Recommended Backup Approach

1. **Full backups**: Daily full database backups
2. **Incremental backups**: Hourly transaction log backups
3. **JSON data**: Ensure backup tool supports JSON column types
4. **Retention**: Keep backups for 30 days minimum
5. **Testing**: Regular restore testing

### Critical Data Priority

1. **High priority**: `legal_fact_patterns` (core data)
2. **High priority**: `users` (authentication)
3. **Medium priority**: `agent_collaborations`, `legal_memos` (can be regenerated but expensive)
4. **Low priority**: `discovery_packages`, `case_chronologies` (easily regenerated)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-10-29 | Initial schema documentation |

---

## Support

For questions or issues related to the database schema:

1. Check the migration files in `database/migrations/`
2. Review the model files in `app/Models/`
3. Consult the service implementations in `app/Services/`

---

**Last Updated**: October 29, 2024
**Schema Version**: 1.0.0
