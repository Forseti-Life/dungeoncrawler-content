# Suite Activation: dc-apg-archetypes

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-09T00:38:44+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-archetypes"`**  
   This links the test to the living requirements doc at `features/dc-apg-archetypes/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-archetypes-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-archetypes",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-archetypes"`**  
   Example:
   ```json
   {
     "id": "dc-apg-archetypes-<route-slug>",
     "feature_id": "dc-apg-archetypes",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-archetypes",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-archetypes

## Coverage summary
- AC items: ~35 (archetype system rules, 26+ archetypes, integration, edge cases)
- Test cases: 20 (TC-ARC-01–20)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-ARC-01 — Dedication feat gate: L2 minimum
- Description: Archetype Dedication feats appear at L2 in class feat slot; cannot be taken earlier
- Suite: playwright/character-creation
- Expected: dedication feats only available when character.level ≥ 2; class feat slot required
- AC: System-1

## TC-ARC-02 — 2-before-another-dedication rule
- Description: Cannot take a second Dedication feat from the same archetype until 2 other archetype feats from that archetype are selected
- Suite: playwright/character-creation
- Expected: second Dedication from same archetype blocked until 2 non-Dedication feats from that archetype are taken
- AC: System-2

## TC-ARC-03 — Dedication prerequisite enforcement
- Description: No archetype feat from a given archetype can be taken before its Dedication feat
- Suite: playwright/character-creation
- Expected: non-Dedication archetype feats blocked unless character has corresponding Dedication
- AC: System-1

## TC-ARC-04 — Proficiency cap: archetype grants do not exceed class maximums
- Description: Proficiency grants from Dedication feats are capped at the character's current class proficiency maximums
- Suite: playwright/character-creation
- Expected: weapon/armor/skill proficiency from archetype capped by class_max_proficiency for that category
- AC: System-Integration-3

## TC-ARC-05 — Combat archetypes: Acrobat proficiency scaling
- Description: Acrobat: Expert Acrobatics at dedication; Master at L7; Legendary at L15; crit Tumble Through ignores difficult terrain
- Suite: playwright/character-creation
- Expected: acrobatics proficiency upgrades at correct levels; tumble_through_crit.difficult_terrain_ignored = true
- AC: Martial-1

## TC-ARC-06 — Combat archetype: Assassin Mark for Death + weapon trait interaction
- Description: Mark for Death is 3-action; 1 mark at a time; +2 circumstance to Seek/Feint vs. mark; agile/finesse/unarmed vs. mark get backstabber + deadly d6 OR upgrade existing deadly die
- Suite: playwright/encounter
- Expected: mark stored per-target; only 1 active; attack bonus correct; deadly upgrade (not addition) if deadly already present
- AC: Martial-3, Edge-2

## TC-ARC-07 — Combat archetype: Marshal aura
- Description: 1-action aura (10-ft emanation); grants +1 circumstance to attacks OR +1 status to saves; chosen each activation
- Suite: playwright/encounter
- Expected: aura effect choice offered per activation; correct bonus type applied; mutually exclusive per activation
- AC: Martial-11, Edge-Integration-6

## TC-ARC-08 — Skill/social archetype: Bounty Hunter
- Description: Grants Hunt Prey (ranger feature); target must be known creature; +2 circumstance to Gather Information about prey
- Suite: playwright/character-creation
- Expected: Hunt Prey action available; target requirement enforced; gather_info bonus = +2 circumstance vs. prey
- AC: Skill-2

## TC-ARC-09 — Magic archetype: Beastmaster
- Description: Dedication → young animal companion; Call Companion 1-action to switch active; Cha-based primal focus spells; 1 FP pool; Refocus by tending companion
- Suite: playwright/character-creation
- Expected: companion added; Call Companion available only if ≥2 companions; focus pool = 1; refocus via tend
- AC: Magic-1, Integration-4

## TC-ARC-10 — Magic archetype: Blessed One
- Description: Available to ALL classes; grants lay on hands (divine focus spell); creates focus pool of 1 FP; Refocus via 10-min meditation
- Suite: playwright/character-creation
- Expected: feat available regardless of class; lay_on_hands added; focus pool created if not already present
- AC: Magic-2, Integration-5

## TC-ARC-11 — Magic archetype: Familiar Master
- Description: Grants familiar to characters without a class that normally provides one; uses standard familiar rules
- Suite: playwright/character-creation
- Expected: familiar added to character; familiar follows standard familiar mechanics
- AC: Magic-3

## TC-ARC-12 — Social/skill archetypes: Shadowdancer
- Description: Shadow jump/teleport abilities; shadow-based stealth bonuses; 5 total reqs
- Suite: playwright/encounter
- Expected: shadow_jump action available; stealth_bonus_shadow applies in low-light/darkness; all 5 feat reqs gated
- AC: Magic-19

## TC-ARC-13 — Social/skill archetype: Vigilante
- Description: Dual identity (social/vigilante personas); Perception-based identity protection; 8 total reqs
- Suite: playwright/character-creation
- Expected: two identity states stored; identity_revealed check uses Perception vs. Vigilante DC; 8-feat chain gated
- AC: Magic-23

## TC-ARC-14 — Social/skill archetype: Snarecrafter
- Description: Snare crafting time reduction; snare feat access; 4 total reqs
- Suite: playwright/character-creation
- Expected: snare_crafting_time reduced per feat progression; snare feats accessible; 4-feat chain
- AC: Magic-20

## TC-ARC-15 — Multiclass spellcasting archetypes: basic/expert/master progression
- Description: Archetypes with spellcasting follow basic/expert/master spellcasting progression
- Suite: playwright/character-creation
- Expected: spell slots granted per spellcasting progression table; proficiency follows basic→expert→master chain
- AC: System-4

## TC-ARC-16 — Integration: all 26+ dedication feats at L2 in feat selector
- Description: All 26+ archetype Dedication feats appear in L2 class feat slot
- Suite: playwright/character-creation
- Expected: feat count ≥ 26 archetypes visible at L2; each requires no other archetype feat first
- AC: Integration-1

## TC-ARC-17 — Integration: 2-before-dedication enforced at selection
- Description: Second Dedication from same archetype blocked until 2 non-Dedication feats selected
- Suite: playwright/character-creation
- Expected: UI blocks second Dedication; shows "need 2 more feats from [archetype]" message
- AC: Integration-2

## TC-ARC-18 — Edge: Archer bow proficiency scaling
- Description: Archer's bow proficiency upgrades at same character levels as class weapon proficiency (not independent)
- Suite: playwright/character-creation
- Expected: archetype proficiency upgrade triggers are tied to class level milestones for weapon proficiency
- AC: Edge-1

## TC-ARC-19 — Edge: Cavalier mount dependency
- Description: Cavalier requires a mount present; mount system must be implemented or flagged as dependency
- Suite: playwright/character-creation
- Expected: Cavalier dedication available; mount-dependent actions fail gracefully if no mount active; mount_system_required flag documented
- AC: Edge-3

## TC-ARC-20 — Edge: Ritualist without class spellcasting
- Description: Ritualist allows ritual casting without class spellcasting; ritual modifier uses chosen skill (not spellcasting modifier)
- Suite: playwright/character-creation
- Expected: Ritualist accessible to non-spellcasting classes; ritual_check_modifier = chosen_skill_modifier (not spell DC)
- AC: Magic-8, Edge-4

### Acceptance criteria (reference)

# Acceptance Criteria: APG Archetypes System

## Feature: dc-apg-archetypes
## Source: PF2E Advanced Player's Guide, Chapter 3

---

## Archetype System Rules

- [ ] Archetype feats are obtained by selecting a Dedication feat (minimum L2) as a class feat
- [ ] Each archetype requires its Dedication feat before any other archetype feats
- [ ] Cannot select a second Dedication feat from the same archetype until 2 other feats from that archetype are selected ("2-before-another-dedication" rule)
- [ ] Multiclass spellcasting archetypes follow basic/expert/master spellcasting progression patterns
- [ ] Archetype system integrates with the existing multiclass archetype system from the Core Rulebook

---

## Archetypes (26 defined in APG ch03)

### Martial / Combat Archetypes
- [ ] **Acrobat**: Dedication → Expert Acrobatics; scales to master (L7), legendary (L15); crit Tumble Through ignores difficult terrain
- [ ] **Archer**: Dedication → trained all simple/martial bows; bow proficiency scales with class; Expert in a bow → crit specialization for that bow
- [ ] **Assassin**: Dedication → Mark for Death (3-action); 1 mark at a time; +2 circumstance to Seek/Feint vs. mark; agile/finesse/unarmed vs. mark gain backstabber + deadly d6 (or upgrade existing deadly die)
- [ ] **Bastion**: Dedication → Reactive Shield fighter feat; satisfies Reactive Shield prerequisites
- [ ] **Cavalier**: Dedication → mount training; mount-based combat bonuses defined per feat chain
- [ ] **Dragon Disciple**: Dedication → draconic transformation chain; breath weapon and physical dragon traits gained via feats
- [ ] **Dual-Weapon Warrior**: Dedication → dual weapon attacks; combines two-weapon fighting bonuses
- [ ] **Duelist**: Dedication → precise dueling bonuses; one-handed weapon focus
- [ ] **Eldritch Archer**: Dedication → imbue arrows with spells; ranged spell delivery options
- [ ] **Gladiator**: Dedication → crowd-fighting bonuses; demoralize enhancements
- [ ] **Marshal**: Dedication → 1-action aura (10-ft emanation) granting allies +1 circumstance to attack or +1 status to saves; 7 total reqs implemented
- [ ] **Martial Artist**: Dedication → unarmed attack proficiency bump; ki spell options
- [ ] **Mauler**: Dedication → two-handed weapons; damage-focused feat chain
- [ ] **Sentinel**: Dedication → trained in all armor; heavy armor access without prerequisites
- [ ] **Viking**: Dedication → shield-focused abilities; brutal strike enhancements
- [ ] **Weapon Improviser**: Dedication → improvised weapon proficiency; improvised weapon traits

### Skill / Social Archetypes
- [ ] **Archaeologist**: Dedication → Expert Society + Expert Thievery; +1 circumstance to Recall Knowledge on ancient/historical subjects
- [ ] **Bounty Hunter**: Dedication → Hunt Prey (ranger feature); target must be known creature; +2 circumstance to Gather Information about prey
- [ ] **Celebrity**: Dedication → fame/recognition mechanics; Perform-based social benefits
- [ ] **Dandy**: Dedication → social manipulation; Impression and Make an Impression bonuses
- [ ] **Horizon Walker**: Dedication → terrain movement bonuses; trackless step options
- [ ] **Linguist**: Dedication → +2 bonus languages; accelerated language learning
- [ ] **Loremaster**: Dedication → Recall Knowledge bonuses; secret lore access feats
- [ ] **Pirate**: Dedication → ship combat + nautical actions
- [ ] **Scout**: Dedication → +2 to initiative when using Stealth; Avoid Notice benefit enhancement

### Magic / Hybrid Archetypes
- [ ] **Beastmaster**: Dedication → young animal companion; stackable with existing companion; Call Companion 1-action to switch active companion; Cha-based primal focus spells; focus pool at 1 FP; Refocus by tending companion
- [ ] **Blessed One**: Dedication → lay on hands devotion spell (divine); creates focus pool of 1 FP; Refocus via 10-min meditation; available to any class
- [ ] **Familiar Master**: Dedication → familiar without a class that normally grants one; familiar uses standard familiar rules
- [ ] **Herbalist**: Dedication → advanced healing items; herbal preparation actions
- [ ] **Medic**: Dedication → Battle Medicine improvements; expanded healing feat chain
- [ ] **Poisoner**: Dedication → poison application improvements; poison DC scaling
- [ ] **Ritualist**: Dedication → ritual casting without class spellcasting; ritual preparation bonuses; 6 total reqs
- [ ] **Scroll Trickster**: Dedication → Use Magic Item for scrolls without tradition match; improvised spellcasting
- [ ] **Scrounger**: Dedication → improvised item creation from found materials; Crafting without kits
- [ ] **Shadowdancer**: Dedication → shadow jump/teleport; shadow-based stealth bonuses; 5 total reqs
- [ ] **Snarecrafter**: Dedication → snare crafting time reduction; snare feat access; 4 total reqs
- [ ] **Talisman Dabbler**: Dedication → attach talismans faster; affix without proficiency restrictions
- [ ] **Vigilante**: Dedication → dual identity mechanics (social/vigilante personas); Perception-based identity protection; 8 total reqs

---

## Integration Checks

- [ ] All 26+ archetype Dedication feats appear at L2 in the feat selector (class feat slot)
- [ ] 2-before-another-dedication rule enforced at selection time
- [ ] Proficiency grants from dedication feats do not exceed the character's current class maximums (capped properly)
- [ ] Beastmaster: Call Companion action available only if character has ≥2 companions
- [ ] Blessed One: available to ALL classes, not gated behind divine spellcasting
- [ ] Marshal aura is a 1-action activity; effect (attack bonus OR save bonus) chosen each time it's activated

## Edge Cases

- [ ] Archer Dedication: bow proficiency scaling applies at the same character levels as class weapon proficiency upgrades (not independent)
- [ ] Assassin Mark for Death: weapon trait interaction — if weapon already has deadly trait, increase die size (not add new deadly)
- [ ] Cavalier: requires a mount to be present; mount system must be implemented or flagged as dependency
- [ ] Ritualist: character does not need class spellcasting to perform rituals; ritual-casting modifier uses chosen skill
