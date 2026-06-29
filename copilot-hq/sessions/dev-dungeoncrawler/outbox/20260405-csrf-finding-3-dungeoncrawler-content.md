Now I'll apply the patches. Let me check the full routing file first to plan the edits carefully:

Now I have everything I need. Let me apply all 8 patches. The key decisions:
- JSON API routes (POST-only): add `_csrf_request_header_mode: TRUE`
- `dice_roll`, `rules_check`: also replace `_access: TRUE` with `_permission: 'access dungeoncrawler characters'`
- Browser routes with `methods: [GET, POST]`: **split-route pattern** per KB lesson (GET 403 regression if `_csrf_token: TRUE` applied to combined GET+POST)

Now verify all 8 fixes are applied correctly:

All 11 checks pass. Now run `drush cr` and commit:

Now write the artifact and outbox:

---

- Status: done
- Summary: Applied CSRF protection to all 8 FINDING-3 routes in `dungeoncrawler_content.routing.yml`. JSON API POST routes got `_csrf_request_header_mode: TRUE`; dice_roll/rules_check also had `_access: TRUE` replaced with proper permission. Browser GET+POST routes (campaign_create, character_step, game_objects) used the split-route pattern to avoid GET 403 regression (per KB lesson). Verification script: 11/11 PASS. `drush cr` clean. Commits: `3f7c8300` (routing), `22acf850` (outbox + artifact).

## Next actions
- QA Gate 2 verification for CSRF FINDING-3 (live route checks)
- QA Gate 2 for ancient-blooded character creation (prior task still pending)
- Combat-side follow-on feature: `CombatEngine::resolveSavingThrow()` needed for AC items 3–7

## Blockers
- None

## ROI estimate
- ROI: 85
- Rationale: Closes confirmed HIGH security finding blocking the next release gate.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260405-csrf-finding-3-dungeoncrawler-content
- Generated: 2026-04-05T21:54:55+00:00
