# Problem Statement: Release KPI Stagnation (2026-03-28)

## Context

**What is stagnating:**
`20260327-dungeoncrawler-release-b` Gate 2 (unit-test verification) for 4 features is stuck in the qa-dungeoncrawler inbox. Dev completed all 4 features (commits confirmed). All 4 Gate 2 unit-test inbox items have been created. QA has not processed any of them.

**Root cause identified:**
Gate 2 unit-test items were assigned ROI values **significantly lower** than competing QA inbox items:
- `unit-test-...-action-economy`: ROI=56
- `unit-test-...-ancestry-system`: ROI=50
- `unit-test-...-dice-system`: ROI=47
- `unit-test-...-difficulty-class`: ROI=43

Competing items in the same QA inbox have ROI 84–300 (improvement rounds, daily reviews, testgen items, audit reruns, forseti improvement rounds). Under strict ROI-ordered processing, the Gate 2 items will not be reached until all 15+ higher-ROI items are consumed — which may span multiple sessions.

**Secondary issue:**
The ROI assignment for release-bound Gate 2 verification items does not reflect their actual criticality: these items are **release blockers**. A release-blocking item should have an ROI that reflects the cost of a delayed release, not just the item's standalone value.

**Why now:**
The stagnation pattern is identical to GAP-DC-01 from the prior cycle (testgen items held up by ROI ordering). That gap drove 8+ days of pipeline stall. Catching this now — immediately after dev completion — prevents a repeat.

## Goals (Outcomes)
- Get QA to process all 4 Gate 2 items for `20260327-dungeoncrawler-release-b` before any lower-priority QA work
- Establish a policy that release-blocking Gate 2 items always get ROI ≥ the highest competing item in the queue (minimum: ROI=200 for release-blocking Gate 2)
- Update the ROI guidance for qa-dungeoncrawler inbox item creation to reflect this

## Non-Goals
- Changing the ROI values on all existing QA inbox items retroactively
- Changing the ROI ordering mechanism itself (dev-infra scope)
- Blocking other QA work — only asserting priority ordering for release-bound items

## Users / Personas
- pm-dungeoncrawler: release is stalled; needs Gate 2 done before signoff
- qa-dungeoncrawler: needs clear signal on which items are release-blocking vs background
- ceo-copilot: needs to update ROI values on the 4 Gate 2 items (out of pm-dungeoncrawler scope) and/or establish policy
- pm-forseti: push is blocked; dungeoncrawler Gate 2 must clear before coordinated push can happen

## Constraints
- pm-dungeoncrawler cannot modify `sessions/qa-dungeoncrawler/inbox/*/roi.txt` (out of scope — owned by qa-dungeoncrawler)
- The fix requires either CEO/ceo-copilot to update the ROI values, or qa-dungeoncrawler to be directly instructed to prioritize these items
- No code changes required; this is a process/ROI-calibration issue

## Success Metrics
- All 4 Gate 2 items processed by qa-dungeoncrawler (APPROVE or BLOCK) before any item with lower ROI is processed
- ROI guidance updated in qa-dungeoncrawler seat instructions: release-blocking Gate 2 items get ROI ≥ 200
- Gate 2 verification latency for `20260327-dungeoncrawler-release-b`: < 1 session after ROI is corrected

## Dependencies
- CEO/ceo-copilot: update ROI values on 4 Gate 2 items OR instruct qa-dungeoncrawler to prioritize
- qa-dungeoncrawler: process items after ROI is corrected

## Risks
- Without ROI correction, the Gate 2 items may sit unprocessed for 3–5 sessions (pattern from GAP-DC-01)
- If pm-forseti is waiting on dungeoncrawler Gate 2, the coordinated release push is delayed proportionally

## Knowledgebase check
- Pattern: GAP-DC-01 — testgen items were also deprioritized by ROI ordering; caused 8+ day stall (2026-03-20 to 2026-03-27)
- Lesson: `knowledgebase/lessons/20260322-escalation-gates-rationale.md` (gate rationale — Gate 2 is release-critical)
