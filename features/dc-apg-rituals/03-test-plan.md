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
