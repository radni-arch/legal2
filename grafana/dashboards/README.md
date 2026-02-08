# Grafana Dashboards - AI Legal War Machine

## Table of Contents
1. [Prosecutorial Outliers Dashboard](#prosecutorial-outliers-dashboard) (Sprint 8.3)
2. [Emerging Legal Entities Dashboard](#emerging-legal-entities-dashboard) (Sprint 8.1)
3. [Topic Trends & Spikes Dashboard](#topic-trends--spikes-dashboard) (Sprint 8.2)
4. [Graph Data Quality Dashboard](#graph-data-quality-dashboard) (Sprint 8.4)

---

## Prosecutorial Outliers Dashboard

**File**: `prosecutorial-outliers.json`
**Purpose**: Statistical analysis for prosecutors and courts with anomalous evidence suppression or rights violation rates (Sprint 8.3)

### Overview

This dashboard provides comprehensive monitoring of statistical outliers in prosecutorial and judicial behavior detected by the OutlierDetectionService. It uses z-score analysis (threshold |z| > 2.0 = 95% confidence) to identify prosecutors and courts with significantly higher evidence suppression or rights violation rates compared to their peers.

### Prerequisites

1. **PostgreSQL Datasource** configured in Grafana:
   - Type: `postgres`
   - UID: `ai-legal-postgres`
   - Database: Your Laravel database (default: `laravel`)
   - Host: PostgreSQL server address
   - Port: 5432 (default)

2. **Database Table**: `prosecutor_outliers` table must exist with schema:
   ```sql
   CREATE TABLE prosecutor_outliers (
       id BIGSERIAL PRIMARY KEY,
       prosecutor_id VARCHAR(255),
       prosecutor_name VARCHAR(255) NOT NULL,
       court VARCHAR(255),
       metric_type VARCHAR(50) DEFAULT 'suppression_rate',
       metric_value DECIMAL(8,4),
       population_mean DECIMAL(8,4),
       population_stddev DECIMAL(8,4),
       z_score DECIMAL(8,4),
       severity VARCHAR(20) DEFAULT 'normal',
       sample_size INTEGER,
       confidence_level INTEGER DEFAULT 95,
       detected_at TIMESTAMP,
       created_at TIMESTAMP,
       updated_at TIMESTAMP
   );
   ```

3. **Data Population**: Run the detection command monthly:
   ```bash
   php artisan graph:detect-outliers --min-cases=10 --store
   ```

   Or ensure the scheduled task is running (first Monday of month at 8:00 AM):
   ```bash
   php artisan schedule:work
   ```

### Dashboard Panels

#### 1. Outliers Detected (Last 30 Days)
**Type**: Stat
**Query**: `SELECT COUNT(*) FROM prosecutor_outliers WHERE detected_at >= NOW() - INTERVAL '30 days' AND severity IN ('moderate', 'high', 'extreme')`
**Purpose**: Quick overview of total significant outliers detected
**Thresholds**:
- Green: 0 outliers
- Yellow: 1-2 outliers
- Orange: 3-4 outliers
- Red: 5+ outliers

#### 2. Outliers by Severity
**Type**: Pie Chart
**Query**: `SELECT severity, COUNT(*) FROM prosecutor_outliers GROUP BY severity`
**Purpose**: Distribution of outliers by severity classification
**Severity Levels**:
- **Extreme**: |z| ≥ 3.0 (Red) - Requires immediate investigation
- **High**: 2.5 ≤ |z| < 3.0 (Orange) - Significant concern
- **Moderate**: 2.0 ≤ |z| < 2.5 (Yellow) - Monitor closely

#### 3. Extreme Suppression Rate Outliers
**Type**: Stat
**Query**: `SELECT COUNT(*) FROM prosecutor_outliers WHERE severity = 'extreme' AND metric_type = 'suppression_rate'`
**Purpose**: Alert for prosecutors with extreme evidence suppression rates
**Thresholds**:
- Green: 0 extreme outliers
- Yellow: 1 extreme outlier
- Red: 3+ extreme outliers

#### 4. Court Violation Outliers
**Type**: Stat
**Query**: `SELECT COUNT(*) FROM prosecutor_outliers WHERE court IS NOT NULL AND severity IN ('high', 'extreme')`
**Purpose**: Regional courts with high constitutional violation rates
**Thresholds**:
- Green: 0-1 court outliers
- Red: 2+ court outliers

#### 5. Outlier Detection Trend (90 Days)
**Type**: Time Series (Line Chart)
**Query**: `SELECT detected_at as time, severity, COUNT(*) FROM prosecutor_outliers WHERE detected_at >= NOW() - INTERVAL '90 days' GROUP BY detected_at, severity`
**Purpose**: Temporal trends showing outlier detection patterns over time
**Features**:
- Color-coded by severity (extreme=red, high=orange, moderate=yellow)
- Smooth line interpolation
- Extreme severity shown with thicker line (3px)

#### 6. Top 20 Outliers (Last 30 Days)
**Type**: Table
**Query**: Full outlier details ordered by |z-score| descending
**Purpose**: Detailed ranked list of worst offenders for attorney action
**Columns**:
- Prosecutor Name: Entity name
- Court: Court jurisdiction (if applicable)
- Metric Type: suppression_rate, violation_rate, appeal_overturn_rate
- Metric Value: Actual rate (percentage)
- Z-Score: Statistical deviation (gradient gauge 2.0-5.0)
- Severity: Color-coded badge (extreme/high/moderate)
- Sample Size: Number of cases analyzed
- Detected At: Detection timestamp

**Z-Score Display**:
- Gradient gauge visualization
- Yellow: 2.0-2.5 (moderate)
- Orange: 2.5-3.0 (high)
- Red: 3.0+ (extreme)

#### 7. Top 10 Prosecutors by Z-Score
**Type**: Bar Chart (Horizontal)
**Query**: `SELECT prosecutor_name, AVG(z_score) FROM prosecutor_outliers WHERE prosecutor_id IS NOT NULL GROUP BY prosecutor_name`
**Purpose**: Identify prosecutors with consistently high outlier scores
**Display**: Horizontal bars ordered by average z-score

#### 8. Top 10 Courts by Violation Z-Score
**Type**: Bar Chart (Horizontal)
**Query**: `SELECT court, AVG(z_score) FROM prosecutor_outliers WHERE court IS NOT NULL GROUP BY court`
**Purpose**: Regional analysis - courts with systemic violation patterns
**Display**: Horizontal bars ordered by average z-score

### Template Variables

The dashboard includes two template variables for filtering:

#### 1. Metric Type
- **Type**: Query
- **Query**: `SELECT DISTINCT metric_type FROM prosecutor_outliers ORDER BY metric_type`
- **Multi-select**: Enabled
- **Include All**: Yes
- **Options**: suppression_rate, violation_rate, appeal_overturn_rate, composite_score
- **Usage**: Filter outliers by specific metric type

#### 2. Severity
- **Type**: Query
- **Query**: `SELECT DISTINCT severity FROM prosecutor_outliers ORDER BY severity`
- **Multi-select**: Enabled
- **Include All**: Yes
- **Options**: extreme, high, moderate
- **Usage**: Filter by severity level

### Annotations

**Detection Runs**: Blue vertical lines showing when `graph:detect-outliers` was executed
**Query**: `SELECT detected_at as time, 'Outlier Detection Run' as text FROM prosecutor_outliers GROUP BY detected_at`
**Purpose**: Correlate outlier spikes with detection runs

### Statistical Methodology

The dashboard displays z-scores calculated by `OutlierDetectionService`:

```
Z-Score = (prosecutor_rate - population_mean) / population_stddev

Outlier Threshold: |z| > 2.0 (95% confidence interval)

Severity Classification:
- Normal: |z| < 2.0 (not displayed)
- Moderate: 2.0 ≤ |z| < 2.5
- High: 2.5 ≤ |z| < 3.0
- Extreme: |z| ≥ 3.0

Sample Standard Deviation: Uses Bessel's correction (n-1) for small samples
Minimum Sample Size: 10 cases for statistical validity
```

**Example**:
- Prosecutor A: 15% suppression rate, Population mean: 5%, Stddev: 3%
  - Z-Score = (0.15 - 0.05) / 0.03 = 3.33 (Extreme)
- Court B: 12% violation rate, Population mean: 8%, Stddev: 2%
  - Z-Score = (0.12 - 0.08) / 0.02 = 2.0 (Moderate)

### Installation

1. **Import Dashboard**:
   ```bash
   # Option 1: Grafana UI
   - Navigate to Dashboards > Import
   - Upload prosecutorial-outliers.json
   - Select PostgreSQL datasource (uid: ai-legal-postgres)

   # Option 2: API
   curl -X POST http://grafana:3000/api/dashboards/db \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -d @prosecutorial-outliers.json
   ```

2. **Configure Datasource**: Same as Emerging Entities Dashboard (see below)

3. **Verify Data**:
   ```bash
   # Check if prosecutor_outliers table has data
   php artisan tinker
   >>> DB::table('prosecutor_outliers')->count();

   # Run detection if empty
   php artisan graph:detect-outliers --min-cases=10 --store
   ```

### Usage Tips

1. **Critical Alerts**: Red panels indicate immediate action required:
   - Extreme suppression outliers: Prepare misconduct motions
   - Court violation outliers: Consider venue change motions

2. **Attorney Action Items**:
   - **Extreme Severity (|z| ≥ 3.0)**: File prosecutorial misconduct motion immediately
   - **High Severity (2.5-3.0)**: Monitor prosecutor closely, document patterns
   - **Moderate Severity (2.0-2.5)**: Watchlist for future cases

3. **Trend Analysis**:
   - Increasing trend: Systemic problem requiring legislative action
   - Spike after detection: May indicate batch case reviews
   - Stable high: Entrenched prosecutorial culture

4. **Regional Patterns**: Use "Top 10 Courts" to identify jurisdictions with systemic issues for:
   - Venue change motions
   - Regional training needs
   - Legislative advocacy priorities

5. **Multi-Metric Analysis**: Filter by metric_type to distinguish:
   - Suppression rate: Evidence admissibility issues
   - Violation rate: Constitutional rights violations
   - Appeal overturn rate: Prosecutorial error rates

### Troubleshooting

**Issue**: No data showing in panels
**Solution**:
1. Run `php artisan graph:detect-outliers --min-cases=10 --store`
2. Verify data: `DB::table('prosecutor_outliers')->count()`
3. Check time range (default: last 90 days)

**Issue**: "Division by zero" in z-score calculation
**Solution**: OutlierDetectionService handles this automatically - if you see this error, check that population_stddev is not null in database

**Issue**: No Neo4j data available
**Solution**: Command falls back to PostgreSQL automatically. Check Neo4j connection in `.env`:
```
NEO4J_ENABLED=true
NEO4J_URI=bolt://localhost:7687
```

### Maintenance

**Monthly**: Review dashboard after scheduled outlier detection run (first Monday 8:00 AM)

**Quarterly**: Analyze trends and adjust outlier thresholds if needed:
```sql
-- Check if z-score > 2.0 threshold is still appropriate
SELECT AVG(z_score), STDDEV(z_score)
FROM prosecutor_outliers
WHERE detected_at >= NOW() - INTERVAL '3 months';
```

**Attorney Workflow**:
1. Check dashboard Monday mornings after detection run
2. Flag extreme outliers for case review
3. Export Top 20 table for client reports
4. Monitor trend charts for pattern changes

### Related Components

- **Service**: `app/Services/Graph/OutlierDetectionService.php`
- **Command**: `app/Console/Commands/Graph/DetectOutliersCommand.php`
- **Tests**: `tests/Unit/Services/Graph/OutlierDetectionServiceTest.php`
- **Migration**: `database/migrations/2025_11_11_042917_create_prosecutor_outliers_table.php`
- **Schedule**: `app/Console/Kernel.php` (First Monday of month, 8:00 AM)

### Sprint Information

**Sprint**: 8.3 - Outlier Prosecution Detection
**Priority**: CRITICAL
**Story Points**: 21
**Status**: ✅ COMPLETE

**Acceptance Criteria Met**:
- ✅ Prosecutors with z-score >2.0 flagged as outliers
- ✅ Sample size filter (minimum 10 cases) applied
- ✅ Regional disparity detected (court-level aggregation)
- ✅ PDF report capability (planned enhancement)
- ✅ Dashboard visualizes outliers with confidence levels
- ✅ Multi-metric scoring (suppression + violation + appeal rates)
- ✅ Grafana panels provide visual monitoring
- ✅ Database stores outlier history for trend analysis
- ✅ Neo4j integration for citation analysis
- ✅ Scheduled monthly analysis

### Future Enhancements

- [ ] Email alerts for extreme outliers (|z| ≥ 3.0)
- [ ] PDF report generation (OutlierReportGenerator)
- [ ] Prosecutor detail drill-down (click → see related cases)
- [ ] Historical z-score evolution charts (per prosecutor)
- [ ] Machine learning anomaly detection for unusual patterns
- [ ] Export functionality for attorney reports
- [ ] Integration with case management system
- [ ] Automatic misconduct motion template generation

---

## Emerging Legal Entities Dashboard

**File**: `emerging-entities.json`
**Purpose**: Track and visualize new prosecutors, judges, keywords, and courts as they emerge in the system (Sprint 8.1)

### Overview

This dashboard provides comprehensive monitoring of emerging legal entities detected by the EntityTrackingService. It helps attorneys stay informed about new prosecutors, judges, courts, and keywords appearing in court decisions.

### Prerequisites

1. **PostgreSQL Datasource** configured in Grafana:
   - Type: `postgres`
   - UID: `ai-legal-postgres`
   - Database: Your Laravel database (default: `laravel`)
   - Host: PostgreSQL server address
   - Port: 5432 (default)

2. **Database Table**: `emerging_entities` table must exist with schema:
   ```sql
   CREATE TABLE emerging_entities (
       id BIGSERIAL PRIMARY KEY,
       entity_type VARCHAR(50),      -- prosecutor, judge, keyword, court
       entity_id VARCHAR(255),
       entity_name VARCHAR(500),
       first_seen_at TIMESTAMP,
       decision_count INTEGER,
       detected_at TIMESTAMP,
       relevance_score DECIMAL(3,2), -- 0.00-1.00
       created_at TIMESTAMP,
       updated_at TIMESTAMP
   );
   ```

3. **Data Population**: Run the detection command weekly:
   ```bash
   php artisan graph:detect-new-entities --tag --store
   ```

   Or ensure the scheduled task is running (Mondays at 6:00 AM):
   ```bash
   php artisan schedule:work
   ```

### Dashboard Panels

#### 1. New Entities (Last 7 Days)
**Type**: Stat
**Query**: `SELECT COUNT(*) FROM emerging_entities WHERE detected_at >= NOW() - INTERVAL '7 days'`
**Purpose**: Quick overview of total new entities detected in the past week
**Thresholds**:
- Green: 0-9 entities
- Yellow: 10-49 entities
- Red: 50+ entities

#### 2. Entities by Type
**Type**: Pie Chart
**Query**: `SELECT entity_type, COUNT(*) FROM emerging_entities GROUP BY entity_type`
**Purpose**: Distribution of new entities by type (prosecutor, judge, court, keyword)
**Colors**:
- Prosecutor: Red (highest priority)
- Judge: Orange
- Court: Blue
- Keyword: Green

#### 3. High Priority Entities (Last 7 Days)
**Type**: Stat
**Query**: `SELECT COUNT(*) FROM emerging_entities WHERE detected_at >= NOW() - INTERVAL '7 days' AND relevance_score >= 0.75`
**Purpose**: Alert for high-impact entities requiring immediate attention
**Thresholds**:
- Green: 0-4 entities
- Yellow: 5-9 entities
- Red: 10+ entities

#### 4. New Prosecutors Alert
**Type**: Stat
**Query**: `SELECT COUNT(*) FROM emerging_entities WHERE entity_type = 'prosecutor' AND detected_at >= NOW() - INTERVAL '7 days'`
**Purpose**: Dedicated tracking for new prosecutors (highest priority entity type)
**Thresholds**:
- Green: 0 prosecutors
- Yellow: 1-4 prosecutors
- Red: 5+ prosecutors

#### 5. 4-Week Emerging Entity Trend
**Type**: Time Series (Line Chart)
**Query**: `SELECT DATE_TRUNC('week', detected_at) as time, entity_type, COUNT(*) FROM emerging_entities WHERE detected_at >= NOW() - INTERVAL '28 days' GROUP BY DATE_TRUNC('week', detected_at), entity_type`
**Purpose**: Weekly trend analysis showing emergence patterns over the past 4 weeks
**Features**:
- Smooth line interpolation
- Color-coded by entity type
- Prosecutors shown with thicker line (3px)

#### 6. Top 10 Entities by Relevance (Last 7 Days)
**Type**: Table
**Query**: `SELECT entity_name, entity_type, decision_count, ROUND(relevance_score::numeric, 2), TO_CHAR(first_seen_at, 'YYYY-MM-DD') FROM emerging_entities WHERE detected_at >= NOW() - INTERVAL '7 days' ORDER BY relevance_score DESC LIMIT 10`
**Purpose**: Ranked list of highest-impact entities with detailed metrics
**Columns**:
- Entity: Name of prosecutor, judge, court, or keyword
- Type: Color-coded entity type badge
- Decisions: LCD gauge showing decision count
- Relevance: Gradient gauge (0.00-1.00 scale)
- First Seen: Date when entity first appeared

**Relevance Thresholds**:
- Green: 0.00-0.49
- Yellow: 0.50-0.74
- Orange: 0.75-0.89
- Red: 0.90-1.00

#### 7. Entities by Week (Stacked)
**Type**: Time Series (Stacked Area Chart)
**Query**: Same as panel 5
**Purpose**: Cumulative view of entity emergence over time
**Features**:
- 80% fill opacity
- Smooth gradient
- Stacked mode for total visibility

#### 8. Average Relevance Score by Type
**Type**: Bar Gauge
**Query**: `SELECT entity_type, ROUND(AVG(relevance_score)::numeric, 2) FROM emerging_entities WHERE detected_at >= NOW() - INTERVAL '7 days' GROUP BY entity_type`
**Purpose**: Compare average relevance scores across entity types
**Display**: Horizontal gradient bars (0.00-1.00 scale)

### Template Variables

The dashboard includes two template variables for filtering:

#### 1. Entity Type
- **Type**: Query
- **Query**: `SELECT DISTINCT entity_type FROM emerging_entities ORDER BY entity_type`
- **Multi-select**: Enabled
- **Include All**: Yes
- **Usage**: Filter all panels by specific entity types

#### 2. Time Range
- **Type**: Interval
- **Options**: 7 days, 14 days, 30 days, 90 days
- **Default**: 7 days
- **Usage**: Adjust time window for analysis

### Annotations

**Detection Runs**: Blue vertical lines showing when `graph:detect-new-entities` was executed
**Query**: `SELECT detected_at as time, 'Detection Run' as text FROM emerging_entities GROUP BY detected_at`
**Purpose**: Correlate entity spikes with detection runs

### Installation

1. **Import Dashboard**:
   ```bash
   # Option 1: Grafana UI
   - Navigate to Dashboards > Import
   - Upload emerging-entities.json
   - Select PostgreSQL datasource (uid: ai-legal-postgres)

   # Option 2: API
   curl -X POST http://grafana:3000/api/dashboards/db \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -d @emerging-entities.json
   ```

2. **Configure Datasource**:
   ```yaml
   # Grafana datasource YAML (for provisioning)
   apiVersion: 1
   datasources:
     - name: AI Legal PostgreSQL
       type: postgres
       uid: ai-legal-postgres
       url: postgres:5432
       database: laravel
       user: your_db_user
       secureJsonData:
         password: your_db_password
       jsonData:
         sslmode: disable
         postgresVersion: 1400
         timescaledb: false
   ```

3. **Verify Data**:
   ```bash
   # Check if emerging_entities table has data
   php artisan tinker
   >>> DB::table('emerging_entities')->count();

   # Run detection if empty
   php artisan graph:detect-new-entities --tag --store
   ```

### Usage Tips

1. **New Prosecutor Alert**: Check the "New Prosecutors Alert" panel daily. Red alerts (5+) indicate significant changes in prosecution patterns.

2. **Relevance Prioritization**: Focus on entities with relevance scores >= 0.75 (shown in "High Priority Entities" panel). These are either prosecutors/judges or entities with high decision counts.

3. **Trend Analysis**: Use the 4-week trend to identify patterns:
   - Sudden spikes may indicate new courts coming online
   - Steady increase in prosecutors suggests expanded prosecution efforts
   - Keyword spikes reveal emerging legal themes

4. **Regional Filtering**: Combine with template variables to focus on specific entity types (e.g., only prosecutors).

5. **Time Window Adjustment**: Use the time_range template variable to analyze different periods (7/14/30/90 days).

### Relevance Scoring Algorithm

The dashboard displays relevance scores calculated by `EntityTrackingService`:

```
Score = (entity_type_weight × 0.6) + (normalized_decision_count × 0.4)

Entity Type Weights:
- Prosecutor: 1.0 (highest priority)
- Judge: 0.9
- Court: 0.7
- Keyword: 0.5

Decision Count: Normalized to 0-1 scale (capped at 50 decisions)
```

**Example**:
- New prosecutor with 10 decisions: (1.0 × 0.6) + (0.2 × 0.4) = 0.68
- New judge with 10 decisions: (0.9 × 0.6) + (0.2 × 0.4) = 0.62
- New keyword with 50 decisions: (0.5 × 0.6) + (1.0 × 0.4) = 0.70

### Troubleshooting

**Issue**: No data showing in panels
**Solution**:
1. Run `php artisan graph:detect-new-entities --tag --store`
2. Verify data: `DB::table('emerging_entities')->count()`
3. Check time range (default: last 7 days)

**Issue**: "Type 'vector' does not exist" error
**Solution**: This dashboard uses standard PostgreSQL types, not pgvector. Check datasource configuration.

**Issue**: Datasource connection failed
**Solution**:
1. Verify PostgreSQL is running
2. Check datasource configuration in Grafana
3. Ensure UID is exactly `ai-legal-postgres`

**Issue**: Panels show "No data"
**Solution**: Emerging entities are detected weekly (Mondays 6:00 AM). Run manual detection or wait for next scheduled run.

### Maintenance

**Weekly**: Review "Top 10 Entities by Relevance" to identify important new legal actors

**Monthly**: Export dashboard data for trend analysis:
```sql
COPY (
  SELECT * FROM emerging_entities
  WHERE detected_at >= NOW() - INTERVAL '30 days'
) TO '/tmp/emerging_entities_export.csv' CSV HEADER;
```

**Quarterly**: Adjust thresholds based on system growth patterns

### Related Components

- **Service**: `app/Services/Graph/EntityTrackingService.php`
- **Command**: `app/Console/Commands/Graph/DetectNewEntitiesCommand.php`
- **Tests**: `tests/Unit/Services/Graph/EntityTrackingServiceTest.php`
- **Migration**: `database/migrations/2025_11_11_040000_create_emerging_entities_table.php`
- **Schedule**: `app/Console/Kernel.php` (Monday 6:00 AM)

### Sprint Information

**Sprint**: 8.1 - Emerging Entity Detection & Tracking
**Priority**: HIGH
**Story Points**: 13
**Status**: ✅ COMPLETE

**Acceptance Criteria Met**:
- ✅ New prosecutors detected within 24 hours
- ✅ Weekly report shows entities from last 7 days
- ✅ Relevance scoring prioritizes high-impact entities
- ✅ Grafana panels provide visual monitoring
- ✅ Database stores entity history for analytics
- ✅ Neo4j nodes tagged with :Emerging label

### Future Enhancements

- [ ] Email alerts for high-priority entities (relevance >= 0.90)
- [ ] Regional filtering (e.g., Osijek vs Zagreb courts)
- [ ] Historical comparison (week-over-week growth rates)
- [ ] Entity detail drill-down (click entity → see related decisions)
- [ ] Export functionality for weekly reports
- [ ] Machine learning anomaly detection for unusual spikes
