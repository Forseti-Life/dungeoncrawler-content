# Risk + Security (PM/Security-owned)

## Risks
- Only 2 of 3+ targeted features activated (3rd feature pool exhausted at scope-activate time). Release-g runs in parallel with remaining 3 features.
- Playwright regression suite deferred to post-release (admin auth env vars unavailable); curl-automatable checks are the primary coverage.

## Security sanity check
- Open Critical findings shipped? no
- Open High findings shipped? no
- Notes / mitigations:
  - deadline-tracker: ownership guard verified (403 on non-owner), date validation enforced server-side, blank→NULL stored (no injection surface).
  - release-panel: no hardcoded paths, env var used for HQ root, is_readable() guards prevent information disclosure on missing state files.

## Decision log
- No known risk acceptance exceptions required for this release.
