# Command

- created_at: 2026-06-01T14:30:28+00:00
- work_item: dc-reward-system-redesign
- topic: quest-rewards-xp-treasure
- requester: Board
- owner: ceo-copilot-2

## Command text

Plan and execute a Dungeoncrawler reward-system redesign. Quest completion rewards must be clear, deterministic, and server-authoritative. XP and treasure generation should use PF2e-style accomplishment, encounter, hazard, and treasure-budget guidance rather than the current arbitrary generated quest formula.

## Required outcomes

- One authoritative server-side reward application path.
- Generated rewards v2 schema for XP awards, treasure parcels, recipient policy, budget ledger refs, grant status, and rationale.
- Quest XP generated from objective/accomplishment composition and actual encounter/hazard content.
- Treasure generated from a party-level budget ledger.
- Reward grants recorded idempotently with durable ledger entries.
- UI reads pending/granted rewards from server state only.
- Legacy generated rewards remain migration fallback only.
- Regression tests cover generation, completion, idempotency, recipient policies, and legacy migration.
