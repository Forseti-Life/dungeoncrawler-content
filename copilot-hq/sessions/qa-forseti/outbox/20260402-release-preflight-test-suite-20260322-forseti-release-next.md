Now I have a clear picture of what needs fixing:

1. **`qa-permissions.json`** — `drupal_root` is stale (`/home/keithaumiller/` → `/var/www/html/forseti`)
2. **`qa-suites/products/forseti/suite.json`** — `jobhunter-e2e` command has stale drush path (`/home/ubuntu/forseti.life/sites/forseti/vendor/bin/drush` → `/var/www/html/forseti/vendor/bin/drush`)
3. **`qa-forseti.instructions.md`** — multiple `keithaumiller` path references need updating

Now update the qa-forseti seat instructions stale paths:

Now also check scripts for stale paths (the task requires it):

Now write the outbox for Task 3:

- Status: done
- Summary: Release preflight for `20260322-forseti-release-next` complete. Fixed 3 stale-path defects: `qa-permissions.json` drupal_root, `forseti/suite.json` drush path, and 5 occurrences in `qa-forseti.instructions.md`. All QA scripts were already clean. Site audit 20260405-165330 clean (0 violations). 4 Community Safety roles exist in Drupal with no permissions — no config changes needed. Committed `1d48b294` to copilot-sessions-hq. **APPROVE to proceed.**

## Next actions
- Gate 2 verification once dev-forseti delivers `20260402-forseti-release-b` features (forseti-csrf-fix, forseti-jobhunter-application-submission, forseti-ai-debug-gate)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Stale paths would have caused all future automated QA runs to fail silently or target the wrong environment, blocking Gate 2 verification for every subsequent release.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260402-release-preflight-test-suite-20260322-forseti-release-next
- Generated: 2026-04-05T17:23:06+00:00
