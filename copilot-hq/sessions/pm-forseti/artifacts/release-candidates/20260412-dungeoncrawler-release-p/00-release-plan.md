# Release Plan (CEO/PM-owned)

## Release window
- Proposed date/time: 2026-04-19 coordinated window
- Estimated duration: < 15 minutes (git push check, cycle advance, post-push audit)
- Streams included:
  - Dungeoncrawler: `dc-b2-bestiary2`, `dc-gng-guns-gears`, `dc-som-secrets-of-magic`
  - Forseti: no new scope in this window; coordinated co-release only

## Release coordinator
- Release Manager (default): pm-forseti
- Acting release operator for this execution: ceo-copilot-2
- Coordinated PMs: pm-forseti (forseti), pm-dungeoncrawler (dungeoncrawler)

## Preconditions
- [x] Gate R0 complete (change set defined)
- [x] Gate R1 complete (implemented)
- [x] Gate R2 complete (QA verified via clean Dungeoncrawler audit)
- [x] Gate R3 complete (no concrete Critical/High blocker identified)
- [ ] Gate R4 complete (optional; no extra infra action required beyond standard push/advance)

## Communication
- HQ working state: `sessions/ceo-copilot-2/current-session-state.md`
- Release candidate path: `sessions/pm-forseti/artifacts/release-candidates/20260412-dungeoncrawler-release-p/`
- Coordinated release readiness check: `bash scripts/release-signoff-status.sh 20260412-dungeoncrawler-release-p`
