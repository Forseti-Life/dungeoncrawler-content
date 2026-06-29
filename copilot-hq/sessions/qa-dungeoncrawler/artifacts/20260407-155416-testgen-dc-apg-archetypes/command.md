# Test Plan Design: dc-apg-archetypes

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-archetypes/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-archetypes "<brief summary>"
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
- Agent: qa-dungeoncrawler
- Status: pending
