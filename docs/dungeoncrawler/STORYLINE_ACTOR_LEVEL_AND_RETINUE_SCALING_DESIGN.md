# Storyline Actor Level and Retinue Scaling: Target Architecture & Design Plan

**Status:** Pinned Future Enhancement (Reference Design)  
**Scope:** Storyline Builder authoring contracts, Canonical Storyline Definitions, Campaign Attachment, and Storyline-to-Campaign Realization  
**Originating Case:** Storyline Builder draft `ff3f1920-6586-49e9-96e6-49b467bba08d` (`torment-and-legacy-u1-d64e33f1`, revision 6 — *"Gnash should be level X based on the highest character level in the party with X other goblins making up the difference if the party is larger"*)  
**Architecture Policy:** Single canonical path for system logic (no competing encounter/level calculators); hard-fail on contract violations.

---

## 1. Executive Summary & Problem Statement

Storylines are authored as reusable canonical templates (`storyline-authoring-draft-v1` -> `storyline-definition-v1`) before the specific campaign party (`dc_campaign_characters` where `type = 'pc'`) is known, or while a campaign's party composition and character levels are still evolving.

Currently, storyline actors (`/objects/actors/generation_specs` in drafts and `contacts[]` / `asset_references[]` in canonical templates) have **no authored scaling rules**, and `StorylineRealizationService::buildStorylineNpcSpecs()` assigns static integer levels derived solely from the storyline's fixed `metadata.level_range` (`min`, `mid`, `max`). Furthermore, `StorylineRealizationService::realizeStorylineNpcs()` only materializes 1:1 named actors (`npc_<entity_ref>`) and cannot spawn a variable number of companion/retinue/rabble actors (`X other goblins`) to balance the encounter when the party is larger or higher level.

This document defines the target architecture, JSON schema contracts, PF2E budget math, and exact codebase integration seams so this capability can be implemented cleanly as part of the core storyline mechanic.

---

## 2. Where This Lives in the Repository (Codebase Seam Map)

To obey the single-path architecture rule, **all math must reuse `CharacterManager`, `EncounterGenerationRules`, and `Pf2eGenerationRules`**, and **all storyline realization must flow through one dedicated resolver (`StorylineScalingResolverService`)** invoked by the existing storyline authorities:

1. **Authoring Draft Schema (`config/schemas/storyline_authoring_draft.schema.json`)**
   - Seam: `$defs/actor` (`/objects/actors/generation_specs/*`) and `$defs/conflict` (`/process_flow/conflicts/*`).
   - Role: Declares optional `level_scaling` and `retinue_scaling` objects on authored storyline actors.
2. **Canonical Storyline Definition Schema (`config/schemas/storyline_definition.schema.json`)**
   - Seam: `contacts[].relationship_state.runtime_materialization` and `asset_references[].link_data`.
   - Role: Persists validated scaling contracts in published `storyline-definition-v1` templates.
3. **Draft-to-Template Projection (`src/Service/StorylineAuthoringDraftService.php`)**
   - Seam: `buildTemplateDefinitionFromDraftPayload()` (lines ~1875–1935) and `hydrateDraftRowsFromCanonicalTemplate()` (lines ~2000+).
   - Role: Projects `level_scaling` and `retinue_scaling` losslessly between `storyline-authoring-draft-v1` and `storyline-definition-v1`.
4. **Storyline Scaling Authority — New Service (`src/Service/StorylineScalingResolverService.php`)**
   - Seam: Single canonical authority injected into `StorylineRealizationService` and `StorylineCampaignAttachmentService`.
   - Role: Queries active party PCs (`dc_campaign_characters` with `type = 'pc'`), resolves party metrics (`party_size`, `highest_party_level`, `average_party_level`, `lowest_party_level`, `total_party_hd`), resolves target actor level, and calculates deterministic retinue/rabble counts (`X` companions) using `CharacterManager::computeCreatureXp()` and `EncounterGenerationRules::xpBudget()`.
5. **Storyline Actor Realization (`src/Service/StorylineRealizationService.php`)**
   - Seam: `buildStorylineNpcSpecs()` (line ~920) and `realizeStorylineNpcs()` (line ~326).
   - Role: Replaces hardcoded `$level_bounds['min' | 'max']` with `StorylineScalingResolverService::resolveActorLevel()`, scales base stats via `Pf2eGenerationRules::baselineCreaturePf2eStats()`, passes the resolved level to `NpcSheetGenerationService::enqueueNpcSheetGeneration()`, and spawns deterministic retinue instances (`npc_<leader_ref>_retinue_1..N`) into the leader's `canonical_location_id`.
6. **Campaign Attachment & Lazy Scene/Room Activation (`src/Service/StorylineCampaignAttachmentService.php` & `src/Service/NavigationRuntimeService.php`)**
   - Seam: `StorylineCampaignAttachmentService::plan()` / `apply()` (line ~835) and room/scene entry in `NavigationRuntimeService`.
   - Role: Materializes initial scaled actors on attachment and re-evaluates **unengaged** scaled actors (`scaling_locked = false`) when the party composition or PC levels change prior to first encounter/room entry.

---

## 3. Data Model & JSON Schema Contracts

### 3.1. `level_scaling` Contract (on `$defs/actor`)

Added as an optional property on `$defs/actor` in `config/schemas/storyline_authoring_draft.schema.json`:

```json
"level_scaling": {
  "type": "object",
  "additionalProperties": false,
  "required": ["mode"],
  "properties": {
    "mode": {
      "type": "string",
      "enum": [
        "fixed",
        "storyline_min",
        "storyline_mid",
        "storyline_max",
        "highest_party_level",
        "average_party_level",
        "lowest_party_level"
      ]
    },
    "fixed_level": {
      "type": "integer",
      "minimum": -1,
      "maximum": 25
    },
    "level_offset": {
      "type": "integer",
      "minimum": -4,
      "maximum": 4,
      "default": 0
    },
    "min_level": {
      "type": "integer",
      "minimum": -1,
      "maximum": 25
    },
    "max_level": {
      "type": "integer",
      "minimum": -1,
      "maximum": 25
    },
    "lock_on": {
      "type": "string",
      "enum": ["campaign_attachment", "scene_activation", "room_entry"],
      "default": "room_entry"
    }
  }
}
```

### 3.2. `retinue_scaling` Contract (on `$defs/actor`)

Supports cases like *"1 named goblin (Gnash) + X other goblins making up the difference if the party is larger"*:

```json
"retinue_scaling": {
  "type": "object",
  "additionalProperties": false,
  "required": [
    "mode",
    "companion_definition_id"
  ],
  "properties": {
    "mode": {
      "type": "string",
      "enum": [
        "none",
        "pf2e_xp_budget_remainder",
        "party_hd_difference",
        "per_extra_party_member"
      ]
    },
    "companion_definition_id": {
      "type": "string",
      "description": "Canonical creature or actor definition ID for spawned companions (e.g. goblin-warrior)."
    },
    "companion_display_name": {
      "type": "string"
    },
    "threat_tier": {
      "type": "string",
      "enum": ["trivial", "low", "moderate", "severe", "extreme"],
      "default": "moderate"
    },
    "companion_level_mode": {
      "type": "string",
      "enum": ["fixed", "leader_offset", "party_offset"],
      "default": "leader_offset"
    },
    "companion_level_offset": {
      "type": "integer",
      "minimum": -4,
      "maximum": 2,
      "default": -2
    },
    "companion_fixed_level": {
      "type": "integer",
      "minimum": -1,
      "maximum": 25
    },
    "baseline_party_size": {
      "type": "integer",
      "minimum": 1,
      "maximum": 8,
      "default": 1
    },
    "min_count": {
      "type": "integer",
      "minimum": 0,
      "maximum": 12,
      "default": 0
    },
    "max_count": {
      "type": "integer",
      "minimum": 0,
      "maximum": 12,
      "default": 8
    }
  }
}
```

### 3.3. Example Authored Payload for Gnash (`ff3f1920-6586-49e9-96e6-49b467bba08d`)

```json
{
  "contact_id": "contact-gnash",
  "actor_id": "gnash-the-goblin",
  "role": "antagonist",
  "display_name": "Gnash",
  "attitude": "hostile",
  "availability": "available",
  "chapter_id": "first-engagement",
  "scene_id": "tal-goblin-camp-assault",
  "location_id": "north-woods-goblin-camp",
  "level_scaling": {
    "mode": "highest_party_level",
    "level_offset": 0,
    "min_level": 1,
    "max_level": 10,
    "lock_on": "room_entry"
  },
  "retinue_scaling": {
    "mode": "pf2e_xp_budget_remainder",
    "companion_definition_id": "goblin-warrior",
    "companion_display_name": "North Woods Goblin Rabble",
    "threat_tier": "moderate",
    "companion_level_mode": "leader_offset",
    "companion_level_offset": -2,
    "baseline_party_size": 1,
    "min_count": 0,
    "max_count": 8
  }
}
```

---

## 4. Deterministic Resolution Algorithms (`StorylineScalingResolverService`)

### 4.1. Party Snapshot Resolution

Given `$campaign_id`, query `dc_campaign_characters` where `campaign_id = :cid AND type = 'pc'`:

- If 0 PCs are attached yet (e.g., storyline attached during pre-party campaign setup), fall back to storyline `level_range` minimum and `party_size = 1`, and mark `scaling_locked = false` so resolution runs when PCs join/enter.
- Otherwise compute:
  - `party_size = count($pcs)`
  - `highest_party_level = max($pc_levels)`
  - `lowest_party_level = min($pc_levels)`
  - `average_party_level = (int) round(array_sum($pc_levels) / $party_size)`
  - `total_party_hd = array_sum($pc_levels)` (in PF2E, character level equals Hit Dice).

### 4.2. Actor Target Level Calculation

```text
base_level = match(level_scaling.mode):
  'fixed'               => level_scaling.fixed_level
  'highest_party_level' => party.highest_party_level
  'average_party_level' => party.average_party_level
  'lowest_party_level'  => party.lowest_party_level
  'storyline_min'       => storyline.level_bounds.min
  'storyline_mid'       => storyline.level_bounds.mid
  'storyline_max'       => storyline.level_bounds.max

resolved_level = clamp(
  base_level + (level_scaling.level_offset ?? 0),
  level_scaling.min_level ?? -1,
  level_scaling.max_level ?? 20
)
```

### 4.3. Retinue / Rabble Count (`X`) Calculation

Supports three deterministic modes using existing `CharacterManager` & `EncounterGenerationRules` helpers:

1. **`pf2e_xp_budget_remainder` (Recommended PF2E Canonical Mode)**
   - Uses `EncounterGenerationRules::xpBudget($party.average_party_level, $party.party_size, $threat_tier)['target_xp']` (which applies `CharacterManager::adjustBudgetForPartySize()` at `±20 XP` per PC from 4).
   - Computes leader XP cost relative to `$party.average_party_level` via `CharacterManager::computeCreatureXp($leader_level, $party.average_party_level)`.
   - Computes single companion level (`$companion_level`) and XP cost (`$companion_xp = CharacterManager::computeCreatureXp($companion_level, $party.average_party_level)`).
   - `remaining_xp = max(0, $target_xp - $leader_xp)`
   - `count = clamp((int) floor($remaining_xp / max(10, $companion_xp)), $min_count, $max_count)`
   - *Example (Gnash)*:
     - Solo Level 2 PC (`party_size = 1`, `highest_party_level = 2`): Moderate budget for 1 PC = `80 - (3 * 20) = 20 XP` (or Low/Moderate solo match = 40 XP). Gnash is Level 2 (`40 XP`). Remainder = `0 XP` -> **0 extra goblins** (1v1 duel with Gnash).
     - 4-PC Party (Levels 2, 2, 2, 2): Moderate budget = `80 XP`. Gnash is Level 2 (`40 XP`). Remainder = `40 XP`. Level 0 Goblin Rabble (`party_level - 2`) costs `20 XP` each -> **2 Goblin Rabble** spawned alongside Gnash (`40 + 2*20 = 80 XP`).
     - 4-PC Party with one Level 3 PC (Levels 3, 2, 2, 2; `highest = 3`, `avg = 2`): Severe budget = `120 XP`. Gnash is Level 3 (`60 XP` vs avg 2). Remainder = `60 XP`. Level 1 Goblin Rabble (`leader - 2 = 1`, `30 XP` vs avg 2) -> **2 Goblin Rabble**.

2. **`party_hd_difference` (Direct Hit-Dice / Total Level Pool Mode)**
   - Matches classic tabletop HD pool phrasing (*"Gnash is level X = highest PC level, with X other goblins making up the HD difference"*):
   - `remaining_hd = max(0, $party.total_party_hd - $leader_level)`
   - `companion_hd = max(1, $companion_level)`
   - `count = clamp((int) floor($remaining_hd / $companion_hd), $min_count, $max_count)`
   - *Example*:
     - Solo Level 3 PC (`total_party_hd = 3`): Gnash = Level 3. `remaining_hd = 3 - 3 = 0` -> **0 extra goblins**.
     - Three Level 3 PCs (`total_party_hd = 9`): Gnash = Level 3. `remaining_hd = 9 - 3 = 6`. With 1-HD goblins -> **6 extra goblins** (or with 2-HD goblins -> **3 extra goblins**).

3. **`per_extra_party_member`**
   - `extra_pcs = max(0, $party.party_size - $baseline_party_size)`
   - `count = clamp($extra_pcs * $companions_per_pc, $min_count, $max_count)`

---

## 5. Lifecycle, Locking & Edge Cases ("Why It Gets Complicated")

When implementing this enhancement, the following edge cases must be handled explicitly:

1. **Attachment Time vs. Room Entry Time (`lock_on`)**
   - A storyline is often attached to a campaign when characters are Level 1, or before all players have joined the campaign, while a mid-storyline conflict (like the North Woods Goblin Camp) might not be reached until the party is Level 3.
   - **Rule**: Store `state_data.storyline_scaling` on the `dc_campaign_characters` NPC row with `scaling_locked: false` at campaign attachment time (`StorylineCampaignAttachmentService::apply()`).
   - When the scene activates or a PC enters the room (`NavigationRuntimeService` / room state transition from `initial`), if `scaling_locked === false` and the actor has not taken damage or entered combat, re-run `StorylineScalingResolverService::syncCampaignStorylineActorScaling($campaign_id, $storyline_id, $room_id)` and set `scaling_locked = true`.

2. **Idempotent Retinue Spawning & Cleanup**
   - Retinue instances must use deterministic instance IDs: `npc_<leader_entity_ref>_retinue_<1..N>`.
   - If party size shrinks before `scaling_locked = true`, excess unengaged retinue rows (`index > N`) must be cleanly removed rather than orphaned.
   - Once combat begins or any member of the encounter takes damage/interacts, `scaling_locked` flips to `true` for the leader and all retinue members.

3. **Canonical Creature / Actor Validation**
   - `companion_definition_id` must reference a valid published canonical `creature` or `actor` definition in `dungeoncrawler_content_definitions` (validated during `StorylineAuthoringDraftService::validateDraft()`).
   - Per org-wide rules, if `companion_definition_id` does not exist at realization time, hard-fail with a structured finding (`storyline_retinue_companion_definition_missing`) rather than silently inventing a fallback stub.

4. **Hex Placement Capacity in Target Room**
   - Spawned retinue actors (`1..N`) must be placed on passable, unoccupied hexes in the leader's `location_ref` room (`EntityPlacerService` / canonical room `layout_data.hexes`).
   - `max_count` must be bounded by available passable hexes in the target room so a 16-hex room is never overfilled.

5. **Editor GM Tooling & UI Surface**
   - `EditorGmIntentParser` and `StorylineAuthoringDraftService` collection contracts (`/objects/actors/generation_specs`) will automatically accept `level_scaling` and `retinue_scaling` once added to `storyline_authoring_draft.schema.json`.
   - The Storyline Builder UI (`js/storyline-builder.js`) Actor table/drawer should expose optional "Level Scaling" (`Fixed / Storyline Range / Highest PC / Average PC`) and "Retinue / Minion Scaling" controls.

---

## 6. Phased Implementation Checklist (For Future Activation)

- [ ] **Phase 1: Schema & Draft Projection**
  - Add `$defs/level_scaling` and `$defs/retinue_scaling` to `config/schemas/storyline_authoring_draft.schema.json` and `config/schemas/storyline_definition.schema.json`.
  - Project both blocks in `StorylineAuthoringDraftService::buildTemplateDefinitionFromDraftPayload()` and `hydrateDraftRowsFromCanonicalTemplate()`.
- [ ] **Phase 2: `StorylineScalingResolverService`**
  - Implement party snapshot lookup (`dc_campaign_characters`), level resolution, and `pf2e_xp_budget_remainder` / `party_hd_difference` / `per_extra_party_member` retinue count math delegating to `CharacterManager` and `EncounterGenerationRules`.
- [ ] **Phase 3: Realization & Lazy Room-Entry Locking**
  - Wire `StorylineScalingResolverService` into `StorylineRealizationService::buildStorylineNpcSpecs()` and `realizeStorylineNpcs()`.
  - Trigger lazy re-sync on room/scene entry before locking (`scaling_locked = true`).
- [ ] **Phase 4: Storyline Builder UI & Contract Tests**
  - Add Actor scaling controls to `js/storyline-builder.js` and add isolated contract tests covering 1-PC vs 4-PC level/retinue realization.
