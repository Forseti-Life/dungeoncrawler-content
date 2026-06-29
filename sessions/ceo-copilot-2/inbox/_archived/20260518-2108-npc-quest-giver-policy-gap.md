Issue: NPC quest-giver policy gap - no system-wide allowlist for who may issue which quests

Priority: High
Product: DungeonCrawler

Summary:
DungeonCrawler currently has **partial** NPC quest-giver controls, but not a deterministic system-wide policy. Storyline contacts do tie a specific broker / quest giver to a specific storyline, and brokered activation respects that storyline's current questline position. However, there is **no canonical NPC allowlist contract** that says an NPC may only issue specific storyline IDs or quest template IDs across the whole game.

Evidence:
- `RelationshipManagerService::seedStorylineContacts()` seeds storyline-scoped `broker` / `quest_giver` contacts into runtime relationships.
- `RelationshipManagerService::buildCampaignStorylineContacts()` surfaces storyline-specific broker and quest-giver pairings back into campaign state.
- `RoomChatService::ensureBrokeredStorylineQuestRows()` activates quest templates only from the contacted storyline's current chapter / scene via `getStorylineQuestTemplateIdsForActivation()`.
- But there is **no first-class contract field** like `allowed_storyline_ids` or `allowed_quest_template_ids` on NPCs / contacts.
- Standalone and generated quests still rely on runtime context such as `giver_npc_id`, which acts as an assignment/reference, not an enforced authorization rule.

Why this matters:
- NPC narrative authority is not deterministic enough: a giver can be attached to a quest without a canonical permission model.
- Future quest-generation and discovery work can drift if giver assignment remains context-driven instead of policy-driven.
- The system cannot yet answer or enforce "which quests/storylines is this NPC allowed to offer?" as a formal rule.

Recommended follow-up:
1. Define a canonical **NPC quest-giver contract** with explicit `allowed_storyline_ids` and `allowed_quest_template_ids` (or equivalent).
2. Decide ownership: whether this policy lives on NPC definitions, storyline contacts, or a dedicated relationship/registry layer.
3. Enforce the policy in:
   - brokered storyline activation
   - standalone/location quest generation
   - available-quest listing / discovery surfaces
4. Add audit coverage to fail when an NPC is assigned as a giver for a quest/storyline outside its allowed set.
