# Dungeoncrawler service-boundary validation

- Owner: ceo-copilot-2
- Status: validated against current codebase
- Scope: codex/social planning reality check

## Result

The planning work is **substantially grounded in the live Dungeoncrawler codebase**. The major service and storage seams assumed by the codex/social plan do exist, and the intended boundaries are directionally correct.

This moves the workstream from "good conceptual planning" to "validated planning with remaining architecture and QA signoff needed."

## Validated current seams

### 1. `RelationshipManagerService` is a real bridge seam, but not the final canonical model

Validated files:

- `src/Service/RelationshipManagerService.php`
- `dungeoncrawler_content.services.yml`
- `dungeoncrawler_content.install`

Confirmed facts:

- registered service: `dungeoncrawler_content.relationship_manager`
- current storage:
  - `dungeoncrawler_content_relationships` = library-default relationship templates
  - `dc_campaign_relationships` = campaign runtime relationship graph
- runtime rows currently use:
  - `source_type`
  - `source_id`
  - `target_type`
  - `target_id`
  - `relationship_type`
  - `attitude`
  - `status`
  - JSON `relationship_state`
- service behavior is currently storyline/contact-heavy:
  - seeds library defaults
  - seeds storyline contacts
  - builds tavern/storyline contact summaries

Assessment:

- **Good bridge:** yes
- **Good final codex/social authority:** no

Why:

The service already owns the closest thing to reusable relationship persistence, but it is still keyed around generic type/id pairs and storyline contact flows rather than canonical codex record ids and social-state semantics.

### 2. `NpcService` is a strong authority seam for campaign NPC runtime records

Validated files:

- `src/Service/NpcService.php`
- `dungeoncrawler_content.install`

Confirmed facts:

- `dc_npc` exists as the campaign NPC catalog
- `dc_npc_history` exists as audit/history storage
- `NpcService` owns:
  - campaign NPC CRUD
  - campaign access validation
  - attitude changes via `applySocialCheck()`
  - NPC history logging
  - AI-prompt-friendly NPC context
- NPC rows already have stable-ish runtime identity material via `entity_ref`
- NPCs already carry lore and dialogue text that fit codex seeding/mapping

Assessment:

- **Good NPC runtime authority:** yes
- **Good complete social authority:** no

Why:

`NpcService` is a clean source for campaign NPC identity and authored lore/dialogue, but its current social model is still only `attitude` plus `dc_npc_history`, not full trust/loyalty/reputation state.

### 3. `CampaignStateService` is real and should stay a cache/snapshot layer

Validated files:

- `src/Service/CampaignStateService.php`
- `dungeoncrawler_content.services.yml`
- `dungeoncrawler_content.install`

Confirmed facts:

- registered service: `dungeoncrawler_content.campaign_state_service`
- reads and writes `dc_campaigns.campaign_data`
- stores `state` inside campaign JSON with optimistic versioning
- already used by `RelationshipManagerService` for cached storyline contact summaries

Assessment:

- **Good runtime snapshot/cache seam:** yes
- **Good canonical source of truth for codex/social records:** no

Why:

This exactly matches the planning rule: keep canonical world/social records in dedicated storage and allow campaign state to hold projections, summaries, or active-view caches.

### 4. `ChatSessionManager` is clearly separate and should remain separate

Validated files:

- `src/Service/ChatSessionManager.php`
- `dungeoncrawler_content.services.yml`
- `dungeoncrawler_content.install`

Confirmed facts:

- registered service: `dungeoncrawler_content.chat_session_manager`
- manages hierarchical session keys and `dc_chat_sessions`
- owns campaign/dungeon/room/party/whisper/spell/gm_private/system_log/encounter session structure
- persists message/session hierarchy, not canonical world-state or social-state records

Assessment:

- **Good narrative/history seam:** yes
- **Good codex/social source of truth:** no

Why:

The current implementation strongly supports the planning posture that chat memory remains separate from codex/social truth and may link to it, not replace it.

## Database-design reality check

### Strong evidence the DB plan is directionally correct

- existing runtime already distinguishes:
  - library defaults vs campaign runtime relationships
  - campaign NPC records vs history/audit
  - campaign JSON snapshot state vs relational feature tables
  - chat hierarchy vs NPC/runtime state

This means the proposed codex/social table families are **not alien to the codebase's current design style**.

### Main DB risk that still remains

The current relationship graph uses generic `source_type/source_id/target_type/target_id`, while the codex/social plans want canonical runtime subject ids and codex record ids.

That is solvable, but it is the main migration/cutover challenge.

## Framework-design reality check

The framework posture is good:

- codex-first sequencing is correct
- social-on-top-of-codex identity is correct
- `RelationshipManagerService` should be treated as a bridge/generalization candidate
- `NpcService` should remain the NPC-specific authority
- `CampaignStateService` should remain projection/cache only
- `ChatSessionManager` should stay separate

## Updated readiness assessment

### What is now effectively validated

- handoff contracts: **strong**
- framework posture: **strong**
- DB direction: **strong conceptually and supported by current code patterns**

### What still blocks "ready to build"

1. explicit architecture call on whether `RelationshipManagerService` is generalized, wrapped, or partially retired
2. cutover/migration design from current type/id pairs to codex record ids and runtime subject ids
3. QA confirmation that the slice-1 contracts are testable as written

## Bottom line

We are no longer just doing speculative planning. The core plan is **validated by the live codebase**, but there are still **architecture and migration decisions** between "validated planning" and "implementation-ready."
