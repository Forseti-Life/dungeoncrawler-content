Now let me check CraftingService and the remaining methods:

I have a clear picture. One gap: **AC-003** — retraining feats should take `7 × feat_level` days but current code uses flat 7. All other ACs are covered. Let me fix that:

Now lint-check and clear cache:

Now write the outbox:

---

- Status: done
- Summary: Audited all AC items for dc-cr-downtime-mode. AC-001 (time scale), AC-002 (Earn Income), AC-004 (Crafting + Advanced Alchemy), and AC-005 (Subsist/Treat Disease/Run Business) were already fully implemented. Fixed the only gap — AC-003: feat retraining now computes `7 × feat_level` days instead of a flat 7 days, matching PF2e's "1 week per feat level" rule. Committed `137b412b1`.

## Next actions
- QA Gate 2 verification.

## Blockers
- None.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260412-034324-impl-dc-cr-downtime-mode
- Generated: 2026-04-12T07:22:14+00:00
