# Agent Validation Test Data Design

**Date:** 2025-11-10
**Sprint:** 1.5
**Story:** Test Data for Agent Validation
**Owner:** AI/ML + Backend

## Overview

This design specifies the structure, format, and labeling criteria for 45 labeled test cases used to validate the accuracy of three specialist agents: PrecedentAnalystAgent, RiskAnalystAgent, and StrategySpecialistAgent.

## Goals

- Create 45 total labeled test cases across three agents
- Each test case includes full scenario (inputs + expected outputs)
- JSON format for easy programmatic loading
- Clear, objective labeling criteria
- Realistic Croatian legal scenarios
- Integration with future benchmark infrastructure

## Directory Structure

```
tests/Fixtures/AgentValidation/
├── README.md (labeling criteria)
├── PrecedentAnalyst/
│   ├── high-applicability/ (10 cases, score 80-100)
│   ├── medium-applicability/ (5 cases, score 50-79)
│   └── low-applicability/ (5 cases, score 0-49)
├── RiskAnalyst/
│   ├── high-risk/ (5 cases, score 75-100)
│   ├── medium-risk/ (5 cases, score 50-74)
│   └── low-risk/ (5 cases, score 0-49)
└── StrategySpecialist/
    └── scenarios/ (10 diverse cases)
```

**Total:** 45 test cases
- PrecedentAnalyst: 20 cases
- RiskAnalyst: 15 cases
- StrategySpecialist: 10 cases

## JSON Schema Specifications

### PrecedentAnalystAgent Test Case

```json
{
  "test_id": "precedent_high_001",
  "category": "high-applicability",
  "expected_score_range": [80, 100],
  "description": "Supreme Court ruling on proportionality of home search",

  "input": {
    "problem_statement": "Client charged with drug possession after home search. Question: Was the search warrant proportionate to the suspected offense?",
    "researched_decisions": [
      {
        "ecli": "HR:VSRH:2023:1234",
        "court": "Vrhovni sud Republike Hrvatske",
        "case_number": "I Kž-123/2023",
        "decision_date": "2023-05-15",
        "title": "Proportionality assessment in drug possession searches",
        "summary": "Court ruled that extensive home search for minor drug offense violated proportionality principle..."
      }
    ],
    "researched_laws": [
      {
        "law_number": "ZKP Članak 197",
        "title": "Pretres stana",
        "content": "Pretres stana može se odrediti samo...",
        "similarity": 0.89
      }
    ]
  },

  "expected_output": {
    "applicability_score": 92,
    "binding_authority": "binding",
    "key_factors": ["proportionality principle", "minor offense", "extensive search"],
    "favorable": true,
    "reasoning": "Supreme Court binding precedent directly addressing proportionality in similar drug search case"
  },

  "validation_notes": "Should score 90+ due to Supreme Court binding authority + high factual similarity"
}
```

### RiskAnalystAgent Test Case

```json
{
  "test_id": "risk_high_001",
  "category": "high-risk",
  "expected_risk_level": "high",
  "expected_score_range": [75, 100],
  "description": "Multiple argument weaknesses with adverse binding precedent",

  "input": {
    "problem_statement": "Client charged with robbery, prosecution has video evidence and eyewitness testimony",
    "legal_arguments": [
      {
        "title": "Chain of custody broken",
        "argument": "Video evidence shows gaps in chain of custody",
        "strength_score": 45,
        "legal_basis": ["ZKP Članak 289"],
        "potential_counterarguments": ["Evidence log shows continuous custody"]
      }
    ],
    "strongest_precedents": [],
    "strategic_recommendations": {
      "procedural_steps": ["Challenge video admissibility", "Depose witnesses"]
    }
  },

  "expected_output": {
    "overall_risk_score": {
      "score": 82,
      "level": "high"
    },
    "argument_risks": [
      {
        "risk_level": "high",
        "impact_if_fails": "high",
        "weaknesses": ["Evidence log appears complete", "Video shows clear continuous possession"]
      }
    ],
    "procedural_risks": [
      {
        "severity": "medium",
        "probability": "high"
      }
    ]
  },

  "validation_notes": "High risk due to weak arguments + strong prosecution evidence"
}
```

### StrategySpecialistAgent Test Case

```json
{
  "test_id": "strategy_001",
  "description": "Multiple strong precedents supporting defense",

  "input": {
    "problem_statement": "Client charged with assault, claims self-defense",
    "strongest_precedents": [
      {
        "court": "Vrhovni sud RH",
        "applicability_score": 88,
        "binding_authority": "binding",
        "case_number": "I Kž-456/2022"
      }
    ],
    "analyzed_laws": [
      {
        "law_number": "KZ Članak 18",
        "title": "Nužna obrana",
        "relevance_score": 0.92
      }
    ]
  },

  "expected_output": {
    "arguments_count_range": [3, 5],
    "primary_strategy_theme": "self-defense",
    "success_probability_range": [0.65, 0.85],
    "should_include": {
      "settlement_recommendation": true,
      "procedural_steps": ["gather witness statements", "file self-defense motion"],
      "evidence_needed": ["medical records", "witness testimony"]
    }
  },

  "validation_notes": "Strong precedents should yield 3-5 arguments with 65-85% success probability"
}
```

## Labeling Criteria

### PrecedentAnalystAgent

**High Applicability (80-100):**
- Binding authority (Supreme Court, Constitutional Court)
- Direct factual similarity to current case (80%+ overlap)
- Same legal principles/statutes involved
- Recent decision (within 5 years preferred)
- Favorable outcome for defense position
- Clear reasoning applicable to current case

**Medium Applicability (50-79):**
- Persuasive authority (lower courts) OR
- Partial factual similarity (50-79% overlap) OR
- Related but not identical legal principles
- May be older precedent (5-10 years)
- Mixed favorability or distinguishable facts
- Reasoning requires adaptation

**Low Applicability (0-49):**
- Informative only (foreign courts, legal scholarship)
- Minimal factual similarity (<50%)
- Different legal area but tangentially related
- Outdated or superseded by later rulings
- Unfavorable precedent easily distinguished
- Reasoning not directly transferable

### RiskAnalystAgent

**High Risk (75-100):**
- 3+ critical argument weaknesses OR
- Binding adverse precedent exists OR
- Critical procedural defect (jurisdiction, statute of limitations) OR
- Strong opposing evidence with weak defense evidence
- High probability of adverse outcome (>60%)

**Medium Risk (50-74):**
- 1-2 significant argument weaknesses OR
- Persuasive adverse precedent exists OR
- Moderate procedural issues (timeline concerns, standing questions)
- Balanced evidence on both sides
- Uncertain outcome (40-60% probability)

**Low Risk (0-49):**
- Minor argument weaknesses only OR
- No significant adverse precedents OR
- Procedural issues unlikely or easily resolved
- Strong defense evidence, weak prosecution case
- Favorable outcome likely (>60% probability)

### StrategySpecialistAgent

**Diverse Scenarios (10 cases covering):**
- Strong defense position (3 cases) - multiple binding precedents, clear legal basis
- Moderate position (4 cases) - mixed precedents, some legal support
- Weak position (3 cases) - limited precedents, challenging facts
- Varying legal areas: drug offenses, property crimes, assault, procedural violations
- Different strategic approaches: motion to dismiss, suppression, self-defense, proportionality

**Quality Indicators:**
- Arguments should number 3-5 for most cases
- Success probability should correlate with precedent strength
- Settlement recommendations appropriate to case strength
- Procedural steps realistic and specific to Croatian law
- Evidence needs clearly tied to legal strategy

## Test Data Scenarios

### PrecedentAnalyst High-Applicability (10 cases)

1. Supreme Court proportionality ruling in drug search
2. Constitutional Court privacy violation in surveillance
3. Supreme Court evidence exclusion for illegal detention
4. Supreme Court Miranda rights violation
5. Constitutional Court fair trial violation
6. Supreme Court prosecutorial misconduct precedent
7. Supreme Court entrapment defense
8. Constitutional Court due process violation
9. Supreme Court self-defense justification
10. Supreme Court excessive force in arrest

### PrecedentAnalyst Medium-Applicability (5 cases)

1. County court drug possession with partial similarity
2. Appellate court search warrant with different facts
3. Lower court evidence suppression (older precedent)
4. County court procedural violation (mixed outcome)
5. Appellate court chain of custody (distinguishable facts)

### PrecedentAnalyst Low-Applicability (5 cases)

1. Foreign court precedent (EU jurisdiction)
2. Academic legal commentary (not binding)
3. Outdated precedent (superseded by new law)
4. Different legal area (civil case in criminal context)
5. Unfavorable precedent easily distinguished

### RiskAnalyst High-Risk (5 cases)

1. Multiple argument weaknesses + strong prosecution evidence
2. Binding adverse precedent + weak defense theory
3. Critical procedural defect (statute of limitations expired)
4. Video evidence contradicting defense claims
5. Multiple eyewitnesses + forensic evidence

### RiskAnalyst Medium-Risk (5 cases)

1. One significant argument weakness + balanced evidence
2. Persuasive adverse precedent (distinguishable)
3. Timeline concerns but likely manageable
4. Mixed witness testimony
5. Evidence gaps on both sides

### RiskAnalyst Low-Risk (5 cases)

1. Strong defense evidence + weak prosecution case
2. No adverse precedents + favorable binding authority
3. Procedural violation by prosecution
4. Clear proportionality violation
5. Strong alibi + weak identification

### StrategySpecialist (10 cases)

1. Strong position: Self-defense with binding precedents (3 strong)
2. Strong position: Proportionality violation with Constitutional Court support
3. Strong position: Evidence suppression with Supreme Court backing
4. Moderate position: Drug possession with mixed precedents (4 moderate)
5. Moderate position: Assault with partial self-defense claim
6. Moderate position: Property crime with some procedural issues
7. Moderate position: Theft with contested facts
8. Weak position: Multiple charges with adverse precedents (3 weak)
9. Weak position: Strong prosecution evidence, limited defense
10. Weak position: Procedural compliance, challenging facts

## Implementation Notes

- Each JSON file is standalone and independently loadable
- Use realistic Croatian case numbers, court names, and legal citations
- Include enough detail for meaningful agent testing
- Expected outputs should be specific enough to validate agent accuracy
- Validation notes explain the reasoning behind expected scores

## Future Integration

These test cases will integrate with:
- Benchmark infrastructure (Sprint 2.5)
- Agent performance dashboards
- Continuous validation pipeline
- A/B testing of agent improvements
