# Test Plan: dc-cr-skills-arcana-borrow-spell

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Arcana skill — knowledge domain, Recall Knowledge (untrained), Borrow an Arcane Spell
**KB reference:** spellcasting dependency pattern follows dc-cr-class-wizard/03-test-plan.md (deferred TCs on dc-cr-spellcasting for slot/preparation interaction).
**Dependency note:** dc-cr-spellcasting (deferred) — 2 TCs deferred for slot-interaction outcomes; 8 TCs immediately activatable at Stage 0.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | Arcana knowledge domain coverage, Recall Knowledge proficiency gating, Borrow an Arcane Spell dual-gate (Trained + arcane-prepared-spellcaster), exploration activity type |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing exploration handler routes only |

---

## Test Cases

### Arcana Knowledge Domain

### TC-ARC-01 — Arcana covers arcane magic knowledge
- **Suite:** module-test-suite
- **Description:** Arcana skill is tagged with the `arcane_magic` knowledge domain; Recall Knowledge checks on arcane magic topics use Arcana.
- **Expected:** skill_domain includes `arcane_magic`; Recall Knowledge on arcane magic topic resolves using Arcana modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ARC-02 — Arcana covers arcane creature identification
- **Suite:** module-test-suite
- **Description:** Arcana is the applicable skill for identifying arcane creatures (e.g., constructs, dragons, elementals); Recall Knowledge against arcane creature types routes to Arcana.
- **Expected:** creature_type in {construct, dragon, elemental, ...} → Recall Knowledge skill = Arcana.
- **Notes to PM:** Confirm the canonical arcane creature type list in scope — this TC needs the creature type taxonomy to match the AC creature list.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ARC-03 — Arcana covers planar lore (Elemental, Astral, Shadow planes)
- **Suite:** module-test-suite
- **Description:** Arcana is the applicable skill for Recall Knowledge on planar topics specifically: Elemental planes, Astral plane, and Shadow plane.
- **Expected:** topic_type in {elemental_planes, astral_plane, shadow_plane} → Recall Knowledge skill = Arcana.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ARC-04 — Untrained characters can Recall Knowledge on arcane topics
- **Suite:** module-test-suite
- **Description:** Recall Knowledge using Arcana has no proficiency gate — even Untrained characters may attempt the check; no "requires Trained" block.
- **Expected:** acrobatics_rank = untrained → Recall Knowledge on arcane topic proceeds; check rolled at Untrained modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Borrow an Arcane Spell — Gating

### TC-ARC-05 — Borrow an Arcane Spell is an exploration activity
- **Suite:** module-test-suite
- **Description:** Borrow an Arcane Spell is classified as an exploration activity (not an encounter action or downtime).
- **Expected:** action_type = exploration; action not available as a single encounter action.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ARC-06 — Borrow blocked for untrained Arcana
- **Suite:** module-test-suite
- **Description:** Failure mode — a character with Untrained Arcana cannot use Borrow an Arcane Spell even if they are an arcane prepared spellcaster.
- **Expected:** arcana_rank = untrained → action blocked; error returned; no check rolled.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ARC-07 — Borrow blocked for non-arcane-prepared spellcasters
- **Suite:** module-test-suite
- **Description:** Failure mode — a character with Trained Arcana who is not an arcane prepared spellcaster (e.g., spontaneous caster, divine caster, non-caster) cannot use Borrow an Arcane Spell.
- **Expected:** spellcasting_type ≠ arcane_prepared → action blocked; error returned; no check rolled regardless of Arcana rank.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-ARC-08 — Borrow dual-gate: both Trained Arcana AND arcane-prepared-spellcaster required
- **Suite:** module-test-suite
- **Description:** Edge case — action is available only when BOTH gates pass simultaneously: Arcana ≥ Trained AND spellcasting_type = arcane_prepared.
- **Expected:** Trained Arcana alone (non-caster) → blocked; arcane prepared spellcaster alone (Untrained Arcana) → blocked; both conditions true → action available.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Borrow an Arcane Spell — Outcomes (deferred on dc-cr-spellcasting)

### TC-ARC-09 — Borrow Success: borrowed spell available for preparation in open slot
- **Suite:** module-test-suite
- **Description:** On success, the borrowed spell is available to be prepared in any open spell slot of the appropriate level during the current daily preparation.
- **Expected:** borrowed_spell_available = true; spell appears in preparation list; can occupy an open slot of matching level.
- **Dependency note:** Requires dc-cr-spellcasting daily preparation and slot management system.
- **Status:** deferred — pending `dc-cr-spellcasting`

### TC-ARC-10 — Borrow Failure: slot stays open, retry blocked until next prep cycle
- **Suite:** module-test-suite
- **Description:** On failure, the targeted slot remains open (no spell is borrowed into it); attempting Borrow an Arcane Spell again is blocked until the next daily preparation cycle.
- **Expected:** borrowed_spell_available = false; slot_status = open; borrow_retry_blocked = true until next_prep_cycle.
- **Dependency note:** Requires dc-cr-spellcasting preparation cycle tracking.
- **Status:** deferred — pending `dc-cr-spellcasting`

---

### ACL regression

### TC-ARC-11 — ACL regression: no new routes introduced by Arcana actions
- **Suite:** role-url-audit
- **Description:** Arcana skill action implementation adds no new HTTP routes; existing exploration handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing exploration handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Deferred dependency summary

| TC | Dependency feature | Reason deferred |
|---|---|---|
| TC-ARC-09 | `dc-cr-spellcasting` | Borrow success: slot availability and preparation list integration |
| TC-ARC-10 | `dc-cr-spellcasting` | Borrow failure: retry-blocked state and prep-cycle reset |

9 TCs immediately activatable at Stage 0.
2 TCs deferred pending `dc-cr-spellcasting`.

---

## Notes to PM

1. **TC-ARC-02 (arcane creature type list):** The AC states Arcana covers "arcane creature identification" but does not enumerate the creature types. The creature type taxonomy needs to be confirmed (constructs, dragons, elementals are examples — is this list exhaustive for dc-cr scope?). This determines how many creature-type assertions TC-ARC-02 makes.
2. **TC-ARC-09/10 dependency sequencing:** Borrow an Arcane Spell outcome TCs depend on dc-cr-spellcasting (same dependency as Wizard/Sorcerer). Recommend Borrow an Arcane Spell NOT enter full release scope until dc-cr-spellcasting ships — the 2 deferred TCs cover the core success/failure behavior. The 9 immediately activatable TCs (domain coverage, gating, ACL) can activate independently.
3. **Borrow retry scope:** AC states retry is blocked until "next preparation cycle." Confirm whether this is per-character-per-day (resets at daily prep) or per-session. Current TC-ARC-10 assumes per-daily-prep-cycle reset.
