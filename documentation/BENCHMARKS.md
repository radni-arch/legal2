# Benchmark Suite Documentation

Comprehensive benchmarks to measure AI agent quality and system performance objectively.

## Overview

The benchmark suite consists of **16 benchmarks** across **7 categories** that measure different aspects of the AI Legal War Machine system:

- **Legal Research** (3 benchmarks) - Search, retrieval, citation accuracy
- **Misconduct Detection** (3 benchmarks) - Detection accuracy, false positive/negative rates
- **Evidence Analysis** (2 benchmarks) - Admissibility, recontextualization quality
- **Multi-Agent** (2 benchmarks) - Collaboration efficiency, cost effectiveness
- **Precedent Analysis** (3 benchmarks) - Applicability, authority weighting, distinguishing factors
- **Strategy** (2 benchmarks) - Success probability, argument strength
- **Graph** (1 benchmark) - Graph embedding similarity

## Quick Start

### Running All Benchmarks

```bash
# Run all benchmarks
php artisan benchmark:run all

# Run specific benchmark
php artisan benchmark:run CitationAccuracyBenchmark

# Run with comparison to baseline commit
php artisan benchmark:run all --compare=abc123

# Run with custom configuration
php artisan benchmark:run ApplicabilityScoringBenchmark --config='{"tolerance": 0.1}'
```

### Viewing Benchmark Reports

```bash
# Show recent benchmark runs (table format)
php artisan benchmark:report

# Show specific benchmark runs
php artisan benchmark:report --benchmark=CitationAccuracyBenchmark --limit=5

# Export as JSON
php artisan benchmark:report --format=json

# Export as CSV
php artisan benchmark:report --format=csv

# Compare commits
php artisan benchmark:report --compare
```

## Benchmark Categories

### 1. Legal Research Benchmarks

#### 1.1 Citation Accuracy Benchmark
**Class:** `App\Benchmarks\CitationAccuracyBenchmark`

**Purpose:** Measures accuracy of legal citation extraction and validation from text.

**Key Metrics:**
- `extraction_accuracy` - Proportion of correctly extracted citations
- `precision` - True positives / (True positives + False positives)
- `recall` - True positives / (True positives + False negatives)
- `f1_score` - Harmonic mean of precision and recall
- `validation_accuracy` - Accuracy of citation validation against database

**What It Tests:**
- Croatian legal citation format recognition (ZKP, Ustav RH, KZ)
- Citation normalization
- Database validation of law articles

#### 1.2 Precedent Relevance Benchmark
**Class:** `App\Benchmarks\PrecedentRelevanceBenchmark`

**Purpose:** Measures relevance of retrieved court decisions for legal queries.

**Key Metrics:**
- `precision_at_k` - Precision at K retrieved documents
- `mean_average_precision` - MAP score across queries
- `recall_at_k` - Recall at K retrieved documents
- `ndcg` - Normalized Discounted Cumulative Gain (ranking quality)
- `avg_retrieval_time_ms` - Average retrieval latency

**What It Tests:**
- Semantic search quality for Croatian court decisions
- Ranking relevance to legal scenarios
- Keyword matching accuracy

#### 1.3 Law Search Precision Benchmark
**Class:** `App\Benchmarks\LawSearchPrecisionBenchmark`

**Purpose:** Measures precision of law article retrieval for legal concepts.

**Key Metrics:**
- `precision` - Overall precision of law article retrieval
- `recall` - Overall recall of relevant articles
- `f1_score` - Harmonic mean of precision and recall
- `mean_reciprocal_rank` - MRR for first relevant result
- `exact_matches` - Count of perfect law + article matches
- `avg_search_time_ms` - Average search latency

**What It Tests:**
- Conceptual law article search (e.g., "presumption of innocence" → ZKP Article 9)
- Law name + article number matching
- Search performance

### 2. Misconduct Detection Benchmarks

#### 2.1 False Positive Rate Benchmark
**Class:** `App\Benchmarks\FalsePositiveRateBenchmark`

**Purpose:** Measures how often legitimate prosecutorial actions are incorrectly flagged as misconduct.

**Key Metrics:**
- `false_positive_rate` - Proportion of legitimate cases flagged as misconduct
- `specificity` - True negative rate (correctly identified as legitimate)
- `avg_severity_on_legitimate` - Average severity score on non-misconduct cases
- `max_severity_on_legitimate` - Maximum severity assigned to legitimate case

**What It Tests:**
- Discrimination between legitimate prosecution and misconduct
- Over-flagging tendency
- Severity threshold calibration

#### 2.2 False Negative Rate Benchmark
**Class:** `App\Benchmarks\FalseNegativeRateBenchmark`

**Purpose:** Measures how often actual misconduct goes undetected.

**Key Metrics:**
- `false_negative_rate` - Proportion of misconduct cases missed
- `sensitivity` - True positive rate (correctly detected misconduct)
- `avg_severity_detected` - Average severity of detected misconduct
- `avg_severity_missed` - Average severity of missed misconduct
- `detection_by_type` - Detection rates per misconduct type

**What It Tests:**
- Misconduct detection coverage across types (Brady violations, witness tampering, etc.)
- Sensitivity to severity levels
- Type-specific detection accuracy

#### 2.3 Severity Scoring Benchmark
**Class:** `App\Benchmarks\SeverityScoringBenchmark`

**Purpose:** Measures accuracy of severity scoring for prosecutorial misconduct.

**Key Metrics:**
- `category_accuracy` - Accuracy of severity category assignment (critical/high/medium/low/minimal)
- `range_accuracy` - Percentage within expected severity range
- `mean_absolute_error` - Average absolute error in severity scores
- `correlation` - Pearson correlation with expected severity

**What It Tests:**
- Severity score calibration (0-100 scale)
- Category boundaries (critical: 90-100, high: 70-89, etc.)
- Consistency with expert judgment

### 3. Evidence Analysis Benchmarks

#### 3.1 Admissibility Accuracy Benchmark
**Class:** `App\Benchmarks\AdmissibilityAccuracyBenchmark`

**Purpose:** Measures accuracy of evidence admissibility determinations under Croatian law.

**Key Metrics:**
- `accuracy` - Overall correctaccuracy of admissibility determinations
- `precision` - Precision for admissible evidence
- `recall` - Recall for admissible evidence
- `issue_detection_accuracy` - Accuracy of identifying inadmissibility issues

**What It Tests:**
- Constitutional violation detection (illegal search, coercion, lack of counsel)
- Chain of custody evaluation
- Hearsay identification
- Croatian ZKP compliance

#### 3.2 Recontextualization Quality Benchmark
**Class:** `App\Benchmarks\RecontextualizationQualityBenchmark`

**Purpose:** Measures quality of recontextualizing prosecution evidence from defense perspective.

**Key Metrics:**
- `avg_quality_score` - Overall recontextualization quality (0-1)
- `avg_alternative_count` - Average number of defense angles generated
- `coverage_score` - Coverage of expected defense perspectives
- `novelty_score` - Novelty of generated alternatives
- `legal_soundness` - Legal validity of recontextualizations

**What It Tests:**
- Alternative narrative generation
- Exculpatory interpretation identification
- Defense strategy creativity

### 4. Multi-Agent Benchmarks

#### 4.1 Collaboration Efficiency Benchmark
**Class:** `App\Benchmarks\CollaborationEfficiencyBenchmark`

**Purpose:** Measures efficiency of multi-agent collaboration.

**Key Metrics:**
- `avg_message_latency_ms` - Average inter-agent message latency
- `delegation_success_rate` - Proportion of successful task delegations
- `avg_coordination_overhead` - Ratio of coordination messages to total
- `parallelization_efficiency` - Efficiency of parallel task execution

**What It Tests:**
- Agent communication performance
- Task delegation reliability
- Coordination overhead
- Parallel execution patterns

#### 4.2 Cost Effectiveness Benchmark
**Class:** `App\Benchmarks\CostEffectivenessBenchmark`

**Purpose:** Measures cost efficiency of AI agent operations.

**Key Metrics:**
- `total_cost_usd` - Total API costs in USD
- `avg_cost_per_operation` - Average cost per agent operation
- `avg_tokens_per_operation` - Average token usage per operation
- `cost_per_successful_outcome` - Cost per successful task completion
- `cost_efficiency_score` - Combined efficiency score (0-1)

**What It Tests:**
- Token usage optimization
- Model selection efficiency (GPT-4 vs GPT-4o-mini)
- Cost vs quality tradeoffs

### 5. Precedent Analysis Benchmarks

#### 5.1 Applicability Scoring Benchmark
**Class:** `App\Benchmarks\ApplicabilityScoringBenchmark`

**Purpose:** Measures accuracy of scoring how applicable precedents are to current cases.

**Key Metrics:**
- `within_tolerance` - Percentage of scores within tolerance (default ±0.15)
- `mean_absolute_error` - MAE of applicability scores vs expected
- `correlation` - Correlation with expert assessments

**What It Tests:**
- Fact pattern similarity assessment
- Applicability score calibration (0-1 scale)
- Legal relevance judgment

#### 5.2 Authority Weighting Benchmark
**Class:** `App\Benchmarks\AuthorityWeightingBenchmark`

**Purpose:** Measures accuracy of weighting different legal authorities by court hierarchy.

**Key Metrics:**
- `hierarchy_accuracy` - Accuracy of hierarchy-based weighting
- `correct_weights` - Exact weight matches
- `mean_absolute_error` - MAE of authority weights

**What It Tests:**
- Croatian court hierarchy understanding (Vrhovni sud > Visoki upravni sud > Županijski sud > Općinski sud)
- Binding vs persuasive precedent distinction
- Authority weight calibration (0-1 scale)

#### 5.3 Distinguishing Factors Benchmark
**Class:** `App\Benchmarks\DistinguishingFactorsBenchmark`

**Purpose:** Measures accuracy of identifying factors that distinguish cases from precedents.

**Key Metrics:**
- `avg_factors_identified` - Average number of distinguishing factors found
- `precision` - Precision of factor identification
- `recall` - Recall of expected factors
- `f1_score` - F1 score for factor identification

**What It Tests:**
- Material fact difference identification
- Legal distinction reasoning
- Why-precedent-doesn't-apply analysis

### 6. Strategy Benchmarks

#### 6.1 Success Probability Benchmark
**Class:** `App\Benchmarks\SuccessProbabilityBenchmark`

**Purpose:** Measures accuracy of predicting success probability for defense strategies.

**Key Metrics:**
- `mean_absolute_error` - MAE of probability predictions
- `brier_score` - Brier score for probabilistic predictions (lower is better)
- `calibration_score` - Calibration quality (1 - Brier score)
- `prediction_accuracy` - Binary outcome prediction accuracy

**What It Tests:**
- Strategy success probability calibration (0-1 scale)
- Factual strength assessment
- Probabilistic prediction quality

#### 6.2 Argument Strength Benchmark
**Class:** `App\Benchmarks\ArgumentStrengthBenchmark`

**Purpose:** Measures accuracy of assessing legal argument strength.

**Key Metrics:**
- `mean_absolute_error` - MAE of strength assessments
- `correlation` - Correlation with expert assessments
- `classification_accuracy` - Accuracy of strong vs weak classification
- `strong_args_identified` - Count of correctly identified strong arguments

**What It Tests:**
- Argument strength assessment (0-1 scale)
- Precedent support weighting
- Legal reasoning quality evaluation

## Interpretation Guide

### Metric Types

**Accuracy Metrics** (Higher is better, 0-1 scale):
- `accuracy`, `precision`, `recall`, `f1_score`, `specificity`, `sensitivity`
- **Good:** > 0.85
- **Acceptable:** 0.70-0.85
- **Needs Improvement:** < 0.70

**Error Metrics** (Lower is better):
- `mean_absolute_error`, `mean_squared_error`, `brier_score`
- **Good:** < 0.10
- **Acceptable:** 0.10-0.20
- **Needs Improvement:** > 0.20

**Rate Metrics** (Lower is better):
- `false_positive_rate`, `false_negative_rate`
- **Good:** < 0.05
- **Acceptable:** 0.05-0.15
- **Needs Improvement:** > 0.15

**Ranking Metrics** (Higher is better, 0-1 scale):
- `mean_reciprocal_rank`, `ndcg`, `mean_average_precision`
- **Good:** > 0.80
- **Acceptable:** 0.60-0.80
- **Needs Improvement:** < 0.60

**Correlation** (Higher is better, -1 to 1):
- Pearson correlation coefficient
- **Good:** > 0.70
- **Acceptable:** 0.50-0.70
- **Needs Improvement:** < 0.50

### Performance Targets

**Latency:**
- Search/retrieval: < 500ms average
- Agent operations: < 2000ms average
- Message passing: < 100ms average

**Cost:**
- Per operation: < $0.10 average
- Per successful outcome: < $0.50 average

**Quality:**
- Detection accuracy: > 85%
- False positive rate: < 10%
- False negative rate: < 15%
- Scoring MAE: < 0.15

## Configuration

Each benchmark supports custom configuration through the `--config` option:

```bash
# Example: Adjust citation benchmark sample size
php artisan benchmark:run CitationAccuracyBenchmark \
  --config='{"sample_size": 200}'

# Example: Adjust severity scoring tolerance
php artisan benchmark:run SeverityScoringBenchmark \
  --config='{"tolerance": 0.10}'

# Example: Adjust precedent retrieval limit
php artisan benchmark:run PrecedentRelevanceBenchmark \
  --config='{"retrieval_limit": 20}'
```

Default configurations are defined in each benchmark's `getDefaultConfig()` method.

## Baseline Data

Baseline benchmark runs are recorded with:
- Git commit hash and branch
- Dirty flag (uncommitted changes)
- PHP version and Laravel version
- System information (OS, memory limit, etc.)
- Full configuration used
- All metric values
- Execution duration

This enables:
- Regression detection (performance degradation over time)
- A/B testing (comparing different approaches)
- CI/CD integration (automated quality gates)
- Historical trend analysis

## Extending Benchmarks

To create a new benchmark:

1. **Create benchmark class** extending `BaseBenchmark`:

```php
<?php

namespace App\Benchmarks;

class MyNewBenchmark extends BaseBenchmark
{
    public function getName(): string
    {
        return 'My New Benchmark';
    }

    public function getDescription(): string
    {
        return 'Measures XYZ aspect of the system';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'sample_size' => 100,
            'threshold' => 0.5,
        ];
    }

    protected function execute(): array
    {
        // Implement benchmark logic
        return [
            'metric1' => 0.85,
            'metric2' => 120,
            // ... more metrics
        ];
    }

    protected function isImprovement(string $metricKey, float $diff): bool
    {
        // Define which direction is improvement
        $lowerIsBetter = ['latency_ms', 'error_rate'];

        if (in_array($metricKey, $lowerIsBetter)) {
            return $diff < 0; // Decrease is improvement
        }

        return $diff > 0; // Increase is improvement
    }
}
```

2. **Run the new benchmark**:

```bash
php artisan benchmark:run MyNewBenchmark
```

The benchmark will automatically:
- Initialize with default config
- Record git information
- Store results in database
- Support comparison with previous runs

## CI/CD Integration

The benchmark suite integrates with GitHub Actions to automatically detect performance regressions in pull requests and track baseline performance on the main branch.

### Overview

Two GitHub Actions workflows provide automated benchmarking:

1. **Pull Request Benchmarks** (`.github/workflows/benchmarks-pr.yml`)
   - Runs on every PR to main/master
   - Executes subset of 5 critical benchmarks (fast ~2-3 minutes)
   - Compares against target branch baseline
   - Posts/updates PR comment with results
   - Fails CI if regression > 5% detected

2. **Main Branch Benchmarks** (`.github/workflows/benchmarks-main.yml`)
   - Runs on push to main/master
   - Executes all 15 benchmarks (full suite ~5-10 minutes)
   - Runs weekly on schedule (Sundays 00:00 UTC)
   - Creates GitHub issue if regression detected
   - Uploads results as artifacts (90-day retention)

### Regression Detection Command

Check for performance regressions:

```bash
# Compare current code against baseline commit
php artisan benchmark:check-regression <baseline-commit> [options]

# Options:
#   --threshold=0.05        Regression threshold (default: 5%)
#   --format=text|json|github   Output format
#   --benchmarks=CLASS      Specific benchmarks to check (multiple allowed)
#   --fail-fast             Stop on first regression

# Examples:

# Check against main branch
git fetch origin main
php artisan benchmark:check-regression origin/main

# Check with stricter threshold (2%)
php artisan benchmark:check-regression abc123 --threshold=0.02

# Check specific benchmarks only
php artisan benchmark:check-regression abc123 \
  --benchmarks=CitationAccuracyBenchmark \
  --benchmarks=CostEffectivenessBenchmark

# Generate GitHub-formatted output for PR comments
php artisan benchmark:check-regression abc123 --format=github
```

### How Regression Detection Works

1. **Baseline Comparison**
   - Fetches baseline benchmark run for specified commit
   - Runs current benchmarks
   - Compares each metric between baseline and current

2. **Metric Direction**
   - Each benchmark defines which direction is improvement
   - Lower is better: latency, error rates, cost metrics
   - Higher is better: accuracy, precision, recall, efficiency

3. **Threshold Checking**
   - Calculates percentage change: `(current - baseline) / |baseline| * 100`
   - Flags regression if: NOT improvement AND |change| > threshold
   - Example: accuracy drops from 0.90 to 0.84 = -6.67% → REGRESSION

4. **Output Format**
   - **text**: Human-readable CLI output with colors
   - **json**: Structured JSON for programmatic consumption
   - **github**: Markdown formatted for GitHub PR comments

### Pull Request Workflow

**Workflow File:** `.github/workflows/benchmarks-pr.yml`

**Triggers:**
- Pull requests targeting main/master branches
- Only on changes to: `app/**`, `tests/**`, `config/**`, `database/**`, `.github/workflows/benchmarks-pr.yml`

**Quick Benchmarks** (subset of 5 critical ones):
1. CitationAccuracyBenchmark
2. FalsePositiveRateBenchmark
3. FalseNegativeRateBenchmark
4. AdmissibilityAccuracyBenchmark
5. CostEffectivenessBenchmark

**What Happens:**

1. Sets up PHP 8.2 with PostgreSQL service container
2. Installs dependencies and runs migrations
3. Gets baseline commit (target branch HEAD)
4. Runs quick benchmark suite
5. Checks for regressions with 5% threshold
6. Posts/updates PR comment with results
7. **Fails CI if regression detected**

**PR Comment Format:**

```markdown
## Benchmark Regression Check

**Threshold:** 5%

### ❌ Regressions Detected (2)

| Metric | Baseline | Current | Change |
|--------|----------|---------|--------|
| CitationAccuracyBenchmark: accuracy | 0.9200 | 0.8500 | -7.61% ⚠️ |
| CostEffectivenessBenchmark: avg_cost_per_operation | $0.0450 | $0.0520 | +15.56% ⚠️ |

### ✅ No Regressions (3)

| Benchmark | Status |
|-----------|--------|
| FalsePositiveRateBenchmark | All metrics within threshold |
| FalseNegativeRateBenchmark | All metrics within threshold |
| AdmissibilityAccuracyBenchmark | All metrics within threshold |

---
**Baseline:** `abc1234` (main)
**Current:** `def5678` (feature-branch)
```

**Concurrency:** Cancels previous runs on new commits to same PR

### Main Branch Workflow

**Workflow File:** `.github/workflows/benchmarks-main.yml`

**Triggers:**
- Push to main/master branches
- Weekly schedule: Sundays at 00:00 UTC
- Manual trigger via `workflow_dispatch`

**What Happens:**

1. Runs all 15 benchmarks (~5-10 minutes)
2. Generates reports (JSON + table format)
3. Uploads results as artifacts (retention: 90 days)
4. Checks for regressions against previous commit
5. **Creates GitHub issue if regression detected**
6. Adds summary to GitHub Actions output

**Issue Creation:**

If regression detected on main branch, automatically creates issue:

```markdown
## ⚠️ Benchmark Regression Detected

Commit: `abc1234`
Branch: `main`

### Regressions

[Regression details from benchmark-results.md]

---
This issue was automatically created by the benchmark regression workflow.

Labels: performance, regression, automated
```

**Artifact Storage:**

Download artifacts from workflow run:
```bash
gh run download <run-id> -n benchmark-results-<commit-sha>
```

**Concurrency:** Does NOT cancel previous runs (baseline data is valuable)

### Configuring Regression Threshold

**Default:** 5% (0.05)

**Adjust in workflow files:**

```yaml
# .github/workflows/benchmarks-pr.yml
- name: Check for regressions
  run: |
    php artisan benchmark:check-regression \
      ${{ steps.baseline.outputs.commit }} \
      --threshold=0.02  # Stricter: 2%
```

**Per-metric thresholds** (requires custom implementation):

```php
// In specific benchmark class
protected function getRegressionThreshold(string $metricKey): float
{
    return match ($metricKey) {
        'accuracy', 'precision', 'recall' => 0.03,  // 3% for critical metrics
        'avg_cost_per_operation' => 0.10,           // 10% for cost metrics
        default => 0.05,                            // 5% default
    };
}
```

### Local Testing

Test regression detection locally before pushing:

```bash
# 1. Establish baseline (before changes)
git checkout main
php artisan benchmark:run all

# 2. Switch to feature branch
git checkout feature-branch

# 3. Make changes...

# 4. Run benchmarks and check regression
php artisan benchmark:run all
php artisan benchmark:check-regression main --threshold=0.05

# 5. View detailed report
php artisan benchmark:report --compare
```

### Interpreting CI Results

**✅ Green (Passing):**
- No regressions detected
- All metrics within threshold
- Performance maintained or improved

**❌ Red (Failing):**
- One or more regressions detected
- Performance degraded beyond threshold
- **Action Required:** Optimize or justify regression

**⚠️ Warning (Passing with notes):**
- Metrics changed but within threshold
- Review changes in PR comment
- Consider if trend is acceptable

### Troubleshooting CI Failures

#### "No baseline found for commit"

**Cause:** Baseline commit has no benchmark runs in database

**Fix:**
```bash
# Run benchmarks on baseline commit first
git checkout <baseline-commit>
php artisan benchmark:run all
git checkout <feature-branch>
```

Or adjust comparison target:
```bash
# Use older commit with benchmark data
git log --all --oneline | grep "benchmark"
php artisan benchmark:check-regression <older-commit>
```

#### "Database connection failed"

**Cause:** PostgreSQL service not ready or credentials incorrect

**Fix in workflow:**
```yaml
services:
  postgres:
    options: >-
      --health-cmd pg_isready
      --health-interval 10s
      --health-timeout 5s
      --health-retries 5  # Increase retries
```

#### "Benchmark timeout"

**Cause:** Benchmark taking too long

**Fix:** Increase workflow timeout:
```yaml
jobs:
  quick-benchmarks:
    timeout-minutes: 20  # Increase from 15
```

Or optimize benchmark configuration:
```php
protected function getDefaultConfig(): array
{
    return [
        'sample_size' => 50,  // Reduce from 100
        // ...
    ];
}
```

#### "Migration failed"

**Cause:** Test database schema out of sync

**Fix:** Ensure migrations run:
```yaml
- name: Setup test database
  run: |
    php artisan migrate --force --env=testing
    php artisan db:seed --class=BenchmarkSeeder --env=testing  # If needed
```

#### "Regression on legitimate improvement"

**Cause:** Metric direction incorrectly defined

**Fix in benchmark class:**
```php
protected function isImprovement(string $metricKey, float $diff): bool
{
    $lowerIsBetter = ['latency_ms', 'error_rate', 'cost_usd'];

    if (in_array($metricKey, $lowerIsBetter)) {
        return $diff < 0;  // Decrease is improvement
    }

    return $diff > 0;  // Increase is improvement
}
```

### Best Practices

**1. Establish Baselines Early**
- Run benchmarks on main branch regularly
- Don't wait for PR to establish first baseline
- Use scheduled runs (weekly) to maintain baseline data

**2. Run Locally Before Pushing**
- Check for regressions locally: `php artisan benchmark:check-regression main`
- Fix regressions before creating PR
- Saves CI time and reviewer attention

**3. Document Intentional Regressions**
- If regression is unavoidable (e.g., new feature adds latency)
- Add comment to PR explaining tradeoff
- Consider adjusting threshold temporarily

**4. Monitor Trends Over Time**
- Download artifact history
- Plot metrics over time: `php artisan benchmark:report --format=csv`
- Identify gradual degradation

**5. Use Fail-Fast for Quick Feedback**
```bash
php artisan benchmark:check-regression main --fail-fast
```

**6. Selective Benchmarking**
- For small changes, run affected benchmarks only:
```bash
php artisan benchmark:check-regression main \
  --benchmarks=CitationAccuracyBenchmark \
  --benchmarks=LawSearchPrecisionBenchmark
```

### Manual Workflow Triggers

Trigger workflows manually from GitHub UI:
1. Go to Actions tab
2. Select workflow (Benchmark Regression Check or Full Benchmark Suite)
3. Click "Run workflow"
4. Select branch
5. Click green "Run workflow" button

Or via GitHub CLI:
```bash
# Trigger PR benchmarks
gh workflow run benchmarks-pr.yml --ref feature-branch

# Trigger full suite
gh workflow run benchmarks-main.yml --ref main
```

### Artifact Download

Download benchmark results:

```bash
# List recent workflow runs
gh run list --workflow=benchmarks-main.yml

# Download artifacts from specific run
gh run download <run-id>

# View artifact contents
cat benchmark-report.json | jq '.benchmarks[] | {name, metrics}'
```

### Quality Gates for Release

Enforce quality gates before release:

```bash
# In release pipeline
RELEASE_COMMIT=$(git rev-parse HEAD)

# Run all benchmarks
php artisan benchmark:run all

# Check against previous release
PREV_RELEASE=$(git describe --tags --abbrev=0 HEAD^)
php artisan benchmark:check-regression $PREV_RELEASE --threshold=0.03

# Fail release if regression
if [ $? -ne 0 ]; then
  echo "❌ Release blocked: Performance regression detected"
  exit 1
fi
```

## Troubleshooting

### "No benchmarks found"
- Ensure benchmark class extends `BaseBenchmark`
- Check class namespace is `App\\Benchmarks`
- Verify file is in `app/Benchmarks/` directory

### "Undefined array key" errors
- Benchmark is missing `getDefaultConfig()` implementation
- Or `execute()` is accessing undefined config keys
- Check that config keys match between `getDefaultConfig()` and `execute()`

### Slow benchmark execution
- Reduce `sample_size` in configuration
- Use smaller test datasets
- Run specific benchmarks instead of all
- Check for database query inefficiencies

### Database connection errors
- Ensure database is running and accessible
- Check `.env` database credentials
- For tests, ensure test database exists

## Performance Optimization

### Benchmark Execution Time

Target: All 15 benchmarks in < 10 minutes

Current optimizations:
- Heuristic-based scoring (no API calls for benchmarks)
- Cached database queries where appropriate
- Parallel-safe execution (can run multiple benchmarks concurrently)
- Minimal test case counts (3-5 per benchmark)

### Database Performance

- Benchmarks use database transactions (auto-rollback in tests)
- Indexes on `benchmark_runs` table for fast queries
- JSON metrics column for flexible schema
- Partitioning by date for long-term storage

## See Also

- [Testing Documentation](TESTING.md) - Test infrastructure
- [API Documentation](API_DOCUMENTATION.md) - API endpoints
- [CLAUDE.md](../CLAUDE.md) - Project overview and commands
