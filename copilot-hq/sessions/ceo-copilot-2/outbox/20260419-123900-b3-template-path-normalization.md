- Status: done
- Summary: Completed the third Bestiary 3 normalization layer for Dungeoncrawler by landing template-seeding support in `TemplateImportService`. Internal `dungeoncrawler_content_registry` example rows that only carry legacy `source_book` / tag-backed creature metadata now persist canonical `bestiary_source` during template import, matching the already-landed catalog read-path and JSON write/import-path normalization.

## Next actions
- `dev-dungeoncrawler` should continue the remaining richer shared-schema ingestion work for B3 using the now-consistent internal structured inventory path.
- `qa-dungeoncrawler` can verify `source=b3` behavior against seeded registry examples as well as JSON-imported creature rows.

## Blockers
- Remaining B3 work is shared-schema depth, not source metadata consistency.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 13
- Rationale: Closed the last major B3 metadata inconsistency between seeded registry examples and JSON import flows, reducing release-q QA ambiguity and future import drift.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-19T12:39:00+00:00
