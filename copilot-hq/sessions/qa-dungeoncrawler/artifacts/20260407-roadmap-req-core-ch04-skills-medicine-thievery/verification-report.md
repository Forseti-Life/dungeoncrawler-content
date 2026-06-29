# Verification Report: Roadmap REQs 1688–1748
# Skills: Medicine, Nature, Occultism, Performance, Religion, Society, Stealth, Survival, Thievery
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK — 1 PASS (detection state structure), 2 PARTIAL, 58 of 61 REQs unimplemented

---

## Foundation (PASS for all 9 sections)

### CharacterCalculator::SKILLS const — PASS
- File: `Service/CharacterCalculator.php` lines 232–253
- medicine=wisdom, nature=wisdom, occultism=int, performance=cha, religion=wisdom, society=int, stealth=dex, survival=wisdom, thievery=dex — all mapped
- Proficiency system, skill check formula, lore variants all confirmed in prior audit (REQs 1602–1687)

---

## Section: Medicine (Wis) — BLOCK

### PASS / PARTIAL
- **PARTIAL REQ 1691**: `ConditionManager::processPersistentDamage()` (line 591–595) auto-rolls flat check DC 15 at end of turn to end persistent damage. The automatic flat check mechanism exists; the named *Stop Bleeding* skill action (where a Medicine check grants the flat check) is NOT wired.
- **NOT PASS REQ 1690**: `HPManager::stabilizeCharacter()` (line 311) removes dying condition and increments wounded — but this is called by the Hero Point heroic-recovery path, not by a Medicine skill check action. No `administer_first_aid` case exists in `EncounterPhaseHandler::processIntent()`.

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1688 | `administer_first_aid` action — not in EPH getLegalIntents() or processIntent() switch | HIGH |
| 1689 | One-ailment-at-a-time enforcement — no handler to enforce | MEDIUM |
| 1690 | Stabilize via Medicine skill check (DC 15 + dying value) — HPManager.stabilizeCharacter() is not accessible via player skill action | HIGH |
| 1691 | Stop Bleeding action (Medicine check grants flat check) — auto-flat-check exists in ConditionManager but no explicit skill action handler | MEDIUM |
| 1692 | Treat Disease (downtime) — not in DowntimePhaseHandler processIntent() | MEDIUM |
| 1693 | Treat Disease bonus/penalty applied to next save only — not enforced | MEDIUM |
| 1694 | Treat Poison (1 action) — not in EncounterPhaseHandler processIntent() | HIGH |
| 1695 | Treat Wounds (exploration) — not in ExplorationPhaseHandler set_activity list | HIGH |
| 1696 | Treat Wounds proficiency-scaled DC and HP tables — not implemented | HIGH |
| 1697 | Treat Wounds degree-of-success outcomes (crit=removes wounded, crit fail=1d8 damage) — not implemented | HIGH |
| 1698 | Extended 1-hour Treat Wounds doubling — not implemented | MEDIUM |

- **Verdict: BLOCK** — 0 clean PASS, 1 PARTIAL, 10 BLOCK

---

## Section: Nature (Wis) — BLOCK

### PASS
- REQ 1699: nature → wisdom confirmed in SKILLS const

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1699 | Command an Animal action (1 action) — not in EPH getLegalIntents() | HIGH |
| 1700 | Animal attitude threshold (Hostile/Unfriendly auto-fail; Helpful +1 step) — not implemented | HIGH |
| 1701 | Animals know default actions (Stride, Strike, Leap, etc.) — no animal action set model | MEDIUM |
| 1702 | Commands executed in order on animal turn; excess forgotten — not implemented | MEDIUM |
| 1703 | Multi-handler conflict resolution — not in scope for MVP; GM-resolved | LOW |

- **Verdict: BLOCK** — 5/5 REQs unimplemented

---

## Section: Occultism (Int) — BLOCK

### PASS
- REQ 1704: occultism → intelligence confirmed in SKILLS const

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1704 | Decipher Writing (occult), Identify Magic (occult), Learn a Spell (occult) — none in ExPH or EPH | HIGH |

- **Verdict: BLOCK** — 1/1 REQs unimplemented

---

## Section: Performance (Cha) — BLOCK

### PASS
- REQ 1705: performance → charisma confirmed in SKILLS const

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1705 | Perform action trait system (auditory/visual/manipulate/linguistic/move) — not in EPH | MEDIUM |
| 1706 | Negative ability penalty logic — not implemented | LOW |
| 1707 | Perform action (1 action) — not in EPH processIntent() switch | HIGH |
| 1708 | Audience DC table — not stored | LOW |

- **Verdict: BLOCK** — 4/4 REQs unimplemented

---

## Section: Religion (Wis) — BLOCK

### PASS
- REQ 1709: religion → wisdom confirmed in SKILLS const

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1709 | Decipher Writing (religious), Identify Magic (divine), Learn a Spell (divine) — none implemented | HIGH |

- **Verdict: BLOCK** — 1/1 REQs unimplemented

---

## Section: Society (Int) — BLOCK

### PASS
- REQ 1710: society → intelligence confirmed in SKILLS const

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1710 | Decipher Writing (codes/archaic/unfamiliar), Create Forgery — not in ExPH or DowntimePhaseHandler | HIGH |
| 1711 | Create Forgery (downtime, secret, GM DC 20 roll) — not in DowntimePhaseHandler processIntent() | HIGH |
| 1712 | Specific vs non-specific handwriting bonus — not implemented | MEDIUM |
| 1713 | Results below 20 expose forgery to passive observers — not implemented | MEDIUM |
| 1714 | Active scrutiny Perception/Society check vs forger DC — not implemented | MEDIUM |

- **Verdict: BLOCK** — 5/5 REQs unimplemented

---

## Section: Stealth (Dex) — BLOCK (infrastructure PASS, actions BLOCK)

### PASS
- **REQ 1715 PASS**: Detection state structure (observed/hidden/undetected/unnoticed) fully modeled in:
  - `entity_ref['detection_states'][attacker_entity_id]` (CombatEngine lines 939, 947, 960)
  - `game_state['visibility'][$actor_id][$target_id]` (EPH processSeek)
- **PARTIAL REQ 1718**: `processSeek()` (EPH line 2192) handles active searcher (Seek action) via Perception vs Stealth DC — correct. Passive observer auto-check at round start is NOT wired separately.
- **PARTIAL REQ 1724**: `CombatEngine::resolveAttack()` line 765 applies `-2 AC` when attacker is hidden (flat-footed penalty) and rolls flat check at DC 11 (line 766). Post-strike reset to Observed (attacker becomes observed after strike) is NOT implemented.

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1716 | Hide action (requires cover/concealment; sets Observed→Hidden) — not in EPH processIntent() | HIGH |
| 1717 | Conceal an Object action (1-action manipulate) — not in EPH | MEDIUM |
| 1718 | Passive observer auto-check at round start — not wired (Seek only) | MEDIUM |
| 1719 | Conceal Object in environment (Seek area to discover) — not implemented | LOW |
| 1720 | Hide requires cover/greater cover/concealment — no Hide action to enforce | HIGH |
| 1721 | Cover bonus on Hide Stealth check — no Hide action | MEDIUM |
| 1722 | Observed→Hidden state transition on Hide success — no Hide action | HIGH |
| 1723 | Non-Hide/Sneak/Step actions set character to Observed — not enforced | HIGH |
| 1725 | Sneak action (1-action move at half Speed + secret check) — not in EPH | HIGH |
| 1726 | Cover bonus for Sneak — no Sneak action | MEDIUM |
| 1727 | Invisible + undetected crit fail → fail upgrade — not implemented | MEDIUM |
| 1728 | Sneak degree outcomes (crit fail=Observed, fail=Hidden, success=Undetected) — no Sneak action | HIGH |
| 1729 | Speaking degrades Undetected → Hidden — not implemented | MEDIUM |

- **Verdict: BLOCK** — 1 PASS (infrastructure), 2 PARTIAL, 12 BLOCK
- **Note (HIGH priority)**: REQ 1723 — no mechanism ensures a character's detection state degrades to Observed when they act. CombatEngine applies hidden-attacker rules correctly at strike time but does not update the detection state after.

---

## Section: Survival (Wis) — BLOCK

### PASS
- survival → wisdom confirmed in SKILLS const
- ExPH `calculateTravelSpeed()` (line 790–813) handles terrain multipliers and hustle fatigue — this is the movement layer Survival would build on

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1730 | Sense Direction (exploration, secret) — not in ExPH set_activity or processIntent() | MEDIUM |
| 1731 | Sense Direction sample DC table — not stored | LOW |
| 1732 | Cover Tracks (exploration; no active check — sets higher tracker DC) — not in ExPH | MEDIUM |
| 1733 | Cover Tracks as 1-action encounter version — not in EPH | MEDIUM |
| 1734 | Track (exploration; half Speed; ongoing checks per hour) — not in ExPH | HIGH |
| 1735 | Track crit fail 24-hour lockout — not implemented | MEDIUM |
| 1736 | Track encounter version (1 action) — not in EPH | MEDIUM |
| 1737 | Track sample DC table — not stored | LOW |

- **Verdict: BLOCK** — 8/8 REQs unimplemented

---

## Section: Thievery (Dex) — BLOCK

### PASS
- thievery → dexterity confirmed in SKILLS const

### BLOCK
| REQ | Gap | Severity |
|---|---|---|
| 1738 | Palm an Object (1 action) — not in EPH | HIGH |
| 1739 | Object taken regardless of check outcome — not implemented | MEDIUM |
| 1740 | Steal auto-fails if target in combat or on guard — not in EPH | HIGH |
| 1741 | Steal DC = target Perception DC; –5 for pocketed items — not implemented | HIGH |
| 1742 | Observer detection also required — not implemented | MEDIUM |
| 1743 | Disable a Device (2 actions; trained required) — not in EPH | HIGH |
| 1744 | Complex devices require multiple successes — not implemented | HIGH |
| 1745 | Crit Success leaves no trace; Crit Fail triggers device — not implemented | HIGH |
| 1746 | Pick a Lock (2 actions; thieves' tools) — not in EPH | HIGH |
| 1747 | Complex locks require multiple successes — not implemented | HIGH |
| 1748 | Crit Fail breaks thieves' tools — not implemented | MEDIUM |

- **Verdict: BLOCK** — 11/11 REQs unimplemented

---

## Summary

| Section | Foundation | Action handlers | Verdict |
|---|---|---|---|
| Medicine (Wis) | PASS | BLOCK — 10/11 missing (Administer First Aid, Stop Bleeding, Treat Wounds, Treat Disease, Treat Poison) | BLOCK |
| Nature (Wis) | PASS | BLOCK — 5/5 missing (Command an Animal) | BLOCK |
| Occultism (Int) | PASS | BLOCK — 1/1 missing (Decipher Writing, Identify Magic, Learn a Spell) | BLOCK |
| Performance (Cha) | PASS | BLOCK — 4/4 missing (Perform) | BLOCK |
| Religion (Wis) | PASS | BLOCK — 1/1 missing (Decipher Writing, Identify Magic, Learn a Spell) | BLOCK |
| Society (Int) | PASS | BLOCK — 5/5 missing (Decipher Writing, Create Forgery) | BLOCK |
| Stealth (Dex) | PASS (detection states) | BLOCK — 12/15 missing (Hide, Sneak, Conceal Object, state transitions) | BLOCK |
| Survival (Wis) | PASS | BLOCK — 8/8 missing (Sense Direction, Cover Tracks, Track) | BLOCK |
| Thievery (Dex) | PASS | BLOCK — 11/11 missing (Palm Object, Steal, Disable Device, Pick a Lock) | BLOCK |

**Overall: BLOCK.** 1 clean PASS (Stealth detection state infrastructure), 2 PARTIAL. 58 of 61 REQs (1688–1748) unimplemented as named skill action handlers.

---

## Suggested feature pipeline items for PM triage

| Feature ID | Scope | Priority |
|---|---|---|
| `dc-cr-skills-medicine-actions` | Administer First Aid (EPH), Stop Bleeding (EPH), Treat Wounds (ExPH), Treat Disease (DTH), Treat Poison (EPH) | HIGH |
| `dc-cr-skills-nature-command-animal` | Command an Animal (EPH); animal attitude system; animal action set | HIGH |
| `dc-cr-skills-decipher-identify-learn` | Decipher Writing + Identify Magic + Learn a Spell (shared across Arcana/Occultism/Religion/Society) | MEDIUM |
| `dc-cr-skills-performance-perform` | Perform (EPH); trait system; audience DC table | MEDIUM |
| `dc-cr-skills-society-create-forgery` | Create Forgery (DTH); passive/active scrutiny | MEDIUM |
| `dc-cr-skills-stealth-hide-sneak` | Hide (EPH); Sneak (EPH); Conceal Object (EPH); post-action detection state reset (REQ 1723) | HIGH |
| `dc-cr-skills-survival-track-direction` | Sense Direction (ExPH); Cover Tracks (ExPH/EPH); Track (ExPH/EPH) | MEDIUM |
| `dc-cr-skills-thievery-disable-pick-lock` | Palm Object (EPH); Steal (EPH); Disable a Device (EPH); Pick a Lock (EPH) | HIGH |

### Shared sub-feature (cross-cutting)
- `dc-cr-skills-decipher-identify-learn` should be a single shared feature that covers Decipher Writing, Identify Magic, and Learn a Spell for all traditions (Arcana, Occultism, Religion, Society). This prevents duplication across 4 separate features.

---

## KB reference
- No prior lesson found for Stealth detection state architecture. REQ 1715 PASS pattern is worth documenting: the infrastructure (entity_ref.detection_states + game_state.visibility) is in place from the senses/detection work, so Stealth action handlers can build on it directly.

## Site audit
- ARCHITECTURE.md reference: skill check formula at `CharacterCalculator::calculateSkillCheck()` lines 275–346 is the universal handler; all skill action handlers should call this as their roll foundation.
