# Agent Validation Test Data

This directory contains labeled test cases for validating the accuracy of specialist agents.

## Overview

- **Total Test Cases:** 45
- **PrecedentAnalystAgent:** 20 cases (10 high, 5 medium, 5 low applicability)
- **RiskAnalystAgent:** 15 cases (5 high, 5 medium, 5 low risk)
- **StrategySpecialistAgent:** 10 diverse cases

## Directory Structure

```
AgentValidation/
├── PrecedentAnalyst/
│   ├── high-applicability/    # 10 cases, score 80-100
│   ├── medium-applicability/  # 5 cases, score 50-79
│   └── low-applicability/     # 5 cases, score 0-49
├── RiskAnalyst/
│   ├── high-risk/             # 5 cases, score 75-100
│   ├── medium-risk/           # 5 cases, score 50-74
│   └── low-risk/              # 5 cases, score 0-49
└── StrategySpecialist/
    └── scenarios/             # 10 diverse cases
```

## Labeling Criteria

### PrecedentAnalystAgent

#### High Applicability (80-100)
- Binding authority (Supreme Court, Constitutional Court)
- Direct factual similarity to current case (80%+ overlap)
- Same legal principles/statutes involved
- Recent decision (within 5 years preferred)
- Favorable outcome for defense position
- Clear reasoning applicable to current case

#### Medium Applicability (50-79)
- Persuasive authority (lower courts) OR
- Partial factual similarity (50-79% overlap) OR
- Related but not identical legal principles
- May be older precedent (5-10 years)
- Mixed favorability or distinguishable facts
- Reasoning requires adaptation

#### Low Applicability (0-49)
- Informative only (foreign courts, legal scholarship)
- Minimal factual similarity (<50%)
- Different legal area but tangentially related
- Outdated or superseded by later rulings
- Unfavorable precedent easily distinguished
- Reasoning not directly transferable

### RiskAnalystAgent

#### High Risk (75-100)
- 3+ critical argument weaknesses OR
- Binding adverse precedent exists OR
- Critical procedural defect (jurisdiction, statute of limitations) OR
- Strong opposing evidence with weak defense evidence
- High probability of adverse outcome (>60%)

#### Medium Risk (50-74)
- 1-2 significant argument weaknesses OR
- Persuasive adverse precedent exists OR
- Moderate procedural issues (timeline concerns, standing questions)
- Balanced evidence on both sides
- Uncertain outcome (40-60% probability)

#### Low Risk (0-49)
- Minor argument weaknesses only OR
- No significant adverse precedents OR
- Procedural issues unlikely or easily resolved
- Strong defense evidence, weak prosecution case
- Favorable outcome likely (>60% probability)

### StrategySpecialistAgent

#### Diverse Scenarios Coverage
- **Strong defense position (3 cases):** Multiple binding precedents, clear legal basis
- **Moderate position (4 cases):** Mixed precedents, some legal support
- **Weak position (3 cases):** Limited precedents, challenging facts

#### Quality Indicators
- Arguments should number 3-5 for most cases
- Success probability should correlate with precedent strength
- Settlement recommendations appropriate to case strength
- Procedural steps realistic and specific to Croatian law
- Evidence needs clearly tied to legal strategy

## Test Case Format

Each test case is a JSON file containing:
- `test_id`: Unique identifier
- `category`: Classification (e.g., "high-applicability", "high-risk")
- `description`: Brief description of the scenario
- `input`: Complete input data for the agent
- `expected_output`: Expected agent output with scoring ranges
- `validation_notes`: Explanation of expected results

## Usage

Load test cases programmatically:

```php
$testCase = json_decode(
    file_get_contents('tests/Fixtures/AgentValidation/PrecedentAnalyst/high-applicability/test_001.json'),
    true
);

// Run agent with test input
$result = $agent->execute($context, $testCase['input']);

// Validate against expected output
assertBetween($testCase['expected_score_range'][0], $testCase['expected_score_range'][1], $result['applicability_score']);
```

## Maintenance

- Keep test cases up to date with Croatian legal changes
- Add new scenarios as edge cases are discovered
- Review expected outputs if agent logic changes significantly
- Document any changes to labeling criteria

## Integration with Benchmarks

These test cases integrate with the benchmark infrastructure (Sprint 2.5) to:
- Measure agent accuracy over time
- Compare performance across agent versions
- Identify areas needing improvement
- Generate performance dashboards
