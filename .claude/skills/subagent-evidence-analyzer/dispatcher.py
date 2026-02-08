#!/usr/bin/env python3
"""
Claude Code Subagent Dispatcher for Evidence Exclusion Analysis

This script creates Task() calls that enforce:
1. Reading the document fully
2. Reasoning through the legal content
3. Outputting structured JSON

The key is the prompt structure that makes skipping impossible.
"""

import json
import os
from pathlib import Path

# The extraction prompt that enforces reasoning
EXTRACTION_PROMPT = '''You are analyzing a Croatian court decision for evidence exclusion (čl. 10. ZKP).

## DOCUMENT TO ANALYZE
{document_text}

## MANDATORY ANALYSIS STEPS

You MUST complete each step IN ORDER. Do not skip any step.

### STEP 1: IDENTIFY BASIC METADATA
Read the document header and extract:
- Court name (look for "Županijski sud u...", "Visoki kazneni sud", etc.)
- Case number (look for "Poslovni broj:", patterns like Kž-XXX/YYYY)
- Decision date (look for Croatian date format or title)
- Decision type (PRESUDA or RJEŠENJE)

Write your findings:
<metadata_extraction>
[Your extraction here - quote the exact text you found]
</metadata_extraction>

### STEP 2: IDENTIFY THE CORE ISSUE
Read the document to understand:
- Is this about čl. 10. ZKP (evidence exclusion)?
- What evidence is being challenged?
- Who is challenging it (defense/prosecution)?

Write your findings:
<issue_identification>
[Explain what the case is about - cite specific paragraphs]
</issue_identification>

### STEP 3: ANALYZE EVIDENCE ITEMS
For EACH piece of evidence mentioned:
- What type of evidence is it?
- What is the claimed illegality?
- What ZKP articles are cited?

Write your findings:
<evidence_analysis>
[List each evidence item with its legal basis]
</evidence_analysis>

### STEP 4: DETERMINE THE OUTCOME
Read the IZREKA (operative part) and OBRAZLOŽENJE (reasoning):
- Was the evidence excluded (izdvojeno)?
- Was the motion rejected (odbijeno)?
- Was the judgment quashed (ukinuto)?
- What was the court's reasoning?

Write your findings:
<outcome_analysis>
[Quote the key sentences that determine outcome]
</outcome_analysis>

### STEP 5: EXTRACT KEY PASSAGES
Find 2-3 sentences that are most legally significant:
- The court's core reasoning
- The legal principle applied
- The decisive factor

Write your findings:
<key_passages>
[Quote exact sentences with explanation of why they matter]
</key_passages>

### STEP 6: OUTPUT STRUCTURED JSON
Based on your analysis above, output the final JSON.
The JSON MUST reflect what you found in Steps 1-5.

```json
{{
  "document_id": "{doc_id}",
  "source_url": "{source_url}",
  "metadata": {{
    "court": "[from Step 1]",
    "case_number": "[from Step 1]",
    "decision_date": "[YYYY-MM-DD format]",
    "decision_type": "[presuda/rješenje]",
    "court_level": "[općinski/županijski/visoki_kazneni/vrhovni]",
    "procedural_stage": "[istraga/optužno_vijeće/rasprava/žalba]"
  }},
  "evidence_exclusion_related": {{
    "is_related": [true/false],
    "relevance_level": "[direct/indirect/unrelated]",
    "rationale": "[from Step 2]"
  }},
  "exclusion": {{
    "outcome": "[excluded/not_excluded/partially_excluded/judgment_quashed/unclear]",
    "successful": [true/false/null],
    "confidence": [0.0-1.0]
  }},
  "evidence_items": [
    {{
      "evidence_type": "[from Step 3]",
      "category": "[search_record/testimony/surveillance/confession/physical/expert/unknown]",
      "description": "[detailed description]",
      "legality_issue": "[what was claimed illegal]",
      "legal_basis_refs": ["čl. X. st. Y. ZKP"],
      "was_targeted_for_exclusion": [true/false],
      "result": "[excluded/retained/tainted/unknown]",
      "confidence": [0.0-1.0]
    }}
  ],
  "alleged_offences": [
    {{
      "article": "[number]",
      "paragraph": "[if applicable]",
      "law": "KZ/11",
      "description": "[offence name in Croatian]"
    }}
  ],
  "law_references": {{
    "zkp_articles": ["čl. X.", "čl. Y."],
    "constitution_articles": [],
    "echr_articles": []
  }},
  "summary": "[2-3 sentence summary in Croatian based on Step 4]",
  "tags": ["relevant", "tags", "here"],
  "key_passages": [
    {{
      "text": "[exact quote from Step 5]",
      "relevance": "[why this matters]"
    }}
  ],
  "confidence_scores": {{
    "metadata": [0.0-1.0],
    "outcome": [0.0-1.0],
    "evidence_items": [0.0-1.0],
    "offences": [0.0-1.0]
  }},
  "processing_notes": "[any observations about the case's significance]"
}}
```

IMPORTANT: Your JSON must be consistent with your analysis in Steps 1-5. 
If you found the evidence was excluded in Step 4, the outcome MUST be "excluded".
If you couldn't find certain information, set confidence lower and note it.
'''

def create_dispatch_script(documents_dir: str, output_dir: str) -> str:
    """
    Creates a Claude Code compatible script that dispatches subagents.
    
    In Claude Code, you would run this and it generates Task() calls.
    """
    
    script = '''
# Claude Code Dispatch Script
# Run this in Claude Code to process documents with parallel subagents

import os
from pathlib import Path

documents_dir = "{documents_dir}"
output_dir = "{output_dir}"

# Get list of documents to process
docs = list(Path(documents_dir).glob("*.txt"))
print(f"Found {{len(docs)}} documents to process")

# Process in batches of 10 (Claude Code can handle ~10 parallel tasks well)
batch_size = 10
results = []

for i in range(0, len(docs), batch_size):
    batch = docs[i:i+batch_size]
    print(f"Processing batch {{i//batch_size + 1}}: documents {{i+1}}-{{min(i+batch_size, len(docs))}}")
    
    # Dispatch tasks for this batch
    tasks = []
    for doc_path in batch:
        doc_id = doc_path.stem
        doc_text = doc_path.read_text(encoding='utf-8')[:20000]  # Limit size
        
        # Create the task prompt
        prompt = f"""Analyze this Croatian court decision for evidence exclusion.
        
Document ID: {{doc_id}}
Source URL: https://odluke.sudovi.hr/Document/Text?id={{doc_id}}

DOCUMENT TEXT:
{{doc_text}}

INSTRUCTIONS:
1. First, read the entire document
2. Identify if this is about čl. 10. ZKP (evidence exclusion)  
3. Extract all metadata (court, case number, date)
4. Identify each piece of evidence being challenged
5. Determine the outcome (excluded/not_excluded/judgment_quashed)
6. Find key passages with legal reasoning
7. Output ONLY valid JSON matching this schema:

{{
  "document_id": "...",
  "metadata": {{"court": "...", "case_number": "...", "decision_date": "YYYY-MM-DD", ...}},
  "exclusion": {{"outcome": "...", "successful": true/false, "confidence": 0.X}},
  "evidence_items": [...],
  "summary": "...",
  "key_passages": [...],
  ...
}}

Think step by step. Quote specific text from the document to support your analysis.
Output ONLY the JSON, no other text.
"""
        
        # In Claude Code, this would be:
        # task = Task(prompt, description=f"Analyze {{doc_id}}")
        # tasks.append(task)
        
    # Wait for all tasks in batch to complete
    # In Claude Code: results.extend([t.result for t in tasks])

print(f"Processed {{len(results)}} documents")
'''.format(documents_dir=documents_dir, output_dir=output_dir)
    
    return script


def create_single_document_prompt(doc_id: str, doc_text: str) -> str:
    """
    Creates the prompt for a single document analysis.
    This is what you'd pass to Task() in Claude Code.
    """
    return EXTRACTION_PROMPT.format(
        document_text=doc_text[:20000],  # Limit to ~20k chars
        doc_id=doc_id,
        source_url=f"https://odluke.sudovi.hr/Document/Text?id={doc_id}"
    )


# Example usage for Claude Code
CLAUDE_CODE_EXAMPLE = '''
# In Claude Code, you would do:

from pathlib import Path

# Read document
doc_path = Path("/home/claude/evidence-analysis/batch_texts/027029ce-1b1d-4087-83fe-9985091c57df.txt")
doc_text = doc_path.read_text()
doc_id = doc_path.stem

# Create the analysis prompt
prompt = f"""
[The full EXTRACTION_PROMPT with document inserted]
"""

# Dispatch as a Task
task = Task(
    prompt,
    description=f"Analyze evidence exclusion case {doc_id}"
)

# The task runs in a fresh context, follows the steps, outputs JSON
result = task.result

# Parse the JSON from the result
import json
import re

# Extract JSON from the response (may have markdown code blocks)
json_match = re.search(r'```json\\n(.+?)\\n```', result, re.DOTALL)
if json_match:
    analysis = json.loads(json_match.group(1))
else:
    analysis = json.loads(result)

# Save result
with open(f"/home/claude/results/{doc_id}.json", "w") as f:
    json.dump(analysis, f, indent=2, ensure_ascii=False)
'''

if __name__ == "__main__":
    # Generate example prompt for one document
    example_doc = """Kž-368/2024-9, Županijski sud u Puli - Pola, 26.11.2024.
    [Document text would go here...]
    """
    
    prompt = create_single_document_prompt(
        "027029ce-1b1d-4087-83fe-9985091c57df",
        example_doc
    )
    
    print("=== EXAMPLE PROMPT FOR CLAUDE CODE TASK ===")
    print(prompt[:2000])
    print("...")
