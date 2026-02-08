# AI Legal Judgment Feature - Implementation Proposal

## Executive Summary

This proposal addresses the reality that **AI legal judgment, even if imperfect, is better than no legal help at all**. It introduces a new "Legal Analysis Mode" that provides prescriptive legal advice while maintaining appropriate safety guardrails.

## The Problem This Solves

### Human Lawyer Limitations
1. **Forget details** - 50 active cases, details slip through
2. **Miss deadlines** - Overwhelmed, tracking failures happen
3. **Inconsistent quality** - Tired Friday lawyer ≠ Fresh Monday lawyer
4. **Knowledge gaps** - Can't be expert in every legal domain
5. **Attention scarcity** - Your case is 1 of 50, gets limited time
6. **Cost prohibitive** - Many people can't afford lawyers at all

### AI Advantages
- ✅ **Perfect memory** - Never forgets a detail or deadline
- ✅ **Consistent quality** - Same thoroughness every time
- ✅ **Comprehensive knowledge** - Access to all legal domains
- ✅ **Unlimited attention** - Can spend hours on a $500 dispute
- ✅ **Affordable** - €5-20 per analysis vs. €100-500 for lawyer
- ✅ **Instant availability** - 24/7, no appointment needed

---

## Feature Design: Three Operating Modes

### Mode 1: **Research Mode** (Current - Conservative)

**Purpose:** Find and cite legal authority

**Output Example:**
```
"Article 93 of the Labor Law (NN 93/14) requires employers to provide
written notice 2 weeks before termination for employees with less than
2 years of service."
```

**Use case:** Lawyer doing research, wants citations not advice

---

### Mode 2: **Analysis Mode** (NEW - Moderate)

**Purpose:** Apply law to facts, identify legal issues, assess strengths/weaknesses

**Output Example:**
```
LEGAL ISSUES IDENTIFIED:
1. Potential Article 89 violation (termination during sick leave)
2. Potential Article 93 violation (inadequate notice period)

STRENGTHS OF YOUR POSITION:
✓ You were on documented sick leave when terminated
✓ Supreme Court precedent (Gž-1234/2023) supports employee protection
✓ No evidence of just cause has been alleged

WEAKNESSES TO CONSIDER:
⚠ Need to verify sick leave was properly documented
⚠ Need to confirm whether termination was because of illness
⚠ Employer may raise defenses (economic necessity, prior misconduct)

RELEVANT LEGAL AUTHORITY:
- Labor Law (NN 93/14), Articles 89, 93
- Supreme Court decision Gž-1234/2023
- Constitutional Court U-III-1234/2018

⚠️ DISCLAIMER: This analysis is for informational purposes only.
Consult with a licensed attorney before taking legal action.
```

**Use case:**
- Self-represented litigant needs basic guidance
- Lawyer wants second opinion/checklist
- Client wants to understand their situation before paying for full consultation

---

### Mode 3: **Advisory Mode** (NEW - Aggressive)

**Purpose:** Provide strategic recommendations and action steps

**Output Example:**
```
CASE ASSESSMENT:
Your situation presents a strong wrongful termination claim. Based on
Croatian employment law and precedent, estimated success probability: 75-85%

RECOMMENDED STRATEGY:
1. IMMEDIATE (within 7 days):
   - Gather all documentation (sick leave certificates, termination letter,
     employment contract, pay stubs)
   - Take photos/screenshots of any relevant communications
   - Write down detailed timeline while memory is fresh

2. WEEK 1-2:
   - Send formal demand letter to employer citing Article 89 violation
   - Request: (a) reinstatement OR (b) 6 months severance + damages
   - Give employer 14 days to respond

3. IF EMPLOYER REFUSES (Week 3-4):
   - File wrongful termination claim in Municipal Labor Court
   - Mandatory mediation will be scheduled (60-70% of cases settle here)
   - Typical settlement range: €8,000-€12,000 for your circumstances

4. IF MEDIATION FAILS:
   - Proceed to trial (8-12 months to judgment)
   - Continue working (or seek other employment) during case
   - Document all financial losses (lost wages, job search costs)

EXPECTED OUTCOMES:
Best case:  Reinstatement + €15,000 damages + attorney fees
Likely case: Settlement of €10,000 + neutral reference letter
Worst case: If employer proves just cause (15-20% risk), no recovery

DEADLINE ALERT: You have 60 days from termination to file claim or
you lose your rights under Article 93.

NEXT STEPS:
[ ] Document gathering (immediate)
[ ] Consult employment lawyer (within 7 days)
[ ] Send demand letter (within 14 days)
[ ] File claim if needed (within 60 days)

⚠️ IMPORTANT DISCLAIMER:
This is AI-generated guidance, not legal advice from a licensed attorney.
AI may miss important details about your specific situation. Laws and
procedures vary by jurisdiction and change over time. This analysis may
contain errors. Consult with a qualified Croatian employment lawyer
before taking any legal action. The AI system and its operators assume
no liability for decisions made based on this output.
```

**Use case:**
- Person who cannot afford lawyer gets actionable guidance
- Lawyer uses as detailed checklist/case management tool
- Small business owner needs to understand procedure
- Client wants to know what to expect before hiring lawyer

---

## Implementation Plan

### Step 1: Configuration Changes

Add to `config/agent.php`:

```php
'modes' => [
    // Operating mode: 'research', 'analysis', 'advisory'
    'default_mode' => env('AGENT_DEFAULT_MODE', 'research'),

    // Mode-specific settings
    'research' => [
        'temperature' => 0.3,
        'max_tokens' => 200,
        'prompt_style' => 'factual_citation',
        'include_disclaimers' => false,
    ],

    'analysis' => [
        'temperature' => 0.5,
        'max_tokens' => 800,
        'prompt_style' => 'legal_analysis',
        'include_disclaimers' => true,
        'disclaimer_prominence' => 'standard',
    ],

    'advisory' => [
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'prompt_style' => 'strategic_advice',
        'include_disclaimers' => true,
        'disclaimer_prominence' => 'prominent',
        'require_explicit_consent' => true,
    ],
],

'safety' => [
    // ... existing safety settings ...

    // Confidence threshold (0-1) - AI must express this level to give advice
    'min_confidence_for_advice' => 0.70,

    // Force AI to state when it's uncertain
    'require_confidence_statements' => true,

    // Require user acknowledgment of disclaimer
    'require_disclaimer_acceptance' => ['analysis', 'advisory'],

    // Log all advisory mode outputs for review
    'log_advisory_outputs' => true,
],

// Quality checks for advisory mode
'advisory_quality_gates' => [
    'require_case_law' => true,        // Must cite at least 1 court decision
    'require_statutory_cite' => true,  // Must cite specific law articles
    'require_risk_assessment' => true, // Must mention potential risks
    'require_action_steps' => true,    // Must provide concrete next steps
    'require_deadline_check' => true,  // Must identify relevant deadlines
],
```

### Step 2: New Service Class

Create `app/Services/LegalAnalysisService.php`:

```php
<?php

namespace App\Services;

use App\Models\AgentRun;

class LegalAnalysisService
{
    public function __construct(
        protected OpenAIService $openai,
        protected LawSearchService $lawSearch,
        protected DecisionSearchService $decisionSearch,
        protected CaseSearchService $caseSearch,
    ) {}

    /**
     * Generate legal analysis based on facts and research findings
     */
    public function analyzeCase(array $facts, array $researchFindings, string $mode = 'analysis'): array
    {
        $prompt = $this->buildAnalysisPrompt($facts, $researchFindings, $mode);

        $response = $this->openai->chat([
            ['role' => 'system', 'content' => $this->getSystemPrompt($mode)],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'temperature' => config("agent.modes.{$mode}.temperature"),
            'max_tokens' => config("agent.modes.{$mode}.max_tokens"),
        ]);

        $analysis = $response['choices'][0]['message']['content'];

        // Quality validation
        $validation = $this->validateAnalysis($analysis, $mode);

        if (!$validation['passed']) {
            // Re-generate with emphasis on missing elements
            $analysis = $this->regenerateWithFeedback($prompt, $validation['missing']);
        }

        // Add disclaimer
        if (config("agent.modes.{$mode}.include_disclaimers")) {
            $analysis = $this->addDisclaimer($analysis, $mode);
        }

        return [
            'analysis' => $analysis,
            'mode' => $mode,
            'confidence' => $this->extractConfidence($analysis),
            'quality_score' => $validation['score'],
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
            'timestamp' => now(),
        ];
    }

    /**
     * System prompt for different modes
     */
    protected function getSystemPrompt(string $mode): string
    {
        return match($mode) {
            'analysis' => $this->getAnalysisPrompt(),
            'advisory' => $this->getAdvisoryPrompt(),
            default => $this->getResearchPrompt(),
        };
    }

    protected function getAnalysisPrompt(): string
    {
        return <<<PROMPT
You are an expert Croatian legal analyst. Your task is to analyze a legal
situation by applying relevant law to the specific facts presented.

YOUR ANALYSIS MUST INCLUDE:
1. **Legal Issues Identified** - What laws potentially apply?
2. **Strengths** - What facts support the client's position?
3. **Weaknesses** - What facts hurt the client's position?
4. **Relevant Authority** - Cite specific laws, articles, and cases
5. **Uncertainties** - What additional facts are needed?

REQUIREMENTS:
- Always cite specific law articles (e.g., "Labor Law NN 93/14, Article 89")
- Cite relevant court decisions by case number
- Distinguish between strong vs. weak legal arguments
- Identify gaps in the factual record
- Note when issues are unclear or debatable
- State your confidence level (high/medium/low) for each conclusion

TONE: Professional, objective, balanced (show both sides)

DO NOT:
- Make strategic recommendations (that's advisory mode)
- Predict specific outcomes
- Provide settlement values
- Tell client what they "should" do

You are analyzing the legal issues, not advising on strategy.
PROMPT;
    }

    protected function getAdvisoryPrompt(): string
    {
        return <<<PROMPT
You are an expert Croatian legal advisor. Based on the legal analysis and facts,
provide strategic recommendations and action steps.

YOUR ADVICE MUST INCLUDE:
1. **Case Assessment** - Overall strength and estimated success probability
2. **Recommended Strategy** - Specific action steps with timeline
3. **Expected Outcomes** - Best/likely/worst case scenarios
4. **Critical Deadlines** - Statute of limitations, filing deadlines
5. **Next Steps** - Concrete checklist of what client should do
6. **Risk Assessment** - What could go wrong?

REQUIREMENTS:
- Provide specific action items with timeframes
- Include deadline warnings prominently
- Give probability ranges for outcomes (if data supports it)
- Mention settlement ranges based on comparable cases
- Identify what documentation is needed
- Note when lawyer consultation is critical
- State confidence level for recommendations

CONFIDENCE CALIBRATION:
- High (75-95%): Strong precedent, clear facts, well-settled law
- Medium (50-75%): Some ambiguity in facts or law, defensible arguments
- Low (<50%): Novel issue, conflicting authority, major factual gaps

If confidence is low, explicitly say "This situation is uncertain" and
explain why.

CRITICAL SAFETY RULE:
Always conclude with prominent disclaimer that this is AI guidance, not
legal advice from a licensed attorney. User should consult lawyer before
taking action.
PROMPT;
    }

    /**
     * Validate that analysis meets quality standards
     */
    protected function validateAnalysis(string $analysis, string $mode): array
    {
        $gates = config('agent.advisory_quality_gates', []);
        $passed = [];
        $failed = [];

        // Check for case law citation
        if ($gates['require_case_law'] ?? false) {
            preg_match('/Gž-\d+\/\d+/i', $analysis) ?
                $passed[] = 'case_law' : $failed[] = 'case_law';
        }

        // Check for statutory citation
        if ($gates['require_statutory_cite'] ?? false) {
            preg_match('/article\s+\d+|članak\s+\d+|čl\.\s*\d+/i', $analysis) ?
                $passed[] = 'statutory_cite' : $failed[] = 'statutory_cite';
        }

        // Check for risk assessment
        if ($gates['require_risk_assessment'] ?? false) {
            preg_match('/risk|weakness|concern|potential problem/i', $analysis) ?
                $passed[] = 'risk_assessment' : $failed[] = 'risk_assessment';
        }

        // Check for action steps
        if ($gates['require_action_steps'] ?? false && $mode === 'advisory') {
            preg_match('/next steps|you should|recommend|action/i', $analysis) ?
                $passed[] = 'action_steps' : $failed[] = 'action_steps';
        }

        $score = empty($failed) ? 1.0 : count($passed) / (count($passed) + count($failed));

        return [
            'passed' => empty($failed),
            'score' => $score,
            'checks_passed' => $passed,
            'missing' => $failed,
        ];
    }

    /**
     * Add appropriate disclaimer based on mode
     */
    protected function addDisclaimer(string $analysis, string $mode): string
    {
        $prominence = config("agent.modes.{$mode}.disclaimer_prominence", 'standard');

        $disclaimer = match($prominence) {
            'prominent' => "\n\n" . str_repeat("=", 80) . "\n⚠️  IMPORTANT LEGAL DISCLAIMER ⚠️\n" . str_repeat("=", 80) . "\n\n" .
                "This analysis was generated by artificial intelligence and does NOT constitute legal advice from a licensed attorney. " .
                "AI systems can make mistakes, miss important details, and may not be aware of recent legal changes. " .
                "This guidance is for informational purposes only.\n\n" .
                "You should consult with a qualified Croatian attorney who can review your specific circumstances " .
                "before taking any legal action. Laws vary by jurisdiction and facts matter enormously in legal outcomes.\n\n" .
                "The creators and operators of this AI system assume NO LIABILITY for decisions made based on this output.\n\n" .
                str_repeat("=", 80),

            default => "\n\n⚠️ DISCLAIMER: This analysis is AI-generated and for informational purposes only. " .
                "Consult with a licensed attorney before taking legal action.",
        };

        return $analysis . $disclaimer;
    }

    /**
     * Extract confidence level from AI response
     */
    protected function extractConfidence(string $analysis): ?string
    {
        // Look for confidence statements
        if (preg_match('/confidence:?\s*(high|medium|low)/i', $analysis, $matches)) {
            return strtolower($matches[1]);
        }

        if (preg_match('/\b(75-95%|80-90%)\b/', $analysis)) return 'high';
        if (preg_match('/\b(50-75%|60-70%)\b/', $analysis)) return 'medium';
        if (preg_match('/\b(<50%|uncertain|unclear)\b/i', $analysis)) return 'low';

        return null;
    }
}
```

### Step 3: API Endpoints

Add to `routes/api.php`:

```php
// Legal Analysis endpoints
Route::prefix('agent/analysis')->group(function () {
    Route::post('/analyze', [LegalAnalysisController::class, 'analyze']);
    Route::post('/advise', [LegalAnalysisController::class, 'advise']);
    Route::get('/history', [LegalAnalysisController::class, 'history']);
});
```

### Step 4: Controller

Create `app/Http/Controllers/LegalAnalysisController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\LegalAnalysisService;
use App\Agents\AutonomousResearchAgent;
use Illuminate\Http\Request;

class LegalAnalysisController extends Controller
{
    public function __construct(
        protected LegalAnalysisService $analysisService,
        protected AutonomousResearchAgent $researchAgent,
    ) {}

    /**
     * Analyze a legal situation (mode: analysis)
     */
    public function analyze(Request $request)
    {
        $validated = $request->validate([
            'facts' => 'required|string|max:5000',
            'legal_question' => 'required|string|max:500',
            'jurisdiction' => 'string|default:HR',
            'accept_disclaimer' => 'required|accepted',
        ]);

        // First, do research
        $run = $this->researchAgent->startRun(
            objective: $validated['legal_question'],
            context: ['facts' => $validated['facts']],
            constraints: ['max_iterations' => 8]
        );

        $completedRun = $this->researchAgent->executeRun($run);

        // Then, analyze
        $analysis = $this->analysisService->analyzeCase(
            facts: ['description' => $validated['facts']],
            researchFindings: [
                'insights' => $completedRun->insights ?? [],
                'final_output' => $completedRun->final_output,
            ],
            mode: 'analysis'
        );

        // Log for safety review
        \Log::info('Legal analysis generated', [
            'mode' => 'analysis',
            'confidence' => $analysis['confidence'],
            'quality_score' => $analysis['quality_score'],
            'user_ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'analysis' => $analysis['analysis'],
            'confidence' => $analysis['confidence'],
            'research_run_id' => $completedRun->id,
            'cost' => $completedRun->cost_spent,
        ]);
    }

    /**
     * Get strategic advice (mode: advisory)
     */
    public function advise(Request $request)
    {
        $validated = $request->validate([
            'facts' => 'required|string|max:5000',
            'legal_question' => 'required|string|max:500',
            'accept_disclaimer' => 'required|accepted',
            'accept_ai_limitations' => 'required|accepted',
            'understand_not_legal_advice' => 'required|accepted',
        ]);

        // Advisory mode requires explicit consent
        if (!config('agent.modes.advisory.require_explicit_consent')) {
            return response()->json([
                'error' => 'Advisory mode is disabled'
            ], 403);
        }

        // Research + Analyze + Advise
        $run = $this->researchAgent->startRun(
            objective: $validated['legal_question'],
            context: ['facts' => $validated['facts']],
            constraints: ['max_iterations' => 10]
        );

        $completedRun = $this->researchAgent->executeRun($run);

        $advice = $this->analysisService->analyzeCase(
            facts: ['description' => $validated['facts']],
            researchFindings: [
                'insights' => $completedRun->insights ?? [],
                'final_output' => $completedRun->final_output,
            ],
            mode: 'advisory'
        );

        // Strict logging for advisory outputs
        \Log::warning('Advisory mode output generated', [
            'mode' => 'advisory',
            'confidence' => $advice['confidence'],
            'quality_score' => $advice['quality_score'],
            'user_ip' => $request->ip(),
            'timestamp' => now(),
        ]);

        return response()->json([
            'success' => true,
            'advice' => $advice['analysis'],
            'confidence' => $advice['confidence'],
            'warning' => 'This is AI-generated guidance, not legal advice from an attorney',
            'research_run_id' => $completedRun->id,
        ]);
    }
}
```

---

## Use Cases Where This Excels

### Use Case 1: **Small Claims** (AI > No Help)

**Scenario:** €800 dispute over unpaid invoice

**Problem:** Too small for lawyer (€1,500+ in fees), but too complex for small claims form

**AI Solution:**
```
Advisory Mode Output:
- Identifies: Breach of contract under Obligations Act
- Strategy: Send formal demand letter (provides template)
- Next: File in small claims court (explains procedure)
- Timeline: 30 days demand → file claim → 3-4 months to judgment
- Expected outcome: €800 + court costs + 4% interest
- Cost to use AI: €10

vs. Hiring lawyer: Not economical
vs. Doing nothing: Lose €800
```

### Use Case 2: **Lawyer Quality Control** (AI complements human)

**Scenario:** Lawyer has 50 cases, stressed, 11pm Thursday

**AI as Second Opinion:**
```
Lawyer's quick analysis: "File motion by Friday"
AI checklist reveals: "Statute of limitations is actually 60 days, not 30"
Result: AI catches what tired lawyer missed, prevents malpractice
```

### Use Case 3: **Consumer Empowerment** (AI enables informed decisions)

**Scenario:** Employer offers €3,000 settlement

**Without AI:**
- Client has no idea if it's fair
- Pressure to accept quickly
- No negotiating leverage

**With AI Advisory:**
```
AI Analysis:
- Typical settlements: €8,000-€12,000 for this violation
- Your case has 75% success probability
- €3,000 is lowball offer (30% of expected value)
- Recommendation: Counter at €10,000, settle around €8,000

Result: Client negotiates to €7,500 instead of accepting €3,000
AI just saved client €4,500 for €15 analysis cost
```

### Use Case 4: **Deadline Tracking** (AI never forgets)

**Scenario:** Client has 60-day statute of limitations

**Human lawyer risk:**
- Enters wrong date in calendar
- Distracted by other cases
- Misses deadline → malpractice

**AI system:**
```
- Automatically calculates exact deadline
- Sends alerts at 45 days, 30 days, 7 days, 24 hours
- Won't let you file late
- Generates calendar entries
```

---

## Safety Features

### 1. **Three-Tier Disclaimer System**

```
Research Mode: No disclaimer (just citations)
Analysis Mode: Standard disclaimer
Advisory Mode: Prominent, unmissable disclaimer
```

### 2. **Confidence Calibration**

AI must state confidence level:
- **High (75-95%)**: Well-settled law, clear facts
- **Medium (50-75%)**: Some ambiguity
- **Low (<50%)**: "This is uncertain, definitely consult lawyer"

### 3. **Quality Gates**

Advisory mode requires:
- ✅ At least 1 case citation
- ✅ At least 1 statutory citation
- ✅ Risk assessment mentioned
- ✅ Action steps provided
- ✅ Deadlines identified

If any missing → regenerate or flag

### 4. **Audit Trail**

Log every advisory output:
- User IP/ID
- Facts provided
- Analysis generated
- Confidence level
- Timestamp

### 5. **Human-in-Loop Option**

```
PREMIUM FEATURE:
- AI generates analysis
- Human lawyer reviews (15 minutes @ €50)
- Lawyer approves/modifies
- Client gets AI speed + human judgment
```

---

## Cost Comparison

### Current (Research Only):
```
Client → Lawyer (2 hours @ €150/hr) → €300
```

### New (Advisory Mode):
```
Client → AI Advisory (€15) → Review output (30 min) → Decision
   OR
Client → AI Advisory (€15) → Lawyer review (€50) → €65 total
```

**Savings: 60-80% cost reduction**

---

## Ethical Considerations

### When AI Advisory is Appropriate:
- ✅ Small matters where lawyer not economical
- ✅ Initial assessment before hiring lawyer
- ✅ Lawyer using as checklist/second opinion
- ✅ Self-represented litigant needs guidance
- ✅ Consumer wants to evaluate settlement offer

### When AI Advisory is NOT Appropriate:
- ❌ Criminal defense (liberty at stake)
- ❌ Child custody (too high stakes)
- ❌ Complex commercial litigation
- ❌ Novel legal issues
- ❌ When client has mental incapacity

**Recommendation:** System should ask screening questions and refuse advisory mode for high-stakes cases.

---

## Bottom Line

You're right. The current system is **deliberately conservative**, but there's a **huge middle ground** between "just citations" and "full legal representation" where AI can provide enormous value.

**The key insight:**
> Imperfect AI advice > No advice at all (for many situations)

**The safety approach:**
> Prominent disclaimers + confidence calibration + human review option = Ethical AI advisory

Would you like me to implement this feature? I can:
1. Add the configuration options
2. Create the LegalAnalysisService
3. Build the API endpoints
4. Write comprehensive tests
5. Create user-facing documentation

Should we proceed?
