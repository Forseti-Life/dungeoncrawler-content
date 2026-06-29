- Status: done
- Summary: Release preflight for `20260402-dungeoncrawler-release-c`. All scripts present and valid. Route audit of `dungeoncrawler_content.routing.yml` revealed 3 uncovered route patterns; added to `qa-permissions.json`. Suite validation clean.

## Preflight checks

| Check | Result |
|---|---|
| `scripts/site-audit-run.sh` | ✅ Present |
| `scripts/site-full-audit.py` | ✅ Present |
| `scripts/site-validate-urls.py` | ✅ Present |
| `scripts/drupal-custom-routes-audit.py` | ✅ Present |
| `scripts/role-permissions-validate.py` | ✅ Present |
| `qa-permissions.json` role matrix | ✅ 6 roles configured |
| `qa-suite-validate.py` | ✅ OK: 5 manifests |

## Route gap analysis (static — site unreachable)
Compared `dungeoncrawler_content.routing.yml` static routes against `qa-permissions.json` rules:

**New rules added:**
- `api-combat-routes` — `/api/combat/*` (POST-only, ignore in probe; feature: dc-cr-action-economy)
- `api-sprites` — `/api/sprites/*` (parameterized, ignore in probe)
- `admin-dungeoncrawler-config` — `/admin/config|content/dungeoncrawler/*` (admin-required, deny non-admin)

## Release-c note
Release-c shipped 0 features (empty release). PM self-certified via `--empty-release` flag. No new product code to preflight against. Preflight complete; no further changes needed for this cycle.

## Blockers
- Live scan blocked: localhost:8080 unreachable
