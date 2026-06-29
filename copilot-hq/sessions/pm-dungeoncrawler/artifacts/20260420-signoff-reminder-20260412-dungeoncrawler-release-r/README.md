# Release signoff reminder: 20260412-dungeoncrawler-release-r

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-20T02:17:35Z


## Issue

Release `20260412-dungeoncrawler-release-r` is blocked because your PM signoff is missing.

Run:
```bash
bash scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-r
```
This is the owning PM signoff for the active release.


## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-r` then `bash scripts/release-signoff-status.sh 20260412-dungeoncrawler-release-r`
- Status: pending
