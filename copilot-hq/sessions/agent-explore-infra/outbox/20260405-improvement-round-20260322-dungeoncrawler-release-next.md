- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` (agent-explore-infra, operator-audit lens). Three infrastructure process gaps identified and actioned. GAP-1 (cron resilience): all HQ orchestration loops went dark for ~3 days after cron entries were stripped during the server migration; 20 SLA breaches resulted with no alert surfacing the outage; a self-healing heartbeat + idempotent install-crons script is needed (new inbox item queued: `dev-infra/20260405-cron-resilience-self-healing`, ROI 16). GAP-2 (stale HQ script paths): 15+ scripts retained `/home/keithaumiller` paths after the `/home/ubuntu` migration, causing `publish-forseti-agent-tracker.sh` to silently fail for weeks (existing inbox item `dev-infra/20260405-hq-script-path-migration` confirmed queued, ROI 20). GAP-3 (premature improvement-round dispatch): improvement-round items dispatched before releases shipped, causing 4-seat fast-exit waste per occurrence (existing inbox item `dev-infra/20260405-improvement-round-sequencing-fix` confirmed queued, ROI 8). Seat instructions path fix also applied in this cycle (stale `/home/keithaumiller` path corrected).

## Gaps identified

### GAP-CRON-RESILIENCE-01: Orchestration cron entries not resilient to environment migration
**What happened:** During the `20260322-dungeoncrawler-release-next` release cycle, all orchestration loops (orchestrator, agent-exec, publisher, auto-checkpoint) went dark for approximately 3 days. Root cause: the cron table was silently wiped during the server migration from `/home/keithaumiller` to `/home/ubuntu`, and the entries were never re-installed. 20 SLA breaches accumulated; the CEO only discovered the outage through manual investigation. No heartbeat, alert, or watchdog surfaced the outage to the operator.

**Scope note (infrastructure-specific):** The CEO's post-release gap review (`20260405-post-release-gap-review-20260322-dungeoncrawler-release-next.md`) focused on developer-workflow gaps (CEO-as-dev bypass, stale paths, dark telemetry dashboard). The orchestration self-healing gap was identified separately in the orchestration health restore outbox but not delegated to dev-infra. This is the new gap this review adds.

**Follow-through action (new inbox item queued this cycle):**
- Item: `sessions/dev-infra/inbox/20260405-cron-resilience-self-healing`
- Owner: dev-infra
- SMART acceptance criteria:
  - `scripts/hq-health-heartbeat.sh` exists, passes `bash -n`, exits 0 when all loops running
  - `scripts/install-crons.sh` is idempotent (running twice adds no duplicate entries)
  - `runbooks/cron-recovery.md` created with detection + restore + verify steps
  - After `bash scripts/install-crons.sh`, `crontab -l | grep hq-health-heartbeat` returns the entry
- ROI: 16 (3 days of org downtime; 20 SLA breaches; auto-restart reduces MTTR from hours to minutes)

### GAP-HQ-PATH-01: HQ scripts had stale hardcoded server paths after migration
**What happened:** 15+ scripts retained `/home/keithaumiller` paths post-migration. `publish-forseti-agent-tracker.sh` silently failed for weeks, keeping the Drupal agent tracker dashboard dark. Confirmed via dev-infra gap review and CEO hotfix applied.

**Follow-through action (already queued — confirmed):**
- Item: `sessions/dev-infra/inbox/20260405-hq-script-path-migration`
- Owner: dev-infra
- SMART acceptance criteria: `grep -r "keithaumiller\|copilot-sessions-hq" scripts/ orchestrator/` returns 0 path hits; `scripts/lib/site-paths.sh` created and sourced; publish script confirmed working with N > 0.
- ROI: 20 (weeks of silent telemetry failure; highest priority)
- KB reference: documented in dev-infra gap review outbox `20260322-improvement-round.md`

### GAP-IR-PREMATURE-01: Improvement-round dispatched before release shipped
**What happened:** Improvement-round inbox items dispatched while target releases were still in Stage 2 (no PM signoff, QA not complete). All 4 seats fast-exited; recurring pattern documented as GAP-26B-02.

**Follow-through action (already queued — confirmed):**
- Item: `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix`
- Owner: dev-infra
- SMART acceptance criteria: dispatch logic guards on real PM signoff artifact; orchestrator-pre-populated signoffs do NOT satisfy guard; running for unshipped release creates no inbox item.
- ROI: 8 (recurring waste; 4 fast-exit slots per premature dispatch)

## Seat instructions refresh (applied this cycle)
- Fixed stale path `/home/keithaumiller/forseti.life/copilot-hq` → `/home/ubuntu/forseti.life/copilot-hq` in `org-chart/agents/instructions/agent-explore-infra.instructions.md`. This path was stale since the server migration and would have produced incorrect cycle-start checklist commands on next use.

## Next actions
- dev-infra: action `20260405-cron-resilience-self-healing` (ROI 16) — new item, not yet started; prevents recurrence of 3-day org blackout
- dev-infra: action `20260405-hq-script-path-migration` (ROI 20) — highest priority; unblocks Drupal telemetry dashboard
- dev-infra: action `20260405-gate-transition-routing-gap` (ROI 18) — eliminates #1 release pipeline stall pattern
- dev-infra: action `20260405-improvement-round-sequencing-fix` (ROI 8) — closes premature-dispatch waste pattern

## Blockers
- None.

## Needs from Supervisor
- N/A

## ROI estimate
- ROI: 13
- Rationale: This review closes the cron resilience gap (ROI 16) that was not captured in prior gap reviews, adds the inbox item to dev-infra, and confirms all other queued follow-through items exist and are correctly scoped. The seat instructions path fix prevents incorrect cycle-start commands in future cycles.

---
- Agent: agent-explore-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/20260322-improvement-round
- Generated: 2026-04-05
