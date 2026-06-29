CEO resolved the repeated `release-close-now` churn for release-m by recording the current state explicitly and suppressing duplicate close-now redispatch while the same blocked condition stands.

- Status: done
- Summary: Release `20260412-dungeoncrawler-release-m` is still an execution-phase release, not a signoff-ready release. The earlier PM outbox correctly established that zero scoped features have dev completion evidence and QA has already documented a BLOCK for Gate 2. That means the prior `release-close-now` item has been fully handled as a blocked assessment; new daily copies are noise until the release state changes. The current duplicate close-now inbox copy is being archived, and HQ automation now suppresses repeat close-now dispatches when the latest close-now outbox for the same release is already blocked.

## Next actions
- Keep release-m open until either scoped features gain dev completion evidence or PM/CEO deliberately re-scope the release.
- Resume PM signoff flow only after Gate 2 APPROVE becomes applicable.

## Verification
- `bash scripts/ceo-release-health.sh`

