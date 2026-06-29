---

- Status: done
- Summary: Release preflight complete for `20260406-dungeoncrawler-release-b`. Reviewed all five target scripts (`site-audit-run.sh`, `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, `role-permissions-validate.py`) and `qa-permissions.json`. All scripts are valid: `ALLOW_PROD_QA=1` gate is correctly enforced in `site-audit-run.sh`, Python scripts accept `base_url` as argument with no hardcoded prod URLs. One gap found and fixed: two routes from `ai_conversation.routing.yml` — `/node/{node}/chat` and `/node/{node}/trigger-summary` — were present in routing YAMLs but absent from `qa-permissions.json`. Both require `use ai conversation` permission (only `administrator` has it) and are parameterized, so both are added as `ignore` for all roles. Role coverage confirmed: active Drupal roles are `anonymous`, `authenticated`, `content_editor`, and `administrator`; `dc_playwright_player`/`dc_playwright_admin` are runtime-created via `drush dctr:qa-users-ensure` (by design). Suite manifest validated (18 suites, `python3 scripts/qa-suite-validate.py` passes). Note: phpunit suites in `suite.json` target `tests/src/` which maps to Functional tests requiring a full Drupal browser environment — these fail locally by design; this is a pre-existing known limitation documented in prior preflight reports, not a regression. Committed `c44585213`.

## Next actions
- Proceed to next inbox items: `20260406-roadmap-req-2290-2310-exploration-downtime` and the queued unit-test verification items
- Note for dev-dungeoncrawler: phpunit Functional suite commands in `qa-suites/products/dungeoncrawler/suite.json` (e.g., `--filter=AncestrySystem tests/src/`) require `SIMPLETEST_BASE_URL` and a full Drupal test environment — they cannot run on this host as configured; PM-dungeoncrawler should consider gating these as `skip_in_ci: true` or converting to `drush ev` smoke tests

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Preflight keeps the audit baseline accurate and prevents false-negative ACL reports on the two new node-based AI chat routes. Quick fix with high accuracy value for the live audit system; low effort, but mandatory for release-cycle hygiene.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-release-preflight-test-suite-20260406-dungeoncrawler-release-b
- Generated: 2026-04-07T01:06:49+00:00
