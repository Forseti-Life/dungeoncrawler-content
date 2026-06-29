# Cross-team Cosign Required — 20260409-dungeoncrawler-release-g

- Release id: 20260409-dungeoncrawler-release-g
- Site: dungeoncrawler (DC-only release; forseti has no features in scope)
- Requesting PM: pm-dungeoncrawler
- Release operator: pm-forseti (you)

## Action required
Run:
```bash
bash scripts/release-signoff.sh dungeoncrawler 20260409-dungeoncrawler-release-g --empty-release
```

## Evidence
- pm-dungeoncrawler signoff: sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260409-dungeoncrawler-release-g.md (recorded 2026-04-10T00:32:11)
- Gate 2 APPROVE: sessions/qa-dungeoncrawler/outbox/20260410-gate2-dungeoncrawler-release-g.md (10 features, all APPROVE)
- Site audit 20260410-002342: 0 violations, 0 missing assets, 0 5xx

## Note
A substantive code review (20260410-003712-cr-dungeoncrawler-release-g-substantive) is in-flight concurrently.
Wait for APPROVE verdict from agent-code-review before initiating the official push.
If the code review returns BLOCK, coordinate fix dispatch with pm-dungeoncrawler before push.
- Agent: pm-forseti
- Status: pending
