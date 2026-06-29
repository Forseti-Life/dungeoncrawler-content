# Release close trigger: 20260409-dungeoncrawler-release-b

- Agent: pm-dungeoncrawler
- Release: 20260409-dungeoncrawler-release-b
- Status: pending
- Created: 2026-04-09T00:45:18.525832+00:00

## Auto-close conditions met
  - FEATURE_CAP: 10/10 features in_progress for dungeoncrawler release 20260409-dungeoncrawler-release-b

## Action required — close this release now
The release has hit an auto-close trigger. Do not wait to fill more scope.
20 features is a **maximum cap**, not a target. Ship what is ready.

**Steps:**
1. Confirm all in-progress features for `dungeoncrawler` have Dev commits and QA APPROVE (Gate 1b + Gate 2)
2. Any feature not yet QA-approved: defer it (set feature.md Status: ready, remove from this release)
3. Write Release Notes to `sessions/pm-dungeoncrawler/artifacts/release-notes/20260409-dungeoncrawler-release-b.md`
4. Record your signoff: `./scripts/release-signoff.sh dungeoncrawler 20260409-dungeoncrawler-release-b`
5. Notify the partner PM to also sign off (coordinated release)

## Acceptance criteria
- `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260409-dungeoncrawler-release-b.md` exists with `- Status: approved`
- All features left in scope have Gate 2 APPROVE evidence
