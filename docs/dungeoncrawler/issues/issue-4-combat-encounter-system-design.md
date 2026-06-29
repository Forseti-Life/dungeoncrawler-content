# Issue #4: Combat & Encounter System Design

**Status**: Design Phase  
**Type**: Design Document  
**Priority**: High  
**Created**: 2026-02-12

## Verification Notes (2026-02-18)

- This remains a target-state design document.
- Current module runtime includes a lightweight implemented combat API surface via `CombatEncounterApiController` routes (`/api/combat/start`, `/api/combat/end-turn`, `/api/combat/attack`, `/api/combat/get`, `/api/combat/set`, `/api/combat/end`).
- Full service/controller/UI behavior described below is only partially implemented and should be treated as planned where code does not yet exist.

## Overview

Design a comprehensive PF2e-compliant combat and encounter system that manages initiative, turn-based actions, conditions, damage calculations, and the full combat lifecycle. This document outlines the complete design including state machines, database schemas, service architecture, validation rules, API endpoints, and UI specifications.

## Requirements

### Features
- Initiative tracking with proper tie-breaking rules
- Turn-based actions (3 actions per turn)
- Condition tracking with duration management
- Damage/healing calculations with resistances and weaknesses
- Degrees of success (critical hit/fail)
- Action economy (reactions, free actions)
- Multiple Attack Penalty (MAP) enforcement
- Real-time combat state synchronization

### Deliverables
1. ✅ Combat state machine diagram
2. ✅ Database schema (active_encounters, combat_log)
3. ✅ CombatEngine service pseudocode
4. ✅ Action validation rules
5. ✅ API endpoints for combat actions
6. ✅ Frontend combat UI design

## Design Documents

This issue includes the following design documents:

1. **[Combat State Machine](./combat-state-machine.md)** - State transitions and lifecycle management
2. **[Combat Database Schema](./combat-database-schema.md)** - Database tables and relationships
3. **[Combat Engine Service](./combat-engine-service.md)** - Service layer pseudocode and business logic
4. **[Action Validation Rules](./combat-action-validation.md)** - Rules engine for action validation
5. **[Combat API Endpoints](./combat-api-endpoints.md)** - REST API specification
6. **[Combat UI Design](./combat-ui-design.md)** - Frontend interface mockups and interactions

## Related Documentation

- [Combat and Encounter Mechanics](../02-combat-encounter-mechanics.md) - PF2e rules reference
- [Action System](../03-action-system.md) - Three-action economy details
- [Database Schema Design](../database-schema-design.md) - Overall database architecture
- [PR-02: Combat Encounter Implementation](../PR-02-combat-encounter-implementation.md) - Implementation guide

## Design Principles

### 1. Rule Compliance
- Strictly follow Pathfinder 2E Core Rulebook rules
- Support all standard combat actions and conditions
- Enforce Multiple Attack Penalty correctly
- Handle degrees of success appropriately

### 2. Performance
- Real-time state updates for all participants
- Efficient database queries with proper indexing
- Caching for frequently accessed data
- Optimistic UI updates with server confirmation

### 3. Flexibility
- Support both character and monster participants
- Extensible action system for custom abilities
- Configurable combat settings and house rules
- Grid-based or theater-of-the-mind combat modes

### 4. User Experience
- Intuitive combat tracker interface
- Clear visual feedback for all actions
- Minimal clicks for common actions
- Mobile-responsive design

### 5. Maintainability
- Clear separation of concerns (state, logic, presentation)
- Well-documented code with examples
- Testable components
- Versioned API endpoints

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Frontend Layer                       │
│  ┌───────────┐  ┌───────────┐  ┌────────────┐              │
│  │  Combat   │  │ Initiative│  │  Action    │              │
│  │  Tracker  │  │  Display  │  │  Controls  │              │
│  └─────┬─────┘  └─────┬─────┘  └──────┬─────┘              │
└────────┼──────────────┼────────────────┼─────────────────────┘
         │              │                │
         └──────────────┴────────────────┘
                        │
         ┌──────────────▼────────────────┐
         │       API Gateway             │
         │   (REST + WebSocket)          │
         └──────────────┬────────────────┘
                        │
         ┌──────────────▼────────────────┐
         │      Combat Controller        │
         │   (Request Handling)          │
         └──────────────┬────────────────┘
                        │
         ┌──────────────▼────────────────┐
         │     Combat Engine Service     │
         │   ┌─────────────────────┐    │
         │   │  State Manager      │    │
         │   │  Action Processor   │    │
         │   │  Rules Engine       │    │
         │   │  Calculator Service │    │
         │   └─────────────────────┘    │
         └──────────────┬────────────────┘
                        │
         ┌──────────────▼────────────────┐
         │      Data Access Layer        │
         │   (Repository Pattern)        │
         └──────────────┬────────────────┘
                        │
         ┌──────────────▼────────────────┐
         │        Database Layer         │
         │  ┌──────────────────────┐    │
         │  │ combat_encounters    │    │
         │  │ combat_participants  │    │
         │  │ combat_actions       │    │
         │  │ combat_conditions    │    │
         │  │ combat_log           │    │
         │  └──────────────────────┘    │
         └───────────────────────────────┘
```

## Combat Flow Overview

```
┌──────────────────────────────────────────────────────────────┐
│                    COMBAT ENCOUNTER                          │
└──────────────────────────────────────────────────────────────┘
                           │
         ┌─────────────────┴─────────────────┐
         │   1. SETUP PHASE                  │
         │   - Select participants           │
         │   - Roll initiative               │
         │   - Sort initiative order         │
         └─────────────────┬─────────────────┘
                           │
         ┌─────────────────▼─────────────────┐
         │   2. ROUND START                  │
         │   - Increment round counter       │
         │   - Decrement condition durations │
         │   - Process round-start effects   │
         └─────────────────┬─────────────────┘
                           │
         ┌─────────────────▼─────────────────┐
         │   3. PARTICIPANT TURN             │
         │   ┌───────────────────────────┐  │
         │   │ A. Turn Start              │  │
         │   │   - Grant 3 actions        │  │
         │   │   - Grant 1 reaction       │  │
         │   │   - Reset MAP              │  │
         │   │   - Process start effects  │  │
         │   └──────────┬─────────────────┘  │
         │              │                    │
         │   ┌──────────▼─────────────────┐  │
         │   │ B. Action Phase             │  │
         │   │   - Execute actions (x3)    │  │
         │   │   - Apply action effects    │  │
         │   │   - Update combat state     │  │
         │   │   - Log all actions         │  │
         │   └──────────┬─────────────────┘  │
         │              │                    │
         │   ┌──────────▼─────────────────┐  │
         │   │ C. Turn End                 │  │
         │   │   - Apply persistent damage │  │
         │   │   - Process end effects     │  │
         │   │   - Remove expired buffs    │  │
         │   └──────────────────────────────┘  │
         └─────────────────┬─────────────────┘
                           │
                    [Next Participant]
                           │
         ┌─────────────────▼─────────────────┐
         │   4. ROUND END                    │
         │   - Check win/lose conditions     │
         │   - Update round effects          │
         └─────────────────┬─────────────────┘
                           │
                   [Next Round OR End]
                           │
         ┌─────────────────▼─────────────────┐
         │   5. ENCOUNTER END                │
         │   - Calculate XP awards           │
         │   - Log encounter results         │
         │   - Update character records      │
         └───────────────────────────────────┘
```

## Key Concepts

### Initiative System
- Perception-based by default (can use other skills)
- Rolled once at combat start
- Ties: NPCs before PCs, otherwise player choice
- Can be modified by Delay action
- Can be interrupted by Ready action triggers

### Three-Action Economy
- 3 actions per turn (single, two-action, three-action activities)
- 1 reaction per round (usable on any turn)
- Free actions (don't cost actions, subject to triggers)
- Actions cannot be saved between turns
- Reactions reset at start of each turn

### Multiple Attack Penalty (MAP)
- First attack: no penalty
- Second attack: -5 (or -4 for agile weapons)
- Third+ attack: -10 (or -8 for agile weapons)
- Applies to any action with Attack trait
- Resets at start of next turn

### Degrees of Success
- Critical Success: Roll ≥ DC+10 OR natural 20
- Success: Roll ≥ DC
- Failure: Roll < DC
- Critical Failure: Roll ≤ DC-10 OR natural 1
- Natural 20 improves result by one step
- Natural 1 worsens result by one step

### Conditions
- Valued conditions (frightened 2, clumsy 3)
- Duration types: rounds, turns, sustained, permanent
- Stacking rules (same type, different sources)
- Automatic decrements (frightened reduces by 1 each turn)
- Effects on stats (penalties, action restrictions)

### Hit Points and Damage
- Current HP, max HP, temporary HP tracked separately
- Dying condition when HP = 0
- Death at dying 4
- Recovery checks required when dying
- Resistances reduce damage (minimum 0)
- Weaknesses increase damage

## Implementation Phases

### Phase 1: Core Combat Engine (This Design)
- Combat state management
- Initiative tracking
- Turn management
- Basic action handling
- HP and condition tracking

### Phase 2: Advanced Actions
- Spell casting integration
- Special combat maneuvers
- Environmental effects
- Area of effect resolution

### Phase 3: UI Enhancements
- Battle map integration
- Movement visualization
- Animation and sound effects
- Mobile optimization

### Phase 4: AI and Automation
- Monster AI behaviors
- Combat suggestions
- Automated minion management
- Combat analytics

## Success Metrics

### Functionality
- [ ] All PF2e combat rules correctly implemented
- [ ] 100% action validation accuracy
- [ ] Zero combat state desync issues
- [ ] All conditions apply effects correctly

### Performance
- [ ] <100ms action processing time
- [ ] <500ms state update to all clients
- [ ] Support 20+ participants per combat
- [ ] Handle 100+ concurrent combats

### Usability
- [ ] <3 clicks for common actions
- [ ] <5 second GM setup time
- [ ] 90%+ user satisfaction rating
- [ ] <10% support tickets for combat issues

## Testing Strategy

### Unit Tests
- State machine transitions
- Calculation accuracy (attack rolls, damage, MAP)
- Condition effect application
- Action validation rules

### Integration Tests
- Database operations
- API endpoint responses
- Real-time state synchronization
- Combat lifecycle flows

### End-to-End Tests
- Complete combat scenarios
- Multi-participant coordination
- Edge cases (death, fleeing, surrender)
- Performance under load

## Security Considerations

- Validate all user inputs
- Prevent action spoofing
- Ensure participant authorization
- Rate limit action submissions
- Log all combat actions for audit
- Prevent dice roll manipulation

## Accessibility

- Screen reader support for combat events
- Keyboard navigation for all actions
- High contrast mode for UI
- Configurable text size
- Audio cues for turn changes

## Future Enhancements

- Automated encounter difficulty calculation
- Combat replay and analysis
- AI-assisted monster tactics
- Integration with VTT platforms
- Recorded combat sessions
- Combat templates and presets
- Player-vs-player combat mode
- Mass combat rules support

## Notes

**DESIGN-ONLY**: This is a design document. Implementation will be tracked in separate issues and PRs.

## References

- Pathfinder 2E Core Rulebook (4th Printing)
- Pathfinder 2E Gamemastery Guide
- Archives of Nethys (https://2e.aonprd.com)
- Existing documentation in `/docs/dungeoncrawler/`

---

**Last Updated**: 2026-02-12  
**Status**: Complete  
**Next Steps**: Review design, create implementation issues
