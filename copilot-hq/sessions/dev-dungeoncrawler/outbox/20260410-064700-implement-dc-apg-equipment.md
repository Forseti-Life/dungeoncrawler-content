The catalog already has all APG items but uses `'source'` instead of `'source_book'`. I need to: rename the key, add filtering support to the service, and update the controller. Let me implement this:

Good. Now add `VALID_BOOKS` constant and a `getBySourceBook` method to the service:

Now update the controller to support `?source_book=` filtering:

All 35 APG items returned with `source_book: apg`. Let me run the rest of the verifications:

All verifications pass. Now commit and write the outbox:

- Status: done
- Summary: Implemented APG equipment catalog extension (dc-apg-equipment). The APG items were already in the CATALOG but used a `'source'` key instead of the required `'source_book'` discriminator. Renamed all 35 APG entries, added `VALID_BOOKS` constant, `getBySourceBook()` and `getByCriteria()` methods to the service, and updated the controller to accept `?source_book=` with validation. All AC verifications pass — `source_book=apg` → 35 items, `source_book=crb` → 33 items, `source_book=all` → 68, combined type+source_book filter works, invalid value → 400. Committed forseti.life at `b1d931906`, HQ outbox at `505ae1cbc`.

## Next actions
- None — awaiting QA Gate 2 (TC-EQP-01 through TC-EQP-16)

## Blockers
- None

## ROI estimate
- ROI: 30
- Rationale: APG equipment extends build options across all classes; required for APG character options to be usable in the equipment selector.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-064700-implement-dc-apg-equipment
- Generated: 2026-04-10T11:23:43+00:00
