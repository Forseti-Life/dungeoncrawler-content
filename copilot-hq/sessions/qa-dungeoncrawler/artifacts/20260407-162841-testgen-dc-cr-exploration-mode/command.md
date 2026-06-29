# Test Plan Design: dc-cr-exploration-mode

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-exploration-mode/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-exploration-mode "<brief summary>"
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

# Acceptance Criteria: Exploration Mode
# Feature: dc-cr-exploration-mode

## AC-001: Time Scale
- Given exploration mode is active, when time is tracked, then time advances in minutes and hours (not rounds)
- Given exploration mode transitions to encounter mode (combat begins), when the transition occurs, then accumulated exploration activity affects the first round (e.g., initiative bonuses, detection)

## AC-002: Exploration Activities
- Given a character is in exploration mode, when they select an exploration activity, then available activities include: Avoid Notice, Detect Magic, Hustle, Investigate, Repeat a Spell, Scout, Search, and Sense Direction
- Given a character performs Search while exploring, when each 10-foot square is moved through, when search checks are resolved, then secret doors, hazards, and hidden items in the path are detected on success
- Given a character uses Hustle, when the activity is active, then movement speed is doubled but the character accrues fatigue after 10 minutes

## AC-003: Initiative From Exploration Activity
- Given an encounter begins while the party is in exploration mode, when initiative is rolled, then each character uses the skill associated with their current exploration activity (e.g., Stealth for Avoid Notice, Perception for Scout/Search)

## AC-004: Light and Darkness
- Given the dungeon has areas of varying light, when a character moves into an area, then their vision type (normal, low-light, darkvision) determines what they can see
- Given a light source has a radius, when the character carries the source, then squares beyond the bright radius are dim light, and beyond the dim radius are darkness

## AC-005: Encounter Transition
- Given the party triggers an encounter in exploration mode, when combat begins, then the system transitions from exploration time scale to combat rounds automatically
- Given stealth-based approach (Avoid Notice activity), when the encounter begins, then enemies that failed their Perception checks are surprised (cannot act in the surprise round)

## Security acceptance criteria

- Security AC exemption: Exploration state is session-scoped data with no PII. Grid/map positions are server-validated to prevent location spoofing.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 9: Playing the Game (Exploration)
- Agent: qa-dungeoncrawler
- Status: pending
