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
