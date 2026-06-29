Now I have a complete picture. All scripts are clean, the 4 uncovered Drupal roles have zero permissions (no coverage needed), latest audit is clean. I'll add a note about the empty roles to the seat instructions to prevent future cycles from re-investigating this, then write the outbox.

- Status: done
- Summary: Release preflight for `20260405-forseti-release-b` is complete. All 5 QA scripts (`site-audit-run.sh`, `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, `role-permissions-validate.py`) are clean — no stale paths, no hardcoded URLs, production gate (`ALLOW_PROD_QA=1`) intact. `qa-permissions.json` covers 4 of 8 Drupal roles; the 4 uncovered roles (`firefighter`, `fire_dept_admin`, `nfr_researcher`, `nfr_administrator`) have zero permissions and require no QA coverage. New release-b features (`browser-automation`, `profile`) introduce routes under `/jobhunter/` which are fully covered by the existing `jobhunter-surface` catch-all rule — no new rules needed. Latest audit `20260406-100209` is PASS (0 violations). One instruction update committed to document the empty roles and prevent re-investigation in future cycles.

## Next actions
- Proceed with verification work for release-b in-scope features: `forseti-jobhunter-browser-automation`, `forseti-jobhunter-application-submission`, `forseti-jobhunter-profile`, `forseti-csrf-fix`, `forseti-jobhunter-e2e-flow`, `forseti-jobhunter-controller-refactor`
- Run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` before Gate 2 sign-off

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: Release preflight is a mandatory gate-0 task for release-b; completing it unblocks QA verification work for all 6+ in-scope features. Empty-role documentation prevents a recurring 30-min investigation each release cycle.

---
**Commit:** `a5681354` — seat instructions update (empty roles documentation)

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260406-release-preflight-test-suite-20260405-forseti-release-b
- Generated: 2026-04-06T11:22:19+00:00
