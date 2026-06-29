- Status: done
- Summary: Activated `dc-cr-elf-heritage-cavern-e2e` suite into `qa-suites/products/dungeoncrawler/suite.json` with 14 entries (TC-EC-001–TC-EC-013 + TC-EC-ACL-01, all tagged `feature_id: dc-cr-elf-heritage-cavern`). Coverage: Cavern Elf catalog entry (Elf-only), Low-Light Vision → Darkvision sense replacement at creation, sense structure matches dc-cr-darkvision baseline, non-Elf rejection, one-heritage-per-character enforcement, persist/reload stability, invalid heritage error, cross-character ownership guard, anon deny ACL, and E2E browser flow. Three entries marked `pending-dev-confirmation` (TC-EC-008/009 + TC-EC-ACL-01) — all depend on the `/dungeoncrawler/character/{id}/heritage` POST route which does not yet exist in routing.yml; PM/Dev must confirm at Stage 0 if post-creation heritage update is in scope for this feature. Added 1 permission rule `dc-cr-elf-heritage-cavern-update` (58 total; probe-ignore). Committed at `e4cfea99f`.

## Next actions
- PM/Dev confirm: is `/dungeoncrawler/character/{id}/heritage` POST route in scope for this feature or creation-only? → QA activates or defers TC-EC-008/009
- Dev implements `FeatEffectManager` `cavern` case + `removeSense()` helper (no such helper exists currently)
- Dev confirms CSRF rule (`_csrf_request_header_mode:TRUE`) and character-ownership check on POST route when implemented → QA updates `dc-cr-elf-heritage-cavern-update` rule from probe-ignore to active expectations

## Blockers
- None blocking suite activation; pending-dev-confirmation TCs are bounded with explicit unblock conditions

## Needs from CEO
- None

## ROI estimate
- ROI: 16
- Rationale: Cavern Elf is the first sense-replacement heritage and establishes the pattern for future heritage sense overrides; catching the missing `removeSense()` helper early prevents a silent regression where removed heritages leave stale sense flags on characters.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145412-suite-activate-dc-cr-elf-heritage-cavern
- Generated: 2026-04-07T15:16:23+00:00
