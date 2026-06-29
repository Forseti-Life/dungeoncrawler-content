# Dungeon Crawler Forseti Life Documentation Hub

This directory contains player reference material, system design notes, and implementation documentation for Dungeon Crawler Forseti Life.

## Player Audience Focus

For on-site user documentation and marketing copy, the primary audience is:
- Former tabletop/classic RPG players returning for long-form campaign play
- Players seeking a permanent home for characters to live, adventure, and retire
- Parties who value continuity, progression history, and world persistence

## Documentation Verification Notes (2026-02-18)

- This folder mixes **PF2e tabletop reference material**, **implementation design documents**, and **module-runtime architecture notes**.
- The `01-06` process guides are rules references, not strict runtime contracts for `dungeoncrawler_content` APIs.
- Runtime API/controller behavior should be validated against:
   - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`
   - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/`
- Issue #4 remains design-only; current character state synchronization is REST-first (no production WebSocket route/handler in this module).


## Overview

This documentation provides comprehensive guides to the core PF2e-inspired mechanics and campaign processes used by Dungeon Crawler Forseti Life. These guides support both quick in-session lookups and long-term character/campaign planning.

## Quick Reference Guides

### Core Game Processes

1. **[Character Creation Process](./01-character-creation-process.md)**
   - 10-step process for building a new character
   - Ability score generation
   - Ancestry, background, and class selection
   - Equipment and finishing touches

2. **[Combat and Encounter Mechanics](./02-combat-encounter-mechanics.md)**
   - Initiative and turn order
   - Round structure and timing
   - Turn phases (start, actions, end)
   - Common combat actions and conditions

3. **[Action System](./03-action-system.md)**
   - Three-action economy explained
   - Single actions, activities, reactions, and free actions
   - Multiple Attack Penalty (MAP)
   - Strategic action use

4. **[Skill Checks](./04-skill-checks.md)**
   - Four-step check resolution process
   - Degrees of success (critical success through critical failure)
   - Difficulty Classes (DCs)
   - Common skill actions

5. **[Spellcasting Process](./05-spellcasting-process.md)**
   - Casting spells and components
   - Spell slots and prepared vs spontaneous casting
   - Spell durations and sustaining
   - Spell attacks and saving throws

6. **[Leveling Up Process](./06-leveling-up-process.md)**
   - Eight-step leveling checklist
   - Gaining abilities, feats, and proficiency
   - Experience point system
   - Character progression milestones

## Architecture & System Design Documents

- **[Room & Dungeon Generator Architecture](./ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md)**: Procedural dungeon and room generation with hex-based layouts, entity placement, and tileset generation
- **[Quest Tracker & Generator Architecture](./QUEST_TRACKER_GENERATOR_ARCHITECTURE.md)**: Campaign-level quest system with procedural generation, progress tracking, and reward distribution
- **[Quest System Quick Reference](./QUEST_SYSTEM_QUICK_REFERENCE.md)**: Implementation guide with code examples and API usage
- **[Quest API Documentation](./QUEST_API_DOCUMENTATION.md)**: Complete REST API reference for quest endpoints (Phase 3)
- **[Quest Implementation Status - Phase 2 Complete ✅](./QUEST_IMPLEMENTATION_PHASE2_COMPLETE.md)**: Phase 2 implementation - Database, services, templates, and Drush commands
- **[Quest Implementation Status - Phase 3 Complete ✅](./QUEST_IMPLEMENTATION_PHASE3_COMPLETE.md)**: Phase 3 implementation - REST API controllers, routing, and integration framework
- **[Character Tracking System](./CHARACTER_TRACKING_SYSTEM.md)**: Campaign character and NPC management with state persistence
- **[Database Schema Design](./database-schema-design.md)**: Complete database architecture following Library → Campaign → Runtime pattern

## Document Structure

Each guide follows aconsistent structure:

- **Overview**: Brief introduction to the mechanic
- **Process Steps**: Detailed step-by-step procedures
- **Key Rules**: Important rules and modifiers
- **Examples**: Practical scenarios demonstrating mechanics
- **Tips**: Strategic advice and common pitfalls
- **Related Mechanics**: Links to connected topics

## Using These Guides

### For New Players

Start with these documents in order:
1. Character Creation Process (to build your character)
2. Action System (to understand what you can do)
3. Skill Checks (to understand basic task resolution)
4. Combat and Encounter Mechanics (to understand combat flow)

Then continue with on-site gameplay flow:
5. Create/open a campaign at `/campaigns`
6. Select a completed character via Tavern Entrance
7. Launch into hexmap and begin persistent campaign progression

### For Experienced Players

Use as quick reference:
- Quick lookup for specific procedures
- Clarify edge cases
- Reference tables and DCs
- Share with new players
- Plan long-term builds intended for campaign continuity and eventual retirement arcs

### For Game Masters

- Reference during sessions for ruling questions
- Share specific pages with players
- Use examples to demonstrate mechanics
- Adapt tables for custom challenges

## Source Materials

These guides are derived from analysis of:

- **PF2E Core Rulebook - Fourth Printing** (primary source)
- **PF2E Advanced Player's Guide**
- **PF2E Gamemastery Guide**
- **PF2E Bestiary series** (1, 2, 3)
- **PF2E Gods and Magic**
- **PF2E Guns and Gears**
- **PF2E Secrets of Magic**

All text files and PDFs are available in the `docs/dungeoncrawler` directory.

## Key Pathfinder 2E Concepts

### The Three-Action Economy

Unlike many RPGs, PF2E gives each character **3 actions** per turn plus **1 reaction**. This creates flexible, tactical gameplay where you can:

- Strike three times
- Move twice and Strike once
- Cast a spell (2 actions) and Stride once
- Use special multi-action abilities

### Degrees of Success

Every check has **four possible outcomes**:
- **Critical Success**: Beat DC by 10+ (or natural 20)
- **Success**: Meet or beat DC
- **Failure**: Below DC
- **Critical Failure**: Miss DC by 10+ (or natural 1)

This creates more interesting outcomes than simple pass/fail.

### Proficiency Ranks

Characters grow more proficient over time:
- **Untrained** (+0 + level)
- **Trained** (+2 + level)
- **Expert** (+4 + level)
- **Master** (+6 + level)
- **Legendary** (+8 + level)

Your level is always part of the bonus, keeping characters relevant as they advance.

### Encounter Challenge Levels

Encounters are built using a budget system:
- Party level determines baseline
- Adding higher-level creatures costs more "budget"
- Creating balanced, challenging encounters is systematic

## Quick Reference Tables

### Action Icons

| Icon | Type | Per Turn |
|------|------|----------|
| [one-action] | Single Action | Up to 3 |
| [two-actions] | Two-Action Activity | 1 (uses 2 actions) |
| [three-actions] | Three-Action Activity | 1 (uses all actions) |
| [reaction] | Reaction | 1 (on any turn) |
| [free-action] | Free Action | As appropriate |

### Proficiency Bonuses

| Rank | Bonus |
|------|-------|
| Untrained | +0 + level |
| Trained | +2 + level |
| Expert | +4 + level |
| Master | +6 + level |
| Legendary | +8 + level |

### Simple DCs

| Task Difficulty | DC |
|----------------|-----|
| Untrained | 10 |
| Trained | 15 |
| Expert | 20 |
| Master | 30 |
| Legendary | 40 |

### Degrees of Success

| Degree | Result |
|--------|--------|
| **Critical Success** | Beat DC by 10+ or natural 20 |
| **Success** | Meet or beat DC |
| **Failure** | Below DC |
| **Critical Failure** | Miss DC by 10+ or natural 1 |

### Time Measurements

| Unit | Duration |
|------|----------|
| Round | 6 seconds |
| Minute | 10 rounds |
| Hour | 600 rounds |

### Multiple Attack Penalty

| Attack | Normal | Agile |
|--------|--------|-------|
| 1st | –0 | –0 |
| 2nd | –5 | –4 |
| 3rd+ | –10 | –8 |

## Additional Resources

### Where to Find More

- **Archives of Nethys** (https://2e.aonprd.com): Complete free online rules reference
- **Pathfinder 2E Official Site** (https://paizo.com/pathfinder): Official rules, errata, FAQs
- **Pathfinder Subreddit** (r/Pathfinder2e): Community discussions and questions
- **Pathfinder 2E Discord**: Real-time rules questions and community

### Rules Questions

When you have a rules question:
1. Check the relevant guide in this documentation
2. Look up the specific rule in the Core Rulebook
3. Search Archives of Nethys for the mechanical entry
4. Ask your GM for their ruling
5. Check official Paizo FAQs for clarifications

## Issue Design Documents

Design documents for upcoming features and system improvements:

1. **[Issue #1: Character Creation Class HP Lookup](./issues/issue-1-character-class-hp-design.md)**
   - System design for retrieving class HP from schema data

2. **[Issue #2: Hex Map Rendering System](./issues/issue-2-hexmap-rendering-design.md)**
   - Performant hexagonal map rendering with fog of war

3. **[Issue #3: Game Content System](./issues/issue-3-game-content-system-design.md)**
   - Scalable content management for PF2E game data

4. **[Issue #4: Enhanced Character Sheet](./issues/issue-4-enhanced-character-sheet-design.md)**
   - Design proposal for real-time character management (WebSocket section is future-state)
   - Mobile-responsive design
   - Resource tracking and condition management

## System Implementation Documents

Production systems and features:

1. **[Inventory Management & Transfer System](./INVENTORY_MANAGEMENT_SYSTEM.md)**
   - Complete inventory management for characters and containers
   - Item transfers between inventories
   - PF2e-compliant bulk and encumbrance calculations
   - Capacity enforcement and permission validation
   - Audit logging and transaction safety
   - **Quick Start**: [INVENTORY_IMPLEMENTATION_GUIDE.md](./INVENTORY_IMPLEMENTATION_GUIDE.md)

2. **[Quest Tracker & Generator System](./QUEST_TRACKER_GENERATOR_ARCHITECTURE.md)**
   - Procedural quest generation from templates (Phase 1-2: ✅ Complete)
   - Campaign-level quest tracking with progress persistence (Phase 1-2: ✅ Complete)
   - Multi-phase objectives with branching paths (Phase 1-2: ✅ Complete)
   - REST API endpoints for quest management (Phase 3: ✅ Complete)
   - Integration with combat, exploration, and inventory systems (Phase 4: In Progress)
   - **API Reference**: [QUEST_API_DOCUMENTATION.md](./QUEST_API_DOCUMENTATION.md)
   - **Phase Status**: [Phase 2 Complete](./QUEST_IMPLEMENTATION_PHASE2_COMPLETE.md) | [Phase 3 Complete](./QUEST_IMPLEMENTATION_PHASE3_COMPLETE.md)
   - **Quick Start**: [QUEST_SYSTEM_QUICK_REFERENCE.md](./QUEST_SYSTEM_QUICK_REFERENCE.md)

## Document History

- **2026-02-12**: Issue #4 design added (Enhanced Character Sheet)
- **2026-02-09**: Initial documentation created
  - Extracted from PF2E Core Rulebook and supplements
  - Created comprehensive markdown guides
  - Organized into clear process flows
  - Issues #1-3 design documents added

## Contributing

To improve these guides:

1. Identify areas needing clarification
2. Add practical examples
3. Create visual diagrams (future enhancement)
4. Cross-reference related mechanics
5. Update with official errata

## License

These guides are fan-created reference materials based on Pathfinder 2nd Edition rules. Pathfinder is a registered trademark of Paizo Inc. These materials are not official and are not endorsed by Paizo Inc.

## Feedback

Found an error or have suggestions? Please:
- Open an issue in the repository
- Note the specific document and section
- Provide the correct information with source reference
- Suggest improvements for clarity

---

## Quick Navigation

- [Character Creation Process →](./01-character-creation-process.md)
- [Combat and Encounter Mechanics →](./02-combat-encounter-mechanics.md)
- [Action System →](./03-action-system.md)
- [Skill Checks →](./04-skill-checks.md)
- [Spellcasting Process →](./05-spellcasting-process.md)
- [Leveling Up Process →](./06-leveling-up-process.md)

---

**Happy Gaming!** May your dice roll high and your adventures be legendary.
