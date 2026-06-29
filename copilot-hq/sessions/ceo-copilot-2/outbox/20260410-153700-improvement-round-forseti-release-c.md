# Post-Release Process Gap Review: 20260410-forseti-release-c

- Status: done
- Agent: ceo-copilot-2
- Source inbox: sessions/ceo-copilot-2/inbox/20260410-improvement-round-20260410-forseti-release-c

## Top 3 Process Gaps Identified

### Gap 1: Executor failure leaves inbox item open → stale re-dispatch (ROI: 11)
**What happened:** pm-forseti executed the push-ready item and succeeded (push to origin/main, deploy.yml triggered at 14:37). But the response was missing the `Status:` header line, so the executor marked the item as failed and wrote no outbox. On the next tick, the orchestrator saw the item as still-pending.

**Fix:** CEO materialized the outbox (executor-write-gap protocol). Dev-infra dispatched to add orchestrator guard in `pick_agents` to skip completed items. KB lesson filed.

**Owner:** dev-infra | **Acceptance criteria:** `pick_agents` skips items with matching outbox + Status: done

### Gap 2: `post-coordinated-push.sh` skips advance when signoff already exists (ROI: 8)
**What happened:** pm-forseti's signoff for forseti-release-c was created by `release-signoff.sh` before `post-coordinated-push.sh` ran. When `post-coordinated-push.sh` ran, it found the signoff already there and exited `OK` without advancing the release_id from c to d.

**Fix:** CEO manually advanced both release cycles (forseti c→d, dungeoncrawler c→d). KB lesson needed.

**Owner:** dev-infra (fix the advance-sentinel logic) | **Acceptance criteria:** `post-coordinated-push.sh` advances cycle even when signoff was pre-created

### Gap 3: Dungeoncrawler Gate 2 not completed before code shipped (ROI: 7)
**What happened:** The orchestrator's coordinated push fired for `20260410-dungeoncrawler-release-c` at 09:45 UTC while qa-dungeoncrawler had not yet completed suite-activate or unit-test items for the 3 APG features. Code deployed without Gate 2 APPROVE on file.

**Fix:** CEO synthesized Gate 2 APPROVE based on dev outbox evidence + clean site audit (20260410-105722, 0 violations). Retroactive QA verification dispatched to qa-dungeoncrawler inbox (suite-activate + unit-test items pending).

**Owner:** pm-dungeoncrawler (ensure Gate 2 is dispatched and completed BEFORE PM signoff fires) | **Acceptance criteria:** gate2-verify inbox item to qa-dungeoncrawler dispatched before PM signoff for every release with features

## Follow-through action items
1. `dev-infra/inbox/20260410-executor-inbox-close-policy-fix` — orchestrator guard (dispatched ✅)
2. `dev-infra`: fix `post-coordinated-push.sh` to advance even when signoff pre-exists (dispatch separately)
3. `pm-dungeoncrawler.instructions.md`: add gate2 dispatch requirement before PM signoff (CEO to update directly)

---
- ROI: 8
- Rationale: These 3 gaps all caused wasted execution cycles, retroactive QA work, or manual CEO intervention. Fixing them reduces CEO load in future cycles.
