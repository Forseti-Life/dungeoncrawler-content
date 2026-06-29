- Status: blocked
- Summary: Verified dc-cr-dwarf-heritage-ancient-blooded for dungeoncrawler (website: dungeoncrawler / module: dungeoncrawler_content / ReactionHandler + CharacterApiController / feature: dc-cr-dwarf-heritage-ancient-blooded). Data layer PASS: `ancient-blooded-dwarf` appears in `/ancestries/dwarf` with correct `granted_abilities`, `special.reaction` block (trigger, bonus, once_per_turn, duration), heritage-ID fix confirmed, server-side ancestry validation and ownership ACL confirmed (commit `f73575471`). BLOCK on three gaps: (1) `ReactionHandler::executeReaction()` has no `case 'call-on-ancient-blood'` — 7 runtime TCs (HAB-003–007, 009, 013) fall to default error; (2) no heritage immutability guard after `wizard_complete=true` — TC-HAB-014 FAIL; (3) feature AC requires a dedicated `POST /dungeoncrawler/character/{id}/heritage` route with CSRF — route is absent, step-save path lacks `_csrf_request_header_mode`. PM decision needed on the route discrepancy.

## Next actions
- Dev to add `case 'call-on-ancient-blood':` to `ReactionHandler.php` and wire saving-throw resolution to dispatch it (unblocks HAB-003–007, 009, 013)
- Dev to add heritage lock guard in `CharacterApiController` when `status=1` (unblocks HAB-014)
- PM to decide: add dedicated `POST /dungeoncrawler/character/{id}/heritage` route per AC, or update AC to accept the existing step-save path
- QA re-runs TCs HAB-003–014 after Dev ships fixes

## Blockers
- `ReactionHandler::executeReaction()` missing `case 'call-on-ancient-blood':` — runtime reaction execution completely non-functional (TCs HAB-003–007, 009, 013 FAIL)
- No heritage immutability guard when `wizard_complete=true` (TC-HAB-014 FAIL)
- Heritage route discrepancy: AC specifies dedicated CSRF-protected POST route; route does not exist

## Needs from Supervisor
- PM decision: should Dev add a dedicated `POST /dungeoncrawler/character/{id}/heritage` route matching the current AC, or should the AC be updated to accept the existing step-save path as the heritage endpoint?

## Decision needed
- Should the dedicated `POST /dungeoncrawler/character/{id}/heritage` route (with `_csrf_request_header_mode`) be implemented as written in the AC, or should PM update the AC to accept the existing step-save path? This determines whether Dev implements a new route or QA adjusts its coverage expectation.

## Recommendation
- Implement the dedicated route per AC. A named heritage route is self-documenting and consistent with the ancestry/spell/equipment endpoints; the step-save path conflates character progression state with heritage selection. Tradeoff: adds ~1 Dev cycle to release-b; accepting the step-save path would ship faster but leaves the API inconsistent and harder to extend for other heritage reactions.

## Commits
- `f73575471` — data-layer verification pass + regression checklist update

## ROI estimate
- ROI: 35
- Rationale: First heritage reaction in the system; unblocking validates the reaction pattern for all future heritages. Missing runtime wiring means the core advertised mechanic is completely non-functional for Dwarf players.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-021500-implement-dc-cr-dwarf-heritage-ancient-blood
- Generated: 2026-04-10T22:37:00+00:00
