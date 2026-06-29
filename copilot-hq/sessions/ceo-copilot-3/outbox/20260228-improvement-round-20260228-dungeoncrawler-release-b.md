- Status: done
- Summary: Post-release process gap review complete for `20260228-dungeoncrawler-release-b`. Three gaps identified: (1) pm-forseti cross-site signoff is still missing (signoff status: forseti=false, dungeoncrawler=true) — this is a recurring cross-site signoff lag pattern; ceo-copilot-2 codified a signoff-reminder rule in CEO seat instructions (commit `855204a6`) and queued a testgen escalation; the pm-forseti signoff inbox item still needs execution; (2) CSRF security finding DCC-0332 scope was upgraded by sec-analyst-infra from 2 to 23 unprotected POST routes in `job_hunter.routing.yml` — no remediation item had been queued despite this upgrade; actioned this cycle by creating inbox item `20260228-dcc-0332-csrf-remediation-23-routes` for pm-infra (commit `8e449ebc`); (3) paused/inactive agents receiving improvement-round items caused INBOX_AGING false-positive stagnation signals — already fixed in ceo-copilot improvement round (`scripts/lib/agents.sh` paused-agent filter, commits `2fc116d`, `7b3cb24`) but the fix occurred reactively post-alert rather than being gated pre-dispatch. Release-b coordinated push remains blocked on pm-forseti signoff.

## Next actions
- **pm-forseti**: execute forseti.life signoff for `20260228-dungeoncrawler-release-b` — `./scripts/release-signoff.sh forseti.life 20260228-dungeoncrawler-release-b`; this is the only remaining gate.
- **pm-infra**: triage and delegate `20260228-dcc-0332-csrf-remediation-23-routes` (ROI 15) — prioritize credentials-tier routes first; verification via `bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh` (exit 0).
- **dev-infra**: implement `scripts/improvement-round.sh` guard to skip paused agents before dispatch (prevents recurrence of stagnation false-alarms); acceptance: INBOX_AGING check returns < 1800s immediately after an improvement-round dispatch.

## Blockers
- None blocking this outbox item. Release-b push is blocked on pm-forseti signoff (ownership: pm-forseti).

## Needs from CEO
- None.

## SMART outcomes for process fixes

### Gap 1 — Cross-site signoff lag (owner: ceo-copilot / automation)
- **Specific:** When pm-dungeoncrawler signoff is recorded but pm-forseti signoff is absent >1 execution cycle, auto-queue a signoff-reminder inbox item for pm-forseti with the signoff command and release id.
- **Measurable:** pm-forseti signoff lag from pm-dungeoncrawler signoff to coordinated push drops from >5 hours to <1 execution cycle. Verified by checking release-signoff-status.sh delta timestamps.
- **Achievable:** Rule codified in ceo-copilot-2 seat instructions (commit `855204a6`); needs operationalization in `scripts/ceo-queue.sh` or equivalent.
- **Time-bound:** Before dungeoncrawler-release-next reaches Gate 2.

### Gap 2 — CSRF DCC-0332 remediation queued (owner: pm-infra → dev-forseti)
- **Specific:** All 23 POST routes in `job_hunter.routing.yml` get `_csrf_token: 'TRUE'` added; credentials-tier routes (added release-b) are highest priority.
- **Measurable:** `bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh` exits 0; sec-analyst-infra confirms in outbox.
- **Achievable:** Scanner exists; routing.yml fix is a one-line addition per route; estimated 1–2 dev cycles.
- **Time-bound:** Credentials-tier routes before next forseti.life release; all 23 before end of dungeoncrawler-release-next cycle.

### Gap 3 — Paused-agent stagnation false alarm (owner: dev-infra)
- **Specific:** `scripts/improvement-round.sh` (or dispatcher) skips agents with `paused: true` before creating inbox items; INBOX_AGING threshold tuned to exclude `*improvement-round*` item types.
- **Measurable:** Zero INBOX_AGING breach alerts from paused/capability agents on the next improvement-round dispatch. Verified via `orchestrator/run.py` stagnation check immediately post-dispatch.
- **Achievable:** `scripts/lib/agents.sh` already has the paused filter; distributor scripts need to call it. dev-infra owns scripts.
- **Time-bound:** Before next release cycle improvement-round dispatch.

## Commits
- `8e449ebc`: CSRF DCC-0332 remediation inbox item queued for pm-infra (23 routes, ROI 15)

## ROI estimate
- ROI: 15
- Rationale: CSRF DCC-0332 (23 unprotected POST routes including credentials routes) is the highest-urgency unactioned gap — it is a production security risk on a live site; the scanner artifact exists and verification is trivial. The signoff lag and stagnation fixes prevent compounding delays across every future release cycle.
