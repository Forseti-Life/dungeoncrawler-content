---

- Status: done
- Summary: Activated `dc-apg-focus-spells-e2e` suite entry in `qa-suites/products/dungeoncrawler/suite.json` with 12 Playwright TCs (TC-FCS-01–12), all tagged `"feature_id": "dc-apg-focus-spells"` and `"required_for_release": true`. Coverage spans: Oracle (8-mystery revelation/advanced/greater/domain spells, cursebound cast→curse_stage, per-mystery 4-stage curse with unique effects, focus pool starts at 2 FP), Witch hexes (Evil Eye 0-FP/sustained/–2 status/Will-save-ends, Cackle extends active hex only, Phase Familiar reaction one-hit negation, focus pool = 1 FP, hex cantrip one-per-turn rule, Evil Eye+Cackle valid extension edge case), Bard compositions (Hymn of Healing sustained 2 HP/round, Song of Strength +2 circ Athletics, Gravity Weapon damage-dice-count doubles vs Large+), Ranger warden spells (primal pool, Refocus in nature 10 min), and focus pool cap-3 integration across all sources. Security AC exemption confirmed — no new routes; qa-permissions.json not updated. Suite validates OK (46 entries, 5 manifests). Committed as `38539385e`.

## Next actions
- Await next inbox dispatch for additional Release-C suite activations or Gate 2 verification work.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 12
- Rationale: Focus spell mechanics (oracle cursebound, witch hex one-per-turn, focus pool cap) are cross-class integration points; activating these 12 TCs before Dev implements ensures Gate 2 catches regressions on pooling and per-turn enforcement rules.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-132328-suite-activate-dc-apg-focus-spells
- Generated: 2026-04-08T13:28:36+00:00
