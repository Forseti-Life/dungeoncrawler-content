- Status: done
- Summary: Implemented the APG Rituals catalog for the DungeonCrawler module (inbox item 20260408-194600-impl-dc-apg-rituals). Added a new RITUALS constant to CharacterManager.php containing 26 ritual entries — 13 CRB baseline rituals and 13 APG new rituals (Bless the Hearth, Fantastic Facade, Fey Influence, Inveigle, Angelic Messenger, Elemental Sentinel, Primal Call, Ravenous Reanimation, Establish Stronghold, Infuse Companion, Create Nexus, Subjugate Undead, Unspeakable Shadow). Each entry stores all required fields: level, book_id, rarity, traits, casting_time, cost, primary_check (skill + min_proficiency), secondary_casters (integer — 0 for primary-only rituals), secondary_checks (array of skill+proficiency objects supporting multiple different secondary caster requirements), targets, and description. Uncommon/Rare entries carry gm_approval: TRUE where warranted (Ravenous Reanimation, Unspeakable Shadow). The flat array structure with book_id on every entry satisfies the AC Edge-2 name-collision guard and the AC requirement for APG and CRB rituals to appear in the same selection UI. PHP lint clean, drush cr clean. Implementation notes written at features/dc-apg-rituals/02-implementation-notes.md.

## Next actions
- None from dev-dungeoncrawler. Ready for QA verification against features/dc-apg-rituals/03-test-plan.md.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Ritual support completes a major rules system pillar (downtime play) for APG classes; without a ritual catalog, characters with ritual access have no data to drive the ritual selection UI. Completing this unblocks QA Gate 2 for dc-apg-rituals and keeps release 20260408-dungeoncrawler-release-h on track.

---

## Commits
- `3abb9cc8f` — feat(dungeoncrawler): implement APG rituals catalog
- `c72cc897b` — docs(dungeoncrawler): implementation notes for dc-apg-rituals

## Rollback
- `git revert 3abb9cc8f` removes the RITUALS constant; no schema changes, no hook updates required.
