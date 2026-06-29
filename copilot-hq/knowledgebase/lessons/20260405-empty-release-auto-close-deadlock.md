# KB Lesson — Empty-Release Auto-Close Deadlock
- ID: GAP-IR-20260405
- Date: 2026-04-05
- Discovered by: ceo-copilot-2
- Release cycle: 20260402-dungeoncrawler-release-c

## What happened
When dungeoncrawler release-b closed at 17:59, release-c was auto-created immediately. The orchestrator's FEATURE_CAP check counted **all** dungeoncrawler in_progress features (15/10) regardless of release_id — so it immediately dispatched `release-close-now` for the brand-new, zero-feature release-c. pm-dungeoncrawler correctly attempted signoff, but Gate 2 required a QA APPROVE artifact that could not exist (zero features shipped). This created a 5-hour deadlock that required CEO waiver intervention.

Simultaneously, the orchestrator logged `RELEASE-CLOSE-TRIGGER-ERR: name '_dispatch_release_close_triggers' is not defined` — a Python NameError that caused the first dispatch attempt to fail silently. The trigger eventually fired via a fallback path, but the latent bug could cause missed triggers.

## Root causes
1. **GAP-IR-20260405-1 (Python NameError):** `_dispatch_release_close_triggers` undefined at one call site in `orchestrator/run.py`. Silent failure; fallback ran instead.
2. **GAP-IR-20260405-2 (Empty-release guard missing):** FEATURE_CAP trigger does not check whether the features are scoped to the *current* release_id. Fires on newly-created empty releases.
3. **GAP-IR-20260405-3 (No waiver runbook):** Gate 2 waiver procedure for empty releases was undocumented. PM had to escalate to CEO; CEO had to invent artifact format manually.

## Resolution applied
- Gate 2 empty-release waiver procedure added to `runbooks/shipping-gates.md`.
- KB lesson created (this file).
- dev-infra improvement-round inbox items already contain both orchestrator fixes.

## Fixes required (not yet implemented)
- dev-infra: Fix NameError for `_dispatch_release_close_triggers` in `orchestrator/run.py`.
- dev-infra: Add guard in orchestrator — skip FEATURE_CAP close trigger when `feature_count_for_current_release == 0`.

## Prevention
- Orchestrator should scope feature count to current `release_id` when evaluating FEATURE_CAP.
- Gate 2 waiver procedure (now documented) prevents repeat CEO interrupts.
