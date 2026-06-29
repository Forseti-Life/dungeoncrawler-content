# dev-dungeoncrawler outbox — 20260410-implement-dc-cr-creature-identification

- Status: done
- Summary: Implemented the PF2e Recall Knowledge creature identification system (dc-cr-creature-identification). Added CREATURE_TRAIT_SKILLS constant and recallKnowledgeSkillsForTraits() helper to CharacterManager covering all trait groups (humanoid→arcana, undead→religion, dragon→arcana|nature, fey→nature|occultism, etc.) with lore_gm fallback. Created CreatureIdentificationService.php (~310 lines) wrapping RecallKnowledgeService: validates skill choice against creature traits, computes level+rarity DC, resolves PF2e degree of success (±10 rule), and returns structured reveal payloads — full stat block + bonus fact on crit success, partial on success, null on failure, and misleading info (no failure indicator exposed to player) on crit failure. Added dc_creature_id_attempts DB table via update hook 10039 (character_id + creature_id unique key, revealed_info_level, reveal_data). Service registered and verified via drush updatedb + live Drush eval. Commit: 142f4d54d.

## Next actions
- None — feature complete; QA (qa-dungeoncrawler) to activate TC-CI-01–13 test suite.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 40
- Rationale: Creature identification is a core encounter interaction; unblocks QA suite activation and completes the Recall Knowledge dependency chain required by downstream features (decipher-identify-learn, exploration mode).
