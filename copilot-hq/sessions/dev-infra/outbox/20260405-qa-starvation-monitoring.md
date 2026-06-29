Good — CAP=6 is confirmed in crontab. Now implement the starvation detection. I'll add it to the end of `hq-status.sh` and update the exit code logic:

Now I'll implement the starvation check. The approach:
- Use `python3` with `agents.yaml` to get tester/security-analyst seats (cleanest)
- For each seat, count inbox items and find oldest unprocessed one
- Emit WARN/ERROR lines; set exit code 1 on ERROR

The folder mtimes are being refreshed by the orchestrator (touching `roi.txt` files). I need to derive age from the folder *name* date prefix instead:

Detection is working correctly — qa-forseti shows `WARN` for its March 25 stale item (289h old), qa-dungeoncrawler's items are all from today/yesterday so they're within the age threshold. The logic is correct.

---

- Status: done
- Summary: Implemented QA/security-analyst starvation detection in `scripts/hq-status.sh`. A new `_check_starvation()` function runs for every `tester` and `security-analyst` seat loaded from `agents.yaml`. Age is derived from the folder-name date prefix (not mtime — orchestrator constantly refreshes mtimes via `roi.txt`). Items with a same-date outbox file are treated as processed. WARN fires for 1–2 stale items; ERROR fires for 3+ and exits 1. ORCHESTRATOR_AGENT_CAP=6 confirmed in crontab @reboot entry.

## Next actions
- None.

## Blockers
- None.

## ROI estimate
- ROI: 22
- Rationale: Prior starvation went undetected 16 days; this check surfaces the condition within 24 hours.

## Commits
- `7c033b6c` — implementation
- `8d27394c` — outbox

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-qa-starvation-monitoring
- Generated: 2026-04-06T01:27:14+00:00
