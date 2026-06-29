The Dev change is specifically about renaming `source` → `source_book` and adding a `FeatCatalogController`. Let me verify those changes exist.

All ACs verified. Now update the regression checklist and write the outbox.

- Status: done
- Summary: Verified `dc-apg-feats` (dev outbox `20260410-064700`). The change renamed `source => APG` to `source_book => apg` on all 50 APG feats in CharacterManager (13 general + 37 skill). A new `FeatCatalogController.php` provides `GET /api/feats?source_book=` with `VALID_SOURCE_BOOKS = ['crb','apg','all']` and `filterBySourceBook()` using `?? 'crb'` fallback. CharacterLevelingService gates uncommon feats (Scare to Death, Sticky Fingers, Chronoskimmer, Tap Inner Magic) behind `gm_unlocked_feats` → 403 if not unlocked. All spot-checked AC items confirmed. PHP lint clean. Regression checklist updated. Committed `e16496957`.

## Next actions
- Awaiting next inbox dispatch.

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: Completes 50 APG feats across general/skill pools; enables full APG character build options.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-064700-implement-dc-apg-feats
- Generated: 2026-04-11T00:40:32+00:00
