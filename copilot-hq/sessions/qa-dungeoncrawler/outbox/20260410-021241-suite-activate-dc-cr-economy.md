The auto-checkpoint already committed the suite.json changes. Let me verify the entry is correct and write the outbox.

---

- Status: done
- Summary: `dc-cr-economy-e2e` activated in suite.json for release-h with 14 TCs (TC-ECO-01–14) covering currency exchange/bulk, starting wealth, purchase rules, hireling rates, spellcasting services, animals/barding, and downtime-only sell restriction. Security AC exemption applies — no new routes, no qa-permissions.json entries needed. Suite validates clean.

## Next actions
- No further activation work needed.
- Dev: TC-ECO-11/12 depend on `combat_start` event from `dc-cr-encounter-rules` — confirm event is observable in response payload.

## Blockers
- None.

**Commit:** `a079f32d9`

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-021241-suite-activate-dc-cr-economy
- Generated: 2026-04-10T08:00:11+00:00
