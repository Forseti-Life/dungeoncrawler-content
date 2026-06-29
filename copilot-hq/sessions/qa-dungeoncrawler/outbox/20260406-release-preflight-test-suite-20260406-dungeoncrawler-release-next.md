Release ID `20260406-dungeoncrawler-release-next` is new (prior outboxes only cover `20260322-dungeoncrawler-release-next`). QA-scoped changes since last preflight: `qa-permissions.json` was updated this session (32→34 rules, ancestry rules added). Scripts are unchanged. No new routing files committed. The ancestry permission rule additions were in-scope QA work — already committed in `5f2cdf0d`. Nothing more to change.

`release-next` is the registered next release ID. Scripts unchanged; `qa-permissions.json` was just updated (34 rules, correct). No config changes required for this preflight.

Update the seat instructions to record this preflight:

- Status: done
- Summary: Release preflight complete for `20260406-dungeoncrawler-release-next`. Full checklist run (new release ID — exception to dedup rule applies). Scripts `site-audit-run.sh`, `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, `role-permissions-validate.py` — all unchanged since release-c 20260406 preflight. `qa-permissions.json` updated during this session's suite activations: 6 roles, 34 rules (up from 32 — ancestry list route and mutation endpoint rules added). No new routing files since last preflight. Preflight date in seat instructions bumped to release-next 20260406. Commit: `c6390fa0`.

## Next actions
- Release-next scope is `20260406-dungeoncrawler-release-next` — no features currently tagged to it; scope activation is PM's next step
- Suite activations for release-b features (action-economy, ancestry-system) are complete this session and will carry forward to release-next if those features defer

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Routine preflight for the next planned release; main value is confirming `qa-permissions.json` is current (34 rules with ancestry additions) before any release-next features are dispatched to QA.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-release-preflight-test-suite-20260406-dungeoncrawler-release-next
- Generated: 2026-04-06T09:39:35+00:00
