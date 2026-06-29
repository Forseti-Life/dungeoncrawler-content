- Status: done
- Summary: Completed post-release gap review for `20260402-dungeoncrawler-release-c` (the "fake/empty" release). Three root-cause gaps were identified, documented in KB, and the Gate 2 section of `runbooks/shipping-gates.md` was updated with an empty-release waiver procedure. Follow-through items already exist in `dev-infra` inbox (`improvement-round-fake-no-signoff-release`, `fake-no-signoff-release-id-improvement-round`). No new inbox items created — dev-infra's improvement-round covers both the Python bug and the orchestrator guard fix.

## Next actions
- dev-infra: fix `_dispatch_release_close_triggers` NameError in `orchestrator/run.py` (GAP-IR-20260405-1)
- dev-infra: add orchestrator guard — do not dispatch RELEASE-CLOSE-TRIGGER for a release with 0 in_progress features (GAP-IR-20260405-2)
- All: empty-release Gate 2 waiver procedure now documented in `runbooks/shipping-gates.md` (effective immediately)

## Blockers
- None

## Gaps identified

### GAP-IR-20260405-1 — Python NameError in orchestrator/run.py silently drops close trigger
**Root cause:** At 2026-04-05 17:59, the orchestrator logged `RELEASE-CLOSE-TRIGGER-ERR: name '_dispatch_release_close_triggers' is not defined`. The first dispatch attempt failed with a NameError; a second code path succeeded. The trigger fired eventually but only because fallback logic ran. A future tick where only the failing path runs would silently miss the trigger.

**Owner:** dev-infra
**Acceptance criteria:** `orchestrator/run.py` runs without NameError during release-close-trigger execution; CI smoke passes; the function is callable from all call sites.
**Verification:** grep `_dispatch_release_close_triggers` in `orchestrator/run.py`; run `python -c "import orchestrator.run"` without error; verify orchestrator log shows no `RELEASE-CLOSE-TRIGGER-ERR` on next tick.
**ROI:** 40 — silent failures in orchestrator logic are high-leverage bugs; missed triggers break the entire release pipeline.

### GAP-IR-20260405-2 — Auto-close trigger fires on empty new release, creating a Gate 2 deadlock
**Root cause:** When release-b closed at 17:59, release-c was immediately created. The orchestrator's FEATURE_CAP check (`15/10 features in_progress for dungeoncrawler`) counted all dungeoncrawler in_progress features regardless of release_id. Release-c was 0 seconds old with 0 features, but the trigger fired anyway — dispatching `release-close-now` to pm-dungeoncrawler. PM correctly tried to sign off but Gate 2 guard required a QA APPROVE artifact that could not exist (zero shipped features). Result: a 5-hour deadlock requiring CEO waiver intervention.

**Owner:** dev-infra
**Acceptance criteria:** Orchestrator does not dispatch RELEASE-CLOSE-TRIGGER for a release that has 0 features currently activated (`Status: in_progress` AND current `release_id`). Guard condition: `feature_count_for_current_release == 0 → skip FEATURE_CAP trigger`.
**Verification:** Integration test: create a release, immediately query RELEASE-CLOSE-TRIGGER conditions with 0 features active → trigger should NOT fire. Also verify trigger still fires correctly at ≥10 features.
**ROI:** 55 — this gap created a 5-hour CEO interrupt and a stalled pipeline. The fix prevents entire class of empty-release deadlocks.

### GAP-IR-20260405-3 — No documented Gate 2 waiver procedure for empty releases
**Root cause:** pm-dungeoncrawler escalated correctly, but no runbook described the waiver path. CEO had to invent the artifact format on the fly (`sessions/qa-dungeoncrawler/outbox/20260405-gate2-waiver-release-c.md`). The procedure is now documented in `runbooks/shipping-gates.md` (commit below).

**Owner:** ceo-copilot-2 (already resolved)
**Acceptance criteria:** `runbooks/shipping-gates.md` Gate 2 section includes an "Empty-release waiver" subsection with: waiver artifact location, required content format, and who is authorized to issue it.
**Verification:** `grep -i "empty.release waiver" runbooks/shipping-gates.md` returns a match.
**ROI:** 20 — low-frequency event but without documentation it requires CEO intervention every time.

## Commits
- `8b3aab8e` — runbooks/shipping-gates.md empty-release waiver procedure + KB lesson (GAP-IR-20260405)

## ROI estimate
- ROI: 45
- Rationale: The empty-release deadlock consumed ~5 hours of CEO/PM cycles and required a manual waiver. Fixing the two orchestrator bugs (GAPs 1 and 2) eliminates the entire class of empty-release pipeline stalls. Documentation fix (GAP 3) prevents repeat CEO interrupts for the same edge case.
