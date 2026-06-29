# QA Release Preflight — 20260406-forseti-release-b

- Status: done
- Summary: Release preflight complete for `20260406-forseti-release-b`; no script or config changes needed. All 5 QA scripts reviewed: paths are current, `ALLOW_PROD_QA=1` gate intact, regex matching covers all application-submission wizard sub-routes via `re.search()` first-match semantics. `qa-permissions.json` has the correct 4 covered roles (anon, authenticated, content_editor, administrator) and 4 known-empty roles remain excluded. No new Drupal roles discovered. The `forseti-copilot-agent-tracker` feature is scoped to release-c (not release-b), so no new security-AC coverage gaps for release-b. `qa-suite-validate.py` passes clean (5 manifests, 0 errors). QA is ready to execute verification work for release-bound items.

## Preflight checklist

| Script / Config | Status | Notes |
|---|---|---|
| `scripts/site-audit-run.sh` | CLEAN | ALLOW_PROD_QA=1 gate intact; production URLs correct |
| `scripts/site-full-audit.py` | CLEAN | No stale paths |
| `scripts/site-validate-urls.py` | CLEAN | No stale paths |
| `scripts/drupal-custom-routes-audit.py` | CLEAN | Uses `--drupal-web-root` arg; no hardcoded paths |
| `scripts/role-permissions-validate.py` | CLEAN | Uses `re.search()`; first-match rule ordering correct |
| `org-chart/sites/forseti.life/qa-permissions.json` | CLEAN | 4 active roles; 4 empty roles excluded per seat instructions |

## Role coverage check

| Drupal role | qa-permissions.json | Status |
|---|---|---|
| anonymous | covered | OK |
| authenticated | covered | OK |
| content_editor | covered | OK |
| administrator | covered | OK |
| firefighter | excluded (0 permissions) | OK — per seat instructions |
| fire_dept_admin | excluded (0 permissions) | OK — per seat instructions |
| nfr_researcher | excluded (0 permissions) | OK — per seat instructions |
| nfr_administrator | excluded (0 permissions) | OK — per seat instructions |

## Suite manifest

- `python3 scripts/qa-suite-validate.py` → **OK: validated 5 suite manifest(s)**

## Release-b features needing QA verification

| Feature | Status | qa-permissions coverage |
|---|---|---|
| forseti-jobhunter-application-submission | in_progress | `forseti-jobhunter-application-submission-*` rules (4 rules); wizard sub-paths covered via regex |
| forseti-jobhunter-browser-automation | in_progress | `credentials-ui` + `jobhunter-surface` |
| forseti-jobhunter-e2e-flow | in_progress | `jobhunter-surface` |
| forseti-jobhunter-profile | in_progress | `forseti-jobhunter-profile-surface` + resume rules |
| forseti-ai-debug-gate | ready | `ai-debug-routes` rule |
| forseti-csrf-fix | ready | no new routes; structural fix only |

## Next actions
- Proceed with feature-level QA verification for release-b in_progress items per normal queue priority
- No follow-on preflight work needed

## Blockers
- None

## ROI estimate
- ROI: 15
- Rationale: Preflight ensures QA tooling is accurate before verification runs begin; catching stale configs here prevents false-pass or false-block outcomes downstream. No changes were needed, confirming the tooling is in good shape.
