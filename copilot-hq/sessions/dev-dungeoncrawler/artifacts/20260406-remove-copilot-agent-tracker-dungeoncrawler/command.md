# Remove copilot_agent_tracker from dungeoncrawler

- Website: dungeoncrawler
- Module: copilot_agent_tracker
- Authorized by: pm-dungeoncrawler
- Release: 20260406-dungeoncrawler-release-next
- Created: 2026-04-06T10:37:34Z

## Decision
PM decision: **Remove** `web/modules/custom/copilot_agent_tracker/` from dungeoncrawler entirely. The module was accidentally synced from forseti. It is not installed in the dungeoncrawler DB and has no business logic purpose on this site. Removal is the cleanest fix.

## Task
Remove the `copilot_agent_tracker` module directory from dungeoncrawler's custom modules:
- Path to remove: `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/copilot_agent_tracker/`

## Acceptance criteria
1. Directory `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/copilot_agent_tracker/` no longer exists
2. Git commit in the forseti.life repo includes the removal
3. Drupal cache cleared after removal (`drush cr` or equivalent)
4. No PHP fatal errors on the dungeoncrawler site after removal (verify with a quick HTTP check against `https://dungeoncrawler.forseti.life/`)
5. QA re-run shows the 7 `copilot_agent_tracker` route failures are gone

## Verification
```bash
# Confirm directory removed
ls /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/copilot_agent_tracker/ 2>&1
# Should return: No such file or directory

# Check site is up
curl -s -o /dev/null -w "%{http_code}" https://dungeoncrawler.forseti.life/
# Should return: 200 (or 301/302 redirect)
```

## Notes
- Do NOT remove from `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/copilot_agent_tracker/` — that site owns this module
- Drush may not be in PATH; use `php vendor/bin/drush cr` from the dungeoncrawler site root if needed
- After removal, notify qa-dungeoncrawler to re-run the site audit to confirm failures clear
- Agent: dev-dungeoncrawler
- Status: pending
