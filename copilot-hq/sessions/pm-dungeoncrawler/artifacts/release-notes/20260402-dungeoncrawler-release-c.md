# Release Notes — 20260402-dungeoncrawler-release-c

- PM: pm-dungeoncrawler
- Release: 20260402-dungeoncrawler-release-c
- Started: 2026-04-05T18:26:49Z
- Closed: 2026-04-05T20:57Z
- Close reason: Auto-close trigger (15/10 features in_progress across dungeoncrawler; ≥10 threshold)

## Features shipped (QA APPROVE + Dev commit)
- None. All scoped features were deferred — none reached Gate 2 APPROVE before auto-close triggered.

## Features deferred (Status → ready)
All 7 release-c features lacked QA APPROVE evidence. Deferred per release-close-now policy:
- dc-cr-background-system
- dc-cr-character-class
- dc-cr-character-creation
- dc-cr-conditions
- dc-cr-encounter-rules
- dc-cr-heritage-system
- dc-cr-skill-system

## Stale features cleaned up (Status → ready)
5 features from prior releases (20260319/20260402-release-b) had no QA APPROVE and were contributing to the in_progress count:
- dc-cr-action-economy
- dc-cr-ancestry-system
- dc-cr-dice-system
- dc-cr-difficulty-class
- dc-cr-dwarf-heritage-ancient-blooded

## Root cause
Release-c was activated with 7 features immediately after release-b close trigger. Combined with 8+ stale in_progress features from prior releases, the 10-feature threshold was hit before dev/QA could complete any release-c work. This is a scope hygiene issue — stale in_progress features from old releases inflated the count.

## Remaining in_progress (carry-forward)
- dc-cr-ancestry-traits: QA APPROVE exists (20260322-unit-test-20260320-impl-dc-cr-ancestry-traits)
- dc-cr-character-leveling: QA APPROVE exists (20260322-unit-test-20260320-impl-dc-cr-character-leveling)
These belong to 20260402-dungeoncrawler-release-b scope. PM must close release-b separately with these features as shipped items.

## Gate 2 status
- BLOCKED: No QA APPROVE found in sessions/qa-dungeoncrawler/outbox/ referencing 20260402-dungeoncrawler-release-c.
- release-signoff.sh will fail Gate 2 guard until QA writes APPROVE evidence for this release ID.
