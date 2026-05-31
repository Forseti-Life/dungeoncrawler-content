# Test Plan: dc-apg-feats

## Coverage summary
- AC items: ~45+ (non-skill general feats, skill feats by skill category, multi-skill feats)
- Test cases: 20 (TC-FEAT-01–20)
- Suites: playwright (character creation, encounter, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-FEAT-01 — Non-skill general feats: prerequisites and basic functionality
- Description: Hireling Manager (+2 circ to hireling checks), Improvised Repair (shoddy state), Keen Follower (scales 3/4 by expertise), Pick Up the Pace (+20 min group Hustle)
- Suite: playwright/character-creation
- Expected: each feat gated by correct prerequisites; bonuses apply correctly; Improvised Repair creates shoddy state distinct from normal
- AC: NonSkill-1–4

## TC-FEAT-02 — Prescient Planner / Prescient Consumable
- Description: Prescient Planner: retroactive procurement of common items (level ≤ half char level, Bulk constrained, not weapon/armor/alchemical/magic); Prescient Consumable extends to consumables
- Suite: playwright/downtime
- Expected: item eligibility filtering enforced; price deducted; once per shopping opportunity; Prescient Consumable requires Prescient Planner as prereq
- AC: NonSkill-5–6

## TC-FEAT-03 — Skitter, Thorough Search, True Perception
- Description: Skitter: Crawl up to half Speed; Thorough Search: double Search time → +2 circ to Seek; True Perception: permanent true seeing (own Perception modifier for counteract)
- Suite: playwright/encounter
- Expected: Skitter allows faster crawl; Thorough Search bonus applies only to doubled-time version; True Perception uses character's Perception for counteract checks
- AC: NonSkill-7–9

## TC-FEAT-04 — A Home in Every Port, Caravan Leader, Incredible Scout
- Description: Home in Every Port: free lodging for self+6 per settlement; Caravan Leader (prereq Pick Up the Pace): group Hustle → longest member duration + 20 min; Incredible Scout: +2 ally initiative (was +1)
- Suite: playwright/downtime
- Expected: lodging for ≤7; Caravan Leader prereq enforced; Scout bonus = +2
- AC: NonSkill-10–12

## TC-FEAT-05 — Acrobatics skill feats: Acrobatic Performer, Aerobatics Mastery
- Description: Acrobatic Performer: Acrobatics substitutes for Perform action; Aerobatics Mastery: +2 to Maneuver in Flight; Master: 2 maneuvers → 1 action (DC+5); Legendary: 3 → 1 (DC+10)
- Suite: playwright/encounter
- Expected: Acrobatic Performer opens as Perform substitute; Aerobatics bonuses gated by proficiency rank
- AC: Acrobatics-1–2

## TC-FEAT-06 — Athletics skill feats: Lead Climber, Water Sprint
- Description: Lead Climber: crit fail climb → ally's regular fail (double crit = both fall); Water Sprint: cross water during Stride (must end on solid)
- Suite: playwright/encounter
- Expected: Lead Climber only applies when ally crits failure; Water Sprint movement path constrained by rank (straight at Master, any at Legendary)
- AC: Athletics-1–2

## TC-FEAT-07 — Crafting skill feats: Crafter's Appraisal, Improvise Tool, Rapid Affixture
- Description: Crafter's Appraisal: Crafting for magic item Identify; Improvise Tool: Repair without kit, basic items without crafter's book; Rapid Affixture: Affix Talisman → 1 min (Master), 3 actions (Legendary)
- Suite: playwright/downtime
- Expected: Identify Magic check uses Crafting for items; Improvise Tool blocked if raw materials absent; Affixture time reduces correctly
- AC: Crafting-1–3

## TC-FEAT-08 — Deception: Doublespeak
- Description: Hidden message in conversation; long-term allies understand automatically; others need Perception vs. Deception DC to detect; crit to decode
- Suite: playwright/encounter
- Expected: long-term ally flag = auto-understand; non-ally: Perception vs. DC check; crit success only decodes (not just detects)
- AC: Deception-1

## TC-FEAT-09 — Diplomacy: Bon Mot
- Description: 1-action; status penalty to Perception + Will saves on target; target removes with verbal/concentrate retort; crit fail = penalty on caster instead
- Suite: playwright/encounter
- Expected: success → target.penalty applied; target removes via retort action; crit fail → self_penalty; no other conditions
- AC: Diplomacy-1

## TC-FEAT-10 — Diplomacy: No Cause for Alarm
- Description: 3-action; reduces Frightened in 10-ft emanation on success/crit; 1-hour immunity after
- Suite: playwright/encounter
- Expected: 3-action cost; Frightened reduced by success; 1-hour immunity applied; emanation = 10 ft
- AC: Diplomacy-2

## TC-FEAT-11 — Intimidation: Terrifying Resistance; Lore: Battle Assessment
- Description: Terrifying Resistance: +1 circ to saves vs. spells from Demoralized creature (24 hr); Battle Assessment: Recall Knowledge reveals highest damage modifier or attack bonus
- Suite: playwright/encounter
- Expected: Terrifying Resistance: timing requires prior Demoralize + 24-hour window; Battle Assessment: success reveals one value (highest)
- AC: Intimidation-1, Lore-1

## TC-FEAT-12 — Medicine: Continual Recovery, Robust Recovery, Ward Medic, Godless Healing
- Description: Continual Recovery: Battle Medicine immunity → 10 min; Robust Recovery: Treat crit = max HP next check + temp HP; Ward Medic: treat up to 4 simultaneously; Godless Healing: no deity requirement
- Suite: playwright/encounter
- Expected: immunity window = 10 min; Ward Medic: ≤4 patients; Godless Healing: unrestricted; Robust Recovery temp HP = roll value
- AC: Medicine-1–4

## TC-FEAT-13 — Group Aid
- Description: Aid up to 4 creatures simultaneously; preparation 1 turn in advance; circumstance bonus to all qualifying skill checks that turn
- Suite: playwright/encounter
- Expected: preparation action taken prior turn; bonus = circumstance (no stacking); applies to up to 4 targets
- AC: MultiSkill-1

## TC-FEAT-14 — Fascinating Spellcaster
- Description: Casting a spell to Fascinate — use spell level to set Fascinate DC; targets must succeed Perception vs. DC
- Suite: playwright/encounter
- Expected: Fascinate DC = f(spell level); targets make Perception check; spellcasting prereq enforced
- AC: MultiSkill-2

## TC-FEAT-15 — Oddity Identification, Recognize Spell expansion
- Description: Oddity Identification: +2 circ to Recall Knowledge for curses/haunts/oddities; Recognize Spell: non-primary tradition at –2; recognize ritual preparations
- Suite: playwright/encounter
- Expected: Oddity bonus applies only to targeted sub-types; Recognize Spell –2 penalty for non-primary; ritual prep identifiable
- AC: MultiSkill-3–4

## TC-FEAT-16 — Scare to Death (Uncommon)
- Description: 1/day; target must be Frightened 4+; Fortitude save; fail → massive damage + unconscious chain; uncommon access gate
- Suite: playwright/encounter
- Expected: frequency = 1/day; Frightened ≥4 prereq enforced; Uncommon gate blocks non-authorized characters
- AC: MultiSkill-5

## TC-FEAT-17 — Shameless Request, Trick Magic Item clarification
- Description: Shameless Request: reduce attitude requirement by 1 step (even hostile); Trick Magic Item: tradition check uses Arcana/Nature/Occultism/Religion per tradition
- Suite: playwright/encounter
- Expected: attitude threshold lowers by 1 step; Trick Magic Item skill routing matches tradition
- AC: MultiSkill-6–7

## TC-FEAT-18 — Nature: Bonded Animal, Train Animal
- Description: Bonded Animal: deep bond with one specific non-combat animal (complex commands); Train Animal: 1 week downtime per trick; prereq Bonded Animal
- Suite: playwright/downtime
- Expected: bond targets exactly one animal; complex commands follow; Train Animal prereq enforced; 1 week per trick
- AC: Nature-1–2

## TC-FEAT-19 — System requirement: general feats purchasable by any class
- Description: All general feats in APG appear in the general feat selection for any character regardless of class/ancestry
- Suite: playwright/character-creation
- Expected: feat selector shows APG general feats; no class/ancestry gate; only level/stat prerequisites
- AC: System-1

## TC-FEAT-20 — Feat prerequisite enforcement (multi-feat chain)
- Description: Caravan Leader requires Pick Up the Pace; Prescient Consumable requires Prescient Planner; Aerobatics Mastery rank gates; all enforced in character creation
- Suite: playwright/character-creation
- Expected: prerequisite chain blocks out-of-order feat selection; selecting prerequisite first unlocks dependent feat
- AC: System-2
