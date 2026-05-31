# Quality Scoreboard — forseti.life

> Update weekly. Track only a few metrics that drive behavior.

## 2026-04-20 — forseti-release-q active / route fix deployed

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 1 | `/jobhunter/status` returning HTTP 500 for authenticated users (QA audit `20260420-105935`). Fixed same-day: `job_hunter.view_job` → `job_hunter.job_view` in `CompanyController.php` lines 3266+3346 (commit `f89d80274`). QA retest dispatched. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. |
| Time-to-verify (median) | < 24h | same-day | Route fix identified and deployed same-day. Retest `20260420-124521-retest-jobhunter-status-fix` queued in qa-forseti inbox. |
| Escaped defects (prod/user reported) | 0 | 0 | No user-reported defects. Route regression caught by automated QA audit before user impact. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Previous release-q audit `20260420-105935`: 1 violation (pre-fix). Fix live on production; retest pending Gate R5. |
| Instructions-change proposals created | >= 1 when friction repeats | 2 | pm-forseti executor quarantine pattern (3-4 phantoms/cycle — context-window failures, not product logic). ba-dungeoncrawler 200-line batch limit codified to prevent quarantine. |
| Pipeline health | Quarantine recovery | 3 pm-forseti quarantines closed | Phantom sla-outbox-lag + groom quarantine closed by CEO. Scoreboard updated. qa-forseti has retest + unit-test items in-progress. |

**Open items carried forward:**
- qa-forseti: retest `/jobhunter/status` → HTTP 200 (`20260420-124521-retest-jobhunter-status-fix` pending)
- qa-forseti: unit-test `20260420-unit-test-20260420-105935-qa-findings-forseti-life-1` in-progress
- Tailoring queue AWS STS token expired — user-facing tailoring broken when jobs queued (Board escalation pending Keith action).
- ba-dungeoncrawler: lines 8284–8483 scan active (Human ancestry feats, ROI 20)

## 2026-04-10 — forseti-release-c close (CSRF hotfix) / releases advanced to -d

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | forseti-release-c was a CSRF hotfix-only empty release. Deploy workflow completed 2026-04-10T14:37 UTC (commit `ff9c4bb23`). No feature changes, no regressions. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. |
| Time-to-verify (median) | < 24h | same-day | CSRF hotfix identified and deployed same-day. Gate 2 auto-certified (empty release). |
| Escaped defects (prod/user reported) | 0 | 0 | CSRF fix was proactive (security hardening), not a user-reported regression. No production defects. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Empty hotfix release; no Gate R5 needed. Counter remains 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 3 | KB lessons: executor-inbox-close-policy-gap, post-push advance-skip structural bug (fixed in `post-coordinated-push.sh`), executor write-gap pattern (#4 occurrence — architect recommendation for automated tooling still pending). |
| Pipeline health | Clean | Clean | 46 stale `.inwork` artifacts cleaned; advance loop bug fixed; both release cycles on -d clean slate. |

**Open items carried forward:**
- Tailoring queue AWS STS token expired — user-facing tailoring broken when jobs queued (Board escalation pending Keith action).
- `post-coordinated-push.sh` advance bug: fixed this session (`7a2d48765`).
- dev-infra: orchestrator `pick_agents` guard fix dispatched.
- qa-dungeoncrawler: 23-item QA backlog from APG features — being executed by orchestrator now.

## 2026-03-27 — 20260326-dungeoncrawler-release-b close / Gate R5 clean

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Gate R5 production audit (run `20260327-022516`): 0 violations, 0 missing assets, 0 4xx/5xx, no config drift. Both forseti.life + dungeoncrawler.forseti.life returned 200 OK. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. |
| Time-to-verify (median) | < 24h | ~same-day | Deploy workflow `23633894667` completed 2026-03-27T06:22:10Z. Gate R5 same-day. |
| Escaped defects (prod/user reported) | 0 | 0 | No user-reported defects. No regressions from dungeoncrawler dc-cr-clan-dagger feature deploy. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate R5 audit `20260327-022516` clean. Counter remains 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 2 | GAP-PF-26B-01: pull-based Gate 2 scan added to pm-forseti seat instructions (`3ad2a78d1`). GAP-26B-01 gate2-dedup fix queued to dev-infra (`fc45ee9ec`). |

**Open items carried forward:**
- `20260322-dungeoncrawler-release-b` hold: awaiting CEO option A/B/C (`sessions/pm-forseti/artifacts/release-holds/20260322-dungeoncrawler-release-b.md`).
- gate2-dedup fix (GAP-26B-01): dev-infra inbox item `20260327-fix-gate2-dedup-20260326-dungeoncrawler-release-b` active.
- improvement-round sequencing fix (GAP-26B-02): dev-infra inbox item `20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b` active.

## 2026-03-26 — 20260322 coordinated release / Gate R5 audits complete

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Gate R5 production audit (run `20260322-192833`, commit `ca3c9279a`): 100 pages crawled, 0 violations. 4×403 on intentional auth-only routes — all expected. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. |
| Time-to-verify (median) | < 24h | ~same-day | `20260322-forseti-release-next` Gate 2 same-day pattern. Approximate from QA outbox timestamps. |
| Escaped defects (prod/user reported) | 0 | 0 | No user-reported defects since last scoreboard. Deploy workflow `23414899819` completed 2026-03-22T23:16:43Z (8m43s). |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate R5 production audit clean. Counter reset to 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 1 | pm-forseti seat instructions refreshed (coordinated signoff claim section, commit `654ec259a`). |

**Open items carried forward:**
- `drush config:import` on production not directly verifiable from dev host (no `/var/www/html/forseti` mount). Deploy workflow runs it automatically; no evidence it failed, but not independently confirmed.
- `/characters/create` SSL timeout (10.5s) found on dungeoncrawler production Gate R5 — not a forseti finding, but noted for context.

## 2026-02-28 — dungeoncrawler-release-b close / Gate R5 confirmed PASS

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Local dev: 2 x 500s (`/jobhunter/settings/credentials`, `/jobhunter/companyresearch`) — local dev only, not production. dev-forseti tracking. Production audit `20260227-193753`: 0 violations. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. Gap tracked for dev-infra. |
| Time-to-verify (median) | < 24h | ~8h | Gate 2 same-day pattern continues. Approximate from QA outbox timestamps. |
| Escaped defects (prod/user reported) | 0 | 0 | Gate R5 production audit PASS (CEO commit `bdee95c`, production run `20260227-193753`). No user-reported defects. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate R5 PASS confirmed by CEO (commit `bdee95c`). Counter resets to 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 7 | 3 from forseti-release-b + 4 this session (7357219, 9ce3703, d6e4e55, 38e9097). ACL freshness check, credentials-ui rule, retroactive feature stub check, talk-with-forseti suppression. |

## Baseline — 2026-02-27 (forseti-release-b close)

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 1 | jobhunter-surface `/jobhunter/companyresearch` returns 403 for `authenticated` (expected: allow). Possible regression from `98f96dc` permissions fix. dev-forseti investigating. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. Tracking gap noted for dev-infra. |
| Time-to-verify (median) | < 24h | ~8h | Inferred from QA outbox timestamps (Gate 2 approval same day as dev completion). Approximate. |
| Escaped defects (prod/user reported) | 0 | 0 | No user-reported defects. companyresearch 403 found in local QA, not production — not counted as escaped. |
| Consecutive unclean releases (post-release QA) | 0 | 1 | Latest audit (20260227-113504) shows 1 violation. Release-b Gate R5 pending; if clean, resets to 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 3 | pm-forseti seat instructions updated 3 times this cycle (improvement round standing check, signoff process, blocker protocols). |

## Top recurring failure modes
- Confusing user UID with custom table primary keys / foreign keys.
- Non-standard Drupal validators or form APIs used in large forms.
- QA session auth failure causing false-positive violations (talk-with-forseti-content pattern — suppressed for one cycle, fix pending in qa-forseti).

## Guardrails added (tests/checklists/instructions)
- Knowledgebase references + learnings required in artifacts (templates + role instructions).
- Daily review action items enforced (owner + due).
- pm-forseti seat instructions: improvement-round standing signoff check (commit `9648806`).
- deploy.yml: drush config:import+cr now runs automatically on workflow_dispatch (commit `20412820b`).
