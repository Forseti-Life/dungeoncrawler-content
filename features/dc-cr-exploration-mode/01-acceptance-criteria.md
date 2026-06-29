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
