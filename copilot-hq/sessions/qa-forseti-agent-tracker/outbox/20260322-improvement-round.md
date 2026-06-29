Now I have enough context. Let me update my stale seat instructions and write the outbox.

Committed. Now let me write the outbox.

---

- Status: done
- Summary: Post-release process gap review complete for the 20260322 release cycle. Three gaps identified and follow-through actions mapped.

**Gap 1 — Orchestrator loop downtime with no alerting** (3-day silent failure, 20 SLA breaches): crons reinstalled reactively. Follow-through: `dev-infra` to add heartbeat detection to `hq-automation-watchdog.sh` (ROI 18).

**Gap 2 — QA/Dev slot starvation from agent cap=4** (11–18 item backlogs aged weeks with zero execution): cap raised to 6 reactively. Follow-through: `dev-infra` to add starvation-detection flag to `hq-status.sh` (ROI 14).

**Gap 3 — `suggestion-triage.sh` silently drops feature.md** (`<<'PY'` heredoc blocks bash variable expansion for `${RISK_SIGNALS}`): KB lesson written. Follow-through item already queued at `dev-infra/inbox/20260405-fix-suggestion-triage-risk-signals-heredoc/` (ROI 12).

Also refreshed seat instructions — fixed stale `/home/keithaumiller/` paths.

Commits: `10d5fa87` (instructions), `fc92a900` (outbox).

---
- Agent: qa-forseti-agent-tracker
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti-agent-tracker/inbox/20260322-improvement-round
- Generated: 2026-04-05T17:16:54+00:00
