# Forseti CEO ops cadence

## Every 2 hours (scheduled CEO quality check)
- Snapshot org health: active vs idle, queue sizes, blockers.
- Confirm repo merge health: no unresolved `MERGE_HEAD` state and no unmerged files.
- Run `scripts/ceo-release-health.sh` for release-gate failures.
- Run `scripts/ceo-system-health.sh` for runtime and org-health failures.
- Surface the CEO inbox snapshot and suggested actions in one log.

Implementation:
- `scripts/ceo-ops-once.sh` (single cycle)
- `scripts/install-cron-ceo-ops.sh` installs cron `0 */2 * * *` to run `ceo-ops-once.sh`

Logs:
- `inbox/responses/ceo-ops-cron.log`

## Every 2 hours
- Auto checkpoint push runs via cron (already installed): `scripts/auto-checkpoint.sh`
- Review priority rankings and adjust resource focus if ROI changes.
