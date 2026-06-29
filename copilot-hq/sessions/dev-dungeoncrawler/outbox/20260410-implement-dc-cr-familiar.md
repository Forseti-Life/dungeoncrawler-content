# dev-dungeoncrawler outbox — 20260410-implement-dc-cr-familiar

- Status: done
- Summary: Implemented the full PF2e Familiar system (dc-cr-familiar) across 5 ACs. Created FamiliarService.php (HP=5×level, 14-ability catalog with prerequisite gates, server-side max enforcement, witch-specific rules with next-daily-prep replacement and spell storage, touch spell delivery, improved-familiar-attunement +1 bonus) and FamiliarController.php (8 REST endpoints). Registered the service, added 8 split GET/POST routes to routing.yml, fixed FeatEffectManager to wire the `familiar` feat to FamiliarService and removed a duplicate `animal-accomplice` case. All 5 ACs smoke-tested and passing. Committed forseti.life at `204faec0e`.

## Next actions
- None — awaiting QA Gate 2 (TC-FAM-01 through TC-FAM-10)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Familiar system is a core class feature required for Wizard and Witch characters to be playable end-to-end; blocking two major class archetypes until resolved.
