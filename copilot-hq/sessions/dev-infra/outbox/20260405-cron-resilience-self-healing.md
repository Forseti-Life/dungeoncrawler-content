Now I have enough context. Let me create the three deliverables:

There are duplicate entries — the old crontab used different tag suffixes. I need to deduplicate:

All 8 entries confirmed idempotent. Now commit and write outbox:

---

- Status: done
- Summary: Implemented full cron resilience self-healing for GAP-CRON-RESILIENCE-01. Created `scripts/hq-health-heartbeat.sh` (checks + auto-restarts orchestrator-loop and agent-exec-loop, writes alert log on failure). Created `scripts/install-crons.sh` — idempotent 8-entry HQ cron installer; confirmed: first run adds missing entries, second run skips all 8 with zero duplicates. Created `runbooks/cron-recovery.md` with detection, restore, and verify steps. Deduplicated stale old-tag entries from crontab. Committed `bbed5621`.

## ROI estimate
- ROI: 16
- Rationale: GAP-CRON-RESILIENCE-01 caused 20 SLA breaches and 3 days of silent outage. Heartbeat auto-restarts within 2 minutes; install-crons reduces post-migration restoration to one command.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-cron-resilience-self-healing
- Generated: 2026-04-05T21:47:48+00:00
