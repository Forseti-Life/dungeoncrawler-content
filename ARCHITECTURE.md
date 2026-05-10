# Architecture: DungeonCrawler Content Module

## System Overview

The DungeonCrawler Content module implements a multi-layered architecture for procedural generation and real-time gameplay:

```
┌────────────────────────────────────┐
│      UI/Presentation Layer         │
│  (Routes, Controllers, Templates)  │
├────────────────────────────────────┤
│     Game Orchestration Layer       │
│  (GameCoordinatorService, States)  │
├────────────────────────────────────┤
│      Content Generation Layer      │
│ (Generation Services, AI Providers)│
├────────────────────────────────────┤
│      Entity/Data Layer             │
│  (Campaigns, Levels, Creatures)    │
├────────────────────────────────────┤
│      Storage Layer                 │
│  (Database, Cache, External APIs)  │
└────────────────────────────────────┘
```

## Key Components

### Controllers
- **CampaignController**: Campaign management
- **GameController**: Live game interface
- **MapController**: Hex map viewing
- **DashboardController**: Player dashboard

### Services
- **GameCoordinatorService**: Central orchestration
- **CombatEngine**: Combat mechanics
- **CreatureGenerator**: AI creature generation
- **EncounterBuilder**: Dynamic encounter design
- **HexMapGenerator**: Procedural map generation

### Entities
- **Campaign**: Game session container
- **Level**: Single dungeon floor
- **Creature**: NPC/monster data
- **Encounter**: Combat scenario
- **State**: Current game state

## Data Model

### Core Entities

```
Campaign
├── title (String)
├── party_id (Reference to party)
├── difficulty (String: easy|normal|hard|deadly)
├── current_level (Integer)
├── state (Reference to State)
├── created (Timestamp)
└── modified (Timestamp)

Level
├── level_number (Integer)
├── hex_map (JSON)
├── rooms (Array)
├── creatures (Array)
├── encounters (Array)
├── lore (Text)
└── generated_at (Timestamp)

Creature
├── name (String)
├── role (String: minion|combatant|elite|boss)
├── level (Integer)
├── abilities (JSON)
├── hp (Integer)
├── ac (Integer)
└── special_traits (Array)
```

## Generation Pipeline

### Dungeon Generation

```
Campaign Created
    ↓
GameCoordinatorService->startGame()
    ↓
HexMapGenerator->generateFloor()
    ↓
EncounterBuilder->designEncounters()
    ↓
CreatureGenerator->generateCreatures() [AI]
    ↓
Level Entity (saved)
    ↓
Campaign State (updated)
    ↓
Ready for Play
```

### Gameplay Loop

```
Player Action
    ↓
GameCoordinatorService->processAction()
    ↓
CombatEngine->resolveAction() [if combat]
    ↓
State Updated
    ↓
Observers Notified
    ↓
UI Refreshed
```

## AI Integration

The module uses LLMs for:
- Creature generation (stats, abilities, lore)
- Encounter design (difficulty balancing)
- Item generation (magical properties)
- NPC dialogue and behavior
- Adaptive difficulty

### API Providers

Supported:
- Google Gemini API
- Google Vertex AI
- (Extensible for others)

Configure via environment variables:

```bash
DUNGEONCRAWLER_AI_PROVIDER=gemini
GEMINI_API_KEY=sk-...
```

## Performance Optimization

### Caching Strategy

- Campaign state: 5-minute TTL
- Generated content: Per-session cache
- Creature stats: 1-hour TTL
- Map data: Persistent (doesn't change during session)

### Database Indexing

```sql
-- Optimized for common queries
CREATE INDEX idx_campaign_party ON dungeoncrawler_campaign(party_id);
CREATE INDEX idx_level_campaign ON dungeoncrawler_level(campaign_id);
CREATE INDEX idx_state_campaign ON dungeoncrawler_state(campaign_id);
CREATE INDEX idx_creature_level ON dungeoncrawler_creature(level_id);
```

## Extension Points

### Hooks

```php
hook_dungeoncrawler_encounter_generated(&$encounter, $campaign)
hook_dungeoncrawler_creature_generated(&$creature, $context)
hook_dungeoncrawler_state_changed($campaign, $old_state, $new_state)
hook_dungeoncrawler_action_processed($action, $result, $campaign)
```

### Custom Generators

Register custom generation services:

```php
$generator = \Drupal::service('dungeoncrawler_content.generator.creature');
$generator->register('custom_race', new CustomRaceGenerator());
```

## Known Limitations

1. API generation subject to LLM rate limits
2. Real-time features require persistent WebSocket (use with caution)
3. Large party sizes (>6) may impact performance
4. Historical state replays limited to current session
5. Procedural content may rarely violate PF2E rules (manual review recommended)

## Future Enhancements

- Multiplayer campaign support
- Save/load persistent campaigns
- Advanced AI for adaptive enemies
- Full voice/text-to-speech integration
- Mobile companion app
- Campaign publishing marketplace

## Related Architecture Docs

- [GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md](GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md) — authoritative runtime gameplay flow
- [CHAT_AND_NARRATION_ARCHITECTURE.md](CHAT_AND_NARRATION_ARCHITECTURE.md) — session hierarchy and narration pipeline
- [DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md](DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md) — proposed deterministic broker architecture for room-chat GM orchestration
