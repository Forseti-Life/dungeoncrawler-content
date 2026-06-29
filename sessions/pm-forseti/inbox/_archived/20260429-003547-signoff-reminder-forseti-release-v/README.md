# Command: signoff-reminder

- Agent: ceo-copilot-2
- Item: 20260429-003547-signoff-reminder-forseti-release-v
- Work item: release-coordination-signoff
- Status: needs-action
- Supervisor: pm-forseti
- Created: 2026-04-29T00:35:47+00:00

## Decision needed
- PM review and signoff on release 20260412-forseti-release-v

## Recommendation
- Review release 20260412-forseti-release-v, confirm ready for production, and run signoff script:
  ```
  ./scripts/release-signoff.sh forseti 20260412-forseti-release-v
  ```

## Command text
The forseti.life product is ready for PM pm-forseti to sign off on release **20260412-forseti-release-v**.

All features scoped for this release have Gate 2 APPROVE evidence and are ready to ship. This is a coordinated release with dungeoncrawler.

## Required verification
- [x] Release ID valid: 20260412-forseti-release-v
- [x] Coordinated release (both forseti and dungeoncrawler must sign)
- [x] Partner PM status: run `./scripts/release-signoff-status.sh 20260412-forseti-release-v`

## Next action
PM must execute:
```bash
./scripts/release-signoff.sh forseti 20260412-forseti-release-v
```

This creates the signoff artifact and unblocks the release operator to push.
