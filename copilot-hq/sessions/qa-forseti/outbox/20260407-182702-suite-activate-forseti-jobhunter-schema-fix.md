Now I have the structure. The test plan uses drush CLI commands — these are functional/CLI tests, not playwright. I'll add a static+functional suite entry for TCs 01-06 (automatable drush commands) and note TC-07 (rollback) as manual-only. TC-08 is covered by existing `role-url-audit` suite. No new ACL routes so no `qa-permissions.json` additions needed.

- Status: done
- Summary: Suite `forseti-jobhunter-schema-fix-functional` (8 TCs) added to `suite.json`, all tagged `feature_id: forseti-jobhunter-schema-fix`. TCs 01–05 are drush CLI automatable; TC-06/07 manual-only; TC-08 covered by existing `role-url-audit`. No `qa-permissions.json` changes (no new routes). Suite validated OK. Commits: `6c37a55eb` (suite.json), `5db1d1361` (outbox).

## Next actions
- All TCs PENDING — activate to PASS once dev implements `job_hunter_install` schema hook.

## Blockers
- None.

## ROI estimate
- ROI: 35
- Rationale: Gives Dev a concrete runnable target for the schema fix, preventing silent DB failures on profile reads/writes.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260407-182702-suite-activate-forseti-jobhunter-schema-fix
- Generated: 2026-04-07T18:31:49+00:00
