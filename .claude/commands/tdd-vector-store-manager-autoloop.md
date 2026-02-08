# TDD autoloop: Livewire Vector Store Manager

## Executive Summary

- **Purpose**: Strict TDD autoloop for Vector Store Manager Livewire component only
- **Limits**: 3-6 TDD slices per session, then stop and summarize
- **Test command**: `./scripts/run-livewire-vector-store-manager-tests.sh` (only this)
- **Stop when**: Slices complete, >3 tests break unexpectedly, or blocking issue found
- **Bail rules**: No cheating TDD, no weakening assertions, no production code before RED

## Quick Reference

| Item | Value |
|------|-------|
| Test file | `tests/Feature/Livewire/VectorStoreManagerTest.php` |
| View file | `resources/views/livewire/vector-store-manager.blade.php` |
| Test script | `./scripts/run-livewire-vector-store-manager-tests.sh` |
| Slices/session | 3-6 |
| Test log | `test-logs/vector-store-manager-tests.txt` |

---

## Overview

You are the `tdd-tall-specialist` agent running a **strict TDD autoloop** focused exclusively on the **Livewire Vector Store Manager** UI and its immediate backend logic.

Your goal is to improve correctness, robustness, and clarity of the Vector Store Manager while obeying **red → green → refactor** on each slice, and using the project's testing conventions.

---

## Scope

You may work only within this scope unless explicitly instructed otherwise:

- **Tests (primary focus):**
    - `tests/Feature/Livewire/VectorStoreManagerTest.php`
    - Shared test helpers and fixtures when needed:
        - `tests/TestCase.php`
        - `tests/UsesTestDatabase.php`
        - `tests/Fixtures/*` and `tests/TestData/*`
        - Any reusable traits under `tests/Concerns/*`

- **Implementation (secondary, only after RED):**
    - Livewire view:
        - `resources/views/livewire/vector-store-manager.blade.php`
    - Any clearly related backend pieces that are dedicated to the Vector Store Manager, for example:
        - Services, actions, jobs, or models under `app/` that are *specifically* for vector store operations used by this component.

- **Documentation/context (read only):**
    - `VECTOR_STORE_MANAGER_IMPROVEMENTS.md`
    - `VECTOR_STORE_MANAGER_QUICK_REFERENCE.md`
    - `LIVEWIRE_COMPONENTS_AUDIT_REPORT.md` (for naming and testing patterns)
    - Any relevant testing docs such as:
        - `FRONTEND_TESTING_BLUEPRINT.md`
        - `SPRINT_3_TESTING_GUIDE.md`
        - `COMPLETE_TESTING_CHECKLIST_SPRINTS_1-4.md`

---

## Allowed tools and commands

You may:

- Read and edit files within this repo.
- Use the Terminal / shell tool **only with this command**:

```bash
./scripts/run-livewire-vector-store-manager-tests.sh
```

Do **not** run `php artisan test` or `php artisan dusk` or `npx playwright test` directly in this autoloop. Always go through the helper script so the human can control what’s being run.

You may *suggest* additional test commands (e.g., Dusk or Playwright specs) in your natural-language summary, but you must not execute them yourself from this command.

---

## Autoloop workflow

Work in **small, bounded TDD slices**. For each invocation of this command, aim for **3–6 slices** and then stop and summarize.

### 1. Load context

1. Read:
    - `tests/Feature/Livewire/VectorStoreManagerTest.php`
    - `resources/views/livewire/vector-store-manager.blade.php`
    - `VECTOR_STORE_MANAGER_IMPROVEMENTS.md`
    - `VECTOR_STORE_MANAGER_QUICK_REFERENCE.md`
    - `LIVEWIRE_COMPONENTS_AUDIT_REPORT.md` (just the parts relevant to this component)
2. Briefly infer:
    - What user behaviors the Vector Store Manager is supposed to support.
    - Which behaviors are well covered by tests.
    - Which behaviors are under-tested or fragile.

Summarize this understanding before you start editing anything.

### 2. Plan bounded TDD slices (no code yet)

Produce a **short TDD plan** for this session:

- Propose **3–6 slices**, each of the form:

    - **Slice N**
        - *Goal*: One small, concrete behavior improvement (e.g., better validation, clearer error handling, improved state transitions, extra edge case).
        - *Tests to update/add*: Which methods in `VectorStoreManagerTest.php` (or new ones) you’ll modify/add.
        - *Likely implementation touch points*: Which parts of the Blade view or backend code will likely change.
        - *Test command*: Always `./scripts/run-livewire-vector-store-manager-tests.sh`.

- Keep slices independent where possible.

Do **not** modify any code in this step. Planning only.

### 3. Execute slices one by one

For each slice in your plan, follow **RED → GREEN → REFACTOR**:

#### 3.1 RED — Tests first

- Modify or add tests in `VectorStoreManagerTest.php` (and test helpers if needed) to express the new or improved behavior.
- Focus on realistic Livewire interactions:
    - Component mounts with specific state
    - Actions and events (e.g., saving vectors, reindexing, clearing stores)
    - Error handling paths and validation rules

- After changing or adding tests, run:

  ```bash
  ./scripts/run-livewire-vector-store-manager-tests.sh
  ```

- Ensure at least one test fails **for the right reason**:
    - If tests unexpectedly pass:
        - Strengthen the assertions or improve the scenario setup.
        - Re-run the script until you have a meaningful failing test that captures the new requirement.

**Do not** touch production code until this RED condition is satisfied.

#### 3.2 GREEN — Minimal implementation

- Once the tests fail correctly, implement the **smallest** production change required to pass them.
- Prioritize:
    - Updating Livewire component logic and bindings that directly affect tested behavior.
    - Extracting or adjusting small helper methods for clarity, but only when needed for the behavior under test.
- Keep changes localized; avoid touching unrelated components or services.

- Re-run the tests:

  ```bash
  ./scripts/run-livewire-vector-store-manager-tests.sh
  ```

- Confirm:
    - All tests in `VectorStoreManagerTest.php` pass.
    - If you accidentally broke unrelated tests in that file due to a mistake, fix the implementation or strengthen test setup rather than weakening assertions.

#### 3.3 REFACTOR — Clean up without changing behavior

- With tests passing, refactor:
    - Remove duplication and clarify intent in both tests and production code.
    - Improve naming, extracted methods, and consistent patterns with other Livewire tests in this repo.
- Do **not** change behavior or relax assertions during refactor.

- Re-run:

  ```bash
  ./scripts/run-livewire-vector-store-manager-tests.sh
  ```

- Ensure you stay green. If not, fix the refactor, not the tests.

Move to the next slice only when the current one is green and refactored.

---

## Constraints and good behavior

You must obey these rules:

- **No cheating TDD**:
    - Do not comment out or delete tests just to get green.
    - Do not weaken assertions solely to make tests pass.
    - Do not write production code for a new behavior before you have at least one failing test describing that behavior.

- **Keep slices small**:
    - Prefer 1–2 new/changed tests and 1–3 production files per slice.
    - If more than ~3 tests break unexpectedly, stop and re-plan instead of patching randomly.

- **Stay in scope**:
    - Do not modify unrelated Livewire components or generic infrastructure from this command, unless a tiny shared helper clearly benefits multiple Vector Store Manager test cases.

- **No heavy runners**:
    - From this command, never run the full test suite, Dusk, or Playwright.
    - Only call `./scripts/run-livewire-vector-store-manager-tests.sh`.

You may **suggest** in your summary that a human (or another command) run Dusk or Playwright for UI regression (e.g., `php artisan dusk --env=dusk.local` or `npx playwright test`), but do not execute them yourself here.

---

## Session completion and output

After you have executed your bounded number of slices or hit a sensible stopping point:

1. Summarize:
    - Which behaviors of the Vector Store Manager are now explicitly tested in `VectorStoreManagerTest.php`.
    - What implementation changes you made to `vector-store-manager.blade.php` and any backend code.
    - Any improvements in error handling, validation, or UX you introduced.

2. Highlight artifacts:
    - Latest test log: `test-logs/vector-store-manager-tests.txt`
    - Last-run metadata: `test-results/.last-run.json`

3. Propose next steps:
    - Additional TDD slices for this component.
    - Follow-up for broader regression (e.g., running all Livewire tests or specific Dusk/Playwright flows).

Always operate under strict **red → green → refactor** discipline with `./scripts/run-livewire-vector-store-manager-tests.sh` as your canonical test command for this autoloop.
