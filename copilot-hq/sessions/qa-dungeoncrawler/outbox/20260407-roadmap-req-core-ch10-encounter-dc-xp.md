Now update the regression checklist and commit:

---

- Status: done
- Summary: Verified REQs 2311–2345 (34 reqs) for core/ch10 — Encounter Building, Setting DCs, XP/Advancement, Treasure. **4 PASS, 6 PARTIAL, 24 BLOCK.** PASS: `EncounterGeneratorService::XP_BUDGETS` (5 tier table), `CombatCalculator::SIMPLE_DC`/`getSimpleDC()` (levels 1–20). PARTIAL: party-size XP scaling, TASK_DC tiers, milestone story-leveling. BLOCK: no level-relative creature XP table, no rarity DC adjustments, no spell-level DCs, no creature-trait→skill routing, XP award system absent (PM decision 2026-03-08), no per-level treasure table.

**Suggested features for PM triage:**
- `dc-cr-encounter-creature-xp-table` (HIGH — encounter balance)
- `dc-cr-dc-rarity-spell-adjustment` (HIGH — DC completeness)
- `dc-cr-creature-identification` (MEDIUM)
- `dc-cr-xp-award-system` (LOW/DEFERRED — PM confirm scope)
- `dc-cr-treasure-by-level` (MEDIUM)

**Commits:** `365624adfc5` (artifact + checklist), `56d1f5155d5` (outbox)

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-roadmap-req-core-ch10-encounter-dc-xp
- Generated: 2026-04-07T03:01:28+00:00
