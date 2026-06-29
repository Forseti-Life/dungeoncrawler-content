Good — there's already a rule covering `/characters/create` routes. Now let me check the full command.md for remaining TCs (14+) and build the suite entry.

I have all 14 TCs from the command.md. Now let me enrich the existing suite entry and add permission rules.

The actual route is `/characters/create` (already covered by `auth-required-create`). The test plan's `/dungeoncrawler/character/create` was illustrative — the real path is `/characters/create`. No new permission rules needed for TC-HS-12.

The `dungeoncrawler-traits-catalog` rule is already tagged `feature_id: dc-cr-heritage-system` — that's fine. No new discrete routes are introduced by heritage-system (it's form UI + validation only). Let me validate and commit.

Commit `ea58d620`. Outbox:

---

- Status: done
- Summary: Activated dc-cr-heritage-system test suite. Enriched the existing stub entry `dc-cr-heritage-system-phpunit` in `suite.json` with 14 test cases covering: heritage count-per-ancestry unit assertion (TC-HS-01), required-field validation with inline error (TC-HS-02), server-side ancestry/heritage mismatch returning 400 (TC-HS-03), valid save and id-stored assertions (TC-HS-04–05), character summary display name (TC-HS-06), AJAX dropdown repopulate and reset on ancestry change (TC-HS-07–08), Human single-option constraint (TC-HS-09), wizard progression (TC-HS-10), back-navigation heritage clear (TC-HS-11), anonymous 403 on character creation (TC-HS-12), and auth/admin access (TC-HS-13–14). Fixed command to `--filter=Heritage`. No new permission rules needed: `/characters/create` is already covered by `auth-required-create`; `dungeoncrawler-traits-catalog` is already tagged `feature_id: dc-cr-heritage-system`. TC-HS-06/07/08/10/11 flagged with Playwright dependency note. Suite validates OK. Commit: `ea58d620`.

## Next actions
- Process next inbox item: `dc-cr-skill-system` suite activation (final item in this activation batch)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Heritage validation gaps (required enforcement, mismatch 400) are new enforcement paths that will fail silently without test coverage. 14 TCs with 5 Playwright-backed AJAX interaction tests give Dev a complete regression harness for the most interactive step in the character creation wizard.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-020529-suite-activate-dc-cr-heritage-system
- Generated: 2026-04-06T11:06:15+00:00
