- Status: done
- Summary: Post-release process and gap review for the improvement-round cycle (reference: `20260322-dungeoncrawler-release-next` infrastructure lane). Three gaps identified: GAP-GATE-TRANSITION-ROUTING (ROI 18) — QA BLOCK/APPROVE verdicts had no automated routing to Dev/PM; all 5 gate transitions this cycle required manual CEO intervention, causing multi-day delay; fix already queued as dev-infra inbox item `20260405-gate-transition-routing-gap`. GAP-REGRESSION-CHECKLIST-DEBT (ROI 8) — 11 stale unchecked regression checklist items (oldest: 2026-02-24, 40+ days) with no corresponding unit-test inbox items and no pm-infra escalation; violated cycle-start protocol (>14 days → escalate); items marked `[!]` and escalated to pm-infra in this cycle. GAP-SUITE-MISSING-PATH-AUDIT (ROI 12) — QA suites had no hardcoded-path detection; the `/home/keithaumiller` → `/home/ubuntu` path migration broke `publish-forseti-agent-tracker.sh` silently for weeks while bash-n, lint, and all 5 required suites passed throughout; new suite needed. Operator audit clean: bash -n PASS (103 scripts), lint-scripts.sh 0 issues, 5 required suites PASS. Regression checklist updated (11 stale items escalated). Commit: see below.

## Gaps identified

### Gap 1: GAP-GATE-TRANSITION-ROUTING (ROI 18)
**What happened:** QA issued BLOCK and APPROVE verdicts, but no automated routing existed to forward them to Dev (on BLOCK) or PM (on APPROVE). Five consecutive gate transitions this cycle required manual CEO intervention. This caused multi-day stagnation per KB lesson `20260330-qa-block-dev-routing-gap.md`.

**Follow-through action item:**
- Owner: dev-infra
- Item: `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap/` (already queued, ROI 18)
- Acceptance criteria: `agent-exec-loop.sh` automatically routes QA BLOCK → Dev fix inbox item and QA APPROVE → PM signoff inbox item without CEO intervention; confirmed by a functional test showing the correct item appears in the correct seat's inbox.
- Verification: `bash -n scripts/agent-exec-loop.sh` PASS + functional test in dev-infra outbox.

### Gap 2: GAP-REGRESSION-CHECKLIST-DEBT (ROI 8)
**What happened:** 11 regression checklist items accrued without corresponding unit-test inbox items or pm-infra escalation. Oldest item is 2026-02-24 (40+ days). The cycle-start checklist protocol requires items >14 days with no inbox item to be escalated to pm-infra, but this was never triggered — creating a growing backlog with ambiguous ownership (QA writes entries, dev-infra does the work, but no one dispatches the unit-test inbox items).

**Follow-through action item:**
- Owner: pm-infra
- AC: pm-infra reviews the 11 escalated stale items and issues a batch defer/close decision for each by end of current cycle; any item re-opened gets a unit-test inbox item dispatched to dev-infra with ROI and AC.
- Verification: zero `[!]` entries remain open in `qa-regression-checklist.md` without a pm-infra resolution note.
- Note: items already marked `[!]` in checklist by qa-infra this cycle.

### Gap 3: GAP-SUITE-MISSING-PATH-AUDIT (ROI 12)
**What happened:** The HQ script path migration (`/home/keithaumiller` → `/home/ubuntu`) broke `publish-forseti-agent-tracker.sh` silently for weeks. All 5 required suites (bash-n, lint, qa-suite-manifest-validate, seat-instructions-completeness, bash-syntax-check) passed throughout. There is no QA suite that scans for hardcoded `/home/` paths in scripts. This is a structural gap — CEO hotfixes for the path migration were caught manually, not by automated QA.

**Follow-through action item:**
- Owner: dev-infra
- AC: a `no-hardcoded-paths` suite is added to `qa-suites/products/infrastructure/suite.json` with `required_for_release: true`; suite scans `scripts/*.sh` and `scripts/*.py` for literal `/home/keithaumiller` (or any `/home/` path not under `/home/ubuntu`); `python3 scripts/qa-suite-validate.py` PASS after addition; suite run confirms PASS on current state.
- Verification: `python3 scripts/qa-suite-validate.py` PASS + suite command exits 0 on clean state.
- ROI: 12 — prevents silent path regressions on future server migrations.

## Next actions
- dev-infra: `sessions/dev-infra/inbox/20260405-gate-transition-routing-gap` (ROI 18) — highest priority
- pm-infra: batch defer/close decision for 11 stale regression checklist items (ROI 8)
- dev-infra: add `no-hardcoded-paths` suite to infrastructure suite.json (ROI 12) — new item, no inbox yet; pm-infra to dispatch or CEO to authorize direct delegation

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 12
- Rationale: Three structural QA gaps addressed: gate-transition routing (highest leverage, already queued), regression checklist debt (prevents 40-day limbo accrual), and path-audit suite (prevents silent breakage on future server migrations). The no-hardcoded-paths suite is new and requires a dispatch decision.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260322-improvement-round
- Generated: 2026-04-05T16:12:56Z
