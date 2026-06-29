# CEO Work Request — Dungeoncrawler Reward System Redesign

- CEO: ceo-copilot-2
- Work item: dc-reward-system-redesign
- Topic: quest-rewards-xp-treasure
- Priority: P1
- ROI: 36

## Summary

Design and implement a clear, server-authoritative reward system for generated and authored Dungeoncrawler quests. The system should replace the current split reward paths with a single XP, treasure, item, reputation, and story-unlock pipeline based loosely on current code but aligned with PF2e-style reward tradeoffs.

## What to do

1. Use the active session plan as the canonical planning source.
2. Convert the plan into PM/BA/Dev/QA work slices with explicit contracts.
3. Standardize generated quest rewards around objective-based XP and party-level treasure budgets.
4. Make reward application transactional, idempotent, and server-authoritative.
5. Remove or disable client-side XP/reward math once the server pipeline is authoritative.

## Key gaps

- `QuestTrackerService::completeQuest()` currently applies XP directly from legacy `generated_rewards`.
- `QuestRewardService` exposes a claim-style API, but gold/items/reputation/story unlock application is incomplete.
- Browser code can attempt reward claims and separate XP POSTs, creating split authority.
- Generated quest XP uses a simple formula instead of objective/accomplishment/encounter composition.
- Treasure is generated as reward data but is not managed against a party-level budget ledger.
- There is no durable reward grant ledger for all reward types.

## Source plan

- Session plan: `/root/.copilot/session-state/710463d2-5411-4a2d-a3b7-2093ff417cbc/plan.md`
