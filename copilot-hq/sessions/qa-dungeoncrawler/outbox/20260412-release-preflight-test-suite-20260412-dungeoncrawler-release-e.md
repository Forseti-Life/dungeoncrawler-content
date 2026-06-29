Good — `/architecture/*` is documented. Missing are: `/roadmap`, `/roadmap/requirement/{req_id}/status`, `/api/spells/*`, `/api/sessions/{session_id}/end`. Now update the seat instructions:

Committed `f9f8c895c`.

---

- Status: done
- Summary: Release-e preflight complete for dungeoncrawler. Reviewed all 6 required scripts and configs: `site-audit-run.sh` ALLOW_PROD_QA=1 gate confirmed valid; `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, and `role-permissions-validate.py` all route through the central audit orchestrator with no stale config. `qa-permissions.json` confirmed current — 6 roles (anon, authenticated, content_editor, administrator, dc_playwright_player, dc_playwright_admin) with correct cookie_env vars. `suite.json` validated OK (114 suites). Seat instructions refreshed: route namespace header updated to release-e date; 3 new routes added that appeared since the release-b preflight (`/api/spells*`, `/api/sessions/{id}/end`, `/roadmap*`). Notable finding: 19 of 32 in_progress dungeoncrawler features have test plans groomed but no suite entries yet — all 19 have `03-test-plan.md` and are awaiting Stage 0 suite activation dispatch from PM.

## Next actions
- Await individual QA unit-test inbox items for release-e features as PM dispatches them.
- When Gate 2 items arrive, consult `tmp/release-cycle-active/dungeoncrawler.release_id` for the active release ID (never use the inbox item dispatch date as a substitute).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 12
- Rationale: Clean preflight prevents stale route configs causing false positives mid-release; 3 new routes documented prevents surprise audit failures in release-e. 19 features with test plans but no suite entries is expected (Stage 0 not yet run).

---
- Commit: `f9f8c895c`

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-release-preflight-test-suite-20260412-dungeoncrawler-release-e
- Generated: 2026-04-12T18:59:29+00:00
