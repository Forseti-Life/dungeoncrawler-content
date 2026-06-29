# XP Subsystem Architecture Plan

## Purpose

Create one clear, deterministic XP subsystem for Dungeoncrawler. XP must be generated from quest/encounter accomplishment value, applied only by the server, recorded in a durable ledger, and visible to the UI as server state.

## Authority rule

Only `XpGrantService` may mutate character XP. Existing direct writes from quest completion and the browser-facing character XP endpoint must be removed from active gameplay flow or converted into internal/admin wrappers around the grant service.

## Core services

| Service | Responsibility |
|---|---|
| `XpPolicyService` | Owns XP tables, accomplishment categories, caps, and validation rules. |
| `QuestXpPlannerService` | Converts quest objective plans into pending XP awards with rationale. |
| `EncounterXpCalculatorService` | Calculates encounter XP from adversary/hazard level relative to party level. |
| `PartyXpRecipientResolver` | Resolves which active PCs receive XP for a quest/encounter completion. |
| `XpGrantService` | Transactionally applies XP, writes grant ledger rows, updates character hot columns and JSON mirrors, and emits events. |
| `LegacyRewardMigrationService` | Converts old `generated_rewards` XP into `generated_rewards_v2` only when needed. |

## XP sources

| Source | XP model |
|---|---|
| Minor accomplishment | 10 XP for a small but meaningful task, clue, delivery, or conversation beat. |
| Moderate accomplishment | 30 XP for resolving a scene objective, investigation step, social challenge, or standard generated quest objective. |
| Major accomplishment | 80 XP for a major milestone, multi-session arc, major faction state change, or campaign-significant result. |
| Adversary encounter | Table-driven by creature level relative to party level. |
| Hazard | Table-driven by hazard level/type relative to party level; do not infer from narrative text alone. |
| Quest completion | Either a completion award or objective awards, not both unless rationale proves they are distinct accomplishments. |

## Encounter XP table baseline

Use PF2e-style level-relative XP values as data, not scattered conditionals:

| Challenge level vs party level | XP |
|---|---:|
| party level - 4 | 10 |
| party level - 3 | 15 |
| party level - 2 | 20 |
| party level - 1 | 30 |
| party level | 40 |
| party level + 1 | 60 |
| party level + 2 | 80 |
| party level + 3 | 120 |
| party level + 4 | 160 |

Out-of-range challenges require an explicit policy decision; do not extrapolate silently.

## Generated quest XP planning

Quest generation must produce pending XP awards before the quest is offered.

Each award should include:

```json
{
  "award_id": "xp_return_books",
  "source_type": "quest_objective",
  "source_id": "return_books",
  "category": "moderate_accomplishment",
  "xp": 30,
  "recipient_policy": "active_party",
  "grant_timing": "objective_complete",
  "rationale": "Returning the spellbooks resolves the scholar's requested scene objective.",
  "farm_guard": {
    "repeatable": false,
    "novelty_key": "campaign:110:quest:collect_spellbooks:return_books"
  }
}
```

## Generated quest XP caps

Caps are guardrails, not formulas:

| Quest shape | Normal XP envelope |
|---|---:|
| Single-step trivial/flavor | 0-10 |
| Single-scene task | 10-30 |
| Standard multi-objective generated quest | 30-80 |
| Multi-phase quest with real risk or multiple scenes | 80-120 |
| Major story/faction arc | 80+ with explicit CEO/PM-authored rationale |

If generated awards exceed the envelope, generation must include a rationale and QA should flag it for review.

## Recipient policy

Default XP recipient policy is `active_party`.

Rules:

- Award the same XP amount to each eligible active PC; do not divide XP by party size.
- Solo campaigns are just a party of one.
- Inactive bench characters do not receive XP unless a campaign-level setting explicitly enables it.
- NPC companions do not receive PC XP unless promoted to a PC-controlled character.
- Deceased/incapacitated PCs remain eligible only if they were active participants in the completed quest/encounter.

## Ledger schema

Create a durable XP grant ledger. Proposed table: `dc_campaign_xp_grants`.

| Field | Purpose |
|---|---|
| `id` | Primary key. |
| `campaign_id` | Campaign scope. |
| `party_id` | Party scope when known. |
| `quest_id` | Quest source when applicable. |
| `encounter_id` | Encounter source when applicable. |
| `award_id` | Stable generated award id. |
| `recipient_character_id` | Character receiving XP. |
| `source_type` | quest_objective, quest_completion, encounter, hazard, manual_adjustment, migration. |
| `source_id` | Objective id, encounter participant set hash, hazard id, or migration id. |
| `xp_amount` | Positive or explicit adjustment amount. |
| `policy_version` | XP policy version used for calculation. |
| `source_hash` | Hash of award payload used for idempotency. |
| `status` | pending, granted, skipped, reversed, failed. |
| `granted_at` | Timestamp. |
| `metadata_json` | Rationale, challenge details, recipient snapshot, and migration notes. |

Unique key: `(campaign_id, recipient_character_id, source_type, source_id, award_id, source_hash)`.

## Character XP mutation contract

`XpGrantService` must update both:

- `dc_campaign_characters.experience_points`
- `state_data.basicInfo.experiencePoints` or the canonical JSON mirror used by the character panel

The service must return the pre-grant XP, post-grant XP, current level, next-level threshold, and whether level-up is available. `CharacterLevelingService` remains the owner of actual level-up choices.

## Grant lifecycle

1. Quest/encounter generation creates pending XP awards.
2. Completion event calls `XpGrantService::grantAwards()`.
3. Service opens a transaction.
4. Service resolves recipients from the recipient policy.
5. Service checks ledger idempotency keys.
6. Service writes `pending` ledger rows or marks duplicates `skipped`.
7. Service mutates character XP for new grants.
8. Service marks ledger rows `granted`.
9. Service emits reward/XP events for UI refresh.
10. Completion response returns granted and skipped XP details.

Any failure must fail the reward grant visibly. No swallow-and-log behavior.

## API/UI contract

Quest and encounter completion responses should include:

```json
{
  "xp": {
    "policy_version": "xp-v2-pf2e-lite",
    "pending_awards": [],
    "grants": [
      {
        "award_id": "xp_return_books",
        "recipient_character_id": 429,
        "xp_amount": 30,
        "status": "granted",
        "pre_xp": 73,
        "post_xp": 103
      }
    ],
    "skipped": []
  }
}
```

The UI may display this response but must not calculate or apply XP.

## Legacy boundaries

- `QuestTrackerService::completeQuest()` should call reward orchestration, not update XP itself.
- `/api/character/{characterId}/experience` should not be used by quest completion or normal browser gameplay.
- Old `generated_rewards.xp` is accepted only by migration code and converted into `generated_rewards_v2.xp_awards`.
- Replayed legacy completions must inspect both prior quest completion state and XP grant ledger state before granting.

## Required tests

- Minor/moderate/major accomplishment awards produce 10/30/80 XP.
- Encounter XP uses the level-relative table and rejects out-of-range values without policy.
- Generated quest XP includes rationale and stable award IDs.
- Active party recipients each receive full XP, not split XP.
- Solo PC receives XP exactly once.
- Duplicate grant calls create no duplicate character XP.
- Character hot column and JSON mirror stay synchronized.
- Legacy generated reward migration preserves old XP value once and never double-grants.
- Browser quest completion has no client-side XP POST fallback.
