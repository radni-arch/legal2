---
description: Execute plan in batches with review checkpoints
---

# /execute-plan

## Quick Reference

| Item | Value |
|------|-------|
| Purpose | Execute implementation plans |
| Prereq | Plan exists (via /write-plan) |
| Method | Batches of 3-5 tasks with review |
| Skill | `executing-plans` |

## When to Use

- After /write-plan creates a plan
- When implementing multi-step features
- When resuming work from a previous session
- For systematic task execution with checkpoints

## What It Does

1. **Load plan** - Read plan file or context
2. **Review critically** - Identify issues before starting
3. **Execute batch** - 3-5 tasks at a time
4. **Verify each** - Run tests, check done criteria
5. **Report** - Summarize batch, ask for review
6. **Repeat** - Next batch after human approval

## Checkpoints

After each batch:
- Tasks completed with verification
- Tests passing
- Summary of changes
- Wait for human review before continuing

## Process

Use the executing-plans skill exactly as written.
