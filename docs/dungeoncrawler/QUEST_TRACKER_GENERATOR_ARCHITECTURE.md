# Quest Tracker & Generator Architecture

**Version**: 1.0.0  
**Last Updated**: 2026-02-19  
**Status**: Design Phase

## Table of Contents

1. [Overview](#overview)
2. [Core Architecture](#core-architecture)
3. [Database Schema](#database-schema)
4. [Service Layer Design](#service-layer-design)
5. [Quest Generation System](#quest-generation-system)
6. [Quest Tracking System](#quest-tracking-system)
7. [API Endpoints](#api-endpoints)
8. [Data Structures](#data-structures)
9. [Integration Points](#integration-points)
10. [Implementation Roadmap](#implementation-roadmap)

---

## Overview

The Quest Tracker & Generator system provides procedural quest creation and comprehensive progress tracking for campaign-level gameplay. Following the established **Library → Campaign → Runtime** pattern, the system ensures:

- **Quest Templates** — Library tables store reusable quest templates
- **Campaign Quests** — Per-campaign quest instances forked from library templates
- **Runtime Tracking** — Active progress, objectives, rewards tracked separately
- **PF2e Integration** — Quests award XP, items, reputation following Pathfinder 2nd Edition rules
- **Procedural Generation** — AI-driven quest creation based on party level, campaign context, and current location

### Key Features

- **Dynamic Quest Generation** based on party composition and location
- **Objective Tracking** with multiple objective types (kill, collect, explore, escort, navigate)
- **Branching Storylines** with multiple outcomes and consequences
- **Reward Systems** including XP, gold, items, reputation, and story unlocks
- **State Persistence** — quest progress survives session interruptions
- **Campaign Integration** — quests affect campaign state and unlock content

---

## Core Architecture

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│               REST API Controllers                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │QuestGenCmd   │  │QuestTracker  │  │QuestReward   │     │
│  │- POST /gen   │  │- GET/PUT     │  │- POST /claim │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└────────┬────────────────┬────────────────┬─────────────────┘
         │                │                │
┌────────▼────────────────▼────────────────▼─────────────────┐
│           Service Layer (Business Logic)                    │
│  ┌──────────────────┐  ┌──────────────────────────────┐   │
│  │QuestGeneratorSvc │  │QuestTrackerSvc               │   │
│  │ - Template Select│  │ - Progress Tracking          │   │
│  │ - Variable Inject│  │ - Objective Completion       │   │
│  │ - Reward Scale   │  │ - State Persistence          │   │
│  │ - Chain Create   │  │ - Branching Logic            │   │
│  └──────────────────┘  └──────────────────────────────┘   │
│  ┌──────────────────┐  ┌──────────────────────────────┐   │
│  │QuestRewardSvc    │  │QuestValidatorSvc             │   │
│  │ - XP Awards      │  │ - Prerequisite Check         │   │
│  │ - Item Grants    │  │ - Level Gates                │   │
│  │ - Reputation     │  │ - Requirement Validation     │   │
│  │ - Story Unlocks  │  │                              │   │
│  └──────────────────┘  └──────────────────────────────┘   │
└────────┬───────────────────────────────────────────────────┘
         │
┌────────▼───────────────────────────────────────────────────┐
│          Data Access Layer (Repositories)                  │
│  ┌──────────────────────┐  ┌──────────────────────────┐   │
│  │QuestTemplateRepo     │  │CampaignQuestRepo         │   │
│  │ - Load Library Quests│  │ - Load Campaign Quests   │   │
│  │ - Filter by Tags     │  │ - Fork from Template     │   │
│  │ - Level Range Query  │  │ - Update Quest Data      │   │
│  └──────────────────────┘  └──────────────────────────┘   │
│  ┌──────────────────────┐  ┌──────────────────────────┐   │
│  │QuestProgressRepo     │  │QuestLogRepo              │   │
│  │ - Track Objectives   │  │ - Record Events          │   │
│  │ - Update State       │  │ - Query History          │   │
│  │ - Branching Paths    │  │                          │   │
│  └──────────────────────┘  └──────────────────────────┘   │
└────────┬───────────────────────────────────────────────────┘
         │
┌────────▼───────────────────────────────────────────────────┐
│          Database Layer (Three-Tier Architecture)          │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ LIBRARY TABLES (Immutable Templates)                 │ │
│  │ ┌────────────────────────────────────────────────┐   │ │
│  │ │ dungeoncrawler_content_quest_templates         │   │ │
│  │ │ - template_id, name, description               │   │ │
│  │ │ - quest_type, level_range, tags               │   │ │
│  │ │ - objectives_schema (JSON)                     │   │ │
│  │ │ - rewards_schema (JSON)                        │   │ │
│  │ │ - prerequisites (JSON)                         │   │ │
│  │ └────────────────────────────────────────────────┘   │ │
│  │ ┌────────────────────────────────────────────────┐   │ │
│  │ │ dungeoncrawler_content_quest_chains            │   │ │
│  │ │ - chain_id, name, description                  │   │ │
│  │ │ - quest_sequence (JSON array)                  │   │ │
│  │ │ - branching_paths (JSON)                       │   │ │
│  │ └────────────────────────────────────────────────┘   │ │
│  └──────────────────────────────────────────────────────┘ │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ CAMPAIGN TABLES (Per-Campaign Instances)             │ │
│  │ ┌────────────────────────────────────────────────┐   │ │
│  │ │ dc_campaign_quests                             │   │ │
│  │ │ - campaign_id, quest_id (PK)                   │   │ │
│  │ │ - source_template_id (FK to library)           │   │ │
│  │ │ - quest_data (JSON - campaign-specific vars)   │   │ │
│  │ │ - generated_objectives (JSON)                  │   │ │
│  │ │ - generated_rewards (JSON)                     │   │ │
│  │ │ - status (available/active/completed/failed)   │   │ │
│  │ └────────────────────────────────────────────────┘   │ │
│  └──────────────────────────────────────────────────────┘ │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ RUNTIME TABLES (Active Progress Tracking)            │ │
│  │ ┌────────────────────────────────────────────────┐   │ │
│  │ │ dc_campaign_quest_progress                     │   │ │
│  │ │ - campaign_id, quest_id, character_id          │   │ │
│  │ │ - objective_states (JSON)                      │   │ │
│  │ │ - started_at, last_updated, completed_at       │   │ │
│  │ │ - current_phase, branch_choice                 │   │ │
│  │ └────────────────────────────────────────────────┘   │ │
│  │ ┌────────────────────────────────────────────────┐   │ │
│  │ │ dc_campaign_quest_log                          │   │ │
│  │ │ - campaign_id, quest_id, log_entry_id          │   │ │
│  │ │ - event_type, event_data (JSON)                │   │ │
│  │ │ - character_id, timestamp                      │   │ │
│  │ └────────────────────────────────────────────────┘   │ │
│  │ ┌────────────────────────────────────────────────┐   │ │
│  │ │ dc_campaign_quest_rewards_claimed              │   │ │
│  │ │ - campaign_id, quest_id, character_id          │   │ │
│  │ │ - reward_data (JSON)                           │   │ │
│  │ │ - claimed_at                                   │   │ │
│  │ └────────────────────────────────────────────────┘   │ │
│  └──────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Library Tables (Immutable Templates)

#### `dungeoncrawler_content_quest_templates`

Stores reusable quest templates that can be instantiated in any campaign.

```php
$schema['dungeoncrawler_content_quest_templates'] = [
  'description' => 'Library of reusable quest templates for procedural generation',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Primary key',
    ],
    'template_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => TRUE,
      'description' => 'Unique template identifier (e.g., rescue_merchant, slay_beast)',
    ],
    'name' => [
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'description' => 'Template name (may contain variables like {target})',
    ],
    'description' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'Quest description template',
    ],
    'quest_type' => [
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
      'description' => 'Type: main_story, side_quest, bounty, patrol, delivery, rescue',
    ],
    'level_min' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
      'description' => 'Minimum party level',
    ],
    'level_max' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 20,
      'description' => 'Maximum party level',
    ],
    'tags' => [
      'type' => 'text',
      'not null' => FALSE,
      'description' => 'JSON array of tags (combat, exploration, social, dungeon)',
    ],
    'objectives_schema' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => TRUE,
      'description' => 'JSON schema defining objectives with variables and branching',
    ],
    'rewards_schema' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'JSON schema for XP, gold, items, reputation rewards',
    ],
    'prerequisites' => [
      'type' => 'text',
      'not null' => FALSE,
      'description' => 'JSON prerequisites (completed_quests, reputation, items)',
    ],
    'story_impact' => [
      'type' => 'text',
      'not null' => FALSE,
      'description' => 'JSON defining how completion affects campaign state',
    ],
    'estimated_duration_minutes' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'Estimated time to complete',
    ],
    'created' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => 'Unix timestamp when created',
    ],
    'updated' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => 'Unix timestamp when updated',
    ],
    'version' => [
      'type' => 'varchar',
      'length' => 20,
      'not null' => FALSE,
      'description' => 'Template version',
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'template_id' => ['template_id'],
  ],
  'indexes' => [
    'type_level' => ['quest_type', 'level_min', 'level_max'],
  ],
];
```

#### `dungeoncrawler_content_quest_chains`

Defines multi-quest storylines with branching paths.

```php
$schema['dungeoncrawler_content_quest_chains'] = [
  'description' => 'Quest chains defining multi-quest storylines',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Primary key',
    ],
    'chain_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => TRUE,
      'description' => 'Unique chain identifier',
    ],
    'name' => [
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'description' => 'Chain name',
    ],
    'description' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'Chain description',
    ],
    'quest_sequence' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => TRUE,
      'description' => 'JSON array of quest_template_ids in order',
    ],
    'branching_paths' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => FALSE,
      'description' => 'JSON defining conditional branches based on choices',
    ],
    'chain_type' => [
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
      'description' => 'Type: linear, branching, parallel',
    ],
    'created' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => 'Unix timestamp when created',
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'chain_id' => ['chain_id'],
  ],
];
```

### Campaign Tables (Per-Campaign Instances)

#### `dc_campaign_quests`

Active quests in a specific campaign, forked from library templates.

```php
$schema['dc_campaign_quests'] = [
  'description' => 'Active quests in campaigns, instantiated from templates',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Primary key',
    ],
    'campaign_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Campaign this quest belongs to',
    ],
    'quest_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => TRUE,
      'description' => 'Unique quest identifier in this campaign',
    ],
    'source_template_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => FALSE,
      'description' => 'FK to dungeoncrawler_content_quest_templates.template_id',
    ],
    'quest_name' => [
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'description' => 'Generated quest name with variables resolved',
    ],
    'quest_description' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'Generated quest description',
    ],
    'quest_type' => [
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
      'description' => 'Type from template',
    ],
    'quest_data' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => TRUE,
      'description' => 'JSON: campaign-specific variables, NPCs, locations',
    ],
    'generated_objectives' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => TRUE,
      'description' => 'JSON array of objectives with current values',
    ],
    'generated_rewards' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'JSON: XP, gold, items scaled to party level',
    ],
    'status' => [
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
      'default' => 'available',
      'description' => 'Status: available, active, completed, failed, abandoned',
    ],
    'giver_npc_id' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'NPC who gave the quest',
    ],
    'location_id' => [
      'type' => 'varchar',
      'length' => 128,
      'not null' => FALSE,
      'description' => 'Location where quest is available/active',
    ],
    'parent_chain_id' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'If part of a chain, references chain',
    ],
    'chain_position' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'Position in quest chain',
    ],
    'created_at' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => 'Unix timestamp when quest generated',
    ],
    'available_at' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'Unix timestamp when quest became available',
    ],
    'expires_at' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'Unix timestamp when quest expires (if time-limited)',
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'campaign_quest' => ['campaign_id', 'quest_id'],
  ],
  'indexes' => [
    'campaign_status' => ['campaign_id', 'status'],
    'source_template' => ['source_template_id'],
    'location' => ['location_id'],
  ],
];
```

### Runtime Tables (Active Progress Tracking)

#### `dc_campaign_quest_progress`

Tracks real-time progress for each character/party on active quests.

```php
$schema['dc_campaign_quest_progress'] = [
  'description' => 'Real-time quest progress tracking per character/party',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Primary key',
    ],
    'campaign_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Campaign ID',
    ],
    'quest_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => TRUE,
      'description' => 'Quest identifier',
    ],
    'character_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
      'description' => 'Character tracking progress (NULL for party quests)',
    ],
    'party_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
      'description' => 'Party tracking progress (NULL for individual quests)',
    ],
    'objective_states' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => TRUE,
      'description' => 'JSON: each objective with current/target values',
    ],
    'current_phase' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => 'Current phase/step in quest progression',
    ],
    'branch_choice' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => FALSE,
      'description' => 'Choice made at branching point (if applicable)',
    ],
    'variables' => [
      'type' => 'text',
      'not null' => FALSE,
      'description' => 'JSON: runtime variables for quest logic',
    ],
    'started_at' => [
      'type' => 'int',
      'not null' => TRUE,
      'description' => 'Unix timestamp when quest started',
    ],
    'last_updated' => [
      'type' => 'int',
      'not null' => TRUE,
      'description' => 'Unix timestamp of last progress update',
    ],
    'completed_at' => [
      'type' => 'int',
      'not null' => FALSE,
      'description' => 'Unix timestamp when completed (NULL if active)',
    ],
    'outcome' => [
      'type' => 'varchar',
      'length' => 50,
      'not null' => FALSE,
      'description' => 'Outcome: success, failure, partial, abandoned',
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'campaign_quest_character' => ['campaign_id', 'quest_id', 'character_id'],
    'campaign_quest_party' => ['campaign_id', 'quest_id', 'party_id'],
  ],
  'indexes' => [
    'character_active' => ['character_id', 'completed_at'],
    'party_active' => ['party_id', 'completed_at'],
  ],
];
```

#### `dc_campaign_quest_log`

Event log for quest-related actions (journal entries, narrative events).

```php
$schema['dc_campaign_quest_log'] = [
  'description' => 'Quest event log for narrative tracking and debugging',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Primary key',
    ],
    'campaign_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Campaign ID',
    ],
    'quest_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => TRUE,
      'description' => 'Quest identifier',
    ],
    'character_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
      'description' => 'Character who triggered event',
    ],
    'event_type' => [
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
      'description' => 'Event: started, objective_completed, phase_advanced, completed, failed',
    ],
    'event_data' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'JSON: event-specific data',
    ],
    'narrative_text' => [
      'type' => 'text',
      'not null' => FALSE,
      'description' => 'Human-readable journal entry',
    ],
    'timestamp' => [
      'type' => 'int',
      'not null' => TRUE,
      'description' => 'Unix timestamp of event',
    ],
  ],
  'primary key' => ['id'],
  'indexes' => [
    'campaign_quest' => ['campaign_id', 'quest_id', 'timestamp'],
    'character_log' => ['character_id', 'timestamp'],
  ],
];
```

#### `dc_campaign_quest_rewards_claimed`

Tracks claimed rewards to prevent duplicate claims.

```php
$schema['dc_campaign_quest_rewards_claimed'] = [
  'description' => 'Tracks rewards claimed from completed quests',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Primary key',
    ],
    'campaign_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Campaign ID',
    ],
    'quest_id' => [
      'type' => 'varchar',
      'length' => 100,
      'not null' => TRUE,
      'description' => 'Quest identifier',
    ],
    'character_id' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'Character who claimed reward',
    ],
    'reward_data' => [
      'type' => 'text',
      'not null' => TRUE,
      'description' => 'JSON: XP, gold, items, reputation granted',
    ],
    'claimed_at' => [
      'type' => 'int',
      'not null' => TRUE,
      'description' => 'Unix timestamp when reward claimed',
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'campaign_quest_character' => ['campaign_id', 'quest_id', 'character_id'],
  ],
  'indexes' => [
    'character' => ['character_id', 'claimed_at'],
  ],
];
```

---

## Service Layer Design

### QuestGeneratorService

**Responsibility**: Generate quests from templates with campaign-specific variables.

```php
namespace Drupal\dungeoncrawler_content\Service;

class QuestGeneratorService {
  
  /**
   * Generate a quest from a template.
   *
   * @param string $template_id
   *   The quest template ID.
   * @param int $campaign_id
   *   The campaign ID.
   * @param array $context
   *   Generation context: party_level, location, npcs, etc.
   *
   * @return array
   *   Generated quest data ready for insertion into dc_campaign_quests.
   */
  public function generateQuestFromTemplate(
    string $template_id, 
    int $campaign_id, 
    array $context
  ): array;
  
  /**
   * Generate multiple quests appropriate for location and party level.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $context
   *   Generation context.
   * @param int $count
   *   Number of quests to generate.
   *
   * @return array
   *   Array of generated quests.
   */
  public function generateQuestsForLocation(
    int $campaign_id,
    array $context,
    int $count = 3
  ): array;
  
  /**
   * Resolve template variables (e.g., {target}, {location}, {reward}).
   *
   * @param string $text
   *   Text with variables.
   * @param array $variables
   *   Variable values.
   *
   * @return string
   *   Resolved text.
   */
  protected function resolveVariables(string $text, array $variables): string;
  
  /**
   * Scale rewards based on party level and difficulty.
   *
   * @param array $rewards_schema
   *   Rewards schema from template.
   * @param int $party_level
   *   Average party level.
   * @param string $difficulty
   *   Difficulty: trivial, low, moderate, severe, extreme.
   *
   * @return array
   *   Scaled rewards.
   */
  protected function scaleRewards(
    array $rewards_schema,
    int $party_level,
    string $difficulty
  ): array;
  
  /**
   * Generate objectives with target values.
   *
   * @param array $objectives_schema
   *   Objectives schema from template.
   * @param array $context
   *   Generation context.
   *
   * @return array
   *   Generated objectives with targets.
   */
  protected function generateObjectives(
    array $objectives_schema,
    array $context
  ): array;
}
```

### QuestTrackerService

**Responsibility**: Track quest progress, update objectives, handle completion.

```php
namespace Drupal\dungeoncrawler_content\Service;

class QuestTrackerService {
  
  /**
   * Start a quest for a character or party.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID (NULL for party quest).
   * @param int|null $party_id
   *   Party ID (NULL for individual quest).
   *
   * @return bool
   *   TRUE if successfully started.
   */
  public function startQuest(
    int $campaign_id,
    string $quest_id,
    ?int $character_id = NULL,
    ?int $party_id = NULL
  ): bool;
  
  /**
   * Update objective progress.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param string $objective_id
   *   Objective identifier.
   * @param int $progress
   *   New progress value.
   * @param int|null $character_id
   *   Character ID.
   *
   * @return array
   *   Updated quest state including completion status.
   */
  public function updateObjectiveProgress(
    int $campaign_id,
    string $quest_id,
    string $objective_id,
    int $progress,
    ?int $character_id = NULL
  ): array;
  
  /**
   * Check if quest is completed.
   *
   * @param array $objective_states
   *   Current objective states.
   *
   * @return bool
   *   TRUE if all objectives complete.
   */
  public function isQuestCompleted(array $objective_states): bool;
  
  /**
   * Complete a quest.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int|null $character_id
   *   Character ID.
   * @param string $outcome
   *   Outcome: success, failure, partial.
   *
   * @return array
   *   Quest completion data including rewards.
   */
  public function completeQuest(
    int $campaign_id,
    string $quest_id,
    ?int $character_id = NULL,
    string $outcome = 'success'
  ): array;
  
  /**
   * Get active quests for a character.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return array
   *   Array of active quests with progress.
   */
  public function getActiveQuests(int $campaign_id, int $character_id): array;
  
  /**
   * Get available quests at a location.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $location_id
   *   Location identifier.
   * @param int $character_id
   *   Character ID (to check prerequisites).
   *
   * @return array
   *   Array of available quests.
   */
  public function getAvailableQuests(
    int $campaign_id,
    string $location_id,
    int $character_id
  ): array;
  
  /**
   * Log a quest event.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param string $event_type
   *   Event type.
   * @param array $event_data
   *   Event data.
   * @param string|null $narrative_text
   *   Human-readable narrative.
   * @param int|null $character_id
   *   Character ID.
   */
  protected function logQuestEvent(
    int $campaign_id,
    string $quest_id,
    string $event_type,
    array $event_data,
    ?string $narrative_text = NULL,
    ?int $character_id = NULL
  ): void;
}
```

### QuestRewardService

**Responsibility**: Grant rewards from completed quests.

```php
namespace Drupal\dungeoncrawler_content\Service;

class QuestRewardService {
  
  /**
   * Claim rewards from a completed quest.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID claiming rewards.
   *
   * @return array
   *   Reward data granted.
   *
   * @throws \Exception
   *   If quest not completed or rewards already claimed.
   */
  public function claimQuestRewards(
    int $campaign_id,
    string $quest_id,
    int $character_id
  ): array;
  
  /**
   * Grant XP to character.
   */
  protected function grantXP(int $character_id, int $xp): void;
  
  /**
   * Grant gold to character.
   */
  protected function grantGold(int $character_id, int $gold): void;
  
  /**
   * Grant items to character inventory.
   */
  protected function grantItems(int $character_id, array $items): void;
  
  /**
   * Update reputation with faction.
   */
  protected function grantReputation(
    int $campaign_id,
    int $character_id,
    string $faction,
    int $amount
  ): void;
  
  /**
   * Unlock story content.
   */
  protected function unlockStoryContent(
    int $campaign_id,
    array $unlocks
  ): void;
}
```

### QuestValidatorService

**Responsibility**: Validate prerequisites and quest availability.

```php
namespace Drupal\dungeoncrawler_content\Service;

class QuestValidatorService {
  
  /**
   * Check if character meets quest prerequisites.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID.
   *
   * @return array
   *   [
   *     'valid' => bool,
   *     'missing' => array of missing prerequisites
   *   ]
   */
  public function validatePrerequisites(
    int $campaign_id,
    string $quest_id,
    int $character_id
  ): array;
  
  /**
   * Check level requirements.
   */
  protected function checkLevelRequirement(
    int $character_level,
    int $min_level,
    int $max_level
  ): bool;
  
  /**
   * Check completed quest requirements.
   */
  protected function checkCompletedQuests(
    int $campaign_id,
    int $character_id,
    array $required_quests
  ): array;
  
  /**
   * Check reputation requirements.
   */
  protected function checkReputationRequirements(
    int $campaign_id,
    int $character_id,
    array $reputation_requirements
  ): array;
  
  /**
   * Check item requirements.
   */
  protected function checkItemRequirements(
    int $character_id,
    array $required_items
  ): array;
}
```

---

## Quest Generation System

### Generation Workflow

```
1. REQUEST QUEST GENERATION
   ↓
2. SELECT APPROPRIATE TEMPLATES
   - Filter by party level
   - Filter by location tags
   - Filter by available prerequisites
   - Exclude already active/completed quests
   ↓
3. CHOOSE TEMPLATE
   - Weight by quest type balance
   - Consider campaign context
   - Apply randomization
   ↓
4. RESOLVE VARIABLES
   - Inject NPC names
   - Inject location names
   - Inject creature types
   - Inject item names
   ↓
5. GENERATE OBJECTIVES
   - Calculate target values
   - Scale to party level
   - Apply randomization within ranges
   ↓
6. SCALE REWARDS
   - Calculate XP (PF2e rules)
   - Scale gold by level
   - Select items from loot tables
   - Calculate reputation gains
   ↓
7. INSERT INTO dc_campaign_quests
   - Mark as 'available'
   - Set location and giver
   ↓
8. RETURN QUEST DATA
```

### Quest Types

| Type | Description | Example Objectives |
|------|-------------|-------------------|
| `main_story` | Critical campaign storyline | Defeat boss, investigate mystery |
| `side_quest` | Optional content | Help NPC, explore area |
| `bounty` | Combat-focused | Kill X creatures |
| `patrol` | Area clearing | Clear dungeon, secure perimeter |
| `delivery` | Transport items/info | Escort NPC, deliver package |
| `rescue` | Save entity | Rescue prisoner, find lost item |
| `exploration` | Discovery-based | Map area, find location |
| `social` | Interaction-focused | Negotiate, gather information |
| `crafting` | Creation objectives | Craft item, gather materials |

### Objective Types

#### Kill Objective
```json
{
  "type": "kill",
  "target": "goblin",
  "current": 0,
  "target_count": 12,
  "location_hint": "Goblin Den"
}
```

#### Collect Objective
```json
{
  "type": "collect",
  "item": "wolf_pelt",
  "current": 0,
  "target_count": 5,
  "sources": ["wolf", "dire_wolf"]
}
```

#### Explore Objective
```json
{
  "type": "explore",
  "location": "ancient_ruins",
  "discovered": false,
  "coordinates": {"x": 120, "y": 85}
}
```

#### Escort Objective
```json
{
  "type": "escort",
  "npc_id": 42,
  "destination": "safe_house",
  "current_location": "starting_town",
  "npc_health": 100,
  "arrived": false
}
```

#### Navigate Objective
```json
{
  "type": "navigate",
  "waypoints": ["checkpoint_1", "checkpoint_2", "destination"],
  "current_waypoint": 0,
  "completed_waypoints": []
}
```

### Variable Resolution

Templates use placeholders that are resolved during generation:

| Variable | Description | Example Resolution |
|----------|-------------|-------------------|
| `{target}` | Quest target entity | "Goblin Chieftain" |
| `{target_count}` | Number of targets | "12" |
| `{location}` | Location name | "Darkwood Forest" |
| `{item}` | Item name | "Ancient Relic" |
| `{npc}` | NPC name | "Merchant Aldric" |
| `{faction}` | Faction name | "City Guard" |
| `{reward_xp}` | XP reward | "400" |
| `{reward_gold}` | Gold reward | "25 gp" |

**Example Template:**
```
Name: "Rescue {npc} from {location}"
Description: "{npc}, a {npc_role}, was captured by {target}s in {location}. 
             Find and rescue them before it's too late!"
```

**Generated Quest:**
```
Name: "Rescue Merchant Aldric from Goblin Den"
Description: "Merchant Aldric, a traveling merchant, was captured by goblins in 
              the Goblin Den. Find and rescue him before it's too late!"
```

---

## Quest Tracking System

### Progress Update Workflow

```
1. PLAYER ACTION
   (kill creature, collect item, reach location)
   ↓
2. IDENTIFY RELEVANT QUESTS
   - Query active quests for character/party
   - Check which objectives match action
   ↓
3. UPDATE OBJECTIVE STATE
   - Increment current value
   - Check if objective completed
   ↓
4. LOG EVENT
   - Record progress change
   - Generate narrative text
   ↓
5. CHECK PHASE ADVANCEMENT
   - Are all objectives in current phase complete?
   - Advance to next phase if applicable
   ↓
6. CHECK QUEST COMPLETION
   - Are all phases complete?
   - Mark quest as completed
   ↓
7. TRIGGER REWARDS
   - Make rewards available
   - Update campaign state
   - Unlock follow-up quests
   ↓
8. NOTIFY PLAYER
   - Quest update notification
   - Journal entry added
```

### Phase System

Quests can have multiple phases that must be completed sequentially:

```json
{
  "phases": [
    {
      "phase_id": 1,
      "name": "Investigate the Ruins",
      "objectives": [
        {"type": "explore", "location": "ancient_ruins"}
      ]
    },
    {
      "phase_id": 2,
      "name": "Find the Artifact",
      "objectives": [
        {"type": "collect", "item": "ancient_artifact", "target_count": 1}
      ],
      "unlocked_after_phase": 1
    },
    {
      "phase_id": 3,
      "name": "Return the Artifact",
      "objectives": [
        {"type": "deliver", "item": "ancient_artifact", "npc_id": 15}
      ],
      "unlocked_after_phase": 2
    }
  ]
}
```

### Branching System

Quests can branch based on player choices:

```json
{
  "phase_id": 3,
  "name": "Choose Your Path",
  "branching_point": true,
  "branches": [
    {
      "choice_id": "spare_enemy",
      "choice_text": "Spare the enemy leader",
      "next_phase": 4,
      "rewards_modifier": {
        "reputation": {"peaceful_faction": 50}
      }
    },
    {
      "choice_id": "execute_enemy",
      "choice_text": "Execute the enemy leader",
      "next_phase": 5,
      "rewards_modifier": {
        "reputation": {"militant_faction": 50}
      }
    }
  ]
}
```

---

## API Endpoints

### Generate Quest

**Endpoint**: `POST /api/campaign/{campaign_id}/quests/generate`

**Request Body**:
```json
{
  "template_id": "rescue_merchant",
  "context": {
    "party_level": 3,
    "location_id": "town_square",
    "npc_pool": [12, 15, 18],
    "difficulty": "moderate"
  }
}
```

**Response**:
```json
{
  "success": true,
  "quest": {
    "quest_id": "quest_12345",
    "name": "Rescue Merchant Aldric from Goblin Den",
    "description": "...",
    "objectives": [...],
    "rewards": {...},
    "status": "available"
  }
}
```

### Get Available Quests

**Endpoint**: `GET /api/campaign/{campaign_id}/quests/available`

**Query Parameters**:
- `location_id`: Filter by location
- `character_id`: Check prerequisites for character

**Response**:
```json
{
  "success": true,
  "quests": [
    {
      "quest_id": "quest_123",
      "name": "Clear the Goblin Den",
      "quest_type": "bounty",
      "rewards": {...},
      "prerequisites_met": true
    },
    ...
  ]
}
```

### Start Quest

**Endpoint**: `POST /api/campaign/{campaign_id}/quests/{quest_id}/start`

**Request Body**:
```json
{
  "character_id": 42
}
```

**Response**:
```json
{
  "success": true,
  "quest_progress": {
    "quest_id": "quest_123",
    "objective_states": [...],
    "current_phase": 1,
    "started_at": 1708372800
  }
}
```

### Update Objective Progress

**Endpoint**: `PUT /api/campaign/{campaign_id}/quests/{quest_id}/progress`

**Request Body**:
```json
{
  "character_id": 42,
  "objective_id": "kill_goblins",
  "progress": 5,
  "action_context": {
    "location": "goblin_den_room_3",
    "timestamp": 1708372900
  }
}
```

**Response**:
```json
{
  "success": true,
  "quest_progress": {
    "objective_states": [
      {
        "objective_id": "kill_goblins",
        "current": 5,
        "target": 12,
        "completed": false
      }
    ],
    "quest_completed": false,
    "notifications": [
      "Progress: 5/12 Goblins Slain"
    ]
  }
}
```

### Complete Quest

**Endpoint**: `POST /api/campaign/{campaign_id}/quests/{quest_id}/complete`

**Request Body**:
```json
{
  "character_id": 42,
  "outcome": "success"
}
```

**Response**:
```json
{
  "success": true,
  "quest_completion": {
    "quest_id": "quest_123",
    "outcome": "success",
    "rewards": {
      "xp": 400,
      "gold": 25,
      "items": ["potion_of_healing"],
      "reputation": {"city_guard": 50}
    },
    "story_unlocks": ["quest_chain_2_unlocked"],
    "completed_at": 1708373000
  }
}
```

### Claim Rewards

**Endpoint**: `POST /api/campaign/{campaign_id}/quests/{quest_id}/rewards/claim`

**Request Body**:
```json
{
  "character_id": 42
}
```

**Response**:
```json
{
  "success": true,
  "rewards_granted": {
    "xp_granted": 400,
    "gold_granted": 25,
    "items_granted": [
      {
        "item_id": "potion_of_healing",
        "name": "Potion of Healing",
        "added_to_inventory": true
      }
    ],
    "reputation_updated": {
      "city_guard": {"old": 100, "new": 150}
    }
  }
}
```

### Get Quest Journal

**Endpoint**: `GET /api/campaign/{campaign_id}/character/{character_id}/quest-journal`

**Response**:
```json
{
  "success": true,
  "journal": {
    "active_quests": [
      {
        "quest_id": "quest_123",
        "name": "Clear the Goblin Den",
        "progress": "5/12 Goblins Slain",
        "current_phase": 1
      }
    ],
    "completed_quests": [
      {
        "quest_id": "quest_100",
        "name": "Find the Lost Cat",
        "outcome": "success",
        "completed_at": 1708300000
      }
    ],
    "available_quests": [
      {
        "quest_id": "quest_150",
        "name": "Escort the Merchant",
        "location": "town_square"
      }
    ]
  }
}
```

---

## Data Structures

### Quest Template Schema

```json
{
  "template_id": "rescue_merchant",
  "name": "Rescue {npc} from {location}",
  "description": "{npc}, a {npc_role}, was captured by {target}s...",
  "quest_type": "rescue",
  "level_min": 2,
  "level_max": 5,
  "tags": ["combat", "rescue", "time_limited"],
  "objectives_schema": [
    {
      "phase": 1,
      "objectives": [
        {
          "objective_id": "find_location",
          "type": "explore",
          "target": "{location}",
          "description": "Find the {location}"
        },
        {
          "objective_id": "defeat_captors",
          "type": "kill",
          "target": "{target}",
          "target_count_range": [4, 8],
          "description": "Defeat the {target}s holding {npc}"
        },
        {
          "objective_id": "rescue_npc",
          "type": "interact",
          "target": "{npc}",
          "description": "Free {npc} from captivity"
        }
      ]
    },
    {
      "phase": 2,
      "objectives": [
        {
          "objective_id": "escort_to_safety",
          "type": "escort",
          "target": "{npc}",
          "destination": "{safe_location}",
          "description": "Escort {npc} back to safety"
        }
      ]
    }
  ],
  "rewards_schema": {
    "xp": {
      "base": 120,
      "per_level": 40
    },
    "gold": {
      "base": 10,
      "per_level": 5,
      "randomize": true
    },
    "items": {
      "loot_table": "rescue_reward",
      "count": 1
    },
    "reputation": {
      "faction": "{npc_faction}",
      "amount": 25
    }
  },
  "prerequisites": {
    "level_min": 2,
    "completed_quests": [],
    "reputation": []
  },
  "time_limit_hours": 24,
  "failure_conditions": [
    {
      "type": "npc_dies",
      "target": "{npc}"
    },
    {
      "type": "time_expired"
    }
  ]
}
```

### Generated Quest Instance

```json
{
  "quest_id": "quest_12345",
  "campaign_id": 1,
  "source_template_id": "rescue_merchant",
  "quest_name": "Rescue Merchant Aldric from Goblin Den",
  "quest_description": "Merchant Aldric, a traveling merchant, was captured by goblins in the Goblin Den. Find and rescue him before it's too late!",
  "quest_type": "rescue",
  "quest_data": {
    "variables": {
      "npc": "Merchant Aldric",
      "npc_id": 42,
      "npc_role": "traveling merchant",
      "npc_faction": "merchants_guild",
      "target": "goblin",
      "location": "Goblin Den",
      "location_id": "dungeon_12",
      "safe_location": "Town Square"
    },
    "party_level": 3,
    "difficulty": "moderate"
  },
  "generated_objectives": [
    {
      "phase": 1,
      "objectives": [
        {
          "objective_id": "find_location",
          "type": "explore",
          "target": "Goblin Den",
          "target_id": "dungeon_12",
          "description": "Find the Goblin Den",
          "completed": false
        },
        {
          "objective_id": "defeat_captors",
          "type": "kill",
          "target": "goblin",
          "current": 0,
          "target_count": 6,
          "description": "Defeat the goblins holding Merchant Aldric",
          "completed": false
        },
        {
          "objective_id": "rescue_npc",
          "type": "interact",
          "target": "Merchant Aldric",
          "target_id": 42,
          "description": "Free Merchant Aldric from captivity",
          "completed": false
        }
      ]
    },
    {
      "phase": 2,
      "objectives": [
        {
          "objective_id": "escort_to_safety",
          "type": "escort",
          "target": "Merchant Aldric",
          "target_id": 42,
          "destination": "Town Square",
          "destination_id": "town_square",
          "description": "Escort Merchant Aldric back to safety",
          "completed": false
        }
      ]
    }
  ],
  "generated_rewards": {
    "xp": 240,
    "gold": 23,
    "items": [
      {"item_id": "potion_of_healing", "quantity": 1}
    ],
    "reputation": {
      "merchants_guild": 25
    }
  },
  "status": "available",
  "giver_npc_id": 42,
  "location_id": "town_square",
  "created_at": 1708372000,
  "available_at": 1708372000,
  "expires_at": 1708458400
}
```

### Quest Progress State

```json
{
  "campaign_id": 1,
  "quest_id": "quest_12345",
  "character_id": 15,
  "objective_states": [
    {
      "phase": 1,
      "objectives": [
        {
          "objective_id": "find_location",
          "completed": true,
          "completed_at": 1708372500
        },
        {
          "objective_id": "defeat_captors",
          "current": 4,
          "target": 6,
          "completed": false
        },
        {
          "objective_id": "rescue_npc",
          "completed": false
        }
      ]
    },
    {
      "phase": 2,
      "locked": true
    }
  ],
  "current_phase": 1,
  "started_at": 1708372400,
  "last_updated": 1708372800
}
```

---

## Integration Points

### Integration with Existing Systems

#### 1. Combat System Integration
- **Combat Victory** → Update "kill" objectives
- **Combat End** → Check for quest-related NPCs (rescue objectives)
- **Loot Drop** → Update "collect" objectives

#### 2. Room/Dungeon Generator Integration
- **Room Entry** → Check for "explore" objectives
- **Room Generation** → Place quest-specific entities (NPCs, items, enemies)
- **Dungeon Complete** → Check for "clear dungeon" objectives

#### 3. Campaign State Integration
- **Quest Completion** → Update campaign flags
- **Story Unlocks** → Make new quests/locations available
- **Reputation Changes** → Affect NPC interactions

#### 4. Character System Integration
- **XP Awards** → Credit to character XP pool
- **Gold Awards** → Add to character wealth
- **Item Grants** → Add to character inventory
- **Level Up** → Unlock higher-level quest templates

#### 5. Inventory System Integration
- **Item Collection Events** → Update "collect" objectives
- **Item Delivery** → Track "deliver" objectives
- **Crafting Completion** → Update "craft" objectives

### Event Hooks

The quest system emits and listens for events:

**Emitted Events**:
- `quest_generated`
- `quest_started`
- `quest_objective_completed`
- `quest_phase_advanced`
- `quest_completed`
- `quest_failed`
- `quest_rewards_claimed`

**Listened Events**:
- `entity_killed` (combat system)
- `item_collected` (loot system)
- `location_discovered` (exploration system)
- `npc_interaction` (dialogue system)
- `combat_encounter_complete`

---

## Implementation Roadmap

### Phase 1: Core Database Schema & Services (1-2 weeks)

**Tasks**:
1. Create database schema update hook
2. Implement `QuestGeneratorService` (basic template instantiation)
3. Implement `QuestTrackerService` (basic progress tracking)
4. Create repository classes for data access
5. Write unit tests for services

**Deliverables**:
- Database tables created
- Services functional with in-memory test data
- 80%+ test coverage

### Phase 2: Quest Template System (1-2 weeks)

**Tasks**:
1. Create 10-15 quest templates covering major quest types
2. Implement variable resolution system
3. Implement reward scaling algorithms
4. Implement objective generation logic
5. Create template validation system

**Deliverables**:
- Quest template library with diverse quests
- Working variable injection
- PF2e-compliant reward scaling

### Phase 3: API Controllers & Endpoints (1 week)

**Tasks**:
1. Implement REST API controllers
2. Add authentication/authorization
3. Add request validation
4. Implement error handling
5. Write API integration tests

**Deliverables**:
- 8 functional API endpoints
- API documentation
- Postman/Insomnia collection

### Phase 4: Quest Tracking & Runtime Logic (1-2 weeks)

**Tasks**:
1. Implement progress update logic
2. Implement phase advancement system
3. Implement branching logic
4. Create quest log system
5. Implement completion detection

**Deliverables**:
- Full quest lifecycle functional
- Progress persistence working
- Branching quests supported

### Phase 5: Reward System Integration (1 week)

**Tasks**:
1. Implement `QuestRewardService`
2. Integrate with character XP system
3. Integrate with inventory system
4. Implement reputation tracking
5. Implement story unlock system

**Deliverables**:
- Rewards automatically granted
- Integration with existing systems
- Duplicate claim prevention

### Phase 6: Advanced Generation & AI (2-3 weeks)

**Tasks**:
1. Implement context-aware generation
2. Add narrative quality checks
3. Implement quest chain system
4. Add dynamic difficulty adjustment
5. Create quest balancing algorithms

**Deliverables**:
- AI-driven quest generation
- Quest chains functional
- Adaptive difficulty working

### Phase 7: Integration & Polish (1-2 weeks)

**Tasks**:
1. Integrate with combat system
2. Integrate with room/dungeon generator
3. Add event emission/listening
4. Create admin UI for quest management
5. Performance optimization

**Deliverables**:
- Full system integration
- Admin tools functional
- Performance benchmarks met

### Phase 8: Testing & Documentation (1 week)

**Tasks**:
1. End-to-end testing
2. Load testing
3. Documentation completion
4. Tutorial creation
5. Bug fixing

**Deliverables**:
- Comprehensive test suite
- Complete documentation
- Known issues documented

---

## Total Estimated Timeline: 10-14 weeks

### Priority Implementation Order:
1. **Core Schema** (Phase 1) — Foundation
2. **Templates** (Phase 2) — Content
3. **APIs** (Phase 3) — Access layer
4. **Tracking** (Phase 4) — Runtime logic
5. **Rewards** (Phase 5) — Player incentive
6. **Advanced** (Phase 6) — Quality of life
7. **Integration** (Phase 7) — System cohesion
8. **Testing** (Phase 8) — Quality assurance

---

## Dependencies

**Required Existing Systems**:
- `dc_campaigns` table (campaign management)
- `dc_campaign_characters` table (character data)
- Character XP system
- Inventory system
- Loot table system (`dungeoncrawler_content_loot_tables`)
- Content registry system (`dungeoncrawler_content_registry`)

**Required PHP Extensions**:
- PDO (database access)
- JSON (data serialization)

**Drupal Services**:
- Database API
- Entity API
- State API
- Event Dispatcher

---

## Notes

- Follow existing **Library → Campaign → Runtime** pattern
- All quest data should be immutable once generated (copy-on-write)
- Quest progress should be resumable after session interruptions
- Support both individual and party quest tracking
- Ensure PF2e XP awards are balanced for encounter difficulty
- Quest generation should be deterministic given the same seed
- Consider quest cooldowns to prevent spam generation
- Add quest quality ratings after completion for tuning templates

---

**End of Quest Tracker & Generator Architecture v1.0.0**
