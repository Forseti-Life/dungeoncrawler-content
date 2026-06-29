# Test Plan Design: dc-cr-animal-companion

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-animal-companion/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-animal-companion "<brief summary>"
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

# Acceptance Criteria: Animal Companion System
# Feature: dc-cr-animal-companion

## AC-001: Animal Companion Content Type
- Given a class grants an animal companion, when the companion is created, when stored, then it includes: companion_id, character_id, companion_type (animal species), size, speeds, senses, HP, AC, saves, attack entries, and advancement level (young|mature|nimble|savage)
- Given a Ranger, Druid, or Beastmaster Archetype character, when they gain a companion, then the companion is initialized at "young" advancement level

## AC-002: Companion Advancement
- Given a character reaches a class feature that advances the companion, when the advancement is applied, then the companion moves from young → mature → nimble or savage based on the chosen specialization
- Given the companion reaches Mature level, when stats are recalculated, then AC, saves, HP, attack modifier, and damage all increase per the Mature Animal Companion table

## AC-003: Commanding the Companion
- Given the companion acts in combat, when the character issues the Command an Animal action (1 action), when successful (DC 15 or DC equal to creature's Will DC), then the companion takes 2 actions on its turn
- Given the Command an Animal action is not used, when the companion's turn begins, then the companion takes only the Stride and/or Strike actions it took last turn (repeating behavior)

## AC-004: Companion vs. Familiar Distinction
- Given an animal companion is active, when queried, then it has full combat stats (attack bonus, damage entries, AC, saves) unlike a familiar
- Given an animal companion takes damage, when HP reaches 0, then the companion falls unconscious; it does not die permanently unless the character decides so or recovery fails over days

## AC-005: Species-Specific Companions
- Given animal companion species are defined (bear, bird, cat, wolf, etc.), when a character selects a species, then the species sets base stats, size, speed, senses, and natural attacks
- Given a companion has Flier movement (eagle, bat), when movement is used in combat, then aerial movement rules (elevation, plunging strike) apply

## Security acceptance criteria

- Security AC exemption: Companion data is character-scoped. No PII. Command resolution and companion stat advancement are server-validated.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 3: Classes (Animal Companion rules)
- Agent: qa-dungeoncrawler
- Status: pending
