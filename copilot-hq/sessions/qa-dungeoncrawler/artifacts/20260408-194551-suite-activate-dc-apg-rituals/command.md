# Suite Activation: dc-apg-rituals

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-rituals"`**  
   This links the test to the living requirements doc at `features/dc-apg-rituals/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-rituals-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-rituals",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-rituals"`**  
   Example:
   ```json
   {
     "id": "dc-apg-rituals-<route-slug>",
     "feature_id": "dc-apg-rituals",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-rituals",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-rituals

## Coverage summary
- AC items: ~12 (system extensibility, APG ritual catalog, integration checks, edge cases)
- Test cases: 7 (TC-RIT-01–07)
- Suites: playwright (downtime)
- Security: AC exemption granted (no new routes)

---

## TC-RIT-01 — Ritual system accepts new entries without structural changes
- Description: APG rituals load into ritual database alongside CRB rituals; no separate APG list; same ritual selection UI
- Suite: playwright/downtime
- Expected: APG ritual entries queryable from same table/source as CRB rituals; UI ritual list includes both
- AC: System-1

## TC-RIT-02 — Ritual stat block completeness: all required fields stored
- Description: Each APG ritual stores: level, casting time, cost components, primary check skill + minimum proficiency, secondary casters + their checks, targets, effect description
- Suite: playwright/downtime
- Expected: ritual detail view shows all fields populated; no null/missing required fields
- AC: Catalog-1

## TC-RIT-03 — Ritual: multiple secondary casters pattern supported
- Description: APG rituals commonly require multiple secondary casters each with potentially different check skills; system stores secondary checks as arrays
- Suite: playwright/downtime
- Expected: secondary_checks = array (multi-item); UI shows each secondary caster role with assigned skill
- AC: Catalog-2, System-3

## TC-RIT-04 — Rare/Uncommon rituals: GM-approval gate
- Description: APG rituals flagged as Uncommon/Rare require GM approval to access
- Suite: playwright/downtime
- Expected: rare/uncommon rituals gated; non-approved character cannot initiate; GM override unlocks
- AC: Catalog-3

## TC-RIT-05 — Integration: primary check modifier stored per-ritual (not hardcoded)
- Description: Each ritual's primary check modifier and proficiency minimum are stored in the ritual record, not hardcoded in UI
- Suite: playwright/downtime
- Expected: different rituals display different primary check requirements; data-driven (not UI-hardcoded)
- AC: Integration-1

## TC-RIT-06 — Edge: ritual with 0 secondary casters is valid
- Description: Primary-caster-only rituals do not require secondary caster UI to appear
- Suite: playwright/downtime
- Expected: ritual with secondary_casters = 0 renders correctly; no secondary-caster error; primary check only
- AC: Edge-1

## TC-RIT-07 — Edge: APG ritual name collision with CRB ritual differentiates by book_id
- Description: If APG and CRB have same-named ritual, system differentiates by book_id
- Suite: playwright/downtime
- Expected: ritual lookup uses (name + book_id) composite key; no inadvertent merge/override
- AC: Edge-2

### Acceptance criteria (reference)

# Acceptance Criteria: APG Rituals

## Feature: dc-apg-rituals
## Source: PF2E Advanced Player's Guide, Chapter 5 (Rituals — APG New Rituals)

---

## System Requirements

- [ ] Ritual system from Core Rulebook accommodates addition of new rituals without structural changes
- [ ] All rituals track: casting time, cost components, primary check skill + minimum proficiency level, secondary casters and their associated checks
- [ ] APG ritual entries loaded into the ritual database alongside CRB rituals

---

## APG New Ritual Catalog

- [ ] Each APG ritual has a complete stat block: level, casting time, cost, primary check skill/rank, secondary checks, targets, and effect description
- [ ] Ritual system correctly handles the "multiple secondary casters" pattern common in APG rituals
- [ ] Ritual UI displays APG rituals in the same ritual selection interface as CRB rituals (no separate APG list)
- [ ] Rare rituals flagged as Uncommon/Rare with GM-approval gate

---

## Integration Checks

- [ ] Existing CRB ritual infrastructure accepts new ritual entries via the standard ritual data format
- [ ] Primary check modifier (and associated proficiency minimum) stored per-ritual, not hardcoded in UI
- [ ] Cost components (material costs, components, etc.) tracked per ritual entry
- [ ] Secondary caster check skills stored as arrays (rituals may require multiple different secondary checks)

## Edge Cases

- [ ] Ritual with 0 secondary casters: valid — primary-caster-only rituals should not require a secondary caster UI
- [ ] APG ritual added with same name as a CRB ritual: system should differentiate by book_id
- [ ] Ritual cost validation: if cost includes rare/valuable materials, system flags but does not block (GM adjudication)
- Agent: qa-dungeoncrawler
- Status: pending
