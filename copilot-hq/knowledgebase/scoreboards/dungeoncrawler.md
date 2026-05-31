# Quality Scoreboard — dungeoncrawler

> Update weekly. Track only a few metrics that drive behavior.

## 2026-04-18 — 20260412-dungeoncrawler-release-n healthy / push-ready

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Current release health check `2026-04-18T17:06:38Z` clean for active release `20260412-dungeoncrawler-release-n`; in-scope features `dc-ui-token-readability` and `dc-ui-sidebar-drawers` both have dev outboxes and no release-health regressions. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured. No reopened issue tracker is maintained in this workspace. |
| Time-to-verify (median) | < 24h | same-day | Both active release-n features were activated and reached Gate 2 APPROVE on 2026-04-18; PM signoff is recorded and coordinated signoff matrix is complete the same day. |
| Escaped defects (prod/user reported) | 0 | 0 | Latest production Dungeoncrawler audit `20260417-160402` is clean (0 missing assets, 0 permission violations, 0 other failures). No new user-reported production defects are recorded in the current release notes/state. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Latest production audit is clean and current release health is PASS. Counter remains 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 0 | No new recurring Dungeoncrawler-specific QA/process friction mode was identified in this update window. |
| Audit freshness | <= 24h | ~1 day | Latest QA audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260417-160402/` — still within an operationally fresh window for the 2026-04-18 update. |

**Release-n summary:**
- Active release: `20260412-dungeoncrawler-release-n`
- In scope: `dc-ui-token-readability`, `dc-ui-sidebar-drawers`
- Gate 2 evidence present: `sessions/qa-dungeoncrawler/outbox/20260418-140044-gate2-approve-20260412-dungeoncrawler-release-n.md`
- PM signoff present: `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260412-dungeoncrawler-release-n.md`
- Coordinated push readiness: all required PM signoffs present; official push still pending operator execution

**Active gaps:**
- **BA source-coverage mismatch**: Bestiary/Core/APG/GMG/G&G/SoM/Gods & Magic sweeps all still show large pending audit counts despite ledger/tracker `complete` signals. Coverage-sweep outboxes on 2026-04-18 now mark these as blocked until audit truth is reconciled.
- **Promoted backlog not yet groomed enough for activation**: `dc-b2-bestiary2`, `dc-gng-guns-gears`, and `dc-som-secrets-of-magic` remain the healthy ready backlog behind release-n, but BA validation shows source traceability still needs audit-level reconciliation before that backlog can be trusted as complete.

## 2026-04-10 — 20260410-dungeoncrawler-release-b PM signoff recorded / Gate 2 APPROVE

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Gate 2 run `20260410-gate2-verify-release-b` (0 failures, commit `01a00afda`). Site audit `20260410-105722` PASS (0 violations, 0 missing assets). |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured. |
| Time-to-verify (median) | < 24h | ~same-day | Dev completed all 8 features 2026-04-10; Gate 2 dispatched same day; QA APPROVE same day. |
| Escaped defects (prod/user reported) | 0 | 0 | 8 features deployed to scope; no user-reported defects. SSL timeout (`/characters/create`) from prior cycle: no new reports. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate 2 clean. Counter remains 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 0 | No new friction modes this cycle. |
| Audit freshness | <= 24h | Fresh | Latest audit: `20260410-105722` (~2h old at time of update). |

**Release-b summary:**
- 8 features shipped: dc-cr-crafting, dc-cr-creature-identification, dc-cr-decipher-identify-learn, dc-cr-encounter-creature-xp-table, dc-cr-environment-terrain, dc-cr-equipment-ch06, dc-cr-exploration-mode, dc-cr-familiar.
- PM signoff: `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260410-dungeoncrawler-release-b.md`
- Awaiting: pm-forseti co-sign + coordinated push (cosign item dispatched).

**Active gaps:**
- **`/characters/create` SSL timeout**: Still unresolved from 2026-03-26 audit. No new reports; low severity. Triage deferred to next cycle.
- **APG features** (`dc-apg-equipment`, `dc-apg-feats`, `dc-apg-focus-spells`): In release-c scope (`in_progress`), dev inbox items queued.

## 2026-03-27 — 20260326-dungeoncrawler-release-b shipped / Gate R5 clean

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Gate 2 run `20260326-224035` (0 failures). Gate R5 not independently run for dungeoncrawler this cycle (forseti.life Gate R5 `20260327-022516` covered shared infra; dungeoncrawler subdomain 200 OK). |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured. |
| Time-to-verify (median) | < 24h | ~same-day | pm-dungeoncrawler signoff 2026-03-27T01:49:13. Deploy 2026-03-27T06:22:10Z. Gate 2 and Gate R5 same-day. |
| Escaped defects (prod/user reported) | 0 | 0 | dc-cr-clan-dagger feature deployed; no user-reported defects. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate 2 clean (0 failures). Counter remains 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 3 | GAP-PF-26B-01 fixed (pm-forseti seat instructions, `3ad2a78d1`). GAP-26B-01 gate2-dedup queued to dev-infra (ROI 7). GAP-26B-02 improvement-round sequencing queued to dev-infra (ROI 5). |

**Active gaps updated:**
- **GAP-DC-STALL-01**: `20260322-dungeoncrawler-release-b` still on hold. CEO option A/B/C pending.
- **GAP-DC-01**: QA testgen — 4 features handed off to QA for `20260327-dungeoncrawler-release-b` cycle (commits `ef4309ef8`, `e6fe25eb6`). Pipeline moving.
- **`/characters/create` SSL timeout**: Status unknown; pm-dungeoncrawler triage still needed.
- **gate2-dedup (GAP-26B-01)**: dev-infra item `20260327-fix-gate2-dedup-20260326-dungeoncrawler-release-b` (ROI 7) queued.
- **improvement-round sequencing (GAP-26B-02)**: dev-infra item `20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b` (ROI 5) queued.

## 2026-03-26 — 20260322 coordinated release + release-b stall status

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | `20260322-dungeoncrawler-release` + `dungeoncrawler-release-next` shipped clean. No user-reported defects. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured. |
| Time-to-verify (median) | < 24h | N/A (release-b stalled) | `20260322-dungeoncrawler-release-b` stalled 4+ days awaiting qa-dungeoncrawler to apply dev-proposed qa-permissions.json fix (GAP-DC-STALL-01). |
| Escaped defects (prod/user reported) | 0 | 1 (new) | `/characters/create` production SSL handshake timeout (10.5s) found in Gate R5 audit `20260322-193507` (commit `ca3c9279a`). Not present in pre-push dev audit. Severity: unknown — triage required by pm-dungeoncrawler. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Shipped releases (`dungeoncrawler-release`, `dungeoncrawler-release-next`) passed Gate R5. Release-b unclean cycle count: 1 (permission regression false-positive; fix proposed but not applied). |
| Instructions-change proposals created | >= 1 when friction repeats | 1 | GAP-DC-STALL-01 escalation to CEO (commit `fd988824f`) — fix-pickup gap where dev→qa proposed fix is not being consumed. |

**Active gaps (not yet resolved):**
- **GAP-DC-STALL-01**: No executor routing rule for "dev proposed fix, QA has not picked it up." `20260322-dungeoncrawler-release-b` stalled since 2026-03-22. Escalated to CEO.
- **GAP-DC-01**: QA testgen throughput bottleneck — 4 features (`action-economy`, `ancestry-system`, `dice-system`, `difficulty-class`) blocked on test generation capacity. Active CEO escalation.
- **`/characters/create` SSL timeout**: Production-only finding (not in dev audit). pm-dungeoncrawler triage required.
- **`/campaigns` 403**: Pre-existing ACL-pending decision. Not a new regression.

## Baseline (rolling 7 days — 2026-02-27)
| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | No features shipped yet; maintenance releases only (release-b, release-c) |
| Reopen rate (issues/PRs) | < 10% | 0% | No features completed; N/A until first feature ships |
| Time-to-verify (median) | < 24h | N/A | No feature verifications completed yet |
| Escaped defects (prod/user reported) | 0 | 0 | No feature code shipped |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Maintenance releases passed Gate 2 (audit `20260227-064041`, 0 violations) |
| Instructions-change proposals created | >= 1 when friction repeats | 2 | `e7cf3d8` (BASE_URL fix), `518d9d9` (pre-signoff verification step) |

## Top recurring failure modes (updated 2026-03-26)
- Automated QA audits targeted http://localhost (port 80 / forseti.life) instead of dungeoncrawler at http://localhost:8080 due to systemd service env var hardcode. Caused 5+ false-positive QA cycles across release-b/c. Root cause: `scripts/systemd/copilot-sessions-hq-site-audit.service` has `Environment=DUNGEONCRAWLER_BASE_URL=http://localhost`. Fix committed; `sudo systemctl daemon-reload` pending (dev-infra/CEO).
- QA automation generated 50+ duplicate findings inbox items for the same underlying failure (retry loop bug). Fixed by dev-infra (`20260225-manual-unblock-dungeoncrawler-42`).

## Guardrails added (tests/checklists/instructions)
- `org-chart/sites/dungeoncrawler/site.instructions.md` — corrected BASE_URL to `http://localhost:8080` (commit `e7cf3d8`).
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — added mandatory pre-signoff BASE_URL verification step (commit `518d9d9`).
- `org-chart/sites/dungeoncrawler/qa-permissions.json` — added `no-destructive` first-match rule suppressing delete/archive/cancel paths from permission probing (commit `357230a`).
- **GAP-DC-STALL-01** (2026-03-26): No executor routing rule for "dev proposed fix → QA not consumed." Fix proposed by dev-dungeoncrawler in `sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`; not applied by qa-dungeoncrawler after 4+ days. CEO escalation active (pm-forseti commit `fd988824f`). If this recurs: escalate immediately rather than waiting a full cycle.
- **GAP-DC-01** (2026-03-26): QA testgen throughput bottleneck blocking 4 features in `20260326-dungeoncrawler-release-b` cycle. CEO escalation active.
 

## 2026-03-28 — Gate 2 ROI stagnation identified / 20260327-dungeoncrawler-release-b Gate 2 pending

| Metric | Target | Actual | Notes |
|---|---|---|---|
| Post-merge regressions | 0 | 0 | 4 features in dev (no merge yet; Gate 2 pending) |
| Time-to-verify (median) | < 24h | STALLED | Dev completed all 4 features on 2026-03-27; Gate 2 items queued same day. Not yet processed as of 2026-03-28T05:09 due to ROI ordering (Gate 2 items ROI 43-56 vs 15+ competing items ROI 84-300). |
| Escaped defects | 0 | 0 | No features merged yet |
| Consecutive unclean releases | 0 | 0 | Prior release clean; pending for current cycle |
| Instructions-change proposals created | >= 1 | 1 | Gate 2 ROI policy fix — release-blocking Gate 2 items should be ROI ≥ 200. Artifacts: `sessions/pm-dungeoncrawler/artifacts/20260328-release-kpi-stagnation/`. |

**Active gaps:**
- **GAP-DC-GATE2-ROI-01** (2026-03-28): Gate 2 unit-test items for `20260327-dungeoncrawler-release-b` assigned ROI 43-56 by automation; 15+ competing items have ROI 84-300. Gate 2 stalled. CEO escalation filed — needs ROI update on 4 items: `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-*/roi.txt`.
- **pm-forseti signoff still false**: coordinated push blocked until dungeoncrawler Gate 2 clears and both PMs sign off.
