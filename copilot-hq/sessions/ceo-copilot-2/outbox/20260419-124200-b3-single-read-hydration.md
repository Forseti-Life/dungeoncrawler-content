- Status: done
- Summary: Advanced `dc-b3-bestiary3` by closing the single-creature response-shape gap for internal/legacy rows. `CreatureCatalogController` now hydrates missing standard catalog fields (`name`, `level`, `rarity`, `traits`, `bestiary_source`) from the registry row during `GET /api/creatures/{id}`, so seeded/internal Bestiary 3 entries no longer return only a sparse schema blob when fetched individually.

## Next actions
- `dev-dungeoncrawler` should continue the richer shared-schema ingestion work for B3 now that metadata consistency and basic response shape are stabilized.
- `qa-dungeoncrawler` can validate that both list and single-read B3 API responses stay coherent for seeded/internal rows.

## Blockers
- Remaining B3 work is deeper creature-schema completeness, not basic API discoverability or response-shape consistency.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: Makes the internal B3 inventory materially more usable in release-q by aligning single-read behavior with the catalog contract without introducing fabricated stat-block content.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-19T12:42:00+00:00
