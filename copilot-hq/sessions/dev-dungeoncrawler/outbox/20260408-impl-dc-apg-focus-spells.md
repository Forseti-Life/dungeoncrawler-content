- Status: done
- Summary: Implemented all APG focus spell rules for the DungeonCrawler module (inbox item 20260408-194600-impl-dc-apg-focus-spells). Added four new constants to CharacterManager.php — ORACLE_MYSTERIES (8 mysteries, each with initial/advanced/greater Cursebound revelation spells and a unique 4-stage curse progression), BARD_FOCUS_SPELLS (3 APG composition spells including Gravity Weapon dice-count scaling and Song of Strength no-stack guard), RANGER_WARDEN_SPELLS (pool config + 3 warden spells), and FOCUS_POOLS (oracle starts at 2 Focus Points — the one documented exception; witch/bard/ranger start at 1). WITCH_HEXES was enriched with sustain, will_save_ends, one_hex_per_turn, auto_heighten flags on all entries; Cackle received requires_active_hex and free_action_feat_required flags with grace-fail semantics; Phase Familiar got incorporeal_brief and no_persist flags. CLASSES['oracle'] and CASTER_SPELL_SLOTS['oracle'] were updated with mystery, focus_pool, and cursebound metadata. PHP lint clean, drush cr clean. Implementation notes written at features/dc-apg-focus-spells/02-implementation-notes.md.

## Next actions
- None from dev-dungeoncrawler. Ready for QA verification against features/dc-apg-focus-spells/03-test-plan.md.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Focus spells are a core mechanic for Oracle, Bard, Ranger, and Witch — blocking character-level calculator completeness for four APG classes. Completing this unblocks QA Gate 2 for the dc-apg-focus-spells feature and keeps release 20260408-dungeoncrawler-release-h on track.

---

## Commits
- `a01e1af8e` — feat(dungeoncrawler): implement APG focus spells (CharacterManager.php)
- `d347c7233` — docs(dungeoncrawler): add implementation notes for dc-apg-focus-spells
