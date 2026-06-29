# Suite Activation: dc-apg-feats

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:01+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-feats"`**  
   This links the test to the living requirements doc at `features/dc-apg-feats/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-feats-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-feats",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-feats"`**  
   Example:
   ```json
   {
     "id": "dc-apg-feats-<route-slug>",
     "feature_id": "dc-apg-feats",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-feats",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: APG General and Skill Feats

## Feature: dc-apg-feats
## Source: PF2E Advanced Player's Guide, Chapter 4

---

## General Feat System

- [ ] System supports general feats purchasable by any character regardless of class or ancestry (subject only to level and stat prerequisites)
- [ ] General feats categorized as non-skill (stat/exploration-based) or skill (tied to a trained skill)

---

## Non-Skill General Feats

- [ ] `Hireling Manager`: +2 circumstance bonus to all skill checks made by the character's hirelings
- [ ] `Improvised Repair` (3-action): broken non-magical item can be used as shoddy version until it takes damage again; shoddy state is distinct (functional but fragile)
- [ ] `Keen Follower`: scales Follow the Expert circumstance bonuses — +3 if ally is Expert, +4 if Master
- [ ] `Pick Up the Pace`: extends group Hustle duration by 20 minutes; capped at highest Con modifier member's solo Hustle limit
- [ ] `Prescient Planner`: retroactive procurement of adventuring gear with constraints — common rarity, level ≤ half character level, Bulk within encumbrance, not weapon/armor/alchemical/magic; must pay price; once per shopping opportunity
- [ ] `Skitter`: Crawl movement at up to half Speed (default Crawl is 5 feet)
- [ ] `Thorough Search`: doubling Search time grants +2 circumstance to Seek checks
- [ ] `Prescient Consumable` (prereq Prescient Planner): extends Prescient Planner to include consumable items within same rarity/level/Bulk constraints
- [ ] `Supertaster`: secret Perception check when eating/drinking near poison; success reveals poisoning without identifying specific poison; +2 to Recall Knowledge when taste is relevant (GM discretion)
- [ ] `A Home in Every Port` (downtime): secures free comfortable lodging for up to 7 characters (self + 6) for 24 hours in any settlement
- [ ] `Caravan Leader` (prereq Pick Up the Pace): extends group Hustle to longest solo duration among all members + 20 more minutes
- [ ] `Incredible Scout`: Scout exploration activity's initiative bonus to allies increases from +1 to +2
- [ ] `True Perception`: permanent true seeing effect using character's Perception modifier for counteract checks

---

## Skill Feats (APG additions by skill)

### Acrobatics
- [ ] `Acrobatic Performer`: Acrobatics substitutes for Perform action
- [ ] `Aerobatics Mastery`: +2 to Maneuver in Flight; Master rank: 2 maneuvers → 1 action (DC +5); Legendary: 3 maneuvers → 1 action (DC +10)

### Athletics
- [ ] `Lead Climber`: Athletics check catches ally's critical climb failure, converting to regular failure (double crit fail → both fall)
- [ ] `Water Sprint`: movement across water surfaces during Stride (must end on solid ground; straight line at Master, any path at Legendary)

### Crafting
- [ ] `Crafter's Appraisal`: Crafting as alternate skill for Identify Magic checks on magic items specifically
- [ ] `Improvise Tool`: Repair without kit; Craft basic mundane items from list without crafter's book (raw materials required)
- [ ] `Rapid Affixture`: Affix Talisman time → 1 minute at Master; 3 actions at Legendary

### Deception
- [ ] `Doublespeak`: hidden messaging in conversation; long-term allies understand automatically; others need Perception vs. Deception DC to detect, crit to decode

### Diplomacy
- [ ] `Bon Mot` (1-action, Auditory, Linguistic, Mental): imposes status penalty to Perception and Will saves on success; target removes via verbal/concentrate retort; critical failure inflicts same penalty on caster instead
- [ ] `No Cause for Alarm` (3-action): reduces Frightened of creatures in 10-ft emanation on success/crit success; 1-hour immunity after

### Intimidation
- [ ] `Terrifying Resistance`: +1 circumstance to saves vs. spells from a successfully Demoralized creature (lasts 24 hours)

### Lore (Warfare)
- [ ] `Battle Assessment` (Lore — Warfare): assess creature's offensive capabilities via Recall Knowledge check; on success GM reveals highest damage modifier or attack bonus

### Medicine
- [ ] `Continual Recovery`: reduces Battle Medicine immunity to 10 minutes; compatible with standard Treat Wounds cooldown
- [ ] `Robust Recovery`: Treat Wounds crit success restores maximum HP on next check; patient gains temporary HP equal to roll
- [ ] `Ward Medic`: treat up to 4 patients simultaneously with Treat Wounds (requires healer's toolkit or equivalent)
- [ ] `Godless Healing` (prereq: no deity): Battle Medicine with no religious component; heal amount identical

### Multi-Skill
- [ ] `Group Aid`: Aid up to 4 creatures simultaneously; requires preparation 1 turn in advance; applies circumstance bonus to all qualifying skill checks that turn
- [ ] `Fascinating Spellcaster` (prereq: spellcasting): Casting a spell to Fascinate — use the spell's level to set Fascinate DC; targets must succeed Perception vs. spell-linked DC
- [ ] `Oddity Identification` (occult/arcane): +2 circumstance to Recall Knowledge for curses/haunts/oddities; identify any of these with success
- [ ] `Recognize Spell` expansion: identify spells from non-primary traditions at –2; recognize ritual preparations
- [ ] `Scare to Death` (Intimidation/Death; Uncommon): attempt 1/day vs. creature frightened 4+; creature makes Fortitude save or takes massive death-threat damage + unconscious penalty chain
- [ ] `Shameless Request` (Diplomacy): reduce attitude requirements by 1 step; works even at hostile attitude
- [ ] `Trick Magic Item` updates: clarify tradition check uses associated skill (Arcana/Nature/Occultism/Religion)

### Nature
- [ ] `Bonded Animal`: form a deep bond with one specific non-combat animal; animal follows complex commands, gains trained condition
- [ ] `Train Animal` (prereq: Bonded Animal): teach bonded animal new tricks (1 week of downtime per trick)

### Occultism
- [ ] `Bizarre Magic`: occult spells cause Flat-Footed condition on crit success instead of crit-specialized effect
- [ ] `Chronoskimmer` (Uncommon): 1/day recall an event from 1 day in the past within 10 feet with Occultism check
- [ ] `Tap Inner Magic` (Uncommon): once per day use Occultism to cast one spell from list without expending a slot
- [ ] `Undead Empathy`: Diplomacy-like attitude shifts usable against undead using Occultism

### Performance
- [ ] `Virtuosic Performer` (APG expansion): grants additional +1 beyond trained bonus when specializing in one instrument or performance type; clarify it stacks with existing Virtuosic Performer if already present

### Religion
- [ ] `Divine Guidance`: once per day Recall Knowledge (Religion) to gain a single-word divine omen; success = vague guidance; crit = clearer guidance
- [ ] `Pilgrim's Token`: carry a religious token; once per day grant ally +1 status bonus to one check (faith-relevant)

### Society
- [ ] `Foil Senses`: conceal items from all sensory checks (not just visual) with Society check
- [ ] `Read Lips`: understand spoken language from visual cues alone (Trained Society); crit success comprehends full meaning even in noisy environment
- [ ] `Sign Language` (prereq: trained Society): communicate silently with anyone else who knows any sign language
- [ ] `Streetwise`: +1 circumstance to Gather Information and Recall Knowledge about local areas; can use Society to find black market vendors
- [ ] `Unmistakable Lore`: critical successes on Lore checks provide maximum possible information with certainty
- [ ] `Untrained Improvisation` update: confirm it applies to all non-Society trained-only checks

### Stealth
- [ ] `Foil Senses` (cross-listed from Society above — same feat, different prerequisites)
- [ ] `Sense Allies`: extended range on detecting ally states while Hidden or Undetected

### Survival
- [ ] `Legendary Survivalist`: build shelter for up to 8 creatures from scavenged materials (1 hour); shelter provides full environmental protection; structure is permanent

### Thievery
- [ ] `Sticky Fingers` (Uncommon): after successful Steal, target doesn't notice item is missing until they reach for it

---

## Integration Checks

- [ ] All APG general feats appear in the general feat pool with correct level and stat prerequisites
- [ ] All APG skill feats appear in the skill feat pool gated behind correct skill proficiency level
- [ ] Uncommon feats (Scare to Death, Sticky Fingers, etc.) require GM unlock in feat selector
- [ ] Bon Mot's critical failure applying penalty to the caster (not target) correctly implemented

## Edge Cases

- [ ] Prescient Planner: timing validation — "not used since last shopping opportunity" tracked per character session state
- [ ] Group Aid: 4-creature limit; applies to all 4 simultaneously; preparation action required the prior turn
- [ ] Water Sprint: no swimming check required; Stride action extended across water; fails if no solid ground at end
- [ ] No Cause for Alarm: Frightened reduction applies to all eligible creatures in emanation simultaneously; 1-hour immunity per creature
- Agent: qa-dungeoncrawler
- Status: pending
