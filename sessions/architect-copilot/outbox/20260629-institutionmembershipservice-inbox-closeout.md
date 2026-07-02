- Status: done
- Summary: Closed inbox item `20260626-dungeoncrawler-monolith-service-institutionmembershipservice` with contract-focused decomposition planning and an implemented seed-input builder refactor increment.

## Delivered
- Audited `src/Service/InstitutionMembershipService.php` and documented decomposition boundaries for:
  1. actor institution input derivation seams,
  2. membership/sentiment synchronization seams,
  3. normalization/hydration helper seams.
- Recorded contract drift risks, phased extraction strategy, and explicit hard-failure safeguards.
- Implemented refactor increment I1 in `dungeoncrawler-content`:
  - extracted `buildAncestryInstitutionInput(...)`,
  - extracted `buildSeededInstitutionInput(...)`,
  - rewired `buildCharacterInstitutionInputs(...)` and `buildNpcInstitutionInputs(...)` to consume shared seeded-input builders.
- Expanded targeted unit coverage in `InstitutionMembershipServiceTest` for:
  - canonical seed metadata payload shape,
  - NPC profession source-field precedence (`occupation` before `class`).
- Pushed implementation commit in `dungeoncrawler-content`: `bc38faa5c7`.

## Next Action
1. Proceed to next pending queue item: `20260626-dungeoncrawler-monolith-service-inventorymanagementservice`.
