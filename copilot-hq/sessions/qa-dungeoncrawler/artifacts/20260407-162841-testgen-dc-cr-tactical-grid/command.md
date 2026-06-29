# Test Plan Design: dc-cr-tactical-grid

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-tactical-grid/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-tactical-grid "<brief summary>"
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

# Acceptance Criteria: Tactical Grid System
# Feature: dc-cr-tactical-grid

## AC-001: Grid Data Model
- Given a combat scene is active, when the grid is initialized, then each square represents a 5-foot space identified by (row, column) coordinates
- Given creatures occupy the grid, when their position is stored, then each creature has a grid_position field with row/column
- Given a creature occupies multiple squares (Large+), when positioning is evaluated, then all occupied squares are tracked

## AC-002: Movement and Reach
- Given a creature takes the Stride action, when movement is calculated, then movement is in 5-foot increments up to Speed, respecting difficult terrain (2 squares per square)
- Given a melee attack is attempted, when reach is checked, then standard reach (5 ft = adjacent square) or reach weapon (10 ft = 2 squares) is enforced
- Given a creature moves away from a threatening enemy, when the movement is evaluated, then the system identifies if a Reaction (Attack of Opportunity) can be triggered

## AC-003: Areas of Effect
- Given a burst (radius) spell is cast, when targets are calculated, then all grid squares within the burst radius (measured from the origin point) are included
- Given a cone is directed from a caster, when the affected area is calculated, then the 90-degree cone from the caster's square in the chosen direction is resolved
- Given a line spell is cast, when targets are evaluated, then each square along the line path is checked for occupying creatures

## AC-004: Flanking
- Given two allies are on directly opposite sides of a creature (sharing the same row or column center), when an attack is made, then both attackers gain the flanking benefit (+2 circumstance to attack rolls)
- Given creatures of different sizes, when flanking is evaluated, then the system uses the creature's primary square to determine flanking positions

## AC-005: Cover
- Given a creature or terrain feature is between attacker and target, when cover is assessed, then standard cover (+2 AC/Reflex) or greater cover (+4 AC/Reflex) is applied appropriately
- Given the target is prone, when cover from prone is calculated, then the prone condition interacts correctly with cover rules

## AC-006: Difficult Terrain
- Given a grid square is marked as difficult terrain, when a creature moves through it, then 2 feet of movement are spent per 5-foot square
- Given a grid square is hazardous terrain, when a creature enters it, then the terrain damage is triggered

## Security acceptance criteria

- Security AC exemption: Grid state is session-scoped combat data. No PII. All grid operations are server-validated to prevent client-side position spoofing.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 9: Playing the Game (Combat)
- Agent: qa-dungeoncrawler
- Status: pending
