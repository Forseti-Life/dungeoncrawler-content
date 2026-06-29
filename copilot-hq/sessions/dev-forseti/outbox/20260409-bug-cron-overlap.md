- Status: done
- Summary: Diagnosed and fixed the cron overlap warning bug on forseti.life. Root cause: `automated_cron.settings` had `interval=10800` (enabled, 3h threshold). High-traffic bot crawling caused concurrent page requests to simultaneously pass the "has 3h elapsed?" check — the first request acquired the cron lock and ran, while the remaining concurrent requests logged "Attempting to re-run cron while it is already running" (16 warnings/day). No crontab entry existed for forseti `drush cron`, so no flock protection was in place. Fix: disabled automated_cron (`interval=0`) via `drush config:set`, updated sync config to match, and added a flock-protected `drush cron` crontab entry running every 3 hours (identical pattern to the dungeoncrawler entry already in crontab). Committed `1af68a272`.

## Next actions
- AC-1 verification is time-gated: confirm cron overlap warnings = 0 in watchdog after 24h (QA or manual check: `drush watchdog:show --type=cron --count=50`)
- AC-2: verify `system.cron_last` updates every 3h after next scheduled cron run at the top of the hour
- AC-3: no action needed — search indexing and queue workers are unaffected (queue workers run via separate crontab entries on 5-min flock-protected loops)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Eliminates 16 watchdog warnings/day; structural fix prevents warnings from scaling with traffic growth; 1-line crontab change already applied mirrors existing dungeoncrawler pattern with zero risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-bug-cron-overlap
- Generated: 2026-04-09T20:45:08+00:00
