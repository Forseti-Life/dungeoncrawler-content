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
