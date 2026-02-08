# Commands & Skills Style Guide

Standardization guide for `.claude/commands/` and `.claude/skills/`.

## Commands Template

Commands should be **scannable** with primary usage ~300 words, appendices allowed.

```markdown
---
description: One-line summary (shown in /help)
---

# /command-name

## Quick Reference

| Item | Value |
|------|-------|
| Purpose | What it achieves |
| Prereq | Required conditions |
| Output | What it produces |
| Skill | Related skill name (if any) |

## When to Use

- Situation 1
- Situation 2
- Situation 3

## What It Does

1. **Step 1** - Brief description
2. **Step 2** - Brief description
3. **Step 3** - Brief description

## Process

[Delegate to skill or provide detailed steps]

---

## Reference (optional)

[Extended documentation, examples, edge cases - for commands needing depth]
```

## Skills Template

Skills should be **150-300 words** in primary content, with extended reference below if needed.

```markdown
---
name: skill-name
description: One-line when-to-use + what-it-does
---

# Skill Name

## When to Use

- Trigger condition 1
- Trigger condition 2

## Inputs/Prerequisites

- Required input 1
- Required state 2

## Steps

1. First action
2. Second action
3. Third action

## Done Criteria

- Output 1 achieved
- State 2 verified

---

## Reference (optional)

[Extended examples, rationale, edge cases - for skills needing depth]
```

## Word Limits

| Type | Primary Section | Appendices |
|------|-----------------|------------|
| Skills | 150-300 words | Allowed in Reference |
| Commands | ~300 words | Allowed in Reference |

## Naming Conventions

- **Commands**: `/kebab-case` matching filename (e.g., `/check-setup` → `check-setup.md`)
- **Skills**: `kebab-case` folder names (e.g., `test-driven-development/SKILL.md`)
- **Agents**: `kebab-case.md` (e.g., `code-reviewer.md`)

## Cross-References

- Skills reference other skills: `superpowers:skill-name`
- Skills reference agents: `superpowers:agent-name`
- Commands reference skills: `Skill: \`skill-name\``

## Checklist for New Commands/Skills

- [ ] Follows template structure
- [ ] Within word limits
- [ ] Has Quick Reference table
- [ ] Has When to Use section
- [ ] Has Steps/What It Does section
- [ ] Has Done Criteria/Output section
- [ ] Cross-references use correct names
- [ ] Filename matches trigger/name

## Current Status

### Standardized Commands
- `/brainstorm` - Quick reference + summary + delegates to skill
- `/write-plan` - Quick reference + summary + delegates to skill
- `/execute-plan` - Quick reference + summary + delegates to skill
- `/check-setup` - Full format with steps
- `/refs` - Simple display command
- `/tdd-multi-component-autoloop` - Executive summary + full reference
- `/tdd-vector-store-manager-autoloop` - Executive summary + full reference

### Skills Needing Standardization

Many skills exceed 300 words. Future work should add operative summaries to top while keeping extended content in Reference section:

| Skill | Words | Status |
|-------|-------|--------|
| writing-skills | 2934 | Needs summary |
| testing-skills-with-subagents | 1939 | Needs summary |
| systematic-debugging | 1508 | Needs summary |
| test-driven-development | 1478 | Needs summary |
| testing-anti-patterns | 1253 | Needs summary |
| browsing | 1002 | Needs summary |
| (etc.) | | |

Target format for oversized skills:
1. Add "Quick Reference" section (150-300 words) at top
2. Keep existing content under "## Reference" section below
