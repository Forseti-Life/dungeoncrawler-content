# QA Verification Report — Rogue Class (dc-cr-class-rogue)

- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-223200-impl-dc-cr-class-rogue
- Feature: dc-cr-class-rogue
- Release: 20260409-dungeoncrawler-release-g
- Verdict: **APPROVE**
- Generated: 2026-04-09

---

## Verification Results

### CLASSES['rogue'] — PASS
- `hp`: 8 ✓
- `key_ability`: 'Dexterity' (racket can override) ✓
- `proficiencies`: Perception Expert, Fortitude Trained, Reflex Expert, Will Expert, class_dc Trained ✓
- `skills`: 7 + INT modifier ✓
- `skill_increases_per_level`: 'every_level_from_2' ✓ (unique vs other classes)
- `skill_feats_per_level`: 'every_level' ✓ (unique vs other classes)
- `sneak_attack.damage_by_level`: L1=1d6, L5=2d6, L11=3d6, L17=4d6 ✓
- `sneak_attack.requires`: 'target is flat-footed to you' ✓
- `sneak_attack.damage_type`: 'precision' ✓
- `sneak_attack.no_vital_organs`: immune clause present ✓
- `racket` system — all 3 options verified:
  - ruffian: STR key ability, Intimidation skill, any simple weapon for SA, crit specialization ✓
  - scoundrel: CHA key ability, Deception skill, critical Feint → flat-footed vs all melee until next turn ✓
  - thief: DEX key ability, Thievery skill, DEX-to-damage with finesse ✓
- `debilitating_strike`: 3 debilitations (enfeebled-1, clumsy-1, flat-footed), mutually exclusive, until next turn ✓

### CLASS_ADVANCEMENT['rogue'] — PASS
- L1: Rogue Racket, Sneak Attack, Surprise Attack ✓
- L3: Deny Advantage ✓
- L5: Sneak Attack 2d6 ✓
- L7: Evasion, Weapon Expertise, Vigilant Senses ✓
- L9: Debilitating Strike, Rogue Expertise (class DC → Expert) ✓
- L11: Sneak Attack 3d6, Improved Evasion ✓
- L13: Light Armor Expertise, Weapon Specialization ✓
- L15: Slippery Mind (Will → Master) ✓
- L17: Sneak Attack 4d6, Greater Weapon Specialization, Light Armor Mastery ✓
- L19: Master Strike (Fortitude save or paralyzed/slowed) ✓
- No duplicate PHP integer keys ✓

### CLASS_FEATS['rogue'] — PASS
- 6 L1 feats present: nimble-dodge, trap-finder, twin-feint, you-re-next, eldritch-trickster-racket, mastermind-racket ✓
- Feature.md does not enumerate a specific count for rogue feats (unlike fighter); scope satisfied

### PHP lint — PASS
- No syntax errors ✓

### Suite — PASS
- `dc-cr-class-rogue-phpunit`: `required_for_release: true` ✓

### Site audit — PASS (reused 20260409-224020)
- 0 violations; data-only changes; no new routes ✓

---

## KB reference
- Lesson: duplicate PHP integer key pattern (first seen in swashbuckler) — rogue CLASS_ADVANCEMENT has no duplicate keys ✓
