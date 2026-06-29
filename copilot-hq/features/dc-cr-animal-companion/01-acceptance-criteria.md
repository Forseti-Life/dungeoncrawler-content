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
