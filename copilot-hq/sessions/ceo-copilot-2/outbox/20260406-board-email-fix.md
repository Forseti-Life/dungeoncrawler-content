- Status: done
- Summary: Diagnosed and fixed board email notification system. Test email confirmed postfix delivers to keith.aumiller@stlouisintegration.com via smtp-relay.gmail.com. Four gaps found and resolved: (1) board.conf missing from new HQ path — copied from old path; (2) board-daily-reminder.sh cron pointed to stale /home/ubuntu/copilot-sessions-hq — fixed to /home/ubuntu/forseti.life/copilot-hq; (3) notify-pending.sh was never installed in cron — installed (every 10 min, NOTIFY_METHOD=sendmail, NOTIFY_EMAIL_TO=keith.aumiller@stlouisintegration.com); (4) release-signoff.sh had no board notification hook — added email send on all-PM-signoff event.

## What changed
- `org-chart/board.conf` — created (board email config)
- `scripts/board-daily-reminder.sh` — added to new HQ path
- `scripts/release-signoff.sh` — board email notification on release push-ready
- Crontab — fixed board-daily-reminder path; added notify-pending every 10 min

## What you'll receive going forward
| Event | Trigger | Email subject |
|---|---|---|
| Release push-ready | All PMs signed off | [forseti.life HQ] Release ready to push: <release-id> |
| CEO inbox items pending | Every 10 min if inbox non-empty | [Forseti] Pending decisions: inbox=N sla_breaches=N |
| SLA breach | Every 10 min while breach exists | [Forseti] Pending decisions: inbox=N sla_breaches=N |
| Daily board digest | 08:00 UTC, if inbox/commands has items | [HQ Board] Reminder: N pending item(s) |

## What's NOT yet covered
- Per-feature completion emails (not built — low ROI vs release-level notification)
- Auto-checkpoint emails (checkpoints are git commits, not board events)

## Next actions
- None required. Monitor for first real release signoff to confirm email fires.

## Blockers
- None.
