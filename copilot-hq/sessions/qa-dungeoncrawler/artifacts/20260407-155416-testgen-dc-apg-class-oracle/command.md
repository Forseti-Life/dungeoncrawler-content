# Test Plan Design: dc-apg-class-oracle

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-class-oracle/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-class-oracle "<brief summary>"
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

# Acceptance Criteria: Oracle Class Mechanics (APG)

## Feature: dc-apg-class-oracle
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 8 + Con per level, key ability Charisma
- [ ] Starting saves: Expert Will, Trained Fortitude, Trained Reflex
- [ ] Divine spontaneous spellcasting using Charisma modifier for spell DC and spell attack
- [ ] All material components replaced by somatic components for oracle spellcasting
- [ ] Starts with 5 cantrips + 2 first-level spells in repertoire
- [ ] Cantrips auto-heighten to half class level rounded up
- [ ] Spells per day follow sorcerer progression (TABLE 2-3)
- [ ] Signature Spells at L3: one spell per accessible spell level can be cast at any available level

---

## Mystery Selection

- [ ] Oracle selects one mystery at character creation; cannot change
- [ ] Mysteries: Ancestors, Battle, Bones, Cosmos, Flames, Life, Lore, Tempest
- [ ] Each mystery grants: unique domain spell options, revelation spells, oracular curse progression

---

## Revelation Spells and Cursebound

- [ ] Revelation spells are focus spells with the cursebound trait
- [ ] Casting any cursebound spell advances the oracular curse one stage
- [ ] Oracle focus pool starts at 2 Focus Points (not 1)
- [ ] Refocus: 10 minutes, restores 1 Focus Point
- [ ] Oracle learns exactly 2 revelation spells at L1: the mystery's initial revelation spell (fixed) + one from associated domains (player's choice)
- [ ] Oracle cannot cast cursebound spells if curse has not been activated today (no curse active yet before first casting)
- [ ] Cursebound spells have divine, focus, and cursebound traits

---

## Oracular Curse (4-Stage Progression)

- [ ] Stage 0 (basic): passive/constant effect from character creation — always active
- [ ] Casting any revelation spell at basic → advances to minor
- [ ] Casting any revelation spell at minor → advances to moderate
- [ ] Casting any revelation spell at moderate → triggers **overwhelmed** condition
- [ ] Overwhelmed: cannot cast or sustain any revelation spell until next daily preparations
- [ ] Refocusing while at moderate or higher: reduces curse to minor + restores 1 Focus Point
- [ ] Resting 8 hours + daily preparations: returns curse to basic
- [ ] Curse has the curse, divine, and necromancy traits
- [ ] Curse **cannot** be removed, mitigated, or suppressed by any spell or item (e.g., remove curse has no effect)
- [ ] At higher levels (system unlock), major and extreme curse stages become accessible before overwhelmed triggers

---

## Mystery Curses and Spells (per mystery)

- [ ] Each mystery defines its own 4-stage curse progression (unique effects per mystery, not generic conditions)
- [ ] Each mystery provides: initial revelation spell, advanced revelation spell, greater revelation spell, domain spell choices
- [ ] Ancestors mystery: curse involves ancestral whispers, potential confusion effect
- [ ] Battle mystery: curse involves a war-scarred body, physical combat-related penalties/bonuses
- [ ] Bones mystery: curse involves undead resonance, effects tied to vitality/void energy
- [ ] Cosmos mystery: curse involves cosmic connection, gravity or light disturbances
- [ ] Flames mystery: curse involves fire and light emissions, fire vulnerability
- [ ] Life mystery: curse involves vital overflow, positive energy emissions
- [ ] Lore mystery: curse involves information overload, mental strain
- [ ] Tempest mystery: curse involves storm and weather manifestations

---

## Integration Checks

- [ ] Curse stage tracker persists across turns within a day; resets on daily prep
- [ ] Each unique mystery curse has a distinct UI representation (not a single shared widget)
- [ ] Overwhelmed state prevents revelation spell slots from being used even if Focus Points remain
- [ ] Focus pool cap remains 2 for oracle unless feats expand it
- [ ] Signature spells at L3: player selects one spell per spell level from repertoire; those spells become flexible-height castable

## Edge Cases

- [ ] Oracle with 0 FP but curse at minor attempting to cast revelation spell: blocked (no FP available)
- [ ] Remove curse targeting an oracle: fails silently (curse is class feature, not a removable affliction)
- [ ] Two revelation spells at L1: first is always mystery's initial revelation (no choice), second is domain spell choice
- [ ] When refocusing at moderate, curse resets to minor (not basic) — verify state machine
- Agent: qa-dungeoncrawler
- Status: pending
