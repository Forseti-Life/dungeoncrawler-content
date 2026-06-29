# Design Issues

This directory contains design documents for major features in the Pathfinder 2E Dungeon Crawler system.

## Active Issues

## Verification Notes (2026-02-18)

This section reflects current implementation coverage in `dungeoncrawler_content`.

### Validity Summary

- **Fully valid/open**: 1 of 6 issue-design documents
- **Partially valid (some implementation exists)**: 4 of 6
- **Mostly stale/resolved as design gap**: 1 of 6

### Per-Issue Status vs Code

1. **Issue #1 (Character Class HP Design)** — **Partially valid**
	- Class-specific HP is now applied through `CharacterManager::getClassHP()` in character save flow.
	- Original schema-loader-driven class lookup remains incomplete (`SchemaLoader::getClassData()` is still TODO/throws).

2. **Issue #2 (Hexmap Rendering Design)** — **Partially valid**
	- Hexmap runtime and ECS-based rendering are implemented.
	- Design-specific storage/services in this document (e.g., `dungeoncrawler_hexmap_*` tables) are not the active implementation path.

3. **Issue #3 (Game Content System Design)** — **Mostly stale as an “unimplemented” claim**
	- Core services and schema tables are implemented (`ContentRegistry`, `ContentQuery`, `ContentGenerator`, campaign content tables).
	- This document is still useful as architecture rationale, but not accurate where it claims no implementation exists.

4. **Issue #4 (Combat & Encounter System Design)** — **Partially valid**
	- Lightweight combat APIs are implemented (`/api/combat/start`, `/end-turn`, `/attack`, `/get`, `/set`, `/end`).
	- Full target-state surface in design docs remains partially implemented.

5. **Issue #4 (Enhanced Character Sheet Design)** — **Partially valid**
	- Character state REST endpoints and service operations exist.
	- WebSocket sync and several advanced flows remain design-target/pending.

6. **Issue #4 (Procedural Dungeon Generation Design)** — **Fully valid/open**
	- Controller/service scaffolding exists, but generation logic and API behavior remain mostly TODO.

### Issue #1: Character Class HP Design
**File**: `issue-1-character-class-hp-design.md`  
**Status**: Design  
**Description**: Character creation and HP calculation system design

### Issue #2: Hexmap Rendering Design
**File**: `issue-2-hexmap-rendering-design.md`  
**Status**: Design  
**Description**: Hexagonal map rendering system for exploration

### Issue #3: Game Content System Design
**File**: `issue-3-game-content-system-design.md`  
**Status**: Design  
**Description**: Game content management and reference data system

### Issue #4: Combat & Encounter System Design ✅
**File**: `issue-4-combat-encounter-system-design.md`  
**Status**: Complete  
**Description**: PF2e-compliant combat and encounter system

**Related Documents**:
- `combat-state-machine.md` - State transitions and lifecycle
- `combat-database-schema.md` - Database tables and relationships
- `combat-engine-service.md` - Service layer pseudocode
- `combat-action-validation.md` - Action validation rules
- `combat-api-endpoints.md` - REST API specification
- `combat-ui-design.md` - Frontend UI design

---

## PF2E Chapter 1 Requirements Issues (Issues #5–#17)

Extracted from paragraph-by-paragraph analysis of PF2E Core Rulebook Chapter 1.  
Source reference: `/docs/dungeoncrawler/PF2requirements/references/chapter-01-introduction.md`

### Issue #5: Rules Vocabulary & Glossary
**File**: `issue-5-rules-vocabulary-glossary.md`  
**Status**: Open | **Priority**: Medium  
**Description**: In-game glossary, abbreviation lookup (AC/DC/HP), contextual term display from stat blocks.

### Issue #6: Action Economy System
**File**: `issue-6-action-economy-system.md`  
**Status**: Open | **Priority**: Critical  
**Description**: 3-action budget, action types (Action/Reaction/Free Action), trigger system, activities, multi-mode play.

### Issue #7: Stat Block Data Model
**File**: `issue-7-stat-block-data-model.md`  
**Status**: Open | **Priority**: High  
**Description**: Universal stat block schema — all fields, prerequisites/frequency/trigger/requirements/special enforcement.

### Issue #8: Character Creation Wizard
**File**: `issue-8-character-creation-wizard.md`  
**Status**: Open | **Priority**: High  
**Description**: Non-linear 10-step wizard, higher-level creation, null-safe fields, step-to-field mapping.

### Issue #9: Ability Score System
**File**: `issue-9-ability-score-system.md`  
**Status**: Open | **Priority**: Critical  
**Description**: Six scores, boost/flaw/modifier mechanics, batch uniqueness enforcement, stat dependencies, rolling mode.

### Issue #10: Ancestry System
**File**: `issue-10-ancestry-system.md`  
**Status**: Open | **Priority**: High  
**Description**: Ancestry entity model, heritages, ancestry feats, special senses, alternate boost option, voluntary flaws, 6 core ancestries.

### Issue #11: Background System
**File**: `issue-11-background-system.md`  
**Status**: Open | **Priority**: High  
**Description**: Background entity (typed+free boosts, skill training, Lore skill sub-type, skill feat grant).

### Issue #12: Class System
**File**: `issue-12-class-system.md`  
**Status**: Open | **Priority**: Critical  
**Description**: Class entity, proficiency ranks T/E/M/L, advancement table, spellcasting config, sub-selections (Order), anathema, class-granted languages. All 12 core classes.

### Issue #13: Equipment & Bulk System
**File**: `issue-13-equipment-bulk-system.md`  
**Status**: Open | **Priority**: Medium  
**Description**: Starting wealth 15 gp, currency cp/sp/gp/pp, Bulk calculation (10L=1 Bulk), encumbrance thresholds, armor DEX cap.

### Issue #14: Derived Statistics Calculator
**File**: `issue-14-derived-statistics-calculator.md`  
**Status**: Open | **Priority**: Critical  
**Description**: Proficiency bonus formula, auto-calculation of all derived stats (AC/saves/Perception/skills/strikes/spell DC/Class DC), modifier stacking model, recalculation triggers.

### Issue #15: Alignment System
**File**: `issue-15-alignment-system.md`  
**Status**: Open | **Priority**: Low  
**Description**: Two-axis 9-alignment enum, alignment traits, class restrictions (Champion/Cleric/Druid), mutable alignment, atonement suppression state.

### Issue #16: Character Details & Session Resources
**File**: `issue-16-character-details-session-resources.md`  
**Status**: Open | **Priority**: Low  
**Description**: Hero Points (reset per session, reroll/stabilize spend), narrative detail fields, session state entity, deity field.

### Issue #17: Leveling Up System
**File**: `issue-17-leveling-up-system.md`  
**Status**: Open | **Priority**: High  
**Description**: XP threshold 1,000/level with carryover, level-up transaction (any-order steps, commit-time validation), HP recalc on CON boost, universal proficiency scaling, ability boosts at 5/10/15/20.

### Issue #18: Hybrid Heritage Overlay System
**File**: `issue-18-hybrid-heritage-overlay-system.md`  
**Status**: Open | **Priority**: High  
**Description**: Half-Elf and Half-Orc as human heritage overlays that add traits, low-light vision, and cross-ancestry feat eligibility.

## Design Document Structure

Each major issue includes:
1. **Main Issue Document**: Overview and requirements
2. **Supporting Documents**: Detailed specifications for each component
3. **Implementation Guide** (when applicable): Step-by-step implementation plan

## Document Status

- ✅ **Complete**: Design finalized, ready for implementation
- 🔨 **In Progress**: Design work ongoing
- 📝 **Draft**: Initial design phase
- 🔄 **Under Review**: Pending review and approval

## Related Documentation

- **Root**: `/docs/dungeoncrawler/README.md` - Main documentation index
- **Mechanics**: `/docs/dungeoncrawler/0X-*.md` - Game mechanics reference
- **Implementation**: `/docs/dungeoncrawler/PR-*.md` - Implementation guides
- **Tracker Audit (2026-02-18)**: `tracker-validity-review-2026-02-18.md` - semantic validity and consolidation plan for active DCC tracker rows
- **Tracker Supersede Map (2026-02-18)**: `tracker-supersede-map-2026-02-18.md` - exact keep-open vs supersede ID mapping for tracker cleanup

## Contributing

When adding new design documents:
1. Create main issue document: `issue-N-feature-name.md`
2. Create supporting documents: `feature-component.md`
3. Update this README
4. Link to related documentation
5. Follow existing document structure

---

**Last Updated**: 2026-04-18
