# Evidence Exclusion Analyzer - Claude Code Dispatch Pattern

## The Key Insight

Claude Code's `Task()` gives you a fresh context with a new Claude instance. To enforce reading and reasoning, you must **structure the prompt so that skipping is impossible**.

## The Anti-Skip Pattern

```python
prompt = f"""
## DOCUMENT
{document_text}

## MANDATORY STEPS (Complete in order, show your work)

### Step 1: QUOTE the court name and case number
Find and quote the exact text containing:
- Court name (e.g., "Županijski sud u...")
- Case number (e.g., "Poslovni broj: Kž-...")

<step1>
Court: "[exact quote]"
Case: "[exact quote]"
</step1>

### Step 2: QUOTE the operative part (izreka)
Find the section starting with "r i j e š i o" or "p r e s u d i o" and quote it:

<step2>
[exact quote of izreka]
</step2>

### Step 3: IDENTIFY the outcome based on Step 2
Based on what you quoted in Step 2:
- "odbija se" = motion rejected (not_excluded)
- "usvaja se" / "izdvaja se" = evidence excluded
- "ukida se presuda" = judgment quashed

<step3>
Outcome: [your determination]
Because: [cite the specific words from Step 2]
</step3>

### Step 4: OUTPUT JSON
Your JSON MUST match what you found above.

```json
{{...}}
```
"""
```

## Why This Works

1. **Quoting forces reading** - Can't quote without reading
2. **Step references force consistency** - "Based on Step 2" means they must have done Step 2
3. **XML tags create checkpoints** - Each `<step>` is a reasoning artifact
4. **Final JSON must match** - Inconsistency is visible

## Claude Code Implementation

```python
from pathlib import Path
import json
import re

def analyze_document(doc_path: Path) -> dict:
    """Dispatch a subagent to analyze one document."""
    
    doc_id = doc_path.stem
    doc_text = doc_path.read_text()[:25000]  # Limit context
    
    prompt = f"""Analyze this Croatian court decision for evidence exclusion (čl. 10. ZKP).

DOCUMENT ID: {doc_id}
SOURCE: https://odluke.sudovi.hr/Document/Text?id={doc_id}

DOCUMENT TEXT:
{doc_text}

---

## MANDATORY ANALYSIS (Show all work)

### Step 1: Extract Metadata
Quote the exact text for:
- Court name
- Case number  
- Decision date

<metadata>
Court: "[quote]"
Case number: "[quote]"
Date: "[quote]"
</metadata>

### Step 2: Identify Evidence Issues
What evidence is being challenged? Quote the relevant section.

<evidence_issues>
[quote the section describing what evidence is challenged and why]
</evidence_issues>

### Step 3: Find the Outcome
Quote the operative part (izreka) and determine:
- excluded (usvaja se prijedlog, izdvaja se)
- not_excluded (odbija se prijedlog/žalba)
- judgment_quashed (ukida se presuda)

<outcome>
Izreka: "[quote]"
Determination: [excluded/not_excluded/judgment_quashed]
Reasoning: [explain based on the quote]
</outcome>

### Step 4: Key Legal Reasoning
Quote 2-3 sentences that contain the court's core legal reasoning.

<key_reasoning>
1. "[quote]" - [why this matters]
2. "[quote]" - [why this matters]
</key_reasoning>

### Step 5: Output JSON
Based ONLY on what you found in Steps 1-4, output:

```json
{{
  "document_id": "{doc_id}",
  "source_url": "https://odluke.sudovi.hr/Document/Text?id={doc_id}",
  "metadata": {{
    "court": "[from Step 1]",
    "case_number": "[from Step 1]",
    "decision_date": "[YYYY-MM-DD]",
    "court_level": "[županijski/visoki_kazneni/vrhovni/općinski]"
  }},
  "exclusion": {{
    "outcome": "[from Step 3 - must match your determination]",
    "successful": [true if excluded/quashed, false if not_excluded],
    "confidence": [0.0-1.0]
  }},
  "evidence_items": [
    {{
      "evidence_type": "[from Step 2]",
      "category": "[search_record/testimony/expert/physical/confession/surveillance]",
      "legality_issue": "[what was claimed illegal]",
      "result": "[excluded/retained]"
    }}
  ],
  "key_passages": [
    {{
      "text": "[from Step 4]",
      "relevance": "[from Step 4]"
    }}
  ],
  "summary": "[2 sentences summarizing the case and outcome in Croatian]"
}}
```
"""

    # Dispatch the task
    task = Task(prompt, description=f"Analyze {doc_id}")
    
    # Parse result
    result = task.result
    
    # Extract JSON from response
    json_match = re.search(r'```json\s*(\{.+?\})\s*```', result, re.DOTALL)
    if json_match:
        return json.loads(json_match.group(1))
    
    # Try parsing as raw JSON
    try:
        return json.loads(result)
    except:
        return {"error": "Failed to parse", "raw": result[:1000]}


def process_batch(doc_paths: list[Path], parallel: int = 5) -> list[dict]:
    """Process multiple documents with parallel subagents."""
    
    results = []
    
    # Process in parallel batches
    for i in range(0, len(doc_paths), parallel):
        batch = doc_paths[i:i+parallel]
        
        # Dispatch all tasks in batch simultaneously  
        tasks = [
            Task(
                create_analysis_prompt(doc),
                description=f"Analyze {doc.stem}"
            )
            for doc in batch
        ]
        
        # Collect results
        for task, doc in zip(tasks, batch):
            try:
                result = parse_json_result(task.result)
                results.append(result)
            except Exception as e:
                results.append({"document_id": doc.stem, "error": str(e)})
    
    return results


# Main execution
if __name__ == "__main__":
    docs = list(Path("urlList.txt").glob("*.txt"))
    results = process_batch(docs, parallel=10)
    
    with open("analysis_results.json", "w") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
```

## Verification

After processing, verify consistency:

```python
def verify_result(result: dict) -> list[str]:
    """Check for common extraction errors."""
    issues = []
    
    # Outcome consistency
    if result['exclusion']['outcome'] == 'excluded':
        if not result['exclusion']['successful']:
            issues.append("Outcome is 'excluded' but successful is False")
    
    # Evidence items should exist
    if not result.get('evidence_items'):
        issues.append("No evidence items extracted")
    
    # Key passages should exist for direct cases
    if result['exclusion']['outcome'] != 'unclear':
        if not result.get('key_passages'):
            issues.append("No key passages for a clear outcome")
    
    return issues
```

## Tips for Claude Code

1. **Limit document size** - Keep under 25k chars, Claude Code has context limits
2. **Use `parallel=5-10`** - More than 10 parallel tasks can cause issues
3. **Add retries** - Some documents may fail, retry with simplified prompt
4. **Save intermediate results** - Write to disk after each batch
5. **Log the reasoning steps** - Save the `<step>` tags for debugging
