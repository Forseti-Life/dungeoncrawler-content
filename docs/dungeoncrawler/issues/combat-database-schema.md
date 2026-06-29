# Combat Database Schema

**Part of**: [Issue #4: Combat & Encounter System Design](./issue-4-combat-encounter-system-design.md)  
**Status**: Design Document  
**Last Updated**: 2026-02-12

## Overview

This document defines the database schema for the combat encounter system, including tables for active encounters, combat participants, actions, conditions, and comprehensive combat logging. The schema is designed for performance, data integrity, and comprehensive audit trails.

## Design Principles

1. **Normalization**: Minimize data redundancy while maintaining query performance
2. **Indexing**: Strategic indexes for common query patterns
3. **Audit Trail**: Complete logging of all combat events
4. **Real-time**: Support for concurrent access and live updates
5. **Scalability**: Handle multiple concurrent combats efficiently

## Entity Relationship Diagram

```
┌──────────────────┐
│    campaigns     │
└────────┬─────────┘
         │
         │ 1:N
         ▼
┌──────────────────────────┐
│  combat_encounters       │◄───────────┐
│  (active_encounters)     │            │
└────────┬─────────────────┘            │
         │                              │
         │ 1:N                          │
         ▼                              │
┌──────────────────────────┐            │
│  combat_participants     │            │
└────┬──────────────┬──────┘            │
     │              │                   │
     │ 1:N          │ 1:N               │
     ▼              ▼                   │
┌────────────┐  ┌─────────────────┐    │
│  combat_   │  │  combat_        │    │
│  conditions│  │  actions        │────┘
└────────────┘  └────────┬────────┘
                         │
                         │ 1:N
                         ▼
                ┌──────────────────┐
                │  combat_         │
                │  reactions       │
                └──────────────────┘

┌──────────────────┐
│  characters      │
└────────┬─────────┘
         │
         │ 1:N
         └──────────────► combat_participants

┌──────────────────┐
│  game_monsters   │
└────────┬─────────┘
         │
         │ 1:N
         └──────────────► combat_participants
```

## Core Tables

### 1. combat_encounters (Active Encounters)

Primary table for managing combat encounters.

```sql
CREATE TABLE combat_encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    
    -- Encounter Metadata
    encounter_name VARCHAR(255) NOT NULL,
    encounter_type ENUM('combat', 'social', 'exploration', 'hazard') DEFAULT 'combat',
    difficulty ENUM('trivial', 'low', 'moderate', 'severe', 'extreme', 'custom') NOT NULL,
    challenge_rating DECIMAL(3,1) NULL, -- Calculated CR
    
    -- Combat State
    status ENUM('setup', 'rolling_initiative', 'initiative_set', 'active', 'paused', 'concluded', 'archived') DEFAULT 'setup',
    current_round TINYINT UNSIGNED DEFAULT 0,
    current_turn_participant_id BIGINT UNSIGNED NULL,
    current_turn_actions_remaining TINYINT UNSIGNED DEFAULT 3,
    
    -- Timing
    started_at TIMESTAMP NULL,
    paused_at TIMESTAMP NULL,
    resumed_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    total_duration_seconds INT UNSIGNED NULL, -- Excludes pause time
    
    -- Outcome
    outcome ENUM('victory', 'defeat', 'retreat', 'truce', 'other', 'ongoing') NULL,
    victory_condition VARCHAR(255) NULL,
    
    -- Rewards
    total_xp_awarded INT UNSIGNED DEFAULT 0,
    treasure_awarded JSON NULL, -- Array of item IDs
    
    -- Combat Settings
    use_grid BOOLEAN DEFAULT TRUE,
    grid_size TINYINT UNSIGNED DEFAULT 5, -- feet per square
    theater_of_mind BOOLEAN DEFAULT FALSE,
    house_rules JSON NULL,
    
    -- Notes
    encounter_notes TEXT NULL,
    gm_notes TEXT NULL,
    
    -- Metadata
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (current_turn_participant_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    
    -- Indexes
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_active (status) WHERE status IN ('active', 'paused')
);
```

**Notes**:
- `current_turn_participant_id`: References which participant's turn it currently is
- `status`: Tracks combat state (see state machine document)
- `house_rules`: JSON for custom combat rules specific to this encounter
- Active encounters can be queried efficiently with `status IN ('active', 'paused')`

---

### 2. combat_participants

Tracks all participants (PCs, NPCs, monsters) in a combat encounter.

```sql
CREATE TABLE combat_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    
    -- Participant Identity
    participant_type ENUM('character', 'monster', 'npc', 'hazard', 'summon') NOT NULL,
    character_id BIGINT UNSIGNED NULL, -- If PC
    monster_id INT UNSIGNED NULL, -- If monster (references game_monsters)
    display_name VARCHAR(100) NOT NULL, -- Display name (e.g., "Goblin Warrior 1")
    
    -- Team/Allegiance
    team ENUM('pc', 'enemy', 'neutral', 'ally') NOT NULL,
    is_hidden BOOLEAN DEFAULT FALSE, -- Hidden from players
    
    -- Initiative
    initiative_roll TINYINT UNSIGNED NOT NULL,
    initiative_modifier TINYINT NOT NULL,
    initiative_total TINYINT NOT NULL,
    initiative_tiebreaker TINYINT UNSIGNED DEFAULT 50, -- For equal initiative (0-99)
    
    -- Hit Points
    max_hp INT UNSIGNED NOT NULL,
    current_hp INT NOT NULL, -- Can be negative
    temp_hp INT UNSIGNED DEFAULT 0,
    damage_taken INT UNSIGNED DEFAULT 0, -- Total damage tracked
    healing_received INT UNSIGNED DEFAULT 0, -- Total healing tracked
    
    -- Action Economy
    actions_remaining TINYINT UNSIGNED DEFAULT 3,
    reaction_available BOOLEAN DEFAULT TRUE,
    has_taken_turn_this_round BOOLEAN DEFAULT FALSE,
    
    -- Attack Tracking
    attacks_this_turn TINYINT UNSIGNED DEFAULT 0,
    current_map_penalty TINYINT DEFAULT 0,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE, -- Still in combat
    is_conscious BOOLEAN DEFAULT TRUE,
    is_defeated BOOLEAN DEFAULT FALSE,
    defeat_reason ENUM('hp_zero', 'fled', 'surrendered', 'removed') NULL,
    
    -- Positioning (for grid combat)
    position_x INT NULL,
    position_y INT NULL,
    facing_direction ENUM('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW') NULL,
    elevation INT DEFAULT 0, -- For vertical positioning (feet)
    
    -- Delayed/Readied Actions
    is_delaying BOOLEAN DEFAULT FALSE,
    delayed_at_initiative INT NULL,
    is_readying BOOLEAN DEFAULT FALSE,
    readied_action JSON NULL, -- {action_type, trigger, description}
    
    -- Participant Stats Snapshot (at combat start)
    stats_snapshot JSON NOT NULL, -- Full stats at combat start
    
    -- Metadata
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    defeated_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (monster_id) REFERENCES game_monsters(id),
    
    -- Indexes
    INDEX idx_encounter_initiative (combat_encounter_id, initiative_total DESC, initiative_tiebreaker DESC),
    INDEX idx_encounter_team (combat_encounter_id, team),
    INDEX idx_encounter_active (combat_encounter_id, is_active),
    INDEX idx_character (character_id),
    INDEX idx_position (combat_encounter_id, position_x, position_y),
    
    -- Constraints
    CHECK (current_hp >= -max_hp), -- Can't go below -max_hp (massive damage)
    CHECK ((character_id IS NOT NULL AND monster_id IS NULL) OR 
           (character_id IS NULL AND monster_id IS NOT NULL) OR
           (character_id IS NULL AND monster_id IS NULL)) -- One or none
);
```

**Notes**:
- `stats_snapshot`: Stores full participant stats at combat start (AC, saves, modifiers, etc.) to handle mid-combat stat changes
- `initiative_tiebreaker`: Random value 0-99 for breaking exact initiative ties
- `position_x/y`: Grid coordinates (null if theater-of-mind)
- `readied_action`: JSON storing readied action details

---

### 3. combat_conditions

Tracks active conditions affecting participants.

```sql
CREATE TABLE combat_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_participant_id BIGINT UNSIGNED NOT NULL,
    combat_encounter_id BIGINT UNSIGNED NOT NULL, -- Denormalized for faster queries
    
    -- Condition Details
    condition_type ENUM(
        -- Senses
        'blinded', 'dazzled', 'deafened',
        -- Physical
        'broken', 'clumsy', 'enfeebled', 'fatigued', 'grabbed', 'immobilized',
        'paralyzed', 'petrified', 'prone', 'restrained', 'slowed', 'stunned',
        -- Mental
        'confused', 'controlled', 'fascinated', 'frightened', 'stupefied',
        -- Health
        'doomed', 'drained', 'dying', 'sickened', 'wounded', 'unconscious',
        -- Visibility
        'concealed', 'hidden', 'invisible', 'observed', 'undetected', 'unnoticed',
        -- Attitude
        'friendly', 'helpful', 'hostile', 'indifferent', 'unfriendly',
        -- Special
        'flat_footed', 'fleeing', 'persistent_damage', 'quickened'
    ) NOT NULL,
    
    -- Valued Conditions
    condition_value TINYINT UNSIGNED NULL, -- For valued conditions (frightened 2, clumsy 3, etc.)
    
    -- Duration
    duration_type ENUM('rounds', 'turns', 'minutes', 'hours', 'unlimited', 'sustained', 'special') DEFAULT 'rounds',
    duration_remaining INT UNSIGNED NULL, -- Rounds/turns/etc remaining (NULL = unlimited)
    expires_at_initiative TINYINT UNSIGNED NULL, -- For turn-based conditions
    
    -- Source
    source_type ENUM('spell', 'ability', 'item', 'environment', 'manual') NOT NULL,
    source_description VARCHAR(255) NOT NULL, -- "Goblin Warrior's Demoralize", "Fireball spell"
    source_participant_id BIGINT UNSIGNED NULL, -- Who/what caused it
    
    -- Persistent Damage (if applicable)
    persistent_damage_type VARCHAR(50) NULL, -- 'fire', 'acid', 'bleed', etc.
    persistent_damage_dice VARCHAR(50) NULL, -- '2d6', '1d8', etc.
    
    -- Condition Metadata
    can_be_removed BOOLEAN DEFAULT TRUE,
    is_suppressed BOOLEAN DEFAULT FALSE, -- Temporarily suppressed but not removed
    suppress_reason VARCHAR(255) NULL,
    
    -- Tracking
    applied_at_round TINYINT UNSIGNED NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (combat_participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (source_participant_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_participant (combat_participant_id),
    INDEX idx_encounter_round (combat_encounter_id, applied_at_round),
    INDEX idx_type (condition_type),
    INDEX idx_active (combat_participant_id, removed_at) WHERE removed_at IS NULL,
    INDEX idx_duration (duration_type, duration_remaining)
);
```

**Notes**:
- `condition_value`: For conditions like frightened 2, clumsy 3, dying 1, etc.
- `expires_at_initiative`: For conditions that last "until start of X's turn"
- `is_suppressed`: Some abilities temporarily suppress conditions without removing them
- `persistent_damage_dice`: Stores the damage dice for persistent damage conditions

---

### 4. combat_actions (Combat Log)

Comprehensive logging of all combat actions.

```sql
CREATE TABLE combat_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    turn_sequence SMALLINT UNSIGNED NOT NULL, -- Overall action sequence in combat
    
    -- Actor
    participant_id BIGINT UNSIGNED NOT NULL,
    participant_name VARCHAR(100) NOT NULL, -- Denormalized for readability
    
    -- Action Details
    action_type ENUM(
        -- Movement
        'stride', 'step', 'leap', 'crawl', 'climb', 'swim', 'fly', 'burrow',
        -- Basic Actions
        'strike', 'interact', 'seek', 'raise_shield', 'take_cover', 'delay',
        -- Skill Actions
        'demoralize', 'feint', 'aid', 'escape', 'grapple', 'shove', 'trip',
        'disarm', 'force_open', 'hide', 'sneak', 'administer_first_aid',
        'treat_wounds', 'create_diversion',
        -- Magic
        'cast_spell', 'sustain_spell', 'dismiss_spell',
        -- Combat Maneuvers
        'ready', 'total_defense', 'fight_defensively',
        -- Items
        'activate_item', 'drink_potion', 'draw_weapon', 'reload',
        -- Special
        'class_ability', 'feat_ability', 'end_turn', 'other'
    ) NOT NULL,
    
    action_name VARCHAR(100) NULL, -- Specific action name (spell name, ability name, etc.)
    actions_spent TINYINT UNSIGNED NOT NULL, -- 0=free, 1=single, 2=two-action, 3=three-action
    
    -- Target(s)
    target_id BIGINT UNSIGNED NULL, -- Primary target
    target_name VARCHAR(100) NULL,
    additional_targets JSON NULL, -- Array of {id, name} for multi-target actions
    
    -- Attack Details (if Strike or attack)
    is_attack BOOLEAN DEFAULT FALSE,
    attack_roll TINYINT NULL, -- d20 result (1-20)
    attack_bonus TINYINT NULL, -- Total bonus applied
    attack_total TINYINT NULL, -- Roll + bonus
    target_ac TINYINT UNSIGNED NULL,
    degree_of_success ENUM('critical_success', 'success', 'failure', 'critical_failure') NULL,
    map_penalty_applied TINYINT NULL, -- MAP penalty at time of attack
    
    -- Damage Details (if applicable)
    damage_roll VARCHAR(100) NULL, -- "2d8+4" or detailed roll breakdown
    damage_dealt INT UNSIGNED NULL, -- Final damage after resistances
    damage_type VARCHAR(50) NULL, -- 'slashing', 'fire', 'bludgeoning', etc.
    is_critical_hit BOOLEAN DEFAULT FALSE,
    
    -- Healing (if applicable)
    healing_amount INT UNSIGNED NULL,
    healing_type VARCHAR(50) NULL, -- 'positive', 'regeneration', etc.
    
    -- Movement (if applicable)
    distance_moved SMALLINT UNSIGNED NULL, -- Feet
    from_position_x INT NULL,
    from_position_y INT NULL,
    to_position_x INT NULL,
    to_position_y INT NULL,
    
    -- Spell Details (if cast_spell)
    spell_id INT UNSIGNED NULL,
    spell_level TINYINT UNSIGNED NULL,
    spell_slot_expended BOOLEAN DEFAULT FALSE,
    
    -- Skill Check (if skill action)
    skill_used VARCHAR(50) NULL,
    skill_roll TINYINT NULL,
    skill_total TINYINT NULL,
    skill_dc TINYINT UNSIGNED NULL,
    
    -- Conditions Applied/Removed
    conditions_applied JSON NULL, -- Array of condition IDs applied by this action
    conditions_removed JSON NULL, -- Array of condition IDs removed by this action
    
    -- Action Results
    action_succeeded BOOLEAN NULL,
    action_result_description TEXT NULL,
    
    -- Metadata
    action_data JSON NULL, -- Flexible storage for action-specific data
    triggered_reactions JSON NULL, -- Array of reaction IDs this action triggered
    
    -- Timestamp
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (spell_id) REFERENCES spells(id),
    
    -- Indexes
    INDEX idx_encounter_round (combat_encounter_id, round_number),
    INDEX idx_encounter_sequence (combat_encounter_id, turn_sequence),
    INDEX idx_participant (participant_id),
    INDEX idx_target (target_id),
    INDEX idx_action_type (action_type),
    INDEX idx_timestamp (action_timestamp),
    
    -- Full-text search on descriptions
    FULLTEXT INDEX ft_action_description (action_result_description)
);
```

**Notes**:
- Complete audit trail of all combat actions
- Denormalized participant/target names for readability
- Flexible `action_data` JSON for action-specific details
- Supports comprehensive combat analytics and replay

---

### 5. combat_reactions

Separate table for reactions (which can occur during others' turns).

```sql
CREATE TABLE combat_reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    
    -- Reactor
    participant_id BIGINT UNSIGNED NOT NULL,
    participant_name VARCHAR(100) NOT NULL,
    
    -- Reaction Details
    reaction_type ENUM(
        'attack_of_opportunity', 'shield_block', 'retributive_strike',
        'nimble_dodge', 'dueling_riposte', 'combat_reflexes',
        'aid', 'readied_action', 'feather_fall', 'counterspell',
        'immediate_action', 'other'
    ) NOT NULL,
    
    reaction_name VARCHAR(100) NULL, -- Specific reaction name
    
    -- Trigger
    trigger_action_id BIGINT UNSIGNED NULL, -- Action that triggered this reaction
    trigger_description TEXT NOT NULL,
    triggered_by_participant_id BIGINT UNSIGNED NULL,
    
    -- Target (if applicable)
    target_id BIGINT UNSIGNED NULL,
    target_name VARCHAR(100) NULL,
    
    -- Attack (if reaction involves attack)
    attack_roll TINYINT NULL,
    attack_total TINYINT NULL,
    target_ac TINYINT UNSIGNED NULL,
    degree_of_success ENUM('critical_success', 'success', 'failure', 'critical_failure') NULL,
    
    -- Damage/Effects
    damage_dealt INT UNSIGNED NULL,
    damage_type VARCHAR(50) NULL,
    damage_prevented INT UNSIGNED NULL, -- For Shield Block, etc.
    
    -- Shield Block Specifics
    shield_hardness TINYINT UNSIGNED NULL,
    shield_damage_taken TINYINT UNSIGNED NULL,
    shield_broke BOOLEAN DEFAULT FALSE,
    
    -- Results
    reaction_succeeded BOOLEAN NULL,
    reaction_result_description TEXT NULL,
    
    -- Metadata
    reaction_data JSON NULL,
    reaction_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (trigger_action_id) REFERENCES combat_actions(id) ON DELETE SET NULL,
    FOREIGN KEY (triggered_by_participant_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (target_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_encounter_round (combat_encounter_id, round_number),
    INDEX idx_participant (participant_id),
    INDEX idx_trigger_action (trigger_action_id),
    INDEX idx_reaction_type (reaction_type)
);
```

---

### 6. combat_effects

Tracks temporary buffs, debuffs, and ongoing effects.

```sql
CREATE TABLE combat_effects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_participant_id BIGINT UNSIGNED NOT NULL,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    
    -- Effect Details
    effect_name VARCHAR(100) NOT NULL,
    effect_type ENUM('buff', 'debuff', 'neutral', 'mixed') NOT NULL,
    effect_source ENUM('spell', 'ability', 'item', 'environment', 'feat') NOT NULL,
    
    -- Modifiers
    modifiers JSON NOT NULL, /* Array of:
        {
            "stat": "attack",
            "modifier_type": "status|circumstance|item|untyped",
            "value": 2,
            "applies_to": "all|melee|ranged|specific"
        }
    */
    
    -- Duration
    duration_rounds INT UNSIGNED NULL,
    duration_type ENUM('rounds', 'turns', 'minutes', 'encounter', 'sustained') DEFAULT 'rounds',
    expires_at_round TINYINT UNSIGNED NULL,
    
    -- Source
    source_description VARCHAR(255) NOT NULL,
    source_participant_id BIGINT UNSIGNED NULL,
    source_action_id BIGINT UNSIGNED NULL,
    
    -- Concentration/Sustain
    requires_concentration BOOLEAN DEFAULT FALSE,
    requires_sustain BOOLEAN DEFAULT FALSE,
    sustained_this_turn BOOLEAN DEFAULT FALSE,
    
    -- Stacking
    stacks_with_same BOOLEAN DEFAULT FALSE,
    
    -- Tracking
    applied_at_round TINYINT UNSIGNED NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (combat_participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (source_participant_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (source_action_id) REFERENCES combat_actions(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_participant_active (combat_participant_id, removed_at) WHERE removed_at IS NULL,
    INDEX idx_encounter (combat_encounter_id),
    INDEX idx_expires (expires_at_round)
);
```

---

### 7. combat_damage_log

Detailed damage tracking for analytics.

```sql
CREATE TABLE combat_damage_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    action_id BIGINT UNSIGNED NULL, -- Related combat action
    
    -- Source
    source_participant_id BIGINT UNSIGNED NOT NULL,
    source_name VARCHAR(100) NOT NULL,
    
    -- Target
    target_participant_id BIGINT UNSIGNED NOT NULL,
    target_name VARCHAR(100) NOT NULL,
    
    -- Damage Details
    damage_type VARCHAR(50) NOT NULL,
    base_damage INT UNSIGNED NOT NULL,
    damage_before_resistance INT UNSIGNED NOT NULL,
    resistance_applied INT UNSIGNED DEFAULT 0,
    weakness_applied INT UNSIGNED DEFAULT 0,
    final_damage INT UNSIGNED NOT NULL,
    
    -- Special
    is_critical BOOLEAN DEFAULT FALSE,
    is_persistent BOOLEAN DEFAULT FALSE,
    is_splash BOOLEAN DEFAULT FALSE,
    is_precision BOOLEAN DEFAULT FALSE,
    
    -- HP Impact
    target_hp_before INT NOT NULL,
    target_hp_after INT NOT NULL,
    temp_hp_absorbed INT UNSIGNED DEFAULT 0,
    
    -- Timestamp
    damage_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES combat_actions(id) ON DELETE SET NULL,
    FOREIGN KEY (source_participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (target_participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_encounter_round (combat_encounter_id, round_number),
    INDEX idx_source (source_participant_id),
    INDEX idx_target (target_participant_id),
    INDEX idx_damage_type (damage_type)
);
```

---

### 8. combat_chat_log

In-game chat and narration during combat.

```sql
CREATE TABLE combat_chat_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NULL,
    
    -- Message Details
    message_type ENUM('action', 'narration', 'dice_roll', 'system', 'chat', 'gm_note') NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    sender_name VARCHAR(100) NULL,
    
    -- Content
    message_text TEXT NOT NULL,
    message_data JSON NULL, -- Structured data (dice rolls, action details)
    
    -- Visibility
    is_public BOOLEAN DEFAULT TRUE,
    visible_to_gm_only BOOLEAN DEFAULT FALSE,
    visible_to_users JSON NULL, -- Array of user IDs who can see this
    
    -- Related Entities
    related_action_id BIGINT UNSIGNED NULL,
    related_participant_id BIGINT UNSIGNED NULL,
    
    -- Timestamp
    message_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_action_id) REFERENCES combat_actions(id) ON DELETE SET NULL,
    FOREIGN KEY (related_participant_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_encounter_timestamp (combat_encounter_id, message_timestamp),
    INDEX idx_encounter_round (combat_encounter_id, round_number),
    INDEX idx_public (combat_encounter_id, is_public)
);
```

---

## Supporting Tables

### 9. encounter_state_history

Tracks state machine transitions for debugging.

```sql
CREATE TABLE encounter_state_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id BIGINT UNSIGNED NOT NULL,
    previous_state VARCHAR(50),
    new_state VARCHAR(50) NOT NULL,
    transition_reason VARCHAR(255),
    transition_data JSON,
    transitioned_by BIGINT UNSIGNED,
    transitioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (transitioned_by) REFERENCES users(id),
    INDEX idx_encounter (encounter_id),
    INDEX idx_timestamp (transitioned_at)
);
```

---

## Views for Common Queries

### Active Combat View

```sql
CREATE VIEW active_combats AS
SELECT 
    ce.id AS encounter_id,
    ce.encounter_name,
    ce.campaign_id,
    c.name AS campaign_name,
    ce.status,
    ce.current_round,
    cp.display_name AS current_turn_name,
    COUNT(DISTINCT CASE WHEN cpart.team = 'pc' THEN cpart.id END) AS pc_count,
    COUNT(DISTINCT CASE WHEN cpart.team = 'enemy' THEN cpart.id END) AS enemy_count,
    ce.started_at,
    TIMESTAMPDIFF(MINUTE, ce.started_at, NOW()) AS duration_minutes
FROM combat_encounters ce
JOIN campaigns c ON ce.campaign_id = c.id
LEFT JOIN combat_participants cp ON ce.current_turn_participant_id = cp.id
LEFT JOIN combat_participants cpart ON ce.id = cpart.combat_encounter_id
WHERE ce.status IN ('active', 'paused')
GROUP BY ce.id;
```

### Combat Statistics View

```sql
CREATE VIEW combat_statistics AS
SELECT
    ce.id AS encounter_id,
    ce.encounter_name,
    ce.current_round AS rounds_elapsed,
    COUNT(DISTINCT ca.id) AS total_actions,
    COUNT(DISTINCT CASE WHEN ca.is_attack THEN ca.id END) AS total_attacks,
    COUNT(DISTINCT CASE WHEN ca.is_attack AND ca.degree_of_success IN ('success', 'critical_success') THEN ca.id END) AS successful_attacks,
    SUM(ca.damage_dealt) AS total_damage_dealt,
    SUM(ca.healing_amount) AS total_healing,
    COUNT(DISTINCT cr.id) AS total_reactions,
    COUNT(DISTINCT cc.id) AS active_conditions
FROM combat_encounters ce
LEFT JOIN combat_actions ca ON ce.id = ca.combat_encounter_id
LEFT JOIN combat_reactions cr ON ce.id = cr.combat_encounter_id
LEFT JOIN combat_conditions cc ON ce.id = cc.combat_encounter_id AND cc.removed_at IS NULL
GROUP BY ce.id;
```

---

## Indexes Strategy

### Primary Performance Indexes

```sql
-- Fast initiative order lookup
CREATE INDEX idx_initiative_order ON combat_participants(
    combat_encounter_id, 
    initiative_total DESC, 
    initiative_tiebreaker DESC
) WHERE is_active = TRUE;

-- Active conditions lookup
CREATE INDEX idx_active_conditions ON combat_conditions(
    combat_participant_id,
    condition_type
) WHERE removed_at IS NULL;

-- Recent actions in current round
CREATE INDEX idx_current_round_actions ON combat_actions(
    combat_encounter_id,
    round_number,
    turn_sequence DESC
);

-- Damage analytics
CREATE INDEX idx_damage_analytics ON combat_damage_log(
    combat_encounter_id,
    damage_type,
    round_number
);
```

---

## Data Retention and Archival

### Archival Strategy

```sql
-- Move concluded combats to archive after 30 days
CREATE EVENT archive_old_combats
ON SCHEDULE EVERY 1 DAY
DO
    UPDATE combat_encounters
    SET status = 'archived'
    WHERE status = 'concluded'
    AND ended_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Partition combat_actions by month for performance
ALTER TABLE combat_actions
PARTITION BY RANGE (YEAR(action_timestamp) * 100 + MONTH(action_timestamp)) (
    PARTITION p202601 VALUES LESS THAN (202602),
    PARTITION p202602 VALUES LESS THAN (202603),
    PARTITION p202603 VALUES LESS THAN (202604)
    -- Add partitions monthly
);
```

---

## Query Examples

### 1. Get Current Turn Info

```sql
SELECT 
    ce.id,
    ce.encounter_name,
    ce.current_round,
    cp.display_name AS current_turn_participant,
    cp.actions_remaining,
    cp.reaction_available,
    cp.current_hp,
    cp.max_hp
FROM combat_encounters ce
JOIN combat_participants cp ON ce.current_turn_participant_id = cp.id
WHERE ce.id = ?;
```

### 2. Get Initiative Order

```sql
SELECT
    cp.id,
    cp.display_name,
    cp.team,
    cp.initiative_total,
    cp.current_hp,
    cp.max_hp,
    cp.is_active,
    cp.has_taken_turn_this_round,
    COUNT(cc.id) AS active_conditions_count
FROM combat_participants cp
LEFT JOIN combat_conditions cc ON cp.id = cc.combat_participant_id 
    AND cc.removed_at IS NULL
WHERE cp.combat_encounter_id = ?
    AND cp.is_active = TRUE
GROUP BY cp.id
ORDER BY cp.initiative_total DESC, cp.initiative_tiebreaker DESC;
```

### 3. Get Participant Conditions

```sql
SELECT
    cc.id,
    cc.condition_type,
    cc.condition_value,
    cc.duration_type,
    cc.duration_remaining,
    cc.source_description,
    sp.display_name AS source_participant_name
FROM combat_conditions cc
LEFT JOIN combat_participants sp ON cc.source_participant_id = sp.id
WHERE cc.combat_participant_id = ?
    AND cc.removed_at IS NULL
ORDER BY cc.applied_at;
```

### 4. Get Combat Log for Round

```sql
SELECT
    ca.turn_sequence,
    ca.participant_name,
    ca.action_type,
    ca.action_name,
    ca.actions_spent,
    ca.target_name,
    ca.attack_total,
    ca.target_ac,
    ca.degree_of_success,
    ca.damage_dealt,
    ca.action_result_description,
    ca.action_timestamp
FROM combat_actions ca
WHERE ca.combat_encounter_id = ?
    AND ca.round_number = ?
ORDER BY ca.turn_sequence;
```

---

## Performance Considerations

### Caching Strategy

```python
# Redis cache keys
cache_patterns = {
    # Active encounter state (30 second TTL)
    "encounter:{id}:state": "30s",
    
    # Initiative order (5 minute TTL, invalidate on change)
    "encounter:{id}:initiative": "5m",
    
    # Participant stats (1 minute TTL)
    "participant:{id}:stats": "1m",
    
    # Active conditions (30 second TTL)
    "participant:{id}:conditions": "30s",
    
    # Combat log (1 hour TTL)
    "encounter:{id}:log:round:{round}": "1h"
}
```

### Connection Pooling

```python
# Database connection pool configuration
database_config = {
    "pool_size": 20,
    "max_overflow": 10,
    "pool_timeout": 30,
    "pool_recycle": 3600,
    "pool_pre_ping": True
}
```

---

## Backup and Recovery

### Point-in-Time Recovery

```sql
-- Backup encounter state before each turn
CREATE TABLE combat_state_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    turn_sequence SMALLINT UNSIGNED NOT NULL,
    state_data JSON NOT NULL,
    snapshot_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    INDEX idx_encounter_round (encounter_id, round_number, turn_sequence DESC)
);
```

---

## Security Considerations

1. **Row-Level Security**: Ensure users can only access combats in their campaigns
2. **Input Validation**: All numeric values must be within valid PF2e ranges
3. **Audit Logging**: All state changes logged with user attribution
4. **Rate Limiting**: Prevent action spam (max 10 actions per second per user)
5. **Data Encryption**: Encrypt GM notes and private combat data

---

## Summary

This database schema provides:

- ✅ Complete combat state tracking
- ✅ Comprehensive action logging
- ✅ Real-time combat management
- ✅ Detailed analytics capabilities
- ✅ Scalable and performant design
- ✅ Complete audit trail
- ✅ Support for all PF2e combat rules

**Related Documents**:
- [Combat State Machine](./combat-state-machine.md)
- [Combat Engine Service](./combat-engine-service.md)
- [Combat API Endpoints](./combat-api-endpoints.md)
