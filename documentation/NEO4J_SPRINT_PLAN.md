# Neo4j Graph Intelligence - Sprint Plan (Sprints 8-10)

**Goal**: Leverage the complete graph intelligence infrastructure to deliver attorney-facing insights, analytics, and courtroom tools.

**Context**:
- Base infrastructure complete (Sprints 1-7)
- Graph embeddings operational (Sprint 7.1)
- Monitoring in place (Sprint 7.2)
- Now focus on deriving actionable insights from graph data

**Duration**: 6 weeks (3 x 2-week sprints)
**Total Points**: 156

---

## Sprint 8: Graph Analytics & Entity Intelligence (2 weeks)

**Goal**: Build analytics to detect patterns, outliers, and emerging entities in the legal citation graph.

**Points**: 52

---

### 8.1 Emerging Entity Detection & Tracking
**Priority**: HIGH
**Points**: 13
**Owner**: Backend + Data

**Story**: As a defense attorney, I need to be notified when new prosecutors, judges, or legal trends emerge in the system so I can stay informed about changing legal landscape.

**Context**:
- Graph currently ingests prosecutors, judges, keywords
- No tracking of "first seen" timestamps
- No alerting when new entities appear
- Attorneys want to know when new hostile prosecutors enter their jurisdiction

**Tasks**:
- [ ] Add `first_seen_at` timestamp to Decision, Law, Prosecutor, Judge nodes
- [x] Create `app/Services/Graph/EntityTrackingService.php`
  - [x] `detectNewEntities()` - Compare current vs 7-day-ago snapshot
  - [x] `tagEmergingEntities()` - Add :Emerging label to new nodes
  - [x] `getEntityEmergenceReport()` - Weekly summary of new entities
- [ ] Create `app/Console/Commands/Graph/DetectNewEntitiesCommand.php`
  - [ ] `php artisan graph:detect-new-entities --period=7days`
- [ ] Schedule weekly detection
  - [ ] Add to `Kernel.php`: Monday 6:00 AM
- [ ] Create Grafana panel: "New Entities This Week"
  - [ ] New prosecutors, judges, keywords
  - [ ] Trend chart (4-week rolling)
- [ ] Store results in `emerging_entities` table:
  ```sql
  CREATE TABLE emerging_entities (
      id BIGSERIAL PRIMARY KEY,
      entity_type VARCHAR(50), -- prosecutor, judge, keyword, court
      entity_id VARCHAR(255),
      entity_name VARCHAR(500),
      first_seen_at TIMESTAMP,
      decision_count INTEGER DEFAULT 1,
      detected_at TIMESTAMP,
      relevance_score DECIMAL(3,2) -- Based on decision impact
  );
  ```
- [ ] Write tests: `EmergingEntityDetectionTest.php`

**Acceptance Criteria**:
- ✅ New prosecutors detected within 24 hours of first decision
- ✅ Weekly report shows all entities first seen in last 7 days
- ✅ Grafana panel displays new entity counts with 4-week trend
- ✅ Scheduled job runs without errors
- ✅ Relevance scoring prioritizes high-impact entities

**Dependencies**:
- Neo4j graph populated with prosecutor/judge data
- Decision ingestion pipeline operational

---

### 8.2 Topic Spike Analytics & Trend Detection
**Priority**: HIGH
**Points**: 13
**Owner**: AI/ML + Backend

**Story**: As a defense attorney, I need to be alerted when specific legal topics (e.g., "pretres doma") suddenly spike in court decisions so I can prepare for emerging trends.

**Context**:
- Topic nodes exist in graph
- No monitoring of topic frequency over time
- Attorneys blindsided by sudden increases in specific charge types

**Tasks**:
- [x] Create `app/Services/Graph/TopicAnalyticsService.php`
  - [x] `getTopicTrends()` - Compare current week vs 4-week baseline
  - [x] `detectTopicSpikes()` - Flag topics with >50% increase
  - [x] `getTopicGrowthReport()` - Weekly summary with charts
- [ ] Create spike detection algorithm:
  - [ ] Baseline: 4-week rolling average of decisions per topic
  - [ ] Spike threshold: >50% increase from baseline
  - [ ] Significance filter: Absolute count >5 decisions
- [ ] Create `app/Console/Commands/Graph/AnalyzeTopicTrendsCommand.php`
  - [ ] `php artisan graph:analyze-topic-trends --threshold=0.5`
- [ ] Schedule weekly analysis
  - [ ] Add to `Kernel.php`: Monday 7:00 AM (after entity detection)
- [ ] Store results in `topic_spike_events` table:
  ```sql
  CREATE TABLE topic_spike_events (
      id BIGSERIAL PRIMARY KEY,
      topic_name VARCHAR(255),
      baseline_count INTEGER,
      current_count INTEGER,
      percent_increase DECIMAL(5,2),
      detected_at TIMESTAMP,
      affected_courts JSONB, -- List of courts showing spike
      severity VARCHAR(20) -- minor, moderate, major
  );
  ```
- [ ] Create Grafana panel: "Topic Trends & Spikes"
  - [ ] Heatmap: Topics x Weeks
  - [ ] Spike alerts (threshold line)
- [ ] Write tests: `TopicSpikeDetectionTest.php`

**Acceptance Criteria**:
- ✅ Spikes detected when topic frequency increases >50%
- ✅ False positives filtered (absolute count <5)
- ✅ Weekly report shows top 10 growing topics
- ✅ Grafana heatmap visualizes topic trends over 12 weeks
- ✅ Severity classification (minor/moderate/major) accurate

**Dependencies**:
- Topic nodes populated in Neo4j
- 4+ weeks of historical decision data

---

### 8.3 Outlier Prosecution Detection
**Priority**: CRITICAL
**Points**: 21
**Owner**: AI/ML + Backend

**Story**: As a defense attorney, I need to identify prosecutors and courts with statistically anomalous evidence suppression or rights violation rates so I can build systematic misconduct cases.

**Context**:
- HomeSearchAbuseDetector finds individual cases (Sprint 2.3)
- No system-wide prosecutor/court profiling
- Need statistical evidence of patterns for misconduct motions

**Tasks**:
- [x] Create `app/Services/Graph/OutlierDetectionService.php`
  - [x] `analyzeProsecutorOutliers()` - Suppression rate z-scores
  - [x] `analyzeCourtOutliers()` - Regional disparity detection
  - [x] `calculateViolationRates()` - Per prosecutor/court
  - [x] `identifySystemicPatterns()` - Multi-metric outlier detection
- [ ] Implement statistical analysis:
  - [ ] Z-score calculation (mean + std dev)
  - [ ] Outlier threshold: |z| > 2.0 (95% confidence)
  - [ ] Multi-metric scoring: suppression rate, violation rate, appeal overturn rate
- [ ] Create Cypher queries:
  ```cypher
  // Prosecutor suppression rates
  MATCH (p:Prosecutor)-[:PROSECUTED]->(d:Decision)
  WHERE d.evidence_suppressed = true
  WITH p, count(d) as suppressed,
       size((p)-[:PROSECUTED]->()) as total
  RETURN p.name, suppressed, total,
         toFloat(suppressed)/total as rate
  ORDER BY rate DESC
  ```
- [ ] Create `app/Console/Commands/Graph/DetectOutliersCommand.php`
  - [ ] `php artisan graph:detect-outliers --metric=suppression`
- [ ] Schedule monthly analysis
  - [ ] Add to `Kernel.php`: First Monday of month, 8:00 AM
- [ ] Store results in `prosecutor_outliers` table:
  ```sql
  CREATE TABLE prosecutor_outliers (
      id BIGSERIAL PRIMARY KEY,
      prosecutor_name VARCHAR(255),
      prosecutor_id VARCHAR(255),
      court VARCHAR(255),
      metric_type VARCHAR(50), -- suppression_rate, violation_rate, etc.
      metric_value DECIMAL(5,4),
      population_mean DECIMAL(5,4),
      population_stddev DECIMAL(5,4),
      z_score DECIMAL(5,2),
      sample_size INTEGER,
      detected_at TIMESTAMP,
      severity VARCHAR(20) -- moderate, high, extreme
  );
  ```
- [ ] Create Grafana dashboard: "Prosecutorial Outliers"
  - [ ] Scatter plot: prosecutors by suppression rate vs case count
  - [ ] Table: Top 20 outliers with z-scores
  - [ ] Regional heatmap: courts by average violation rate
- [ ] Generate PDF report:
  - [ ] `app/Services/Reports/OutlierReportGenerator.php`
  - [ ] Template: `resources/views/reports/outlier-analysis.blade.php`
  - [ ] Export: `php artisan graph:outlier-report --prosecutor="Name" --format=pdf`
- [ ] Write tests: `OutlierDetectionServiceTest.php`
  - [ ] Mock prosecutor data with known outliers
  - [ ] Validate z-score calculations
  - [ ] Test severity classification

**Acceptance Criteria**:
- ✅ Prosecutors with z-score >2.0 flagged as outliers
- ✅ Sample size filter (minimum 10 cases) applied
- ✅ Regional disparity detected (court-level aggregation)
- ✅ PDF report generated with statistical evidence
- ✅ Dashboard visualizes outliers with confidence levels
- ✅ Multi-metric scoring combines suppression + violation + appeal rates

**Dependencies**:
- HomeSearchCase data with prosecutor names
- Minimum 100+ decisions per court for statistical validity
- GraphResearchEnhancer for citation analysis

---

### 8.4 Data Quality Checks & Graph Maintenance
**Priority**: MEDIUM
**Points**: 5
**Owner**: Backend

**Story**: As a system administrator, I need automated data quality checks to detect duplicate nodes, orphaned relationships, and inconsistencies in the graph.

**Tasks**:
- [x] Create `app/Services/Graph/DataQualityService.php`
  - [x] `detectDuplicateNodes()` - Same case_number, different IDs
  - [x] `detectOrphanNodes()` - Nodes with no relationships
  - [x] `detectInconsistentData()` - Missing required properties
  - [x] `generateQualityReport()` - Weekly summary
- [ ] Create `app/Console/Commands/Graph/CheckDataQualityCommand.php`
  - [ ] `php artisan graph:check-quality --fix-duplicates`
- [ ] Schedule weekly checks
  - [ ] Add to `Kernel.php`: Sunday 11:00 PM
- [ ] Create Grafana panel: "Graph Data Quality"
  - [ ] Duplicate node count
  - [ ] Orphan node count
  - [ ] Consistency score (0-100)
- [x] Write tests: `DataQualityServiceTest.php`

**Acceptance Criteria**:
- ✅ Duplicates detected and merged automatically
- ✅ Orphan nodes flagged for manual review
- ✅ Weekly quality report emailed to admins
- ✅ Quality score >95% maintained

**Dependencies**: None

---

## Sprint 9: Attorney-Facing Tools & Notifications (2 weeks)

**Goal**: Build user-facing tools that deliver graph insights to attorneys in actionable formats.

**Points**: 52

---

### 9.1 Citation Drift Tracker & Precedent Analytics
**Priority**: HIGH
**Points**: 13
**Owner**: Backend + Frontend

**Story**: As a defense attorney, I need to track how often specific precedents are cited over time to identify which legal arguments are gaining or losing traction.

**Context**:
- Citation relationships exist in graph
- No temporal analysis of citation patterns
- Attorneys want to know if precedents are becoming "stale" or "trendy"

**Tasks**:
- [ ] Create `app/Services/Graph/CitationDriftService.php`
  - [ ] `getPrecedentAdoptionRate()` - Citations per month over 12 months
  - [ ] `detectRetiredPrecedents()` - Decisions not cited in 6+ months
  - [ ] `detectEmergingPrecedents()` - New decisions gaining citations
  - [ ] `exportDriftChart()` - Generate chart image
- [ ] Create Cypher queries:
  ```cypher
  // Citation frequency over time
  MATCH (d1:Decision)-[c:CITES]->(d2:Decision)
  WHERE d1.decision_date >= date('2024-01-01')
  WITH d2, date.truncate('month', d1.decision_date) as month, count(c) as citations
  RETURN d2.case_number, month, citations
  ORDER BY d2.case_number, month
  ```
- [ ] Create API endpoints:
  - [ ] `GET /api/graph/citation-drift/{decisionId}`
  - [ ] `GET /api/graph/precedent-trends?period=12months`
- [ ] Create Livewire component: `CitationDriftExplorer`
  - [ ] Interactive chart (Chart.js)
  - [ ] Filter by time period, court, topic
  - [ ] Export to PNG/CSV
- [ ] Add to Legal Playground
- [ ] Write tests: `CitationDriftServiceTest.php`

**Acceptance Criteria**:
- ✅ Citation frequency tracked monthly over 12 months
- ✅ Retired precedents detected (0 citations in 6+ months)
- ✅ Emerging precedents flagged (citation growth >50%)
- ✅ Interactive chart rendered in Legal Playground
- ✅ Export to PNG and CSV functional

**Dependencies**:
- Citation relationships in graph (Sprint 4.3)
- 12+ months of historical data

---

### 9.2 Attorney Notification System
**Priority**: HIGH
**Points**: 13
**Owner**: Backend + DevOps

**Story**: As a defense attorney, I need weekly email/Slack digests with new entities, topic spikes, and outlier alerts so I stay informed without checking dashboards daily.

**Context**:
- Grafana alerts exist (Sprint 7.2) but are system-focused
- Attorneys need legal-insight-focused notifications
- Need configurable thresholds per attorney

**Tasks**:
- [ ] Create `attorney_notification_preferences` table:
  ```sql
  CREATE TABLE attorney_notification_preferences (
      id BIGSERIAL PRIMARY KEY,
      user_id INTEGER REFERENCES users(id),
      notification_type VARCHAR(50), -- email, slack, both
      frequency VARCHAR(20), -- daily, weekly, monthly
      enabled_alerts JSONB, -- {new_entities: true, topic_spikes: true, outliers: true}
      topic_filters JSONB, -- {topics: ["pretres doma", "droga"]}
      court_filters JSONB, -- {courts: ["Osijek", "Zagreb"]}
      slack_webhook_url TEXT,
      created_at TIMESTAMP,
      updated_at TIMESTAMP
  );
  ```
- [ ] Create `app/Services/Notifications/AttorneyDigestService.php`
  - [ ] `generateWeeklyDigest()` - Aggregate all alerts for user
  - [ ] `sendEmailDigest()` - Format HTML email
  - [ ] `sendSlackDigest()` - Format Slack message with blocks
- [ ] Create `app/Notifications/WeeklyLegalDigest.php` (Laravel notification)
- [ ] Create `app/Console/Commands/SendAttorneyDigestsCommand.php`
  - [ ] `php artisan notifications:send-attorney-digests`
- [ ] Schedule weekly digests
  - [ ] Add to `Kernel.php`: Monday 8:00 AM
- [ ] Create Livewire component: `NotificationPreferences`
  - [ ] User can configure frequency, channels, filters
  - [ ] Test notification button
- [ ] Create email template: `resources/views/emails/attorney-digest.blade.php`
  - [ ] Section: New entities this week
  - [ ] Section: Topic spikes
  - [ ] Section: Outlier prosecutors detected
  - [ ] Section: Citation trends
  - [ ] CTA: "View full analysis in Legal Playground"
- [ ] Create Slack template with attachments/blocks
- [ ] Write tests: `AttorneyDigestServiceTest.php`

**Acceptance Criteria**:
- ✅ Weekly email digest sent to opted-in attorneys
- ✅ Slack webhook integration functional
- ✅ User can configure notification preferences via UI
- ✅ Filters applied (topics, courts) to digest content
- ✅ Email includes all relevant alerts with links
- ✅ Slack message formatted with blocks (not plain text)

**Dependencies**:
- Emerging entity detection (Sprint 8.1)
- Topic spike analytics (Sprint 8.2)
- Outlier detection (Sprint 8.3)

---

### 9.3 Live Hearing Prep Aids (Cheat Sheets)
**Priority**: MEDIUM
**Points**: 21
**Owner**: AI/ML + Backend + Frontend

**Story**: As a defense attorney, I need to generate issue-focused cheat sheets before hearings that summarize relevant precedents, prosecutor history, and legal arguments from the graph.

**Context**:
- Attorneys prepare for hearings manually
- Graph contains all relevant info but not in usable format
- Need 1-2 page PDF summarizing key points for specific case

**Tasks**:
- [ ] Create `app/Services/HearingPrep/CheatSheetGenerator.php`
  - [ ] `generateCheatSheet()` - Master orchestration method
  - [ ] `gatherCaseContext()` - Extract case details
  - [ ] `findRelevantPrecedents()` - Top 5 similar decisions
  - [ ] `analyzeProsecutorHistory()` - Prosecutor's suppression rate, outlier status
  - [ ] `suggestLegalArguments()` - Based on graph patterns
  - [ ] `formatCheatSheet()` - Render to HTML/PDF
- [ ] Create cheat sheet template: `resources/views/reports/hearing-cheat-sheet.blade.php`
  - [ ] Section 1: Case Overview (charges, court, prosecutor, judge)
  - [ ] Section 2: Relevant Precedents (top 5 with similarity scores)
  - [ ] Section 3: Prosecutor Analysis (suppression rate, outlier status, historical data)
  - [ ] Section 4: Suggested Arguments (based on graph patterns)
  - [ ] Section 5: Key Citations (ZKP, Ustav articles)
  - [ ] Footer: Confidence score, generation timestamp
- [ ] Integrate with GraphResearchEnhancer:
  - [ ] Use `hybridSimilaritySearch()` for precedent discovery
  - [ ] Use `traverseCitationChain()` for related decisions
- [ ] Integrate with OutlierDetectionService:
  - [ ] Check if prosecutor is outlier
  - [ ] Include statistical evidence if yes
- [ ] Create API endpoint:
  - [ ] `POST /api/hearing-prep/generate-cheat-sheet`
  - [ ] Body: `{case_id, prosecutor_name, charges[]}`
  - [ ] Response: PDF download
- [ ] Create Livewire component: `HearingPrepGenerator`
  - [ ] Form: Enter case details
  - [ ] Button: "Generate Cheat Sheet"
  - [ ] Preview: Show HTML before PDF
  - [ ] Download: PDF export
- [ ] Add to Legal Playground
- [ ] PDF generation:
  - [ ] Use Laravel Snappy or DomPDF
  - [ ] 2-page limit (concise)
  - [ ] Professional styling
- [ ] Write tests: `CheatSheetGeneratorTest.php`
  - [ ] Mock case with known precedents
  - [ ] Validate all sections populated
  - [ ] Test PDF generation

**Acceptance Criteria**:
- ✅ Cheat sheet generated in <10 seconds
- ✅ Top 5 precedents found via hybrid search
- ✅ Prosecutor outlier status included if applicable
- ✅ Legal arguments suggested based on graph patterns
- ✅ PDF formatted professionally (2 pages max)
- ✅ Confidence score displayed (0.0-1.0)
- ✅ Accessible from Legal Playground

**Dependencies**:
- GraphResearchEnhancer (Sprint 7.1)
- OutlierDetectionService (Sprint 8.3)
- Citation relationships in graph

---

### 9.4 Interactive Graph Visualization UI
**Priority**: LOW
**Points**: 5
**Owner**: Frontend

**Story**: As a defense attorney, I want to visually explore the citation network around a specific decision to understand its legal context and relationships.

**Tasks**:
- [ ] Create Livewire component: `GraphVisualization`
  - [ ] Use D3.js or Cytoscape.js for rendering
  - [ ] Center node: Selected decision
  - [ ] Connected nodes: Cited decisions, citing decisions (1 hop)
  - [ ] Interactive: Click to expand, hover for tooltips
- [ ] Create API endpoint:
  - [ ] `GET /api/graph/visualization/{decisionId}?depth=1`
  - [ ] Returns nodes and edges in JSON
- [ ] Styling:
  - [ ] Decision nodes: Blue circles
  - [ ] Law nodes: Green squares
  - [ ] Prosecutor nodes: Red triangles
  - [ ] Edge thickness: Citation frequency
- [ ] Add to Legal Playground
- [ ] Write tests: Browser test for visualization load

**Acceptance Criteria**:
- ✅ Visualization loads in <3 seconds
- ✅ 1-hop relationships rendered (cited + citing)
- ✅ Interactive expansion works
- ✅ Accessible from Legal Playground

**Dependencies**:
- Citation relationships in graph

---

## Sprint 10: Governance, Integration & Pilot (2 weeks)

**Goal**: Establish governance policies, integrate with external systems, and run pilot with partner attorneys.

**Points**: 52

---

### 10.1 Data Retention & Ethics Policies
**Priority**: CRITICAL
**Points**: 8
**Owner**: Legal + Backend

**Story**: As a law firm, we need documented retention policies and ethics reviews for prosecutor analytics to ensure we're not violating ethical guidelines.

**Tasks**:
- [ ] Draft retention policy document:
  - [ ] Prosecutor outlier data: 2-year retention
  - [ ] Entity tracking data: 1-year retention
  - [ ] Topic spike data: 6-month retention
  - [ ] Hearing prep cheat sheets: Delete after 90 days
- [ ] Implement automated deletion:
  - [ ] `app/Console/Commands/CleanupStaleDataCommand.php`
  - [ ] Schedule monthly: `Kernel.php`
- [ ] Ethics review checklist:
  - [ ] Prosecutorial data usage: Evidence-based defense only
  - [ ] No public shaming or publication
  - [ ] Statistical thresholds to avoid false accusations
  - [ ] Attorney accountability for data usage
- [ ] Create `docs/DATA_RETENTION_POLICY.md`
- [ ] Create `docs/ETHICS_GUIDELINES.md`
- [ ] Add to onboarding documentation

**Acceptance Criteria**:
- ✅ Retention policy documented and approved by legal counsel
- ✅ Automated deletion runs monthly
- ✅ Ethics guidelines reviewed by Croatian Bar Association
- ✅ All attorneys trained on data usage policies

**Dependencies**: None

---

### 10.2 API for External Legal Research Tools
**Priority**: MEDIUM
**Points**: 13
**Owner**: Backend

**Story**: As a legal researcher, I want to access graph insights via REST API so I can integrate with external tools (e.g., case management systems).

**Tasks**:
- [ ] Create public API endpoints:
  - [ ] `GET /api/v1/decisions/{id}` - Decision details with graph context
  - [ ] `GET /api/v1/decisions/{id}/precedents` - Citing decisions
  - [ ] `GET /api/v1/decisions/{id}/citations` - Cited decisions
  - [ ] `GET /api/v1/prosecutors/{name}/statistics` - Outlier data
  - [ ] `GET /api/v1/topics/trends` - Topic spike data
- [ ] API authentication:
  - [ ] Laravel Sanctum tokens
  - [ ] Rate limiting: 100 requests/hour
- [ ] API documentation:
  - [ ] OpenAPI/Swagger spec
  - [ ] Interactive docs at `/api/docs`
- [ ] Versioning strategy: `/api/v1/`
- [ ] Write tests: API integration tests

**Acceptance Criteria**:
- ✅ All endpoints documented in OpenAPI spec
- ✅ Authentication with Sanctum tokens
- ✅ Rate limiting enforced
- ✅ Response times <500ms
- ✅ CORS configured for external access

**Dependencies**:
- All analytics services (Sprint 8)

---

### 10.3 Export to PDF/Word for Court Filings
**Priority**: MEDIUM
**Points**: 8
**Owner**: Backend

**Story**: As a defense attorney, I need to export outlier analysis and cheat sheets to professionally formatted PDF/Word documents for court filings.

**Tasks**:
- [ ] Enhance PDF export:
  - [ ] Professional header/footer
  - [ ] Attorney firm branding (configurable)
  - [ ] Page numbers, date, case reference
  - [ ] Signature line
- [ ] Add Word export:
  - [ ] Use PHPWord library
  - [ ] Same formatting as PDF
  - [ ] Editable for attorney customization
- [ ] Create templates:
  - [ ] `resources/views/exports/outlier-report-court.blade.php`
  - [ ] `resources/views/exports/cheat-sheet-court.blade.php`
- [ ] Add export options to UI:
  - [ ] Dropdown: PDF, Word, HTML
- [ ] Write tests: Export format validation

**Acceptance Criteria**:
- ✅ PDF export professional quality
- ✅ Word export editable
- ✅ Branding configurable per firm
- ✅ Export includes all relevant data

**Dependencies**:
- CheatSheetGenerator (Sprint 9.3)
- OutlierDetectionService (Sprint 8.3)

---

### 10.4 Pilot Rollout with Partner Attorneys
**Priority**: CRITICAL
**Points**: 13
**Owner**: Product + Support

**Story**: As a product owner, I need to coordinate a pilot rollout with 3-5 partner attorneys to gather feedback and validate the system before full launch.

**Tasks**:
- [ ] Identify pilot attorneys:
  - [ ] 3-5 defense attorneys in Osijek
  - [ ] Represent different case types (drugs, property, violent crimes)
  - [ ] Willing to provide weekly feedback
- [ ] Pilot training:
  - [ ] 2-hour workshop on graph intelligence features
  - [ ] Hands-on demo of hearing prep aids
  - [ ] Weekly office hours for Q&A
- [ ] Feedback collection:
  - [ ] Weekly surveys (5 questions)
  - [ ] Monthly interviews (30 min)
  - [ ] Track feature usage metrics
- [ ] Success metrics:
  - [ ] 80% of attorneys use cheat sheet generator weekly
  - [ ] 90% satisfaction with outlier detection accuracy
  - [ ] 100% report time savings vs manual research
- [ ] Iterate on feedback:
  - [ ] Weekly bug fixes
  - [ ] Bi-weekly feature adjustments
- [ ] Pilot documentation:
  - [ ] User guide: `docs/USER_GUIDE.md`
  - [ ] FAQ: `docs/FAQ.md`
  - [ ] Video tutorials (5-10 min each)

**Acceptance Criteria**:
- ✅ 3+ attorneys onboarded and trained
- ✅ 80% feature adoption rate
- ✅ 90% satisfaction score
- ✅ 50% report measurable time savings
- ✅ All critical bugs resolved

**Dependencies**:
- All Sprint 8-9 features operational

---

### 10.5 Performance Monitoring for Graph Queries
**Priority**: MEDIUM
**Points**: 5
**Owner**: DevOps

**Story**: As a system administrator, I need to monitor Neo4j query performance to ensure graph analytics don't degrade as data grows.

**Tasks**:
- [ ] Add Prometheus metrics:
  - [ ] `neo4j_query_duration_seconds` (already defined in Sprint 7.2)
  - [ ] `neo4j_query_count_total{query_type}`
  - [ ] `neo4j_node_count{node_type}`
  - [ ] `neo4j_relationship_count{relationship_type}`
- [ ] Add Grafana alerts:
  - [ ] Neo4j query time p95 >10s → Warning
  - [ ] Node count growth >20% per week → Info
- [ ] Create query profiling tool:
  - [ ] `php artisan graph:profile-queries --slow-threshold=5s`
- [ ] Schedule weekly profiling:
  - [ ] `Kernel.php`: Sunday 10:00 PM

**Acceptance Criteria**:
- ✅ Query metrics exported to Prometheus
- ✅ Grafana alerts configured
- ✅ Slow queries logged and profiled
- ✅ Weekly profiling report generated

**Dependencies**:
- Prometheus/Grafana setup (Sprint 7.2)

---

### 10.6 Graph Backup & Disaster Recovery
**Priority**: HIGH
**Points**: 5
**Owner**: DevOps

**Story**: As a system administrator, I need automated Neo4j backups and a disaster recovery plan to prevent data loss.

**Tasks**:
- [ ] Implement Neo4j backup:
  - [ ] Daily full backup to S3
  - [ ] Hourly incremental backup
  - [ ] 30-day retention
- [ ] Create restore procedure:
  - [ ] `docs/NEO4J_DISASTER_RECOVERY.md`
  - [ ] Test restore quarterly
- [ ] Add backup monitoring:
  - [ ] Grafana alert if backup fails
- [ ] Schedule backups:
  - [ ] `Kernel.php` or cron job

**Acceptance Criteria**:
- ✅ Daily backups to S3
- ✅ Restore tested successfully
- ✅ Alert fires if backup fails
- ✅ Recovery time objective (RTO) <4 hours

**Dependencies**: None

---

## Summary

### Total Effort
- **Sprint 8**: 52 points (Graph Analytics & Entity Intelligence)
- **Sprint 9**: 52 points (Attorney-Facing Tools & Notifications)
- **Sprint 10**: 52 points (Governance, Integration & Pilot)
- **Total**: 156 points over 6 weeks

### Key Deliverables
1. ✅ Emerging entity detection (prosecutors, judges, keywords)
2. ✅ Topic spike analytics (sudden growth detection)
3. ✅ Outlier prosecution detection (statistical evidence)
4. ✅ Citation drift tracker (precedent trends)
5. ✅ Attorney notification system (email/Slack digests)
6. ✅ Live hearing prep aids (PDF cheat sheets)
7. ✅ Interactive graph visualization
8. ✅ Data retention & ethics policies
9. ✅ Public API for external tools
10. ✅ PDF/Word export for court filings
11. ✅ Pilot rollout with partner attorneys
12. ✅ Performance monitoring & disaster recovery

### Dependencies on Existing Work
- ✅ Graph embeddings (Sprint 7.1) - Used for hybrid search in cheat sheets
- ✅ GraphResearchEnhancer (Sprint 4.3) - Used for precedent discovery
- ✅ TemporalReasoningService (Sprint 4.2) - Used for citation drift
- ✅ Active learning (Sprint 5) - Correlate with decision outcomes
- ✅ Grafana/Prometheus (Sprint 7.2) - Extended with graph metrics
- ✅ HomeSearchAbuseDetector (Sprint 2.3) - Provides prosecutor data

### Success Criteria
- ✅ 80% attorney adoption of hearing prep aids
- ✅ 90% satisfaction with outlier detection
- ✅ 50% time savings vs manual research
- ✅ 95% graph data quality score
- ✅ <5s p95 query time for all graph analytics

### Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Insufficient historical data for outlier detection | Require minimum 6 months data before enabling |
| Prosecutor ethics concerns | Legal counsel review + Croatian Bar approval |
| Neo4j performance degradation | Query optimization + read replicas |
| Low pilot attorney adoption | Weekly check-ins + dedicated support |
| Integration complexity with external tools | Start with simple REST API, iterate |

---

**Next Steps**:
1. Review this plan with stakeholders
2. Prioritize sprints based on attorney feedback
3. Begin Sprint 8 implementation
4. Schedule pilot attorney recruitment
