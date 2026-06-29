# Test Plan Design: dc-cr-familiar

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-familiar/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-familiar "<brief summary>"
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

# Acceptance Criteria: Familiar System
# Feature: dc-cr-familiar

## AC-001: Familiar Content Type
- Given a caster class grants a familiar, when the familiar is created, then it stores: familiar_id, character_id, familiar_type (standard), HP (5 × character level), speed (25 ft land by default), and a list of familiar abilities
- Given the character levels up, when familiar HP is recalculated, then HP = 5 × character level

## AC-002: Familiar Abilities
- Given a character has a familiar, when each day begins, when abilities are assigned, then the character selects familiar abilities up to their class-granted maximum (typically 2 at base, +1 per relevant class feat)
- Given available familiar abilities include: Amphibious, Climber, Darkvision, Fast Movement, Flier, Skilled (skill), Speech, Spellcasting (stores 1 spell slot), Tough, and others, when a character selects, then only available abilities are shown
- Given some familiar abilities have prerequisites (e.g., Flier requires the familiar to have wings), when the selection UI is shown, then prerequisites are enforced

## AC-003: Familiar vs. Animal Companion Distinction
- Given familiars and animal companions are distinct systems, when a character has a familiar, then the familiar has no combat stats (no attack or damage entries)
- Given a familiar is attacked, when damage is applied, then damage resolves against familiar HP; familiar dies at 0 HP
- Given a familiar dies, when recovery begins, when the character uses a weekly ritual, then the familiar can be replaced with 1 week of downtime

## AC-004: Spellcasting Delivery (Touch Spells)
- Given a caster with a familiar, when a spell with range Touch is cast, then the familiar can deliver the spell as its action within its reach
- Given the familiar delivers a touch spell, when it reaches the target, then the spell resolves as if the caster had touched the target

## AC-005: Class-Specific Familiar Rules
- Given a Wizard takes Arcane Bond, when the familiar is granted, then it follows standard familiar rules
- Given a Witch class is active, when the witch creates a familiar, then the familiar is required (not optional) and stores the witch's prepared spells as the "patron's vessel"

## Security acceptance criteria

- Security AC exemption: Familiar data is character-scoped. No PII. Daily ability selection is server-validated to prevent selecting more abilities than the class allows.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 3: Classes (Familiar rules)
- Agent: qa-dungeoncrawler
- Status: pending
