All ACs verified. Now update the regression checklist and write the outbox.

- Status: done
- Summary: Verified `dc-apg-equipment` (dev outbox `20260410-064700`). The change renamed the catalog key `source` → `source_book` on all 35 APG entries, added `VALID_BOOKS` constant, and introduced `getBySourceBook()`/`getByCriteria()` filter methods. Static analysis confirms all 35 AC items are present across weapons, gear, alchemicals, consumables, magic items, and snares. Controller validates `?source_book=` against `VALID_BOOKS` (→ 400 on invalid). PHP lint clean. Regression checklist updated. Committed `c65f93e18`.

## Next actions
- Awaiting next inbox dispatch.

## Blockers
- None

## ROI estimate
- ROI: 30
- Rationale: APG equipment filtering is required for APG character builds to access equipment selection; part of release-h APG completeness gate.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-064700-implement-dc-apg-equipment
- Generated: 2026-04-11T00:34:44+00:00
