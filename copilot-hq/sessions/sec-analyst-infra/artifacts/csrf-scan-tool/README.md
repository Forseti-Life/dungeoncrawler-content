# CSRF Route Scanner — Draft Tool

**Status:** Draft artifact. Proposed to dev-infra for adoption into `scripts/`.
**Owner (proposed):** dev-infra (once adopted)
**Draft by:** sec-analyst-infra (WRAITH)
**Date:** 2026-02-28

---

## What it does

Scans all Drupal `*.routing.yml` files in a repo for POST routes that lack CSRF protection:
- Missing `_csrf_token: 'TRUE'`
- Missing `_csrf_request_header_mode: 'TRUE'`
- Not backed by `_form:` (which gets Drupal form API auto-CSRF)

Exits 0 if clean, 1 if any flags found.

## Why it matters

CSRF on unprotected POST routes has been the #1 repeat finding across both dungeoncrawler and forseti release cycles:
- `ai_conversation.routing.yml` — 3 flagged routes (patches: `sessions/sec-analyst-infra/artifacts/20260227-dungeoncrawler-csrf-patches/patches.md`)
- `job_hunter.routing.yml` — 2 flagged credential routes (patches: `sessions/sec-analyst-infra/artifacts/20260227-forseti-release-preflight/patches.md`)

Running this at pre-commit or CI would catch these before they reach sec-analyst-infra review.

## Usage

```bash
# Scan entire forseti.life repo
bash csrf-route-scan.sh /home/keithaumiller/forseti.life

# Scan single module
bash csrf-route-scan.sh /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter
```

## Self-test (run against known flagged routes)

```bash
bash csrf-route-scan.sh /home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter
# Expected: 2 FLAGs (credentials_delete, credentials_test)
# until dev-infra applies the patches from the forseti release-d preflight
```

## Integration proposal for dev-infra

Proposed adoption path:
1. Copy to `scripts/csrf-route-scan.sh`
2. Add to `qa-suites/products/*/qa-suite.yaml` as a pre-flight lint step
3. Run in CI on any PR touching `*.routing.yml`

Acceptance criteria for adoption:
- `bash -n scripts/csrf-route-scan.sh` passes (syntax OK)
- Running against `forseti.life` repo currently flags ≥2 known unfixed routes (credentials_delete, credentials_test)
- After patches applied: 0 flags on job_hunter, 0 flags on ai_conversation
