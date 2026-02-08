#!/usr/bin/env python3
"""
Script to deduplicate collected evidence analysis data.
Deduplicates cases by their case ID (e.g., "I Kž 15/2020-4").
"""

import re
import json
import os
from collections import OrderedDict

RAW_DIR = "/home/user/ai-legal-war-machine/collected-analysis-data/raw"
OUTPUT_DIR = "/home/user/ai-legal-war-machine/collected-analysis-data/consolidated"
RESULTS_DIR = "/home/user/ai-legal-war-machine/.claude/skills/subagent-evidence-analyzer/results"

def ensure_dirs():
    """Create output directories."""
    os.makedirs(OUTPUT_DIR, exist_ok=True)

def extract_cases_from_markdown(content, header_pattern=r"^## \d+\.\s+(.+)$"):
    """
    Extract individual cases from markdown content.
    Returns dict mapping case ID to full case content.
    """
    cases = OrderedDict()

    # Remove commit markers first
    content = re.sub(r"<!-- COMMIT: [a-f0-9]+ -->\n?", "", content)

    # Split by case headers
    lines = content.split('\n')
    current_case_id = None
    current_case_lines = []

    for line in lines:
        # Check for case header (## N. Case ID)
        header_match = re.match(r"^## \d+\.\s+(.+?)(?:\s*\(|$)", line)
        if header_match:
            # Save previous case
            if current_case_id and current_case_lines:
                case_content = '\n'.join(current_case_lines)
                if current_case_id not in cases:
                    cases[current_case_id] = case_content

            # Start new case
            current_case_id = header_match.group(1).strip()
            current_case_lines = [line]
        elif current_case_id:
            current_case_lines.append(line)

    # Save last case
    if current_case_id and current_case_lines:
        case_content = '\n'.join(current_case_lines)
        if current_case_id not in cases:
            cases[current_case_id] = case_content

    return cases

def deduplicate_exclusions():
    """Deduplicate exclusions.md."""
    print("Processing exclusions...")

    with open(f"{RAW_DIR}/exclusions_all.md", 'r', encoding='utf-8') as f:
        content = f.read()

    # Extract header (title and intro)
    header_match = re.search(r"^(# Successful Evidence Exclusions.*?---)", content, re.DOTALL | re.MULTILINE)
    header = header_match.group(1) if header_match else "# Successful Evidence Exclusions\n\nCases where courts ruled evidence inadmissible under Article 10 ZKP.\n\n---"

    # Extract cases
    cases = extract_cases_from_markdown(content)

    print(f"  Found {len(cases)} unique exclusion cases")

    # Rebuild document
    output_lines = [header, ""]
    for i, (case_id, case_content) in enumerate(cases.items(), 1):
        # Renumber the case
        renumbered = re.sub(r"^## \d+\.", f"## {i}.", case_content, count=1)
        output_lines.append(renumbered)
        output_lines.append("")

    # Add quick reference table if present in any commit
    quick_ref_match = re.search(r"(## Quick Reference: Exclusion Grounds.*)", content, re.DOTALL)
    if quick_ref_match:
        output_lines.append(quick_ref_match.group(1))

    # Add source line
    output_lines.append(f"\n---\n\n*Source: Consolidated analysis from 31 commits*")
    output_lines.append(f"*Total unique cases: {len(cases)}*")
    output_lines.append(f"*Last updated: 2026-01-21*")

    output_content = '\n'.join(output_lines)

    with open(f"{OUTPUT_DIR}/exclusions.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    # Also update the results directory
    with open(f"{RESULTS_DIR}/exclusions.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    return len(cases)

def deduplicate_partial_exclusions():
    """Deduplicate partial-exclusions.md."""
    print("Processing partial exclusions...")

    with open(f"{RAW_DIR}/partial_exclusions_all.md", 'r', encoding='utf-8') as f:
        content = f.read()

    header = "# Partial Evidence Exclusions\n\nCases where courts excluded some evidence but retained other portions.\n\n---"

    cases = extract_cases_from_markdown(content)

    print(f"  Found {len(cases)} unique partial exclusion cases")

    output_lines = [header, ""]
    for i, (case_id, case_content) in enumerate(cases.items(), 1):
        renumbered = re.sub(r"^## \d+\.", f"## {i}.", case_content, count=1)
        output_lines.append(renumbered)
        output_lines.append("")

    output_lines.append(f"\n---\n\n*Source: Consolidated analysis from 31 commits*")
    output_lines.append(f"*Total unique cases: {len(cases)}*")
    output_lines.append(f"*Last updated: 2026-01-21*")

    output_content = '\n'.join(output_lines)

    with open(f"{OUTPUT_DIR}/partial-exclusions.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    with open(f"{RESULTS_DIR}/partial-exclusions.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    return len(cases)

def deduplicate_legal_provisions():
    """Deduplicate legal-provisions.md."""
    print("Processing legal provisions...")

    with open(f"{RAW_DIR}/legal_provisions_all.md", 'r', encoding='utf-8') as f:
        content = f.read()

    # Remove commit markers
    content = re.sub(r"<!-- COMMIT: [a-f0-9]+ -->\n?", "", content)

    # Extract unique provisions by article number (### Article XXX pattern)
    provisions = OrderedDict()
    sections = OrderedDict()  # For ## level sections

    lines = content.split('\n')
    current_section = None
    current_section_lines = []
    current_article = None
    current_article_lines = []

    for line in lines:
        # Check for section header (## Section Name)
        section_match = re.match(r"^## (.+?)$", line)
        article_match = re.match(r"^### (Art(?:icle)?\s*\d+.*?)$", line, re.IGNORECASE)

        if section_match and not article_match:
            # Save previous section/article
            if current_article and current_article_lines:
                article_content = '\n'.join(current_article_lines)
                if current_article not in provisions:
                    provisions[current_article] = article_content
                current_article = None
                current_article_lines = []

            if current_section and current_section_lines:
                section_content = '\n'.join(current_section_lines)
                if current_section not in sections:
                    sections[current_section] = section_content

            current_section = section_match.group(1).strip()
            current_section_lines = [line]

        elif article_match:
            # Save previous article
            if current_article and current_article_lines:
                article_content = '\n'.join(current_article_lines)
                if current_article not in provisions:
                    provisions[current_article] = article_content

            current_article = article_match.group(1).strip()
            current_article_lines = [line]

        elif current_article:
            current_article_lines.append(line)
        elif current_section:
            current_section_lines.append(line)

    # Save last items
    if current_article and current_article_lines:
        article_content = '\n'.join(current_article_lines)
        if current_article not in provisions:
            provisions[current_article] = article_content

    if current_section and current_section_lines:
        section_content = '\n'.join(current_section_lines)
        if current_section not in sections:
            sections[current_section] = section_content

    print(f"  Found {len(provisions)} unique provisions, {len(sections)} sections")

    # Rebuild document - take the best version of the document
    # Split by document headers and take the most complete one
    doc_blocks = content.split("# ZKP Legal Provisions Quick Reference")

    if len(doc_blocks) > 1:
        # Find most complete version (longest with most articles)
        best_block = max(doc_blocks[1:], key=lambda b: (len(b), b.count("###")))
        output_content = "# ZKP Legal Provisions Quick Reference\n" + best_block
    else:
        # Just use the cleaned content
        output_content = content

    # Remove duplicate sections
    output_content = re.sub(r'\n{3,}', '\n\n', output_content)

    output_content += f"\n\n---\n\n*Source: Consolidated from 31 commits*\n*Last updated: 2026-01-21*"

    with open(f"{OUTPUT_DIR}/legal-provisions.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    with open(f"{RESULTS_DIR}/legal-provisions.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    return len(provisions) if provisions else len(sections)

def consolidate_summary():
    """Create consolidated summary from all summaries."""
    print("Processing summaries...")

    with open(f"{RAW_DIR}/summaries_all.md", 'r', encoding='utf-8') as f:
        content = f.read()

    # Extract statistics from all summaries
    # Look for patterns like "Total Analyzed | XXX"
    total_analyzed = set()
    full_exclusions = set()
    partial_exclusions = set()

    # Parse all summary blocks for statistics
    for match in re.finditer(r"\| Total Analyzed \| (\d+)", content):
        total_analyzed.add(int(match.group(1)))

    for match in re.finditer(r"\| Full Exclusions \| (\d+)", content):
        full_exclusions.add(int(match.group(1)))

    for match in re.finditer(r"\| Partial Exclusions \| (\d+)", content):
        partial_exclusions.add(int(match.group(1)))

    # Get max values (most recent/complete analysis)
    max_analyzed = max(total_analyzed) if total_analyzed else 0
    max_full = max(full_exclusions) if full_exclusions else 0
    max_partial = max(partial_exclusions) if partial_exclusions else 0

    # Find the most complete summary (longest one with most data)
    summaries = content.split("# Evidence Exclusion Analysis")
    best_summary = max(summaries, key=len) if summaries else ""

    # Clean it up and add header
    output_content = "# Evidence Exclusion Analysis - Consolidated Summary\n\n"
    output_content += f"**Date:** 2026-01-21 (Consolidated)\n"
    output_content += f"**Source:** 31 commits analyzed\n\n"

    if max_analyzed:
        output_content += f"## Consolidated Statistics\n\n"
        output_content += f"| Metric | Value |\n"
        output_content += f"|--------|-------|\n"
        output_content += f"| Total Analyzed (max) | {max_analyzed} |\n"
        output_content += f"| Full Exclusions (max) | {max_full} |\n"
        output_content += f"| Partial Exclusions (max) | {max_partial} |\n\n"

    # Add the best summary content (cleaned)
    best_summary = re.sub(r"<!-- COMMIT: [a-f0-9]+ -->\n?", "", best_summary)
    best_summary = re.sub(r"^# Evidence Exclusion Analysis.*?\n", "", best_summary)
    output_content += best_summary

    output_content += f"\n\n---\n\n*Source: Consolidated from 31 commits*\n*Last updated: 2026-01-21*"

    with open(f"{OUTPUT_DIR}/summary.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    with open(f"{RESULTS_DIR}/summary.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    return max_analyzed

def consolidate_json():
    """Consolidate JSON analysis reports."""
    print("Processing JSON data...")

    with open(f"{RAW_DIR}/json_data_all.json", 'r', encoding='utf-8') as f:
        content = f.read()

    # Try to parse - it may have nested JSON issues
    data = []
    try:
        data = json.loads(content)
    except json.JSONDecodeError as e:
        print(f"  Warning: Could not parse combined JSON: {e}")
        # Try to extract individual JSON objects by finding complete report blocks
        import re
        matches = re.findall(r'\{"report_metadata".*?\}\s*\}', content, re.DOTALL)
        for match in matches:
            try:
                obj = json.loads(match)
                data.append({'data': obj})
            except:
                pass

    # Find the most complete report (by documents_analyzed count)
    best_report = None
    max_analyzed = 0

    for entry in data:
        if isinstance(entry, dict) and 'data' in entry:
            analysis_data = entry['data']
            if isinstance(analysis_data, dict):
                # Check if it's a report with metadata
                if 'report_metadata' in analysis_data:
                    docs = analysis_data.get('report_metadata', {}).get('documents_analyzed', 0)
                    if docs > max_analyzed:
                        max_analyzed = docs
                        best_report = analysis_data

    print(f"  Found best report with {max_analyzed} documents analyzed")

    if best_report:
        # Update metadata
        best_report['consolidated_metadata'] = {
            "consolidated_date": "2026-01-21",
            "source_commits": 31,
            "original_documents_analyzed": max_analyzed
        }

        with open(f"{OUTPUT_DIR}/evidence-exclusion-analysis-report.json", 'w', encoding='utf-8') as f:
            json.dump(best_report, f, indent=2, ensure_ascii=False)

        with open(f"{RESULTS_DIR}/evidence-exclusion-analysis-report.json", 'w', encoding='utf-8') as f:
            json.dump(best_report, f, indent=2, ensure_ascii=False)
    else:
        print("  Warning: No valid report found")

    return max_analyzed

def consolidate_analysis_report():
    """Create consolidated analysis report."""
    print("Processing analysis reports...")

    with open(f"{RAW_DIR}/analysis_reports_all.md", 'r', encoding='utf-8') as f:
        content = f.read()

    # Remove commit markers
    content = re.sub(r"<!-- COMMIT: [a-f0-9]+ -->\n?", "", content)

    # Find the most comprehensive report (longest with most sections)
    reports = content.split("# Evidence Exclusion Analysis Report")

    if len(reports) > 1:
        # Score each report by completeness
        best_report = max(reports[1:], key=lambda r: (
            len(r),
            r.count("##"),  # Number of sections
            r.count("URL"),  # Number of URLs mentioned
        ))
        output_content = "# Evidence Exclusion Analysis Report - Consolidated\n\n"
        output_content += f"**Consolidated from:** 31 commits\n"
        output_content += f"**Date:** 2026-01-21\n\n"
        output_content += best_report
    else:
        output_content = content

    output_content += f"\n\n---\n\n*Source: Consolidated from 31 commits*\n*Last updated: 2026-01-21*"

    with open(f"{OUTPUT_DIR}/ANALYSIS-REPORT.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

    with open(f"{RESULTS_DIR}/ANALYSIS-REPORT.md", 'w', encoding='utf-8') as f:
        f.write(output_content)

def main():
    """Main consolidation process."""
    print("=" * 60)
    print("Evidence Analysis Data Consolidation")
    print("=" * 60)

    ensure_dirs()

    # Process each file type
    exclusion_count = deduplicate_exclusions()
    partial_count = deduplicate_partial_exclusions()
    provision_count = deduplicate_legal_provisions()
    summary_count = consolidate_summary()
    json_count = consolidate_json()
    consolidate_analysis_report()

    print("\n" + "=" * 60)
    print("Consolidation Complete!")
    print("=" * 60)
    print(f"\nResults:")
    print(f"  - Unique exclusion cases: {exclusion_count}")
    print(f"  - Unique partial exclusions: {partial_count}")
    print(f"  - Unique provisions: {provision_count}")
    print(f"  - Max analyzed documents: {summary_count}")
    print(f"  - Unique JSON analyses: {json_count}")
    print(f"\nOutput saved to:")
    print(f"  - {OUTPUT_DIR}/")
    print(f"  - {RESULTS_DIR}/")

if __name__ == "__main__":
    main()
