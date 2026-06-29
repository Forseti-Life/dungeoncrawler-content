# Status

- status: paused
- created_at: 2026-06-01T14:30:28+00:00
- current_phase: planning saved for later continuation

## Notes

Planning initiated after live quest-completion QA exposed reward-system ambiguity. Current code has split reward authority: quest completion applies XP directly, while a separate reward claim service/API exists but does not fully apply treasure, items, reputation, or story unlocks.

## Planning decisions

- Use PF2e-style XP categories as the baseline: minor, moderate, major accomplishments plus encounter/hazard XP from actual challenge contents.
- Use a party-level treasure budget ledger for generated quest treasure.
- Make the server the only authority for reward generation, grant application, and duplicate prevention.
- Treat existing `generated_rewards` formula as a legacy fallback, not the future source of truth.
- XP authority rule: only the new `XpGrantService` may mutate character XP; quest completion and browser endpoints must delegate to it or leave active gameplay flow.
- XP awards will use stable award IDs, rationale, recipient policy, farm guards, and a durable `dc_campaign_xp_grants` ledger.
- XP grants must update both character hot columns and canonical character JSON mirrors.

## Next step

Dispatch PM/BA/Dev/QA slices for XP schema, service contracts, generation policy, grant idempotency, UI/API removal of client-side XP math, migration, and tests.

## Saved artifact

Planning has been parked for later continuation at:

`/root/.copilot/session-state/710463d2-5411-4a2d-a3b7-2093ff417cbc/files/reward-system-redesign/20260601-dc-reward-system-redesign-planning.tar.gz`
