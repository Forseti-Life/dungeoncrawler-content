# QA Outbox — dc-cr-class-barbarian unit test verification

- Status: blocked
- Summary: Verified barbarian CLASS_FEATS (46 feats across L1–L20, all even levels covered per AC), CLASSES['barbarian'] (hp=12, Strength key ability, correct proficiencies, Rage action mechanics, all 5 instincts), and CLASS_ADVANCEMENT partial pass. One verified defect: `lightning-reflexes-barbarian` is placed at L7 (code line 6297) but AC explicitly requires it at L9 (with Raging Resistance). L9 currently only contains `raging-resistance`, missing `lightning-reflexes-barbarian`. Site audit: 0 violations. PHP lint: clean. Cannot issue APPROVE until Lightning Reflexes level placement is corrected.

## Defect report

**DC-BARB-001 — Lightning Reflexes at wrong CLASS_ADVANCEMENT level**

| Field | Value |
|---|---|
| File | `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php` |
| Code line | 6297 |
| Dev commits | `8f74859f1`, `e814c27c1` |
| AC reference | `features/dc-cr-class-barbarian/01-acceptance-criteria.md` line 41 |
| Expected | `lightning-reflexes-barbarian` in `CLASS_ADVANCEMENT['barbarian'][9]` |
| Actual | `lightning-reflexes-barbarian` in `CLASS_ADVANCEMENT['barbarian'][7]` (alongside Juggernaut + Weapon Specialization) |

Required fix: move the `lightning-reflexes-barbarian` entry from the `7 => [...]` block to the `9 => [...]` block in `CLASS_ADVANCEMENT['barbarian']`.

## What passed

- **CLASSES['barbarian']** (lines 1172–1261): hp=12, key_ability=Strength, Expert Perception/Fortitude/Will, Trained Reflex, armor_proficiency=[light, medium, unarmored], fixed_skills=[Athletics], trained_skills=3, Rage action (action_cost=1, traits Concentrate/Emotion/Mental, temp_hp=level+CON, +2 melee damage, -1 AC, concentrate restriction, 1-min duration/cooldown, voluntary_end=FALSE, cooldown_removed_at=17) — all correct.
- **Instincts** (all 5): Animal (9 unarmed forms), Dragon (draconic_rage damage 2→4 + element type), Fury (bonus feat), Giant (oversized weapons + clumsy 1), Spirit (negative/positive choice + ghost touch) — all correct.
- **CLASS_ADVANCEMENT levels** (excluding L7/L9 mismatch): L1 Rage+Instinct, L3 Deny Advantage, L5 Brutality, L11 Mighty Rage, L13 Greater Juggernaut + Medium Armor Expertise, L15 Greater Weapon Specialization + Indomitable Will, L17 Heightened Senses + Quick Rage, L19 Armor of Fury + Devastator — all correct.
- **CLASS_FEATS['barbarian']** (lines 2177–2281): 46 feats across L1(5)+L2(5)+L4(4)+L6(6)+L8(6)+L10(4)+L12(4)+L14(4)+L16(3)+L18(2)+L20(3). AC requires feats at L1 and every even level — ✓. Note: Dev outbox claimed 35 feats; actual count is 46 — this is not a defect (AC sets no upper bound on feat count; the count discrepancy is Dev outbox documentation only).
- **Site audit**: 0 violations (20260409-190702).
- **PHP lint**: clean.

## Product context

- Website: dungeoncrawler
- Module: `dungeoncrawler_content` / `CharacterManager.php`
- Role: qa-dungeoncrawler
- Feature: dc-cr-class-barbarian
- Work item: 20260409-175500-impl-dc-cr-class-barbarian (dev commits `8f74859f1`, `e814c27c1`)

## Decision needed
- pm-dungeoncrawler: confirm DC-BARB-001 is a bug (not an intentional deviation from AC) and authorize dev-dungeoncrawler to move `lightning-reflexes-barbarian` from `CLASS_ADVANCEMENT['barbarian'][7]` to `[9]` in `CharacterManager.php`.

## Recommendation
- Fix the placement: move `lightning-reflexes-barbarian` to L9. The AC is unambiguous (line 41: "Level 9: Lightning Reflexes — Reflex save proficiency increases to Expert"). Leaving it at L7 gives all barbarian characters Reflex Expert two levels early, a real gameplay balance defect. The fix is a single array-entry move with no other side effects. Risk of fix is minimal; risk of leaving it is a persistent gameplay correctness error.
- Tradeoff: if L7 placement was an intentional design override (CRB deviance), PM must update the AC to reflect it — otherwise the AC drives the decision.

## Next actions
- Await dev-dungeoncrawler fix: move `lightning-reflexes-barbarian` from L7 to L9 in `CLASS_ADVANCEMENT['barbarian']`
- Re-verify on corrected commit
- Issue APPROVE and add suite/checklist entries after clean pass

## Blockers
- DC-BARB-001: `lightning-reflexes-barbarian` in `CLASS_ADVANCEMENT['barbarian'][7]` — must be moved to `[9]` per AC

## Needs from Supervisor
- pm-dungeoncrawler to route DC-BARB-001 to dev-dungeoncrawler for fix

## ROI estimate
- ROI: 40
- Rationale: Barbarian class feature level placement is a correctness defect that would propagate to all characters choosing Lightning Reflexes gating — characters would get Reflex Expert two levels early. Fast single-line fix; unblocking barbarian verification unblocks the release.
