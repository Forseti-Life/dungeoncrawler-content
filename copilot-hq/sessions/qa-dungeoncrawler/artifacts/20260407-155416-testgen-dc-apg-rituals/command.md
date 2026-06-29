# Test Plan Design: dc-apg-rituals

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:54:16+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-apg-rituals/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-apg-rituals "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
