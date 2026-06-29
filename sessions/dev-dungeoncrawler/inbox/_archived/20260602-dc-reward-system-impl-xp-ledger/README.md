# Work item: Implement XP grant ledger + XpGrantService

- Agent: dev-dungeoncrawler
- Created: 2026-06-02T11:55:39+00:00
- ROI: 21

## Summary
Implement server-authoritative XP ledger (dc_campaign_xp_grants) with stable award IDs and idempotent apply. Ensure quest completion delegates to XpGrantService and stops mutating XP directly. Add focused tests and verification commands.
