# Test Plan: dc-apg-class-investigator

## Coverage summary
- AC items: ~28 (class record, key actions, methodologies, integration, edge cases)
- Test cases: 18 (TC-INV-01–18)
- Suites: playwright (character creation, encounter, exploration)
- Security: AC exemption granted (no new routes)

---

## TC-INV-01 — Class record and starting proficiencies
- Description: HP 8+Con/level; key ability Int; Expert Perception/Reflex/Will; Trained Fortitude; trained light armor; trained simple weapons + rapier; Society always trained
- Suite: playwright/character-creation
- Expected: all starting proficiencies as specified; Society in trained skills; key_ability = Int
- AC: Fundamentals-1–4

## TC-INV-02 — Skill count formula
- Description: Total trained skills = 4 + Int modifier + 1 (Society) + 1 (methodology skill)
- Suite: playwright/character-creation
- Expected: character.trained_skill_count = 4 + int_mod + 2 (Society and methodology are automatic)
- AC: Fundamentals-5

## TC-INV-03 — Devise a Stratagem: d20 stored and applied
- Description: 1-action Fortune trait; roll d20 immediately; result stored; applied to next qualifying Strike this turn
- Suite: playwright/encounter
- Expected: action_cost = 1; fortune_d20_stored = result; qualifying strike uses stored roll; cleared at end of turn
- AC: DaS-1–3, Integration-1

## TC-INV-04 — Devise a Stratagem: Int mod substitution on qualifying weapon
- Description: On qualifying Strike after DaS, use Int modifier instead of Str/Dex for attack roll
- Suite: playwright/encounter
- Expected: attack_modifier = int_modifier for qualifying agile/finesse/ranged/sap/unarmed weapon; non-qualifying uses normal mod
- AC: DaS-4

## TC-INV-05 — Devise a Stratagem: free action vs. active lead
- Description: When target is an active lead, DaS costs Free Action instead of 1 Action
- Suite: playwright/encounter
- Expected: if target in leads[] then action_cost = 0 (free)
- AC: DaS-5, Edge-1, Integration-2

## TC-INV-06 — Devise a Stratagem: stored roll discarded if unused
- Description: If no qualifying Strike is made this turn, stored d20 result is discarded at end of turn
- Suite: playwright/encounter
- Expected: stored_roll cleared at end_of_turn regardless of whether Strike was made
- AC: Edge-1

## TC-INV-07 — Pursue a Lead: 2-lead cap with auto-drop
- Description: 1-minute activity; +1 circumstance to investigating checks vs. lead; max 2 active leads; 3rd lead auto-ends oldest
- Suite: playwright/exploration
- Expected: max_leads = 2; adding 3rd removes oldest; +1 circumstance on investigation rolls vs. lead target
- AC: PaL-1–4, Integration-3

## TC-INV-08 — Clue In reaction
- Description: Reaction, 1/10 minutes; triggers on successful investigative check; shares lead circumstance bonus with one ally ≤30 ft
- Suite: playwright/exploration
- Expected: reaction available after successful investigate; one ally within 30 ft gains +1 circumstance; 10-min cooldown
- AC: ClueIn-1

## TC-INV-09 — Strategic Strike precision damage scaling
- Description: Precision damage on DaS-preceded attacks: 1d6/2d6/3d6/4d6/5d6 at L1/5/9/13/17
- Suite: playwright/encounter
- Expected: precision_damage_dice = floor((level - 1) / 4) + 1; capped at 5d6; does not stack with sneak attack
- AC: Strategic-1–2

## TC-INV-10 — Methodology: Alchemical Sciences
- Description: Crafting trained + Alchemical Crafting feat; daily versatile vials = Int modifier; Quick Tincture 1-action from vial
- Suite: playwright/character-creation
- Expected: Crafting = trained; Alchemical Crafting feat auto-granted; vial count = int_mod; Quick Tincture 1-action produces item from formula
- AC: Method-AS-1–4

## TC-INV-11 — Methodology: Empiricism
- Description: +1 Int skill; That's Odd feat; Expeditious Inspection free-action 1/10 min (instant RK/Seek/Sense Motive); waives lead requirement for DaS
- Suite: playwright/character-creation
- Expected: one Int-skill training bonus; Expeditious Inspection 1/10 min; DaS can target any creature (not just leads)
- AC: Method-Em-1–4, Edge-2

## TC-INV-12 — Methodology: Forensic Medicine
- Description: Medicine trained + Battle Medicine + Forensic Acumen; BM healing bonus = level; BM immunity 1 hour (not 1 day)
- Suite: playwright/character-creation
- Expected: Battle Medicine.heal_bonus += character.level; recovery_immunity_duration = 1 hour
- AC: Method-FM-1–4, Integration-6

## TC-INV-13 — Methodology: Interrogation
- Description: Diplomacy trained + No Cause for Alarm; Pursue a Lead usable in social encounters; Pointed Question 1-action exposes inconsistency
- Suite: playwright/character-creation
- Expected: Pointed Question available in social mode; Pursue a Lead triggers in conversation; NCFA feat auto-granted
- AC: Method-In-1–4

## TC-INV-14 — Pursue a Lead: specific target requirement
- Description: Lead target must be a specific creature, object, or location (not a category)
- Suite: playwright/exploration
- Expected: Pursue a Lead requires explicit target entity ID; generic descriptions blocked
- AC: PaL-4

## TC-INV-15 — Clue In 10-minute frequency
- Description: Clue In reaction can only trigger once every 10 minutes
- Suite: playwright/exploration
- Expected: clue_in.cooldown = 10 minutes; second trigger within cooldown window blocked
- AC: ClueIn-1

## TC-INV-16 — Integration: Alchemical Sciences vial count updates with Int modifier change
- Description: If Int modifier changes (ability boost, etc.), daily versatile vial count updates accordingly
- Suite: playwright/character-creation
- Expected: daily_vials recalculated on any Int modifier change
- AC: Integration-5

## TC-INV-17 — Integration: Forensic Medicine 1-hour immunity timer
- Description: Battle Medicine recovery immunity timer is exactly 1 hour for Forensic Medicine methodologists
- Suite: playwright/encounter
- Expected: post_battle_medicine.recovery_block_expires = now + 1_hour
- AC: Integration-6

## TC-INV-18 — Edge: Pointed Question requires prior statement from target
- Description: Pointed Question requires target to have made a statement this encounter; GM check flagged in UI
- Suite: playwright/encounter
- Expected: Pointed Question blocked or flagged if target.has_spoken_this_encounter = false; GM adjudication flag visible
- AC: Edge-3
