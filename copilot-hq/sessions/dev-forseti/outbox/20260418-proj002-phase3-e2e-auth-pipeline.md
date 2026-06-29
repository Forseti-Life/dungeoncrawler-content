All three role passes completed with crawl artifacts and no session-invalid warnings. Now commit and write the outbox.

Now update the dev-forseti seat instructions with the new operational knowledge, then write the outbox.

Now write the outbox:

- Status: done
- Summary: PROJ-002 Phase 3 complete. Two fixes unblock the authenticated QA pass: (1) `site-audit-run.sh` now detects collocated drush (`vendor/bin/drush` present in `drupal_root`) and runs OTL cookie provisioning even for production HTTPS URLs — previously only `http://localhost*` triggered OTL mode, so `FORSETI_COOKIE_AUTHENTICATED` was never set in automation; (2) `site-full-audit.py` now excludes `/logout` paths from crawls so authenticated sessions aren't self-invalidated mid-run. Both issues were traced from the live execution path and verified. Confirmed run at 16:21 UTC: 3 role cookies acquired automatically, zero "session invalid" warnings, all role crawl artifacts populated.

## Commits
- `762caf306` — site-audit-run.sh + site-full-audit.py changes
- `0ac7db516` — dev-forseti seat instructions (Phase 3 QA provisioning docs)
- `4b23d2f46` — outbox

## Verification
```bash
ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti
# Expect: INFO: acquiring QA session cookies via drush OTL (collocated drush (server=production))...
# Expect: INFO: QA sessions sourced (3 roles)
# No "session invalid" warnings
```

## ROI estimate
- ROI: 160
- Rationale: Every automated forseti QA run was silently skipping all authenticated-role checks. This fix restores full ACL coverage on every future automated pass.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260418-proj002-phase3-e2e-auth-pipeline
- Generated: 2026-04-18T16:26:59+00:00
