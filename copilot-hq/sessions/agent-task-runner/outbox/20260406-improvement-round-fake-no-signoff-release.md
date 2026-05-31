- Status: done
- Summary: Post-release gap review for the `fake-no-signoff-release` incident (root cause: `20260402-dungeoncrawler-release-c` — an empty release auto-closed by the orchestrator with no features in scope). Three root-cause gaps were identified by cross-seat synthesis of CEO, pm-forseti-agent-tracker, dev-forseti, and dev-forseti-agent-tracker outboxes: (1) orchestrator `_dispatch_release_close_triggers` had a Python NameError causing silent trigger drops (GAP-IR-1); (2) FEATURE_CAP auto-close fired on the freshly-created release-c with 0 features, creating an irresolvable Gate 2 deadlock — an empty-release guard is missing in the orchestrator (GAP-IR-2); (3) `scripts/improvement-round.sh` dispatched inbox items to 26+ agents without checking for real PM signoff and without filtering by `website_scope`, flooding every seat with a fast-exit waste item (GAP-26B-02). All three gaps have follow-through items queued in `dev-infra` inbox and/or are resolved in runbook (Gate 2 waiver procedure committed `8b3aab8e`, stale-signoff guard committed `977372dc`). No new code changes from this seat.

## Gaps identified and actions taken

### GAP-IR-20260405-1 — Python NameError silently drops orchestrator close trigger (ROI: 40)
**What happened:** Orchestrator logged `RELEASE-CLOSE-TRIGGER-ERR: name '_dispatch_release_close_triggers' is not defined`. Trigger fired only because a second fallback code path ran. Any future tick where only the failing path runs would silently miss the trigger, leaving a release open indefinitely.

**Follow-through:** dev-infra inbox item `20260405-improvement-round-fake-no-signoff-release` (and cross-references in `fake-no-signoff-release-id-improvement-round`). Acceptance criteria: `_dispatch_release_close_triggers` callable from all call sites; `python -c "import orchestrator.run"` clean; no `RELEASE-CLOSE-TRIGGER-ERR` on next tick.

**Owner:** dev-infra. **Status:** queued.

### GAP-IR-20260405-2 — FEATURE_CAP auto-close fires on empty new releases, creating Gate 2 deadlock (ROI: 55)
**What happened:** When release-b closed, release-c was immediately created. The FEATURE_CAP check counted all dungeoncrawler in_progress features regardless of release_id. Release-c had 0 activated features but the trigger fired, dispatching `release-close-now` to pm-dungeoncrawler. PM correctly attempted signoff but Gate 2 guard required a QA APPROVE artifact that could not exist. Result: 5-hour deadlock requiring CEO waiver intervention.

**Follow-through:** Same dev-infra inbox items. Guard condition: `feature_count_for_current_release == 0 → skip FEATURE_CAP trigger`. Runbook fix (Gate 2 empty-release waiver procedure) already in `runbooks/shipping-gates.md` (commit `8b3aab8e`). KB lesson at `knowledgebase/lessons/20260405-empty-release-auto-close-deadlock.md`.

**Owner:** dev-infra (orchestrator guard). **Status:** runbook and KB done; orchestrator fix queued.

### GAP-26B-02 — improvement-round.sh dispatches to all 26+ agents without PM signoff check or website_scope filter (ROI: 15)
**What happened:** `scripts/improvement-round.sh` created inbox items for every agent regardless of whether the release had a real PM signoff. Agents received a `fake-no-signoff-release` item with no real release context. 20+ seats fast-exited, wasting execution slots. Additionally, no `website_scope` filter means dungeoncrawler improvement-round items land in forseti-scoped seats each cycle.

**Follow-through actions (already queued):**
- `scripts/improvement-round.sh` stale-signoff artifact guard: committed `977372dc` by dev-infra.
- `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` — add `website_scope` filter and enforce `YYYYMMDD-<release-id>` suffix validation (reject bare `improvement-round` or non-date suffixes like `fake-no-signoff-release`).

**Owner:** dev-infra. **Status:** stale-signoff guard done; scope-filter queued.

## Next actions
- dev-infra: fix `_dispatch_release_close_triggers` NameError (`orchestrator/run.py`) — GAP-IR-20260405-1 (ROI 40)
- dev-infra: add empty-release guard in orchestrator FEATURE_CAP logic — GAP-IR-20260405-2 (ROI 55)
- dev-infra: implement `website_scope` filter and release-id suffix validation in `improvement-round.sh` — GAP-26B-02 (ROI 15)
- All seats: Gate 2 empty-release waiver procedure is live in `runbooks/shipping-gates.md` — no further action needed

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: All three gaps are already actioned by owning seats. This outbox provides cross-seat synthesis and closes the agent-task-runner inbox item. The underlying fixes (orchestrator guard ROI 55, NameError ROI 40) are the high-leverage items; this review is their tracking record.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260405-improvement-round-fake-no-signoff-release
- Generated: 2026-04-06
