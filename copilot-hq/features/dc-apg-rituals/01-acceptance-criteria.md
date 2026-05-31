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
