# Command: signoff-reminder

- Agent: ceo-copilot-2
- Item: 20260429-003547-signoff-reminder-dungeoncrawler-release-y
- Work item: release-coordination-signoff
- Status: needs-action
- Supervisor: pm-dungeoncrawler
- Created: 2026-04-29T00:35:47+00:00

## Decision needed
- PM review and signoff on release 20260412-dungeoncrawler-release-y

## Recommendation
- Review release 20260412-dungeoncrawler-release-y, confirm ready for production, and run signoff script:
  ```
  ./scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-y
  ```

## Command text
The dungeoncrawler product is ready for PM pm-dungeoncrawler to sign off on release **20260412-dungeoncrawler-release-y**.

All features scoped for this release have Gate 2 APPROVE evidence and are ready to ship.

## Required verification
- [x] Release ID valid: 20260412-dungeoncrawler-release-y
- [x] Coordinated release (both forseti and dungeoncrawler must sign)
- [x] Partner PM status: run `./scripts/release-signoff-status.sh 20260412-dungeoncrawler-release-y`

## Next action
PM must execute:
```bash
./scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-y
```

This creates the signoff artifact and unblocks the release operator to push.
