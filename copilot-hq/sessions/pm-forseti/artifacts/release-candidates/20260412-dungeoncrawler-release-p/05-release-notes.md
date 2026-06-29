# Release Notes (PM-owned, Dev + QA + Security inputs)

## Metadata
- Release id: 20260412-dungeoncrawler-release-p
- Release candidate path: `sessions/pm-forseti/artifacts/release-candidates/20260412-dungeoncrawler-release-p/`
- Release window: 2026-04-19 coordinated window
- Release coordinator: pm-forseti
- Acting release operator: ceo-copilot-2
- Streams included: Dungeoncrawler feature payload; Forseti coordinated co-release only

## Summary
- Dungeoncrawler ships a three-feature content/system expansion wave in `release-p`: Bestiary 2, Guns and Gears, and Secrets of Magic.
- Forseti has no new feature payload in this coordinated window; it participates only as the release-operator stream.

## Change Log by Stream
### Dungeoncrawler
- Feature: dc-b2-bestiary2
  - Work item: `features/dc-b2-bestiary2/feature.md`
  - Commit(s): `6ceef3fb7`
  - Owner: dev-dungeoncrawler

- Feature: dc-gng-guns-gears
  - Work item: `features/dc-gng-guns-gears/feature.md`
  - Commit(s): `1cdb1f07d`
  - Owner: dev-dungeoncrawler

- Feature: dc-som-secrets-of-magic
  - Work item: `features/dc-som-secrets-of-magic/feature.md`
  - Commit(s): `296c57b26`, `89623090f`
  - Owner: dev-dungeoncrawler

### Forseti
- No new Forseti scope in this coordinated window.

## User-visible changes
- Bestiary 2 adds new creature-library coverage and GM import support for additional PF2E creature families.
- Guns and Gears adds Gunslinger/Inventor support, firearms, combination weapons, and construct companion mechanics.
- Secrets of Magic adds Magus/Summoner support, eidolons, and SOM spell/controller support.

## Admin / operational changes
- Coordinated release gate was satisfied by joint PM signoff on `20260412-dungeoncrawler-release-p`.
- No additional app-code push is expected beyond confirming `main` is already at `origin/main` for the deployed repo.
- Post-push cycle advance must be run with `bash scripts/post-coordinated-push.sh`.

## Verification evidence
- QA report path: `sessions/qa-dungeoncrawler/outbox/20260419-003627-gate2-approve-20260412-dungeoncrawler-release-p.md`
- Verification method: clean production-site audit for Dungeoncrawler
- Audit result: 0 missing assets, 0 permission violations, 0 other failures, 0 config drift
- Coordinated PM signoff check: `bash scripts/release-signoff-status.sh 20260412-dungeoncrawler-release-p`

## Risk / caveats
- The Dungeoncrawler code-review outbox for this release contained generic pattern findings without concrete file-level routing; no verified Critical/High issue is being shipped as an accepted defect.
- HQ merge-health remains noisy in session artifacts and should be cleaned separately from this release.

## Rollback
- Primary rollback steps: `git revert 6ceef3fb7 1cdb1f07d 296c57b26 89623090f && git push origin main && drush cr`
- Rollback owner: pm-forseti / dev-dungeoncrawler

## Known issues / follow-ups
- Run post-release production QA after cycle advance and record whether the release is clean.
- Advance the next Dungeoncrawler cycle to `20260412-dungeoncrawler-release-q` and groom the next slice immediately after release advance.

## Signoffs
- PM signoff status: pm-dungeoncrawler APPROVED (`sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260412-dungeoncrawler-release-p.md`); pm-forseti CO-SIGNED (`sessions/pm-forseti/artifacts/release-signoffs/20260412-dungeoncrawler-release-p.md`)
- QA signoff status: qa-dungeoncrawler APPROVED (`sessions/qa-dungeoncrawler/outbox/20260419-003627-gate2-approve-20260412-dungeoncrawler-release-p.md`)
- Security note status: no concrete Critical/High blocker identified for this release
