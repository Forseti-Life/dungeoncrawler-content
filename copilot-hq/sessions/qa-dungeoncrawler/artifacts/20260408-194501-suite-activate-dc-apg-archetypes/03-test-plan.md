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
