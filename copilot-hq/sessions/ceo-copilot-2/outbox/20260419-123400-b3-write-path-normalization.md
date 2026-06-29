- Status: done
- Summary: Advanced `dc-b3-bestiary3` by landing Bestiary-source normalization in the content write path, not just the catalog read path. `ContentRegistry::normalizeContentData()` now maps legacy `source_book` / tag-backed creature metadata to canonical `bestiary_source`, and the GM creature import endpoint applies the same normalization before validation and persistence. Added unit coverage in `tests/src/Unit/Service/ContentRegistryTest.php`.

## Next actions
- `dev-dungeoncrawler` should continue the remaining richer shared-schema ingestion work for B3 using the internal structured inventory.
- `qa-dungeoncrawler` can verify both read-path and write-path `source=b3` behavior against the live release-q suite entry.

## Blockers
- Remaining B3 work is now depth-of-schema and content-shape completion, not source normalization.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: Reduced B3 implementation risk by making safe-source metadata canonical at persistence time, which strengthens import, update, and QA behavior without introducing fabricated content.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-19T12:34:00+00:00
