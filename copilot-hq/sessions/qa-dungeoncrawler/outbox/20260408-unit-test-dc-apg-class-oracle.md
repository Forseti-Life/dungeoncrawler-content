# QA Verification Report — dc-apg-class-oracle

- Feature: dc-apg-class-oracle
- Dev item: 20260408-200013-impl-dc-apg-class-oracle
- Dev commit: `4f3bb2be9`
- QA decision: **APPROVE**
- Date: 2026-04-08

## Evidence

### CLASSES['oracle']
All core mechanics confirmed:
- hp=8, key_ability=Charisma ✓
- proficiencies: Perception=Trained, Fortitude=Trained, Reflex=Trained, Will=Expert ✓
- spontaneous=TRUE, somatic_only=TRUE ✓
- repertoire_start: cantrips=5, first=2 ✓
- cantrip_heightening='half_level_round_up' ✓
- signature_spells: unlocks_at_level=3, count_per_spell_level=1 ✓
- mystery: required=TRUE, 8 options (ancestors/battle/bones/cosmos/flames/life/lore/tempest) ✓
- revelation_spells_at_l1: count=2, initial_fixed=TRUE (mystery's initial revelation is fixed; second is domain choice) ✓

**Oracular Curse** (4-stage state machine confirmed):
- traits: Curse, Divine, Necromancy ✓
- basic_always_active=TRUE ✓
- state transitions: basic→minor→moderate→overwhelmed (each triggered by casting any Cursebound spell) ✓
- overwhelmed: blocks all revelation spell casting until next daily prep ✓
- refocus_at_moderate: resets curse to minor + restores 1 Focus Point ✓
- daily_reset: 8hrs rest + daily prep returns curse to basic ✓
- irremovable=TRUE, irremovable_note confirmed ✓

### CLASS_ADVANCEMENT['oracle']
Confirmed levels:
- L1: 5 auto_features (oracle-divine-spellcasting, oracle-mystery, oracle-revelation-spells, oracle-oracular-curse, oracle-focus-pool) ✓
- L3: signature-spells ✓
- L5: lightning-reflexes (Reflex→Expert) ✓
- L7: weapon-expertise (simple+unarmed→Expert) ✓
- L9: magical-fortitude (Fortitude→Expert) + alertness (Perception→Expert) ✓

### PHP lint
`No syntax errors detected` — clean.

### Site audit
Run: 20260408-231209 — 0 missing assets, 0 permission violations, 0 failures. PASS.
(No new routes — ACL exemption applies.)

## KB references
- ORACLE_MYSTERIES constants (8 mysteries, 38 id entries, 4-stage curses) verified in prior focus-spells item (20260408-194600); cross-confirmed consistent with CLASSES['oracle'] mystery list.

## No new items for Dev
No defects found. PM may proceed to release gate for dc-apg-class-oracle.
