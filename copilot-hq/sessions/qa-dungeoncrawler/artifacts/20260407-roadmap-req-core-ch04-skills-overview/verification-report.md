# Verification Report: Roadmap REQs 1551–1601
# Skills Chapter Overview, General Skill Actions, Skill Table (4-1 Summary)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK — 3 PASS, 3 PARTIAL, 45 of 51 REQs unimplemented

---

## Section: Chapter Overview (REQs 1551–1571) — BLOCK

### PASS
- **REQ 1551 PASS**: All skills mapped to ability scores — `CharacterCalculator::SKILLS` const (lines 235–253): acrobatics=dex, arcana=int, athletics=str, crafting=int, deception=cha, diplomacy=cha, intimidation=cha, lore=int, medicine=wis, nature=wis, occultism=int, performance=cha, religion=wis, society=int, stealth=dex, survival=wis, thievery=dex.
- **REQ 1555 PASS**: Skill check formula confirmed — `CharacterCalculator::calculateSkillCheck()` (lines 275–343): `roll + ability_mod + proficiency_bonus + item_bonus` vs DC, with natural 1/20 degree bumps.

### PARTIAL
- **PARTIAL REQ 1552**: Background skill training exists in CharacterManager (background grants ~2 skills) but the exact initial-training redirect rule (REQ 1553) is not confirmed wired.
- **PARTIAL REQ 1560–1562**: `CharacterLevelingService` has `skill_increases` in level advancement table (lines 1519–1536) and `submitSkillIncrease()` (line 278) — rank advance by one step is wired. REQs 1560–1562 (untrained→trained, trained→expert) are effectively PARTIAL PASS.

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1553 | Redundant training redirect — no logic to detect same-skill grant from two sources and redirect | MEDIUM |
| 1554 | Trained-only gating — calculateSkillCheck() computes rank but returns no error/block if untrained attempts trained-only action | HIGH |
| 1556 | Skill DC (10 + skill mod) — no `calculateSkillDC()` method; skill-vs-skill DCs must be computed by callers without a service | MEDIUM |
| 1557 | GM ability substitution — no override parameter in calculateSkillCheck() for substituting a different ability modifier | MEDIUM |
| 1558 | Simple DC table — not exposed as a service method or constant | LOW |
| 1559 | Simple DCs by proficiency rank (untrained=10, trained=15, expert=20, master=30, legendary=40) — not stored as a constant; values are implied in the formula but not enumerable by callers | LOW |
| 1563 | Expert→master rank ceiling: requires character level ≥ 7 — NOT enforced in `submitSkillIncrease()` (line 305 only blocks "already Legendary") | HIGH |
| 1564 | Master→legendary rank ceiling: requires character level ≥ 15 — NOT enforced in `submitSkillIncrease()` | HIGH |
| 1565 | Redundant rank upgrade (two sources same rank) no benefit and no replacement — not handled | LOW |
| 1566 | Armor check penalty on Str/Dex skill checks — not applied in `calculateSkillCheck()` (no armor check penalty lookup) | MEDIUM |
| 1567 | Attack trait exception to armor check penalty — not implemented (no armor penalty wired at all) | LOW |
| 1568 | Secret trait handling — no secret roll flag in calculateSkillCheck(); numerical result always returned | HIGH |
| 1569 | GM option to make secret rolls public — not implemented | LOW |
| 1570 | Activity traits (exploration/downtime/encounter) — skill actions carry no trait enforcement; no trait-blocking at phase level | MEDIUM |
| 1571 | Exploration/downtime blocked during encounters by default — no guard in EPH blocking activity-trait actions | MEDIUM |

---

## Section: General Skill Actions (REQs 1572–1598) — BLOCK

### PARTIAL
- **PARTIAL REQ 1579 / 1582**: `earn_income` is registered in `DowntimePhaseHandler::$legal_types` (line 64) and has a case (line 151), but the body is `stub=TRUE` returning "not yet implemented". The routing infrastructure exists; the logic does not.

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1572 | General skill action framework — no shared handler; each named action would need its own case | HIGH |
| 1573 | Recall Knowledge and Subsist are untrained; trained-only gating framework absent | HIGH |
| 1574 | Decipher Writing action (trained, exploration, secret) — not in ExPH, EPH, or DTH | HIGH |
| 1575 | Decipher Writing time cost (1 min/page; cipher = 1 hr/page) — not stored | LOW |
| 1576 | Language literacy check for Decipher Writing — not implemented | MEDIUM |
| 1577 | Decipher Writing degree-of-success outcomes (crit=full, success=true, fail=block+penalty, crit fail=false) — not implemented | HIGH |
| 1578 | Decipher Writing applicable skills (Arcana, Occultism, Religion, Society) — no routing | MEDIUM |
| 1579 | Earn Income (downtime, trained) — stub only | HIGH |
| 1580 | Earn Income task level cap = character level — not implemented | MEDIUM |
| 1581 | Earn Income income rate table by task level × degree — not implemented | HIGH |
| 1582 | Earn Income applicable skills (Arcana, Crafting, Lore, Occultism, Performance, Religion, Society) — no multi-skill routing | MEDIUM |
| 1583 | Identify Magic (trained, exploration, 10 min) — not in ExPH | HIGH |
| 1584 | Identify Magic DC formula (standard by level; +5 wrong tradition) — not implemented | HIGH |
| 1585 | Identify Magic degrees (crit=full+bonus, success=full, fail=1-day block, crit fail=false ID) — not implemented | HIGH |
| 1586 | Identify Magic applicable skills (Arcana/Nature/Occultism/Religion) — no routing | MEDIUM |
| 1587 | Learn a Spell (trained, exploration, 1 hr) — not in ExPH | HIGH |
| 1588 | Learn a Spell material cost (spell level × 10 gp, consumed on attempt) — not implemented | HIGH |
| 1589 | Learn a Spell DC (standard by spell level) — not implemented | HIGH |
| 1590 | Learn a Spell degrees (crit=learn+refund half, success=learn, fail=nothing, crit fail=lose materials) — not implemented | HIGH |
| 1591 | Recall Knowledge (1-action, untrained, secret) — not in EPH processIntent() | HIGH |
| 1592 | Recall Knowledge DC (GM-set by obscurity) — not implemented | MEDIUM |
| 1593 | Recall Knowledge degrees (crit=info+bonus, success=info, fail=nothing, crit fail=false) — not implemented | HIGH |
| 1594 | Recall Knowledge applicable skills (Arcana, Crafting, Lore, Medicine, Nature, Occultism, Religion, Society) — no routing | MEDIUM |
| 1595 | Subsist (untrained, downtime) — not in DTH | HIGH |
| 1596 | Subsist DC 15 base; +2 per extra creature — not implemented | MEDIUM |
| 1597 | Subsist degrees (crit=group fed, success=self, fail=meager, crit fail=starvation–1 Fort) — not implemented | HIGH |
| 1598 | Subsist applicable skills (Nature/Society) — no routing | LOW |

---

## Section: Skill Table (4-1 Summary) (REQs 1599–1601) — BLOCK

### PASS
- **REQ 1599 PASS**: All 17 skills implemented in `CharacterCalculator::SKILLS` const (lines 235–253): acrobatics, arcana, athletics, crafting, deception, diplomacy, intimidation, lore, medicine, nature, occultism, performance, religion, society, stealth, survival, thievery.

### PARTIAL
- **PARTIAL REQ 1601**: `processEscape()` (EPH line 2128) accepts a `skill_bonus` parameter from the caller, which means the calling UI/API can pass Acrobatics or Athletics modifier. The system does not enforce that only those two skills are valid choices. PARTIAL — the mechanism works if the caller is correct; the enforcement guard is absent.

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1600 | Trained-only gating per skill action — no enforcement in calculateSkillCheck() or phase handlers that returns an error when untrained character attempts trained-only action | HIGH |
| 1601 | Escape modifier enforcement — processEscape() takes generic `skill_bonus` param; no validation that it is Acrobatics or Athletics modifier | LOW |

---

## Summary

| Section | PASS | PARTIAL | BLOCK | Verdict |
|---|---|---|---|---|
| Chapter Overview (1551–1571) | 2 (REQ 1551, 1555) | 2 (REQ 1552–1562 range) | 15 | BLOCK |
| General Skill Actions (1572–1598) | 0 | 1 (earn_income stub) | 26 | BLOCK |
| Skill Table (1599–1601) | 1 (REQ 1599) | 1 (REQ 1601) | 1 (REQ 1600) | BLOCK |

**Overall: BLOCK.** 3 clean PASS (REQ 1551, 1555, 1599), 3 PARTIAL, 45 of 51 REQs unimplemented.

---

## High-priority gaps for PM triage

| Gap | REQ | Severity | Suggested feature |
|---|---|---|---|
| Expert→master level ≥ 7 ceiling not enforced | 1563 | HIGH | Fix in CharacterLevelingService::submitSkillIncrease() — add level guard |
| Master→legendary level ≥ 15 ceiling not enforced | 1564 | HIGH | Same fix |
| Trained-only gating absent | 1554, 1600 | HIGH | Add rank enforcement flag to calculateSkillCheck(); add guards in phase handlers |
| Secret trait — roll result always exposed | 1568 | HIGH | `dc-cr-skills-secret-trait` — add `secret` flag to calculateSkillCheck() return |
| Recall Knowledge (untrained) | 1591–1594 | HIGH | `dc-cr-skills-recall-knowledge` — EPH 1-action handler, secret, 8+ skills |
| All general skill actions missing | 1574–1598 | HIGH | Covered by previously suggested `dc-cr-skills-decipher-identify-learn`, `dc-cr-skills-lore-earn-income` |
| Armor check penalty | 1566–1567 | MEDIUM | `dc-cr-skills-armor-check-penalty` — add to calculateSkillCheck() |
| Skill DC method (10 + skill mod) | 1556 | MEDIUM | Add `calculateSkillDC()` to CharacterCalculator |
| Simple DC table exposed | 1558–1559 | LOW | Add `SIMPLE_DC_BY_RANK` const to CharacterCalculator |

---

## KB reference
- Prior audit (ch04 Acrobatics–Lore, REQs 1602–1687) confirmed `calculateSkillCheck()` foundation is production-ready. This audit confirms the surrounding infrastructure (training enforcement, rank ceilings, armor penalty, secret trait) is not yet wired, meaning the foundation has correctness risks in practice.
- REQ 1563/1564 defects (rank ceiling missing) are relatively isolated fixes in `CharacterLevelingService::submitSkillIncrease()` — high value for low effort.
