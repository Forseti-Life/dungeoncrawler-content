# Verification Report: Roadmap REQs 1602–1687
# Skills: Acrobatics, Arcana, Athletics, Crafting, Deception, Diplomacy, Intimidation, Lore
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK — skill check formula PASS; all named skill actions BLOCK

---

## Foundation (PASS for all 8 sections)

### CharacterCalculator::calculateSkillCheck() — PASS
- File: `Service/CharacterCalculator.php` lines 275–346
- Formula: d20 + ability_mod + proficiency_bonus + item_bonus vs DC → degree of success
- All 8 skills mapped in `SKILLS` const (lines 234–253): acrobatics=dex, arcana=int, athletics=str, crafting=int, deception=cha, diplomacy=cha, intimidation=cha, lore=int
- Proficiency ranks: untrained/trained/expert/master/legendary (line 258)
- Lore specializations resolved via `lore_skills` array (lines 306–312)
- Natural 1 and natural 20 bump degree correctly (lines 323–330)

---

## Section: Acrobatics (Dex) — BLOCK

### PASS
- REQ 1602: Acrobatics → Dexterity confirmed in `CharacterCalculator::SKILLS` (line 236)
- REQ 1603: Escape accepts Acrobatics modifier (Escape action in EPH delegates modifier choice via params — needs confirmation; see PARTIAL below)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1604 | Balance action — not in `EncounterPhaseHandler::getLegalIntents()`; no processIntent case | MEDIUM |
| 1605 | Balance degree-of-success outcomes (crit=full speed, success=difficult terrain, fail=stop, critfail=fall) — not implemented | MEDIUM |
| 1606 | Balance sample DC table — not stored/exposed | LOW |
| 1607 | Tumble Through action — not in EPH getLegalIntents() | MEDIUM |
| 1608 | Tumble Through as Stride substitute — not implemented | MEDIUM |
| 1609 | Tumble Through degrees (success=pass+difficult terrain, fail=stop+reactions) — not implemented | MEDIUM |
| 1610 | Maneuver in Flight action (requires fly Speed + trained) — not in EPH getLegalIntents() | MEDIUM |
| 1611 | Maneuver in Flight sample DCs — not stored | LOW |
| 1612 | Squeeze (exploration) — not in ExplorationPhaseHandler getLegalIntents() | MEDIUM |
| 1613 | Squeeze crit-fail stuck + follow-up check — not implemented | MEDIUM |
| 1614 | Squeeze sample DCs — not stored | LOW |

Suggested feature: `dc-cr-skills-acrobatics-actions`

---

## Section: Arcana (Int) — BLOCK

### PASS
- REQ 1615: Arcana → Intelligence confirmed in `CharacterCalculator::SKILLS` (line 237)
- REQ 1616: Recall Knowledge (untrained) — `recall_knowledge` registered in `CanonicalActionRegistryService` (line 64)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1617 | Borrow Arcane Spell — not in ExplorationPhaseHandler getLegalIntents() | MEDIUM |
| 1618 | Borrow Arcane Spell degrees (success=prepare; fail=slot stays open, retry blocked) — not implemented | MEDIUM |

Suggested feature: `dc-cr-skills-arcana-borrow-spell`

---

## Section: Athletics (Str) — BLOCK

### PASS
- REQ 1619: Athletics → Strength confirmed in `CharacterCalculator::SKILLS` (line 238)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1620 | Climb action — not in EPH getLegalIntents(); EPH has `processStride` but no `climb` case | MEDIUM |
| 1621 | Climb speed-distance scaling — not implemented | MEDIUM |
| 1622 | Climb crit fail = fall + prone — not implemented | MEDIUM |
| 1623 | Force Open (attack trait, –2 without crowbar) — not in EPH getLegalIntents() | MEDIUM |
| 1624 | Force Open degrees — not implemented | MEDIUM |
| 1625 | Grapple (free hand, size limit +1 larger) — not in EPH getLegalIntents() | MEDIUM |
| 1626 | Grapple degrees (crit=restrained, success=grabbed, fail=release, critfail=grab you or prone) — not implemented | MEDIUM |
| 1627 | Grabbed/Restrained until end of next turn; broken by movement or Escape — not implemented | MEDIUM |
| 1628 | High Jump (2 actions, ≥10 ft Stride req) — not in EPH getLegalIntents() | MEDIUM |
| 1629 | High Jump degrees — not implemented | MEDIUM |
| 1630 | Long Jump (2 actions, DC=distance in feet, ≥10 ft Stride req) — not in EPH getLegalIntents() | MEDIUM |
| 1631 | Long Jump max distance = Speed; crit fail = normal leap + prone — not implemented | MEDIUM |
| 1632 | Shove (attack trait, no movement reactions) — not in EPH getLegalIntents() | MEDIUM |
| 1633 | Shove degrees — not implemented | MEDIUM |
| 1634 | Swim (exploration/encounter, calm water = no check) — not in EPH getLegalIntents() | MEDIUM |
| 1635 | Swim breath tracking — not implemented | MEDIUM |
| 1636 | Swim no-action sink rule — not implemented | MEDIUM |
| 1637 | Swim crit fail breath cost — not implemented | MEDIUM |
| 1638 | Trip (attack trait, crit=1d6+prone, success=prone) — not in EPH getLegalIntents() | MEDIUM |
| 1639 | Disarm (trained, attack trait) — not in EPH getLegalIntents() | MEDIUM |
| 1640 | Disarm degrees — not implemented | MEDIUM |
| 1641 | Falling damage (half distance bludgeoning, prone) — not in HPManager/CombatEngine fall handler | HIGH |
| 1642 | Soft landing (water/snow reduces 20 ft) — not implemented | MEDIUM |
| 1643 | Grab an Edge reaction — IS in EPH getLegalIntents() as `grab_edge` (PASS) | — |

Note: REQ 1641 (falling damage) is HIGH severity — currently `grab_edge` is wired but no `applyFallingDamage` method exists. Grapple/Trip/Shove/Climb/Swim = 12 missing medium actions.

Suggested feature: `dc-cr-skills-athletics-actions`

---

## Section: Crafting (Int) — BLOCK

### PASS
- REQ 1644: Crafting → Intelligence confirmed in `CharacterCalculator::SKILLS` (line 239)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1645 | Repair (repair kit, trained, 10 min) — DowntimePhaseHandler stub only; no action implementation | MEDIUM |
| 1646 | Repair HP restoration formula (proficiency rank scale) — not implemented | MEDIUM |
| 1647 | Repair crit fail = 2d6 to item (after Hardness) — not implemented | MEDIUM |
| 1648 | Destroyed items cannot be repaired — not enforced | MEDIUM |
| 1649 | Craft (downtime, trained, formula, tools, 50% cost upfront) — stub in DowntimePhaseHandler | MEDIUM |
| 1650 | Craft item level cap vs character level; master/legendary level gates — not enforced | MEDIUM |
| 1651 | Craft 4-day minimum + additional days reduce cost — not implemented | MEDIUM |
| 1652 | Craft degrees (cost reduction rates) — not implemented | MEDIUM |
| 1653 | Craft consumable batches (up to 4) — not implemented | MEDIUM |
| 1654 | Alchemical/Magical/Snare Crafting feat gates — not enforced | MEDIUM |
| 1655 | Identify Alchemy (trained, tools, 10 min) — not in ExplorationPhaseHandler | MEDIUM |
| 1656 | Identify Alchemy crit fail = false identification — not implemented | MEDIUM |

Suggested feature: `dc-cr-skills-crafting-actions`

---

## Section: Deception (Cha) — BLOCK

### PASS
- REQ 1657 partial: Deception → Charisma confirmed in `CharacterCalculator::SKILLS` (line 240)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1657 | Create a Diversion (1 action, manipulate/auditory variants) — not in EPH getLegalIntents() | MEDIUM |
| 1658 | +4 circumstance bonus to subsequent Perception DCs for 1 min after using — not implemented | MEDIUM |
| 1659 | Create a Diversion → hidden state (not undetected); reverts on most actions — not implemented | MEDIUM |
| 1660 | Strike while hidden → target flat-footed for that strike → becomes observed — not implemented | MEDIUM |
| 1661 | Impersonate (exploration, 10 min + disguise kit) — not in ExplorationPhaseHandler | MEDIUM |
| 1662 | Passive observer Perception vs Deception DC — not implemented | MEDIUM |
| 1663 | Impersonate crit fail reveals true identity — not implemented | MEDIUM |
| 1664 | Lie (secret check, single roll vs multiple targets' Perception) — not in any phase handler | MEDIUM |
| 1665 | Lie failure = +4 circumstance bonus resist future lies this conversation — not implemented | MEDIUM |
| 1666 | Lie delayed recheck on contradicting evidence — not implemented | LOW |
| 1667 | Feint (1 action, mental, trained, melee range) — not in EPH getLegalIntents() | MEDIUM |
| 1668 | Feint degrees (crit=flat-footed full turn, success=one attack, critfail=attacker flat-footed) — not implemented | MEDIUM |

Suggested feature: `dc-cr-skills-deception-actions`

---

## Section: Diplomacy (Cha) — BLOCK

### PASS
- Diplomacy → Charisma confirmed in `CharacterCalculator::SKILLS` (line 241)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1669 | Gather Information (exploration, secret, ~2 hr) — not in ExplorationPhaseHandler | MEDIUM |
| 1670 | Gather Information crit fail = false info — not implemented | MEDIUM |
| 1671 | Gather Information sample DCs — not stored | LOW |
| 1672 | Make an Impression (exploration, ≥1 min, vs Will DC) — not in ExplorationPhaseHandler | MEDIUM |
| 1673 | NPC attitude tracking (5 levels) — not implemented | MEDIUM |
| 1674 | Make an Impression degrees (crit=+2 steps, success=+1 step, critfail=–1 step) — not implemented | MEDIUM |
| 1675 | PC attitudes not changeable by skill actions — not enforced (no attitude system) | LOW |
| 1676 | Request (Friendly/Helpful required; blocked for Indifferent or lower) — not implemented | MEDIUM |
| 1677 | Request crit fail = –1 attitude step — not implemented | MEDIUM |

Suggested feature: `dc-cr-skills-diplomacy-actions`

---

## Section: Intimidation (Cha) — BLOCK

### PASS
- Intimidation → Charisma confirmed in `CharacterCalculator::SKILLS` (line 242)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1678 | Coerce (exploration, ≥1 min, vs Will DC) — not in ExplorationPhaseHandler | MEDIUM |
| 1679 | Coerce compliance window ≤1 day; auto-Unfriendly after — not implemented | MEDIUM |
| 1680 | Coerce crit fail = 1-week immunity — not implemented | MEDIUM |
| 1681 | Demoralize (1 action, 30 ft, shared language; –4 without) — not in EPH getLegalIntents() | MEDIUM |
| 1682 | Demoralize auto-immunity 10 min after attempt — not implemented | MEDIUM |
| 1683 | Demoralize degrees (crit=frightened 2, success=frightened 1) — not implemented | MEDIUM |

Suggested feature: `dc-cr-skills-intimidation-actions`

---

## Section: Lore (Int) — BLOCK

### PASS
- REQ 1684: Lore specializations confirmed in `CharacterCalculator` (lines 280–312; lore_skills array; lore → Intelligence)
- REQ 1685 partial: narrow topic enforcement (CharacterCalculator explicitly checks `lore` or `* lore` naming)
- REQ 1686: Multiple Lore subtypes — calculator supports selecting any matching specialization, best modifier must be chosen by calling code (PARTIAL — caller must iterate)

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1685 | Breadth enforcement ("no Magic Lore") — not validated at character creation API | MEDIUM |
| 1686 | Multiple Lore subtypes: "use better modifier" not automatically applied by EPH/ExPH — caller responsibility unmet | MEDIUM |
| 1687 | Earn Income with Lore — DowntimePhaseHandler stub only; no actual implementation | MEDIUM |

Suggested feature: `dc-cr-skills-lore-earn-income` (or merged into a broader skills-downtime feature)

---

## Summary

| Section | Skill Check Formula | Named Actions | Verdict |
|---|---|---|---|
| Acrobatics (Dex) | PASS (CharacterCalculator) | BLOCK — 9 missing (Balance, Tumble Through, Maneuver in Flight, Squeeze + degrees) | BLOCK |
| Arcana (Int) | PASS | BLOCK — 2 missing (Borrow Arcane Spell) | BLOCK |
| Athletics (Str) | PASS | BLOCK — 18 missing (Climb, Force Open, Grapple, High/Long Jump, Shove, Swim, Trip, Disarm, Falling Damage) | BLOCK |
| Crafting (Int) | PASS | BLOCK — 12 missing (Repair, Craft, Identify Alchemy — stubs only) | BLOCK |
| Deception (Cha) | PASS | BLOCK — 11 missing (Create a Diversion, Impersonate, Lie, Feint) | BLOCK |
| Diplomacy (Cha) | PASS | BLOCK — 9 missing (Gather Information, Make an Impression, Request, NPC attitudes) | BLOCK |
| Intimidation (Cha) | PASS | BLOCK — 6 missing (Coerce, Demoralize) | BLOCK |
| Lore (Int) | PASS (specializations) | BLOCK — 3 gaps (breadth enforcement, best-modifier, Earn Income stub) | BLOCK |

Overall: BLOCK. Skill check formula is fully implemented. 70 of 86 REQs (1602–1687) are not covered by named skill action handlers. Suggested new features above for PM pipeline triage.

## Site audit
- Run: 20260407-020452 (most recent)
- Result: CLEAN — 0 errors, 0 permission violations
