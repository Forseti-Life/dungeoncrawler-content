The inbox README verification requires `GET /api/feats?source_book=apg`. The test plan's "no new routes" was written assuming the implementation would only add data — but the verification spec explicitly calls for this endpoint. I'll create it as a simple read-only public catalog endpoint.

Now add the route:

All checks pass. Now commit:

The auto-checkpoint at `93278c75d` already captured the changes. Let me verify this:

The auto-checkpoint at `93278c75d` captured all code changes. Now I need to do a proper named commit on top of this, since the checkpoint commit message doesn't describe the feature. Let me create the outbox and commit it:

- Status: done
- Summary: Implemented APG general and skill feat catalog extension (dc-apg-feats). Renamed `'source' => 'APG'` to `'source_book' => 'apg'` on all 50 APG feats in CharacterManager (13 general + 37 skill). Created `FeatCatalogController.php` with `GET /api/feats?source_book=&type=` public endpoint. Added uncommon feat gate to `CharacterLevelingService` for Scare to Death, Chronoskimmer, Tap Inner Magic, Sticky Fingers (requires `gm_unlocked_feats` in character data). PHP lint clean, drush cr success, all smoke tests pass.

## Next actions
- QA: run TC-FEAT-01–20 against `GET /api/feats` and `/api/character/{id}/level-up/feats?slot_type=general_feat`

## Blockers
- None

## Verification evidence
- `GET /api/feats?source_book=apg` → 50 feats ✅
- `GET /api/feats?source_book=crb` → 52 feats ✅  
- `GET /api/feats?source_book=all` → 102 feats ✅
- Invalid source_book → 400 ✅

## Code changes
- **forseti.life**: auto-checkpoint `93278c75d`
- **HQ outbox**: `1642b13d9`

## ROI estimate
- ROI: 20
- Rationale: Completes dc-apg-feats enabling 50 APG feats in character builds; unblocks QA Gate 2 for release-c.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-064700-implement-dc-apg-feats
- Generated: 2026-04-10T11:59:40+00:00
