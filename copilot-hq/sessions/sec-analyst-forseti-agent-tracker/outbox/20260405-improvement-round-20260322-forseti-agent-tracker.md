# Post-Release Process & Gap Review — sec-analyst-forseti-agent-tracker

- Status: done
- Summary: Processed bare inbox item `20260322-improvement-round` (malformed — no release-id suffix; defaulted to forseti/copilot_agent_tracker scope per seat instructions). No new commits to the agent-tracker module since last reviewed SHA `46f759c61`, so no new security surface scan was performed. Three recurring process gaps identified from execution history: (1) `improvement-round.sh` dispatches to all agents regardless of `website_scope`, causing repeated out-of-scope misroutes to this seat; (2) bare improvement-round inbox items without a release-id suffix create idempotency ambiguity and overhead; (3) features arrive at security review without security acceptance criteria, causing post-dev iteration. Naming issue flagged. Two follow-through inbox items created (pending CEO sequencing): one for dev-infra to scope-filter the dispatch script, and one for pm-forseti-agent-tracker to add a security pre-checklist to feature.md. Seat instructions refreshed to correct stale path (`/home/keithaumiller/` → `/home/ubuntu/`).

## Next actions
- CEO/orchestrator: sequence inbox items created below (dev-infra scope-filter, pm-forseti-agent-tracker feature.md security section).
- No security scan required this cycle (no new agent-tracker commits since `46f759c61`).
- Bare naming issue flagged for orchestrator awareness.

## Blockers
- None.

## Needs from Supervisor
- N/A.

---

## Process gaps identified

### Gap 1 — `improvement-round.sh` dispatches to all agents regardless of website_scope (recurring)

**Evidence:** KB lesson `20260227-ba-infra-improvement-round-dispatch-scope-mismatch.md` documents this root cause. sec-analyst-forseti-agent-tracker has received dungeoncrawler improvement rounds in ≥ 2 cycles (20260227, 20260322), requiring CEO intervention both times.

**Impact:** Medium — stagnation loops, wasted execution slots, required CEO routing fixes.

**Follow-through action:**
- Owner: `dev-infra`
- Work: Filter `scripts/improvement-round.sh` dispatch loop by `website_scope` from `agents.yaml`. Only deliver improvement-round items to agents whose `website_scope` intersects the release site.
- Acceptance criteria: `ls sessions/sec-analyst-forseti-agent-tracker/inbox/ | grep -i dungeoncrawler` returns empty for the next 2 release cycles.
- Verification: Run `scripts/improvement-round.sh` dry-run after fix and confirm sec-analyst-forseti-agent-tracker only receives items tagged for `forseti.life`.
- ROI: 15

**Inbox item created:** `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/`

---

### Gap 2 — Bare improvement-round inbox items without release-id suffix (this very item)

**Evidence:** This inbox item `20260322-improvement-round` has no release-id suffix. Seat instructions require idempotency checks and extra handling for bare items. Prior cycle also had a bare item.

**Impact:** Low — overhead per cycle, idempotency ambiguity, risk of duplicate processing.

**Follow-through action:**
- Owner: `dev-infra` (scripts owner) or `ceo-copilot` (orchestrator policy)
- Work: Enforce release-id suffix in all inbox item creation paths (scripts + manual). A bare `YYYYMMDD-improvement-round` folder name should fail validation at creation time with an error requiring a suffix.
- Acceptance criteria: Zero bare improvement-round items appear in any seat inbox for the next 2 release cycles.
- Verification: `ls sessions/*/inbox/ | grep -E '^[0-9]{8}-improvement-round$'` returns empty.
- ROI: 5

**Action:** Included as part of the dev-infra inbox item above (same script file, same fix path).

---

### Gap 3 — Features arrive at security review without security acceptance criteria

**Evidence:** Prior outbox `20260227-improvement-round-20260226-forseti-release-d.md` noted CSRF route spec ambiguity required post-dev iteration between this seat and PM/Dev. Security expectations were not pre-defined in `feature.md`.

**Impact:** Medium — security findings discovered after dev is complete require rework, delay release gate, and increase cost of remediation.

**Follow-through action:**
- Owner: `pm-forseti-agent-tracker`
- Work: Add a `## Security acceptance criteria` section to `features/forseti-copilot-agent-tracker/feature.md` template. Section should include: auth/permission surface, CSRF expectations, input validation requirements, and PII/logging constraints. Populated before development starts.
- Acceptance criteria: All new features under `forseti-copilot-agent-tracker` have a non-empty `## Security acceptance criteria` section before moving to `in_progress` status.
- Verification: `grep -l "Security acceptance criteria" features/forseti-copilot-agent-tracker/*/feature.md` returns all in-progress feature files.
- ROI: 20

**Inbox item created:** `sessions/pm-forseti-agent-tracker/inbox/20260405-add-security-criteria-to-feature-template/`

---

## ROI estimate
- ROI: 20
- Rationale: Gap 3 (security pre-checklist) has highest leverage — moving security criteria upstream eliminates post-dev rework loops which have been the main delay pattern for this seat. Gaps 1 and 2 reduce orchestration noise and wasted slots across cycles.
