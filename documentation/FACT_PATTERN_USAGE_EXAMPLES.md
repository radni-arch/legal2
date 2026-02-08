# Fact Pattern Extractor - Usage Examples

This document provides practical examples of how to use the Fact Pattern Extractor in various scenarios within the AI Legal War Machine application.

## Table of Contents

1. [API Usage Examples](#api-usage-examples)
2. [Service Integration Examples](#service-integration-examples)
3. [Real-World Workflows](#real-world-workflows)
4. [Advanced Use Cases](#advanced-use-cases)

---

## API Usage Examples

### 1. Extract Facts from Client Narrative

**Endpoint:** `POST /api/fact-patterns/extract`

```bash
curl -X POST https://your-api.com/api/fact-patterns/extract \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "narrative": "On March 15, 2024, I signed a contract with XYZ Construction to renovate my restaurant for 500,000 HRK. The work was supposed to be completed by June 1, 2024. They started work on March 20 but stopped after only 2 weeks. They claimed they needed an additional 200,000 HRK to continue. I refused to pay more than the agreed amount. Now they have abandoned the project, and I have lost 3 months of business revenue estimated at 150,000 HRK per month.",
    "save": true
  }'
```

**Response:**

```json
{
  "success": true,
  "request_id": "fact_pattern_abc-123",
  "data": {
    "id": "9c8f7e6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f",
    "user_id": 123,
    "legal_area": "contract",
    "extraction_confidence": 0.87,
    "structured_facts": {
      "parties": [
        {
          "name": "You (Restaurant Owner)",
          "role": "plaintiff",
          "type": "individual"
        },
        {
          "name": "XYZ Construction",
          "role": "defendant",
          "type": "corporation"
        }
      ],
      "events": [
        {
          "description": "Contract signed",
          "date": "2024-03-15",
          "significance": "Formation of contract"
        },
        {
          "description": "Work began",
          "date": "2024-03-20",
          "significance": "Performance started"
        },
        {
          "description": "Work stopped after 2 weeks",
          "date": "2024-04-03",
          "significance": "Breach - cessation of performance"
        }
      ],
      "legal_issues": [
        {
          "issue": "Breach of contract - failure to complete work",
          "area_of_law": "contract",
          "elements": ["Valid contract", "Breach", "Damages"],
          "potential_claims": ["Breach of contract", "Consequential damages"]
        }
      ],
      "damages_or_relief_sought": {
        "type": "monetary",
        "description": "Contract amount + lost business revenue",
        "amount": "Approximately 650,000 HRK"
      },
      "facts_favorable_to_plaintiff": [
        "Written contract with clear completion date",
        "Defendant abandoned project",
        "Documented lost revenue from business closure"
      ],
      "facts_favorable_to_defendant": [],
      "disputed_facts": [
        "Whether additional costs were justified",
        "Exact amount of lost business revenue"
      ],
      "summary": "Breach of construction contract with significant consequential damages from business interruption"
    },
    "created_at": "2024-10-29T12:00:00Z"
  }
}
```

### 2. Batch Extract Multiple Cases

**Endpoint:** `POST /api/fact-patterns/batch-extract`

```bash
curl -X POST https://your-api.com/api/fact-patterns/batch-extract \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "narratives": {
      "case_1": "My employer fired me after I complained about safety violations...",
      "case_2": "I was injured when my neighbor'\''s tree fell on my property...",
      "case_3": "The seller refused to deliver the goods I paid for in advance..."
    },
    "save": true
  }'
```

### 3. Find Similar Cases

**Endpoint:** `POST /api/fact-patterns/{id}/find-similar`

```bash
curl -X POST https://your-api.com/api/fact-patterns/9c8f7e6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f/find-similar?min_similarity=0.6&limit=5 \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response:**

```json
{
  "success": true,
  "target_pattern_id": "9c8f7e6d-5b4a-3c2d-1e0f-9a8b7c6d5e4f",
  "count": 3,
  "similar_patterns": [
    {
      "id": "8b7a6c5d-4e3f-2d1c-0b9a-8c7d6e5f4a3b",
      "legal_area": "contract",
      "confidence": 0.91,
      "similarity_score": 0.78,
      "same_legal_area": true,
      "party_overlap": 0.5,
      "legal_issue_overlap": 0.75,
      "created_at": "2024-08-15T10:30:00Z"
    }
  ]
}
```

### 4. Compare Two Fact Patterns

**Endpoint:** `POST /api/fact-patterns/compare`

```json
{
  "pattern_id_1": "uuid-1",
  "pattern_id_2": "uuid-2"
}
```

---

## Service Integration Examples

### Example 1: Complete Case Intake Workflow

```php
use App\Services\CaseIntakeService;

class IntakeController extends Controller
{
    public function processNewClient(Request $request, CaseIntakeService $intake)
    {
        $validated = $request->validate([
            'client_name' => 'required|string',
            'narrative' => 'required|string|min:100',
            'opponent_name' => 'nullable|string',
            'objectives' => 'array',
        ]);

        // Process complete intake
        $result = $intake->processIntake([
            'narrative' => $validated['narrative'],
            'client_name' => $validated['client_name'],
            'opponent_name' => $validated['opponent_name'] ?? null,
            'objectives' => $validated['objectives'] ?? ['Favorable settlement'],
            'user_id' => auth()->id(),
        ]);

        return response()->json($result);
    }
}
```

**What This Does:**

1. Extracts fact pattern from narrative
2. Creates case record in database
3. Finds similar cases from your history
4. Searches for relevant court decisions
5. Assesses risks
6. Predicts outcome
7. Generates preliminary strategy
8. Identifies next steps

**Example Response:**

```json
{
  "success": true,
  "case_id": "01HKJM...",
  "fact_pattern_id": "9c8f7e6d...",
  "analysis": {
    "legal_area": "contract",
    "extraction_confidence": 0.87,
    "parties": [...],
    "legal_issues": [...],
    "similar_cases_count": 3,
    "risk_assessment": {
      "overall_risk_level": "medium",
      "risk_factors": [
        {
          "type": "evidentiary",
          "description": "Some disputed facts require discovery",
          "severity": "medium"
        }
      ],
      "strengths": [
        "Strong factual position with more favorable facts",
        "Multiple strong pieces of evidence available"
      ]
    },
    "outcome_prediction": {
      "success_probability": 0.72,
      "interpretation": "Strong likelihood of success"
    },
    "preliminary_strategy": {
      "legal_area": "contract",
      "recommended_approach": "Gather evidence, conduct legal research, attempt negotiation before filing",
      "key_arguments": [...]
    },
    "next_steps": [
      {
        "priority": "high",
        "action": "Initiate discovery process",
        "reason": "3 pieces of evidence require discovery",
        "deadline": "Within 30 days"
      }
    ]
  }
}
```

### Example 2: Precedent Finder

```php
use App\Services\LegalReasoning\FactPatternExtractor;
use App\Services\DecisionSearchService;

class PrecedentFinder
{
    public function __construct(
        protected FactPatternExtractor $extractor,
        protected DecisionSearchService $decisionSearch
    ) {}

    public function findRelevantPrecedents(string $narrative, int $limit = 10)
    {
        // Extract facts
        $result = $this->extractor->extract($narrative, auth()->id());

        if (!$result['success']) {
            throw new \Exception('Failed to extract facts');
        }

        $facts = $result['structured_facts'];
        $legalIssues = $facts['legal_issues'] ?? [];

        // Build search query from legal issues
        $queries = [];
        foreach ($legalIssues as $issue) {
            $queries[] = $issue['issue'] ?? '';
        }

        $searchQuery = implode(' OR ', $queries);

        // Search for decisions
        $decisions = $this->decisionSearch->search($searchQuery, [
            'limit' => $limit,
            'use_vector' => true,
            'jurisdiction' => 'Croatia', // Filter by jurisdiction
        ]);

        return [
            'fact_pattern' => $result,
            'precedents' => $decisions['results'],
            'search_query' => $searchQuery,
        ];
    }
}
```

### Example 3: Case Clustering by Similarity

```php
use App\Services\LegalReasoning\FactPatternExtractor;

class CaseClusteringService
{
    public function __construct(
        protected FactPatternExtractor $extractor
    ) {}

    public function clusterUserCases(int $userId, string $legalArea = null)
    {
        // Get all fact patterns
        $patterns = $legalArea
            ? $this->extractor->searchByLegalArea($legalArea, 1000)
                ->where('user_id', $userId)
            : $this->extractor->getUserFactPatterns($userId, 1000);

        // Build similarity matrix
        $clusters = [];
        $processed = [];

        foreach ($patterns as $pattern1) {
            if (in_array($pattern1->id, $processed)) continue;

            $cluster = [
                'anchor' => $pattern1,
                'similar_cases' => [],
            ];

            foreach ($patterns as $pattern2) {
                if ($pattern1->id === $pattern2->id) continue;
                if (in_array($pattern2->id, $processed)) continue;

                $similarity = $this->extractor->comparePatterns($pattern1, $pattern2);

                if ($similarity['overall_similarity'] >= 0.7) {
                    $cluster['similar_cases'][] = [
                        'pattern' => $pattern2,
                        'similarity' => $similarity['overall_similarity'],
                    ];
                    $processed[] = $pattern2->id;
                }
            }

            if (!empty($cluster['similar_cases'])) {
                $clusters[] = $cluster;
                $processed[] = $pattern1->id;
            }
        }

        return $clusters;
    }
}
```

---

## Real-World Workflows

### Workflow 1: Client Consultation Preparation

```php
class ConsultationPrepService
{
    public function prepareForConsultation(string $intakeFormText)
    {
        // 1. Extract facts from intake form
        $factPattern = $this->extractor->extract($intakeFormText, auth()->id(), [
            'save' => true
        ]);

        // 2. Find similar cases you've handled
        $similarCases = $this->findSimilarCases($factPattern);

        // 3. Find relevant precedents
        $precedents = $this->findRelevantPrecedents($factPattern);

        // 4. Generate talking points
        $talkingPoints = $this->generateTalkingPoints($factPattern, $similarCases);

        // 5. Prepare questions to ask
        $questions = $this->generateQuestions($factPattern);

        return [
            'fact_summary' => $factPattern->structured_facts,
            'legal_area' => $factPattern->legal_area,
            'similar_cases' => $similarCases,
            'relevant_precedents' => $precedents,
            'talking_points' => $talkingPoints,
            'questions_for_client' => $questions,
            'estimated_consultation_time' => $this->estimateTime($factPattern),
        ];
    }

    protected function generateQuestions(LegalFactPattern $pattern): array
    {
        $questions = [];

        // Questions based on disputed facts
        $disputedFacts = $pattern->getFact('disputed_facts', []);
        foreach ($disputedFacts as $fact) {
            $questions[] = "Can you provide more details about: {$fact}?";
        }

        // Questions based on missing evidence
        $evidence = $pattern->getFact('evidence', []);
        foreach ($evidence as $item) {
            if (($item['availability'] ?? '') === 'unknown') {
                $questions[] = "Do you have documentation for: {$item['description']}?";
            }
        }

        // Low confidence areas need clarification
        if ($pattern->hasLowConfidence()) {
            $questions[] = "Can you provide a chronological timeline of events?";
            $questions[] = "Are there any witnesses to these events?";
        }

        return $questions;
    }
}
```

### Workflow 2: Automated Case Triage

```php
class CaseTriageService
{
    public function triageIncomingCase(string $narrative): array
    {
        // Extract facts
        $factPattern = $this->extractor->extract($narrative, auth()->id());

        $triage = [
            'priority' => 'normal',
            'assigned_practice_area' => $factPattern['legal_area'],
            'requires_specialist' => false,
            'estimated_value' => null,
            'recommended_action' => 'Standard intake process',
        ];

        $facts = $factPattern['structured_facts'];

        // Check for urgent indicators
        $deadlines = $facts['procedural_posture']['deadlines'] ?? [];
        if (!empty($deadlines)) {
            $triage['priority'] = 'urgent';
            $triage['recommended_action'] = 'Immediate calendar review - existing deadlines';
        }

        // Check case value
        $damages = $facts['damages_or_relief_sought'] ?? [];
        if (isset($damages['amount'])) {
            // Parse amount (this is simplified)
            $amount = $this->parseAmount($damages['amount']);
            if ($amount > 1000000) {
                $triage['priority'] = 'high';
                $triage['requires_specialist'] = true;
                $triage['estimated_value'] = 'high';
            }
        }

        // Complex legal issues need specialist
        $legalIssues = $facts['legal_issues'] ?? [];
        if (count($legalIssues) > 3) {
            $triage['requires_specialist'] = true;
            $triage['recommended_action'] = 'Complex case - specialist consultation needed';
        }

        // Low confidence needs immediate interview
        if ($factPattern['extraction_confidence'] < 0.5) {
            $triage['priority'] = 'high';
            $triage['recommended_action'] = 'Schedule detailed client interview - incomplete information';
        }

        return $triage;
    }
}
```

### Workflow 3: Settlement Evaluation

```php
class SettlementEvaluator
{
    public function evaluateSettlementOffer(string $caseId, float $offerAmount)
    {
        // Get case and fact pattern
        $case = LegalCase::find($caseId);
        $factPattern = LegalFactPattern::where('id', $case->fact_pattern_id)->first();

        if (!$factPattern) {
            throw new \Exception('Fact pattern not found');
        }

        // Find similar cases and their outcomes
        $similarCases = $this->extractor->searchByLegalArea(
            $factPattern->legal_area,
            50
        );

        // Calculate average settlement/judgment in similar cases
        $outcomes = $this->analyzeSimilarOutcomes($similarCases);

        // Assess strength of current case
        $caseStrength = $this->assessCaseStrength($factPattern);

        // Compare offer to likely outcomes
        $evaluation = [
            'offer_amount' => $offerAmount,
            'average_similar_outcome' => $outcomes['average'],
            'high_outcome_percentile' => $outcomes['p75'],
            'low_outcome_percentile' => $outcomes['p25'],
            'case_strength_score' => $caseStrength,
            'recommendation' => $this->generateRecommendation(
                $offerAmount,
                $outcomes,
                $caseStrength
            ),
        ];

        return $evaluation;
    }

    protected function assessCaseStrength(LegalFactPattern $pattern): float
    {
        $facts = $pattern->structured_facts;

        $score = 0.5; // Start neutral

        // More favorable facts = stronger case
        $favorable = count($facts['facts_favorable_to_plaintiff'] ?? []);
        $unfavorable = count($facts['facts_favorable_to_defendant'] ?? []);

        if ($favorable > $unfavorable) {
            $score += 0.2;
        } elseif ($unfavorable > $favorable) {
            $score -= 0.2;
        }

        // Strong evidence = stronger case
        $evidence = $facts['evidence'] ?? [];
        $strongEvidence = collect($evidence)->where('strength', 'strong')->count();
        if ($strongEvidence >= 3) {
            $score += 0.2;
        }

        // Few disputed facts = stronger case
        $disputed = count($facts['disputed_facts'] ?? []);
        if ($disputed < 3) {
            $score += 0.1;
        }

        return max(0, min(1, $score));
    }
}
```

---

## Advanced Use Cases

### Use Case 1: AI-Powered Complaint Drafting

```php
class ComplaintDraftingService
{
    public function generateComplaint(string $factPatternId): string
    {
        $factPattern = $this->extractor->getFactPattern($factPatternId);

        $facts = $factPattern->structured_facts;

        // Build complaint structure
        $complaint = $this->buildComplaintTemplate($factPattern->legal_area);

        // Fill in parties
        $complaint['parties'] = $this->formatParties($facts['parties'] ?? []);

        // Fill in jurisdiction
        $complaint['jurisdiction'] = $this->determineJurisdiction($facts);

        // Build factual allegations
        $complaint['factual_allegations'] = $this->buildFactualAllegations($facts);

        // Build legal claims
        $complaint['causes_of_action'] = $this->buildCausesOfAction(
            $facts['legal_issues'] ?? []
        );

        // Prayer for relief
        $complaint['prayer_for_relief'] = $this->buildPrayerForRelief(
            $facts['damages_or_relief_sought'] ?? []
        );

        // Render as text
        return $this->renderComplaint($complaint);
    }

    protected function buildFactualAllegations(array $facts): array
    {
        $allegations = [];
        $counter = 1;

        // Add events chronologically
        $events = $facts['events'] ?? [];
        usort($events, fn($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        foreach ($events as $event) {
            $allegations[] = [
                'number' => $counter++,
                'text' => $event['description'],
            ];
        }

        // Add favorable facts
        foreach ($facts['facts_favorable_to_plaintiff'] ?? [] as $fact) {
            $allegations[] = [
                'number' => $counter++,
                'text' => $fact,
            ];
        }

        return $allegations;
    }
}
```

### Use Case 2: Case Portfolio Analysis

```php
class PortfolioAnalysisService
{
    public function analyzePortfolio(int $userId): array
    {
        // Get all user's fact patterns
        $patterns = $this->extractor->getUserFactPatterns($userId, 1000);

        $analysis = [
            'total_cases' => $patterns->count(),
            'by_legal_area' => [],
            'by_confidence' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'by_complexity' => [],
            'trending_issues' => [],
            'success_patterns' => [],
        ];

        foreach ($patterns as $pattern) {
            // Count by legal area
            $area = $pattern->legal_area;
            $analysis['by_legal_area'][$area] = ($analysis['by_legal_area'][$area] ?? 0) + 1;

            // Count by confidence
            if ($pattern->hasHighConfidence()) {
                $analysis['by_confidence']['high']++;
            } elseif ($pattern->hasLowConfidence()) {
                $analysis['by_confidence']['low']++;
            } else {
                $analysis['by_confidence']['medium']++;
            }

            // Collect legal issues
            foreach ($pattern->getLegalIssues() as $issue) {
                $issueName = $issue['issue'] ?? 'Unknown';
                $analysis['trending_issues'][$issueName] =
                    ($analysis['trending_issues'][$issueName] ?? 0) + 1;
            }
        }

        // Sort trending issues
        arsort($analysis['trending_issues']);
        $analysis['trending_issues'] = array_slice($analysis['trending_issues'], 0, 10);

        return $analysis;
    }
}
```

### Use Case 3: Predictive Case Matching

```php
class PredictiveCaseMatching
{
    /**
     * When a new court decision is published, find which of your
     * active cases might be affected
     */
    public function findAffectedCases(string $decisionId): array
    {
        $decision = CourtDecision::find($decisionId);

        // Extract facts from the decision
        $decisionNarrative = $decision->summary ?? $decision->content;
        $decisionPattern = $this->extractor->extract($decisionNarrative, 1);

        // Get all active cases
        $activeCases = LegalCase::where('status', 'active')->get();

        $affected = [];

        foreach ($activeCases as $case) {
            if (!$case->fact_pattern_id) continue;

            $casePattern = $this->extractor->getFactPattern($case->fact_pattern_id);

            if (!$casePattern) continue;

            // Compare patterns
            $similarity = $this->extractor->comparePatterns(
                $decisionPattern,
                $casePattern
            );

            if ($similarity['overall_similarity'] >= 0.65) {
                $affected[] = [
                    'case_id' => $case->id,
                    'case_number' => $case->case_number,
                    'similarity_score' => $similarity['overall_similarity'],
                    'impact' => $this->assessImpact($similarity),
                    'recommended_action' => $this->recommendAction($similarity),
                ];
            }
        }

        // Sort by similarity
        usort($affected, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return $affected;
    }
}
```

---

## Frontend Integration Example (JavaScript)

```javascript
// Extract facts from user input
async function extractFacts(narrative) {
  const response = await fetch('/api/fact-patterns/extract', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiToken}`
    },
    body: JSON.stringify({
      narrative: narrative,
      save: true
    })
  });

  const data = await response.json();

  if (data.success) {
    // Display extracted facts
    displayFactPattern(data.data);

    // Find similar cases
    findSimilarCases(data.data.id);
  }
}

async function findSimilarCases(factPatternId) {
  const response = await fetch(
    `/api/fact-patterns/${factPatternId}/find-similar?min_similarity=0.6`,
    {
      headers: {
        'Authorization': `Bearer ${apiToken}`
      }
    }
  );

  const data = await response.json();

  if (data.success && data.count > 0) {
    displaySimilarCases(data.similar_patterns);
  }
}
```

---

## Best Practices

1. **Always save fact patterns** for important cases to build your knowledge base
2. **Use batch extraction** when processing multiple cases to optimize API usage
3. **Check confidence scores** - low confidence may indicate need for additional client interview
4. **Compare with similar cases** to leverage past experience
5. **Combine with search** to find relevant precedents
6. **Track extraction quality** over time to improve intake processes
7. **Use caching wisely** - disable for new cases, enable for analysis of existing patterns

---

## Support and Questions

For more information, see:
- API Documentation: `/docs/api.md`
- Service Documentation: `/docs/services.md`
- GitHub Issues: `https://github.com/your-repo/issues`
