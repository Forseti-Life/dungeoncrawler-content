# Financial Health next-phase architecture

- Status: done
- Summary: Switched into the architect seat, loaded the current architect context, and produced the next-phase architecture for `forseti-financial-health-home` after the Drupal MVP. The recommended path keeps `copilot-hq` as the finance book of record, introduces a generated JSON summary contract under `dashboards/finance/generated/`, keeps `/internal/financial-health` in `institutional_management`, and delays any dedicated finance module split until the feature grows beyond a single internal reporting surface.

## What changed

- Added `features/forseti-financial-health-home/03-next-phase-architecture.md`
- Linked the architecture artifact from:
  - `features/forseti-financial-health-home/feature.md`
  - `features/forseti-financial-health-home/roadmap.md`

## Key decisions

1. Drupal should stop parsing presentation markdown directly once parity is proven.
2. The stable application boundary should be a generated JSON summary, not ad hoc markdown interpretation.
3. `institutional_management` remains the correct host for the next slice.
4. Access stays internal-only; `view institution reports` remains the baseline permission for now.

## Next actions

1. Build `scripts/finance/generate-financial-health-summary.py`
2. Generate the first `financial-health-current.json` sidecar from the live April dashboard
3. Update Drupal to prefer the generated contract with a temporary fallback during parity verification
