# Test Plan Design: dc-apg-feats

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-feats/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-feats "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
