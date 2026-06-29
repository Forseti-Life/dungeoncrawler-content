- Status: done
- Summary: Completed full HQ script path migration audit and hardening. Only one true filesystem path bug remained (`auto-checkpoint.sh` line 9: `/home/ubuntu/copilot-sessions-hq`). Also fixed the systemd service `WorkingDirectory` (`%h/copilot-sessions-hq` → absolute path). Created `scripts/lib/site-paths.sh` as a central env-overridable resolver for `FORSETI_SITE_DIR`, `DUNGEONCRAWLER_SITE_DIR`, and `HQ_ROOT`. Sourced it from the top 5 most-called scripts and replaced hardcoded `/var/www/html/forseti` assignments with overridable defaults. All acceptance criteria pass: stale-path grep is clean, `publish-forseti-agent-tracker.sh` outputs "Published 36 agent(s)", `consume-forseti-replies.sh` exits 0.

## Next actions
- None for this item — all acceptance criteria met and committed.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 18
- Rationale: These scripts run every orchestrator tick; a broken path silently kills telemetry publishing and agent reply consumption. Centralizing site paths prevents the same class of breakage on any future server migration.

## Verification evidence
```
grep -rn "keithaumiller|/home/ubuntu/copilot-sessions-hq" scripts/ orchestrator/ → CLEAN (0 hits)
bash scripts/publish-forseti-agent-tracker.sh → Published 36 agent(s) to Forseti copilot_agent_tracker.
bash scripts/consume-forseti-replies.sh → exit 0
bash -c '. scripts/lib/site-paths.sh && echo HQ_ROOT=$HQ_ROOT' → HQ_ROOT=/home/ubuntu/forseti.life/copilot-hq
```

## Commits
- `e93036b6` — fix(infra): migrate stale HQ script paths; add scripts/lib/site-paths.sh

## Files changed
- `scripts/lib/site-paths.sh` — NEW: shared env-overridable path resolver
- `scripts/auto-checkpoint.sh` — fixed: `/home/ubuntu/copilot-sessions-hq` → `/home/ubuntu/forseti.life/copilot-hq`
- `scripts/systemd/copilot-sessions-hq-orchestrator.service` — fixed: `WorkingDirectory=%h/copilot-sessions-hq` → absolute path
- `scripts/publish-forseti-agent-tracker.sh` — sources site-paths.sh; hardcoded path → overridable default
- `scripts/consume-forseti-replies.sh` — sources site-paths.sh; hardcoded path → overridable default
- `scripts/suggestion-close.sh` — sources site-paths.sh
- `scripts/agent-exec-next.sh` — sources site-paths.sh
- `scripts/prune-legacy-agent-tracker-rows.sh` — sources site-paths.sh; hardcoded path → overridable default
