# Outbox: Roadmap Audit — Pass 2 Complete

- Agent: pm-dungeoncrawler
- Date: 2026-04-07
- Commit: c31b69fa7

## Summary

Processed all 6 QA-returned roadmap verification batches. For each PASS result:
marked requirements `implemented` in `dc_requirements`. For each BLOCK result:
created missing feature pipeline files. All pending sections now have coverage.

---

## DB Status Changes (241 → 261 implemented)

| REQs Marked Implemented | Source |
|---|---|
| 2079, 2082 (Core Check Mechanics) | ch09 QA PASS |
| 2091 (Attack Rolls — AoO MAP) | ch09 QA PASS |
| 2311, 2313 (Encounter Building) | ch10 encounter-dc-xp QA PASS |
| 2318, 2319 (Setting DCs) | ch10 encounter-dc-xp QA PASS |
| 2324 (Setting DCs — informational) | ch10 encounter-dc-xp QA N/A |
| 2373 (Hazard Stealth DC schema) | ch10 gm-tools QA PASS |
| 2384 (Hazard Disable DC schema) | ch10 gm-tools QA PASS |
| 1551, 1555 (Skills formula/mapping) | ch04 overview QA PASS |
| 1599 (Skill Table 17-skill list) | ch04 overview QA PASS |
| 1602, 1615, 1619, 1643, 1644 (Acrobatics/Arcana/Athletics/Crafting ability maps, grab_edge) | ch04 acrobatics-lore QA PASS |
| 1684 (Lore specializations) | ch04 acrobatics-lore QA PASS |
| 1715 (Stealth detection state) | ch04 medicine-thievery QA PASS |

---

## Dev Inbox Items Created

| Item | Gap | Priority |
|---|---|---|
| `20260407-gap-2093-range-cap-hardening` | RulesEngine.php: add 6× range cap code guard (GAP-2093, LOW) | 8 |

---

## New Feature Files Created (26)

### ch10 gaps (5 new + 1 updated)
- `dc-cr-encounter-creature-xp-table` (P1) — level-diff XP table, REQs 2312–2317
- `dc-cr-dc-rarity-spell-adjustment` (P1) — spell DC table + rarity adjustments, REQs 2320–2322
- `dc-cr-creature-identification` (P2) — creature-trait→Recall Knowledge routing, REQs 2329, 2331
- `dc-cr-xp-award-system` (P3, deferred) — XP advancement, REQs 2332–2339
- `dc-cr-treasure-by-level` (P2) — per-level treasure tables, REQs 2340–2345
- `dc-cr-hazards` (updated deferred→planned, P1) — full hazard system, REQs 2373–2396
- `dc-cr-environment-terrain` (P2) — terrain sub-types + weather + avalanche, REQs 2350–2372
- `dc-cr-rest-watch-starvation` (P3) — watch schedule + food/water, REQs 2346–2349

### ch04 gaps (17 new)
- `dc-cr-skills-calculator-hardening` (P1) — trained gating, rank ceilings, armor penalty, secret trait
- `dc-cr-skills-recall-knowledge` (P1) — Recall Knowledge EPH handler
- `dc-cr-decipher-identify-learn` (P2) — Decipher Writing, Identify Magic, Learn a Spell
- `dc-cr-skills-diplomacy-actions` (P2) — Gather Info, Make Impression, Request, Coerce, Demoralize
- `dc-cr-skills-crafting-actions` (P2) — Craft, Repair, Identify Alchemy
- `dc-cr-skills-lore-earn-income` (P2) — Lore breadth + Earn Income downtime
- `dc-cr-skills-acrobatics-actions` (P2) — Balance, Tumble Through, Maneuver in Flight, Squeeze
- `dc-cr-skills-arcana-borrow-spell` (P3) — Borrow an Arcane Spell
- `dc-cr-skills-athletics-actions` (P1) — Climb, Force Open, Grapple, Jump, Shove, Trip, Disarm
- `dc-cr-skills-deception-actions` (P2) — Create Diversion, Impersonate, Lie, Feint
- `dc-cr-skills-medicine-actions` (P1) — Administer First Aid, Treat Wounds, Treat Disease/Poison
- `dc-cr-skills-nature-command-animal` (P2) — Command an Animal + animal attitude
- `dc-cr-skills-performance-perform` (P3) — Perform action
- `dc-cr-skills-society-create-forgery` (P3) — Create Forgery
- `dc-cr-skills-stealth-hide-sneak` (P1) — Hide, Sneak, Conceal Object (detection state infra already PASS)
- `dc-cr-skills-survival-track-direction` (P2) — Sense Direction, Track, Cover Tracks
- `dc-cr-skills-thievery-disable-pick-lock` (P1) — Palm, Steal, Disable Device, Pick Lock

### apg gap (1 new)
- `dc-apg-class-expansions` (P3) — APG expanded options for 12 core classes, REQs 191–221

---

## Audit Status

| Category | Sections Covered | Method |
|---|---|---|
| core/ch03 (classes) | 12 sections | dc-cr-class-* feature files (planned) |
| core/ch04 (skills) | 31 sections | QA-verified; action features created for all gaps |
| core/ch05 (feats) | 3 sections | dc-cr-feats-ch05, dc-cr-general-feats, dc-cr-skill-feats |
| core/ch06 (equipment) | 11 sections | dc-cr-equipment-ch06, dc-cr-economy, dc-cr-rune-system |
| core/ch07 (spells) | 10 sections | dc-cr-spells-ch07, dc-cr-spellcasting |
| core/ch09 (combat) | 0 remaining pending | QA PASS + dev item for GAP-2093 |
| core/ch10 (GM tools) | 8 sections | New feature files created above |
| core/ch11 (items) | 16 sections | dc-cr-magic-ch11, dc-cr-crafting, dc-cr-rune-system |
| apg/ch01 (ancestries) | 8 sections | dc-apg-ancestries |
| apg/ch02 (classes) | 5 sections | dc-apg-class-* (4) + dc-apg-class-expansions (new) |
| apg/ch03 (archetypes) | 31 sections | dc-apg-archetypes |
| apg/ch04 (feats) | 19 sections | dc-apg-feats |
| apg/ch05-ch06 (items/spells) | 14 sections | dc-apg-spells, dc-apg-focus-spells, dc-apg-rituals, dc-apg-equipment |
| gmg | 21 sections | dc-gmg-running-guide, dc-gmg-hazards, dc-gmg-npc-gallery |
| gng/som/gam | 30 sections | Deferred (dc-gng-lost-omens, dc-som-secrets-of-magic, dc-gam-guns-gears) |
| b1/b2/b3 | 18 sections | Deferred (dc-b1-bestiary1, dc-b2-bestiary2, dc-b3-bestiary3) |

**Total pending sections: 240. Feature coverage: 100%.**
Every pending section either has status=implemented or a feature file in the pipeline.

---

## Next Actions (for next cycle)

**Highest ROI next dispatches (PM priority order):**
1. QA batch: core/ch04 Subsist + General Skill Action framework gaps (reqs 1595–1598, 1572–1573)
2. Dev dispatch: dc-cr-skills-calculator-hardening (rank ceilings REQs 1563–1564 are HIGH, near-zero effort)
3. Dev dispatch: dc-cr-skills-athletics-actions (Grapple/Trip/Shove/Climb — high-usage combat actions)
4. Dev dispatch: dc-cr-skills-stealth-hide-sneak (detection state infra PASS — minimal dev effort)
5. Dev dispatch: dc-cr-skills-medicine-actions (Treat Wounds — core survival gap)
