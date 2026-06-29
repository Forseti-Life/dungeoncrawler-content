- Status: done
- Completed: 2026-04-11T01:51:33Z

# Suite Activation: dc-cr-skills-arcana-borrow-spell

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T17:07:56+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-arcana-borrow-spell"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-arcana-borrow-spell/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-arcana-borrow-spell-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-arcana-borrow-spell",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-arcana-borrow-spell"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-arcana-borrow-spell-<route-slug>",
     "feature_id": "dc-cr-skills-arcana-borrow-spell",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-arcana-borrow-spell",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-arcana-borrow-spell

## Gap analysis reference
- DB sections: core/ch04/Arcana (Int) (REQs 1615–1618)
- Depends on: dc-cr-skill-system ✓, dc-cr-spellcasting (deferred)

---

## Happy Path

- [ ] `[NEW]` Arcana covers arcane magic knowledge, arcane creature identification, and planar lore (Elemental, Astral, Shadow planes).
- [ ] `[NEW]` Untrained characters can use Arcana to Recall Knowledge about arcane topics.
- [ ] `[NEW]` Borrow an Arcane Spell is an exploration activity requiring Trained Arcana AND the character must be an arcane prepared spellcaster (spellbook user).
- [ ] `[NEW]` Success: borrowed spell can be prepared normally in an open slot. Failure: slot remains open and retry is blocked until next preparation cycle.

## Edge Cases
- [ ] `[NEW]` Borrow an Arcane Spell blocked for untrained characters and non-arcane-prepared spellcasters.

## Failure Modes
- [ ] `[TEST-ONLY]` Borrow Arcane Spell retry blocked until next prep on failure.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
