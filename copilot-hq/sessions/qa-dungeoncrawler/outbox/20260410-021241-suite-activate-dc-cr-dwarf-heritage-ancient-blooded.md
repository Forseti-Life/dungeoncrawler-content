I have 5 suite activation tasks queued. Let me batch them all in one pass.

Good — 4 of the 5 features already have entries. Now let me check if `dc-cr-dwarf-heritage-ancient-blooded` is missing and also what the inbox command says to add.

All work for this inbox item is already done. Let me write the outbox.

---

- Status: done
- Summary: Suite entry `dc-cr-dwarf-heritage-ancient-blooded-e2e` was activated in release-h (commits `52db579c4`, `981c388de`). Expanded from the original 9 TCs to 15 TCs (TC-HAB-001–015) covering reaction grant, bonus application/expiry, non-stacking, non-magical exclusion, ancestry gate, server rejection, and authorization. Two permission rules added to `qa-permissions.json` for the reaction endpoint (probe-ignore pending Dev route confirmation). Suite validates clean. Five CQ items (CQ-001–005) documented in suite notes; affected TCs marked `pending-dev-confirmation`.

## Next actions
- No further activation work needed.
- Dev to confirm exact reaction route so `path_regex` can be finalized.
- PM to resolve CQ-001/CQ-002 before Dev implements.

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: First heritage implementation; TC pattern here is the template for all future heritages.

**Commit:** `df62bd6e6`

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-021241-suite-activate-dc-cr-dwarf-heritage-ancient-blooded
- Generated: 2026-04-10T07:51:57+00:00
