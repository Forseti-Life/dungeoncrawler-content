- Status: in_progress
- Summary: Harden Dungeoncrawler room conversation and quest orchestration by standardizing canonical room session identity, deterministic room-turn routing, and explicit quest lead/offer/active contracts so tavern onboarding no longer forks session history, resets active scenes, or auto-activates multiple quests from rumor dialogue.

# Dungeoncrawler conversation + quest framework hardening

## Why this is in the CEO inbox

Campaign 94 tavern review surfaced cross-cutting product/runtime defects that span:

- room chat orchestration
- normalized chat session persistence
- hexmap quest summary payloads
- quest lifecycle semantics
- quest progress ingestion

This is CEO-owned because the fix crosses framework boundaries and requires one
authoritative contract, not piecemeal local patches.

## Website

- `dungeoncrawler`

## Priority / ROI

- ROI: 1000
- Rationale: This is a root-cause framework issue in the primary onboarding tavern flow. It affects brand-new campaigns, room-chat coherence, quest activation semantics, and the stability of the player-facing progression model.

## Problem statement

The current runtime still permits:

1. the same logical tavern room to split across multiple `dc_chat_sessions` chains because room-session identity is keyed by partially normalized dungeon scope
2. GM fallback to reset a live conversation with scene-opening narration instead of continuing the active room exchange
3. NPC fanout to broaden from one directed or transactional player line into multiple tavern NPC responses
4. quest lead dialogue to auto-activate too many quests instead of separating:
   - lead discovered
   - offer presented
   - quest accepted
   - objective progress

## Finalized quest state contract

Use these exact steady states for `dc_campaign_quests.status`:

- `lead`
- `offered`
- `active`
- `ready_for_turn_in`
- `completed`
- `failed`
- `expired`
- `rejected`

Rules:

- `lead` = informational only, no progress row, not touchpoint-eligible
- `offered` = accept/reject capable, no progress row, not touchpoint-eligible
- `active` = accepted and in progress, progress row required, touchpoint-eligible
- `ready_for_turn_in` = work complete, closure pending, progress row required
- `completed|failed|expired|rejected` = closed states
- `accepted` is a transition event, not a long-lived quest status
- `available` is not the canonical runtime/client contract going forward

## Finalized quest summary contract

Replace the ambiguous `quest-summary-v1` active/available shape with `quest-summary-v2`:

```json
{
  "schema_version": "quest-summary-v2",
  "location_id": "tavern_entrance",
  "active": [ActiveQuestEntry],
  "offers": [QuestOfferEntry],
  "leads": [QuestLeadEntry],
  "counts": {
    "active": 1,
    "offers": 2,
    "leads": 3
  }
}
```

Rules:

- `active` entries include mutable `objective_states`
- `offers` and `leads` do not expose mutable progress state
- `dc_campaign_quest_progress` rows exist only for `active` and later progressed states

## Scope

### 1. Canonical room/session identity

- Normalize both canonical dungeon scope and canonical room id before room-session creation
- Stop live history from splitting between onboarding and raw map-id session chains
- Update all room/session writers to use the same canonical session identity

### 2. Deterministic room conversation routing

- Add explicit room conversation state
- Route each player line into one authoritative route family
- Suppress unrelated NPC fanout for directed and transactional turns
- Prevent GM fallback scene resets in an already active room conversation

### 3. Quest lifecycle split

- Rumor/lead chatter creates `lead`
- Concrete ask/job presentation creates `offered`
- Explicit acceptance transitions quest to `active`
- Only `active` / `ready_for_turn_in` quests are eligible for quest touchpoints

### 4. Typed touchpoint receipts

- Feed `QuestTouchpointService` from deterministic room/runtime receipts where possible
- Keep text-only matching as secondary and confirmation-safe

## Primary files/components expected to change

- `dungeoncrawler-content/src/Service/RoomChatService.php`
- `dungeoncrawler-content/src/Service/ChatSessionManager.php`
- `dungeoncrawler-content/src/Service/AiSessionManager.php`
- `dungeoncrawler-content/src/Service/GmOrchestrationBrokerService.php`
- `dungeoncrawler-content/src/Service/QuestTrackerService.php`
- `dungeoncrawler-content/src/Service/QuestGeneratorService.php`
- `dungeoncrawler-content/src/Service/QuestTouchpointService.php`
- `dungeoncrawler-content/src/Service/QuestConfirmationService.php`
- `dungeoncrawler-content/src/Controller/QuestTrackerController.php`
- `dungeoncrawler-content/src/Controller/HexMapController.php`
- `dungeoncrawler-content/js/hexmap.js`
- `dungeoncrawler-content/config/schemas/quest_summary.schema.json`

## Acceptance criteria

1. A brand-new tavern campaign creates one canonical room session chain and does not continue writing to raw map-id sibling room sessions.
2. Directed follow-up prompts stay in the active conversation and do not emit fresh scene-opening fallback narration.
3. Merchant/payment turns do not trigger unrelated tavern NPC rumor fanout.
4. Tavern rumor dialogue can create multiple leads/offers without auto-activating multiple quests.
5. Only `active` / `ready_for_turn_in` quests receive touchpoint-driven progress mutation.
6. Hexmap quest payload uses `quest-summary-v2` with explicit `active`, `offers`, and `leads` buckets.

## Verification

- targeted PHPUnit coverage for:
  - room session canonicalization
  - room-chat NPC routing
  - quest lifecycle transitions
  - touchpoint eligibility
  - quest summary schema validation
- runtime verification on a fresh tavern campaign:
  - one room session chain
  - no scene reset on follow-up prompts
  - scoped merchant/direct NPC responses
  - leads/offers separated from active quests

## Constraints

- Do not preserve permanent multi-format live session-key behavior.
- Do not use narration text as canonical quest state.
- Do not reintroduce exploration-mode behavior into the current room runtime path.

## Next execution order

1. Canonical room session identity
2. Explicit room conversation state + route hardening
3. Quest lifecycle/status contract migration
4. Typed touchpoint receipt integration
5. Schema/docs/tests/runtime validation

---
- Agent: ceo-copilot-2
