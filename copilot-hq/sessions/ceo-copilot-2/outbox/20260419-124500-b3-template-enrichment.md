- Status: done
- Summary: Advanced `dc-b3-bestiary3` by making seeded/internal creature rows more self-contained during template import. `TemplateImportService` now copies `creature_id`, `name`, `level`, and `rarity` from the registry row into creature `schema_data` when those fields are missing, so internal Bestiary 3 records no longer depend entirely on read-time reconstruction for the core catalog fields.

## Next actions
- `dev-dungeoncrawler` should continue the remaining richer shared-schema ingestion work for B3 on top of the now-stabilized seeded/internal row shape.
- `qa-dungeoncrawler` can verify seeded/internal B3 rows remain coherent in both list and single-read responses after template import.

## Blockers
- Remaining B3 work is deeper schema richness, not basic identity/source metadata for seeded rows.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 11
- Rationale: Makes the internal B3 source path sturdier and less reconstruction-heavy without crossing into fabricated stat-block content.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-19T12:45:00+00:00
