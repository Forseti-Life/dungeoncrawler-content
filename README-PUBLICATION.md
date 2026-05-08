# DungeonCrawler Content Module

AI-powered procedurally generated living dungeon for Pathfinder 2nd Edition campaigns.

## Overview

The DungeonCrawler Content module provides:

- Procedurally generated dungeon levels
- AI-powered creature and NPC generation
- Dynamic encounter design
- Pathfinder 2E rules compliance
- Campaign state management
- Live gameplay coordination

## Installation

### Requirements

- Drupal 10.3+
- PHP 8.1+
- AI Conversation module (ai_conversation)

### Steps

1. **Place the module**:
   ```bash
   cp -r dungeoncrawler_content {YOUR_DRUPAL_ROOT}/modules/custom/
   ```

2. **Enable dependencies**:
   ```bash
   cd {YOUR_DRUPAL_ROOT}
   ./vendor/bin/drush module:install ai_conversation
   ```

3. **Enable the module**:
   ```bash
   ./vendor/bin/drush module:install dungeoncrawler_content
   ```

4. **Clear caches**:
   ```bash
   ./vendor/bin/drush cache:rebuild
   ```

## Quick Start

1. Navigate to **Game > New Campaign**
2. Set difficulty and party composition
3. Click "Generate Dungeon"
4. Start playing!

## Key Features

- **Living Dungeons**: Dungeons respond to player actions
- **AI Generation**: Uses LLMs for content creation
- **PF2E Compliance**: All encounters follow Pathfinder 2E rules
- **Real-time Updates**: Live gameplay updates for all players
- **Rich Content**: Items, NPCs, lore, hazards
- **Extensible**: Hooks for custom content

## Gameplay Commands

Access via `/game` routes:
- `/game/campaign/123/start` - Start playing
- `/game/campaign/123/map` - View hex map
- `/game/campaign/123/state` - View game state
- `/game/campaign/123/actions` - Available actions

## Configuration

### AI Settings

Navigate to **Manage > Configuration > DungeonCrawler**

Configure:
- LLM provider (Gemini, Vertex AI, etc.)
- Model parameters
- Generation templates
- Difficulty scaling

### Environment Variables

```bash
DUNGEONCRAWLER_AI_PROVIDER=gemini
DUNGEONCRAWLER_LLM_MODEL=gemini-pro
GEMINI_API_KEY=your_key_here
```

## API Documentation

See: API_DOCUMENTATION.md

## Architecture

See: GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md

## Troubleshooting

**Issue**: AI generation fails
- Verify API credentials set in .env
- Check network connectivity
- Review module logs

**Issue**: Encounters too easy/hard
- Adjust difficulty in campaign settings
- Verify party composition is correct

## Support

Report issues: https://www.drupal.org/project/dungeoncrawler_content/issues

## License

GNU General Public License v2.0 or later.
