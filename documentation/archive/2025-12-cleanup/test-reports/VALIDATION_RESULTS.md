# Agent Validation Results

**Date**: 2025-11-11
**Sprint**: 6.3 - Agent Validation with Ground Truth
**Status**: Validation Framework Complete

## Executive Summary

This document outlines the agent validation methodology and framework for measuring the accuracy of three specialist agents against ground truth test data:

- **PrecedentAnalystAgent** - Analyzes applicability and authority of legal precedents
- **RiskAnalystAgent** - Identifies risks, weaknesses, and challenges in legal strategies
- **StrategySpecialistAgent** - Develops legal strategies and estimates success probabilities

## Test Data Overview

### Test Data Location
- **Path**: `tests/Fixtures/AgentValidation/`
- **Total Test Cases**: 45
- **Format**: JSON with input data and expected outputs

### Test Distribution

| Agent | Test Cases | Categories |
|-------|------------|------------|
| **PrecedentAnalyst** | 20 | High (10), Medium (5), Low (5) applicability |
| **RiskAnalyst** | 15 | High (5), Medium (5), Low (5) risk |
| **StrategySpecialist** | 10 | Various strategic scenarios |

## Validation Methodology

### 1. PrecedentAnalystAgent Validation

**Metrics Measured**:
- Applicability Score Accuracy (0-100 scale)
- Binding Authority Classification (binding/persuasive/informative)
- Favorable/Unfavorable Determination

**Accuracy Calculation**:
```
score_accuracy = 1 - (|actual_score - expected_score| / 100)
authority_accuracy = 1.0 if match else 0.0
favorable_accuracy = 1.0 if match else 0.0

overall_accuracy = average(score_accuracy, authority_accuracy, favorable_accuracy)
```

**Pass Criteria**: ≥80% overall accuracy per test case

**Example Test Case Analysis**:

**Test**: `precedent_high_001`
**Scenario**: Supreme Court ruling on proportionality of home search in minor drug offense

**Input**:
- Problem: Client charged with 2g marijuana possession after 4-hour home search
- Precedent: VSRH ruling that 4-hour search for small drug amount is disproportionate
- Law: ZKP 197 (proportionality), Ustav RH 34 (home inviolability)

**Expected Output**:
- Applicability Score: 92/100
- Binding Authority: "binding"
- Favorable: true
- Key Factors: proportionality principle, minor offense, excessive search, evidence exclusion

**Validation Criteria**:
- Score within 85-95 range (high applicability category)
- Correctly identifies Supreme Court as binding authority
- Correctly determines favorable outcome for defense
- Identifies proportionality and excessive search as key factors

### 2. RiskAnalystAgent Validation

**Metrics Measured**:
- Overall Risk Score (0-100 scale)
- Risk Level Classification (low/medium/high/critical)
- Argument Risk Identification
- Adverse Precedent Recognition

**Accuracy Calculation**:
```
level_accuracy = 1.0 if risk_level matches else 0.0
score_accuracy = 1.0 if within expected_range else scaled_penalty

overall_accuracy = average(level_accuracy, score_accuracy)
```

**Pass Criteria**: ≥80% overall accuracy per test case

**Example Test Case Analysis**:

**Test**: `risk_high_001`
**Scenario**: Armed robbery with overwhelming prosecution evidence

**Input**:
- Problem: Armed robbery charge with video, 3 eyewitnesses, fingerprints
- Arguments: Weak alibi (no witnesses), questionable video quality
- Evidence: Strong prosecution case, weak defense

**Expected Output**:
- Risk Score: 88/100 (range: 80-95)
- Risk Level: "high"
- Critical Weaknesses: Uncorroborated alibi, contradicted by physical evidence
- Adverse Factors: Courts accept video + eyewitness combination

**Validation Criteria**:
- Score within 80-95 range (high risk category)
- Correctly classifies as "high" risk level
- Identifies multiple critical argument weaknesses
- Recognizes overwhelming evidence against defense

### 3. StrategySpecialistAgent Validation

**Metrics Measured**:
- Success Probability Accuracy (0-1 scale)
- Argument Count and Quality
- Strategic Recommendation Appropriateness

**Accuracy Calculation**:
```
probability_accuracy = 1 - |actual_prob - expected_prob|
argument_count_accuracy = 1.0 if count >= min_expected else scaled

overall_accuracy = average(probability_accuracy, argument_count_accuracy)
```

**Pass Criteria**: ≥80% overall accuracy per test case

**Example Test Case Analysis**:

**Test**: `strategy_strong_001`
**Scenario**: Self-defense case with strong binding precedents

**Input**:
- Problem: Assault charge after defending against knife attack with witnesses
- Precedents: 2 Supreme Court binding decisions (88, 85 applicability scores)
- Laws: KZ Article 18 (self-defense), KZ Article 19 (excessive defense)

**Expected Output**:
- Success Probability: 0.70-0.85 (strong position)
- Arguments: 4-5 strong arguments (avg strength 75-90)
- Strategy: Aggressive defense, not recommend settlement
- Evidence Needed: Witness statements, medical records, weapon photos

**Validation Criteria**:
- Success probability within 70-85% range
- Generates 4-5 arguments with average strength 75-90
- Correctly recommends against settlement (strong position)
- Identifies appropriate evidence gathering needs

## Validation Command Usage

### Running Validation

```bash
# Validate all agents
php artisan agents:validate all

# Validate specific agent
php artisan agents:validate precedent
php artisan agents:validate risk
php artisan agents:validate strategy

# Save results to file
php artisan agents:validate all --save

# Output JSON format
php artisan agents:validate all --format=json
```

### Output Example

```
🧪 Agent Validation Suite
========================

Testing PrecedentAnalyst...
  Found 20 test cases
  Test Cases: 20
  Passed: 18 / Failed: 2
  Pass Rate: 90%
  Avg Accuracy: 87.5%

Testing RiskAnalyst...
  Found 15 test cases
  Test Cases: 15
  Passed: 13 / Failed: 2
  Pass Rate: 86.67%
  Avg Accuracy: 84.2%

Testing StrategySpecialist...
  Found 10 test cases
  Test Cases: 10
  Passed: 8 / Failed: 2
  Pass Rate: 80%
  Avg Accuracy: 81.3%

Overall Summary
==============
Total Tests: 45
Total Passed: 39
Overall Pass Rate: 86.67%
Overall Accuracy: 84.33%

⚠️  Overall accuracy below 90% - review needed
   Action plan should be created to improve accuracy
```

## Current Validation Status

**Status**: Framework complete, awaiting API key configuration for execution

**Blockers**:
- OpenAI API key not configured in `.env`
- Agents require GPT-4o-mini API access for LLM-based analysis

**Next Steps**:
1. Configure `OPENAI_API_KEY` in environment
2. Run validation command: `php artisan agents:validate all --save`
3. Analyze results and implement improvements based on accuracy scores

## Projected Performance

Based on agent implementation analysis and test case review:

### Expected Accuracy Ranges

| Agent | Expected Accuracy | Confidence Level |
|-------|-------------------|------------------|
| **PrecedentAnalyst** | 85-92% | High |
| **RiskAnalyst** | 80-88% | Medium |
| **StrategySpecialist** | 78-85% | Medium |

### Rationale

**PrecedentAnalyst** (High Confidence 85-92%):
- ✅ Structured scoring with clear criteria (applicability, authority, favorable status)
- ✅ LLM excels at legal text analysis and precedent comparison
- ✅ Binary classifications (binding/persuasive) are straightforward
- ⚠️ Score calibration may vary ±10 points from expected values

**RiskAnalyst** (Medium Confidence 80-88%):
- ✅ Risk level classification clear for extreme cases (very low/very high)
- ✅ LLM good at identifying weaknesses in legal arguments
- ⚠️ Medium-risk cases harder to distinguish from high/low
- ⚠️ Score calibration dependent on subjective risk assessment

**StrategySpecialist** (Medium Confidence 78-85%):
- ✅ Strong at generating creative legal arguments
- ✅ Can assess precedent strength and legal basis
- ⚠️ Success probability estimation inherently uncertain
- ⚠️ Strategy recommendations subjective (settlement vs. trial)
- ⚠️ Argument strength scores may vary ±15 points from expected

## Action Plan for Accuracy <90%

### Phase 1: Diagnosis (If Accuracy <90%)

**1.1 Identify Failing Test Categories**
- Run validation with detailed output: `php artisan agents:validate all --save`
- Analyze which test categories have lowest accuracy:
  - PrecedentAnalyst: high vs. medium vs. low applicability
  - RiskAnalyst: high vs. medium vs. low risk
  - StrategySpecialist: strong vs. weak positions
- Group failures by pattern (e.g., "consistently underestimates risk in medium-risk scenarios")

**1.2 Review Individual Failures**
- For each failed test case, compare:
  - Agent output vs. expected output
  - Identify specific metric causing failure (score off by >20, wrong classification, etc.)
- Categorize failure types:
  - **Calibration errors**: Scores consistently too high/low
  - **Classification errors**: Wrong category (high vs. medium)
  - **Missing analysis**: Failed to identify key factors
  - **Hallucination**: Invented facts not in input data

**1.3 Analyze Root Causes**
- **Prompt issues**: Unclear instructions, ambiguous criteria
- **Model limitations**: GPT-4o-mini may lack legal reasoning depth
- **Context issues**: Missing or poorly formatted input data
- **Scoring logic**: Thresholds (binding=3x, persuasive=2x) may be miscalibrated

### Phase 2: Targeted Improvements

**2.1 Prompt Engineering Improvements**

If failures due to unclear instructions or missing analysis:

```php
// BEFORE (vague)
"Analyze these precedents for applicability"

// AFTER (specific with examples)
"For each precedent, determine applicability using this rubric:
90-100: Near-identical facts, binding authority, recent
80-89: Similar facts, binding authority OR identical facts, persuasive
70-79: Related facts, binding OR similar facts, persuasive
50-69: Tangentially related
0-49: Different facts or legal area

Example: Supreme Court case with identical fact pattern (minor drug possession
+ excessive search) = 95 (near-identical + binding + recent)"
```

**2.2 Scoring Calibration**

If score accuracy <75%:

```php
// Add calibration examples to prompt
$calibrationExamples = [
    ['facts' => 'identical', 'authority' => 'binding', 'score' => 90],
    ['facts' => 'similar', 'authority' => 'binding', 'score' => 80],
    ['facts' => 'similar', 'authority' => 'persuasive', 'score' => 70],
    // ...
];

$prompt .= "\n\nCalibration Examples:\n";
foreach ($calibrationExamples as $ex) {
    $prompt .= "- {$ex['facts']} facts + {$ex['authority']} = {$ex['score']}\n";
}
```

**2.3 Model Upgrade**

If GPT-4o-mini consistently fails complex legal reasoning:

```php
// Try GPT-4o for complex analysis
protected function analyzeComplexCase($data) {
    return $this->openai->chat([
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt],
    ], 'gpt-4o', [  // Upgrade from gpt-4o-mini
        'temperature' => 0.2,  // Lower temp for consistency
    ]);
}
```

**2.4 Multi-Step Reasoning**

If missing key analysis steps:

```php
// BEFORE (single prompt)
$result = $this->analyzeDecisions($decisions);

// AFTER (chain of thought)
$step1 = $this->identifyKeyFacts($decisions);
$step2 = $this->compareFactsToCase($step1, $problem);
$step3 = $this->assessAuthority($step2);
$result = $this->calculateFinalScore($step3);
```

**2.5 Confidence Scoring**

Add confidence intervals to handle uncertainty:

```php
return [
    'applicability_score' => 85,
    'confidence' => 0.75,  // 75% confident in this assessment
    'score_range' => [80, 90],  // Confidence interval
];

// In validation, allow ±10 points if confidence <0.80
```

### Phase 3: Validation and Iteration

**3.1 Re-run Validation**
```bash
php artisan agents:validate all --save
```

**3.2 Compare Results**
- Accuracy improvement by agent and category
- Identify remaining problem areas
- Cost impact (token usage if upgraded to GPT-4o)

**3.3 Iterate Until ≥90%**
- Repeat Phase 1-2 for remaining failures
- Focus on highest-impact improvements first
- Document all changes in `docs/agent-improvements.md`

### Phase 4: Performance Optimization

Once accuracy ≥90%:

**4.1 Cost Optimization**
- Measure token usage per agent execution
- Identify opportunities to use GPT-4o-mini for simple cases, GPT-4o for complex
- Cache common analysis patterns

**4.2 Latency Optimization**
- Batch precedent analysis (current: 5 at a time, could increase)
- Parallel agent execution where possible
- Cache law article analysis

**4.3 Continuous Monitoring**
- Add validation to CI/CD pipeline
- Alert if accuracy drops below 90% threshold
- Monthly re-validation against updated test cases

## Success Criteria

✅ **Validation Framework Complete**:
- Command created: `php artisan agents:validate`
- Test data loaded: 45 cases across 3 agents
- Accuracy calculation logic implemented
- Results saving and JSON output supported

🔄 **Pending Execution** (requires API key):
- Run validation against all 45 test cases
- Generate accuracy report with detailed breakdowns
- Identify specific failure patterns

📋 **Action Plan Ready**:
- 4-phase improvement methodology documented
- Specific fixes for common failure types
- Cost/performance optimization strategies

## Next Steps

1. **Configure API Key** (Immediate)
   ```bash
   echo "OPENAI_API_KEY=sk-..." >> .env
   ```

2. **Run Initial Validation** (1-2 hours)
   ```bash
   php artisan agents:validate all --save
   cat storage/app/validation/agent_validation_*.json
   ```

3. **Analyze Results** (2-4 hours)
   - Review failed test cases
   - Categorize failure types
   - Prioritize improvements

4. **Implement Phase 2 Improvements** (8-16 hours)
   - Prompt engineering enhancements
   - Scoring calibration
   - Model upgrades if needed

5. **Iterate to ≥90% Accuracy** (variable)
   - Re-run validation after each improvement
   - Track accuracy progression
   - Document lessons learned

## References

- Test Data: `tests/Fixtures/AgentValidation/`
- Agent Implementations:
  - `app/Agents/Specialists/PrecedentAnalystAgent.php`
  - `app/Agents/Specialists/RiskAnalystAgent.php`
  - `app/Agents/Specialists/StrategySpecialistAgent.php`
- Validation Command: `app/Console/Commands/ValidateAgentsCommand.php`
- Sprint Plan: `docs/SPRINT_PLAN.md` (Sprint 6.3)
