Preflight complete. Outbox:

---

- Status: done
- Summary: Release preflight for `20260405-forseti-release-c` is complete and clean. All 5 QA scripts (`site-audit-run.sh`, `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, `role-permissions-validate.py`) were reviewed and require no changes. `suite.json` (6 new entries added this cycle) and `qa-permissions.json` (7 new rules added) are fully updated. Drupal roles confirmed: 4 covered roles in qa-permissions are correct — `firefighter`/`nfr_*` roles use dedicated `/nfr/` routes not in the site audit `route_regex` and both routes return 403 for anon (no ACL risk). One bug found and fixed: `qa-forseti.instructions.md` example commands used `/usr/local/bin/drush` which symlinks to the wrong site (`thetruthperspective`) — corrected to `/var/www/html/forseti/vendor/bin/drush --root=/var/www/html/forseti/web`. Suite validator passes: 5 manifests OK.

## Next actions
- Monitor Stage 0 flags: Dev confirms 8-vs-5 route count gap (`forseti-jobhunter-application-submission`) at implementation start
- TC-11 (cross-user profile test) requires dual QA user provisioning — flag to pm-forseti before Gate 2
- TC-12/TC-14 (profile: file-upload + access-control injection) require Dev hooks at Stage 0

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Preflight ensures test infrastructure is clean before Dev writes code for release-c. Catching the wrong-drush-path bug now prevents a silent failure during Gate 2 verification.

**Commits this cycle:**
- `10d6d912` — Bedrock verify: regression checklist updated to QA VERIFIED
- `5cb6a1a2` — Suite activate: forseti-jobhunter-application-submission
- `6f2dcaba` — Suite activate: forseti-jobhunter-browser-automation
- `29899520` — Suite activate: forseti-jobhunter-profile
- `fb35f592` — Seat instructions: fix drush path (wrong site symlink)

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260405-release-preflight-test-suite-20260405-forseti-release-c
- Generated: 2026-04-06T08:51:17+00:00
