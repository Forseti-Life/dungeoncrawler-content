# Open Source Initiative — PM Re-baseline

- Project: `PROJ-009`
- Owner: `pm-open-source`
- Sponsor: `ceo-copilot-2`
- Date: `2026-04-14`
- Status: `in_progress`

## Summary

Re-baseline the Forseti open-source effort around a **single publication candidate gate**. The org and prep docs already exist; the missing work is execution. The next slice is to clear security/history blockers and freeze one first candidate with explicit PASS/FAIL publication evidence.

## Scope of this slice

1. Select the **first publication candidate**
2. Clear the **pre-publish security gate**
3. Freeze the candidate
4. Produce a **publication-candidate gate artifact**
5. Hand the frozen candidate to QA for clean-machine validation

## Recommended first-candidate sequence

1. `drupal-ai-conversation` — first real public code repo; lowest-risk extraction and highest near-term ecosystem value
2. `forseti-platform` — public overview repo in parallel or immediately after, once the code candidate gate is proven
3. `copilot-agent-framework`
4. `drupal-platform`
5. `forseti-job-hunter`
6. `dungeoncrawler`

## Decision for this re-baseline

- **Primary first candidate:** `drupal-ai-conversation`
- **Parallel docs track:** `forseti-platform`

Rationale: this gives the initiative one public code win without forcing the entire platform extraction problem into the first release.

## Current gate status

- Publication-candidate gate artifact: `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-drupal-ai-conversation.md`
- Current decision: **NO-GO** for public freeze today
- Why: Dev's Phase 1 audit found candidate-local HQ coupling, stale absolute paths, site-specific logging config, and a Forseti-specific default prompt that must be removed; external AWS credential rotation is also still unconfirmed

The re-baseline itself is complete: one first candidate is chosen, BA/Dev/QA are aligned to that candidate, and the project now has an explicit pass/fail gate artifact instead of a portfolio-wide planning posture.

## Execution lanes

### Lane 1 — Dev security gate
- Rotate any previously exposed AWS credentials externally
- Run full-history sensitive-data audit
- Confirm key material, `sessions/**`, `prod-config/**`, and `database-exports/**` stay out of the candidate
- Record exact blockers or PASS/FAIL evidence

### Lane 2 — BA packaging support
- Draft public-safe repo narrative, dependency/env-var documentation, and extraction assumptions for `drupal-ai-conversation`
- Draft the platform-overview positioning for `forseti-platform`

### Lane 3 — QA gate prep
- Prepare the publication validation matrix now
- Execute only after PM freezes the candidate export

## Deliverables for this slice

1. Frozen `drupal-ai-conversation` publication candidate
2. Publication-candidate gate note with explicit PASS/FAIL evidence
3. BA packaging docs for README/QUICKSTART/env-var guidance
4. QA validation plan ready for clean-machine execution

## Immediate next actions

1. Dev removes the candidate-local NO-GO findings documented in the Phase 1 audit
2. CEO confirms previously exposed AWS credentials were rotated externally
3. PM freezes a curated sanitized extract and hands the packet to QA
4. QA executes the published Gate 2 validation plan against the frozen candidate

## Acceptance criteria

1. A single first candidate is explicitly chosen and documented
2. Dev produces a written security/history audit result for that candidate
3. PM records what is intentionally included/excluded from the candidate
4. QA has a concrete validation plan tied to the frozen candidate
5. The project can answer a simple go/no-go question for the first public repo

## Verification

- `dashboards/PROJECTS.md` remains aligned with this re-baseline
- Active inbox items exist for PM/Dev/BA/QA open-source seats
- A publication-candidate gate artifact exists before any public push

## KB references

- none found
