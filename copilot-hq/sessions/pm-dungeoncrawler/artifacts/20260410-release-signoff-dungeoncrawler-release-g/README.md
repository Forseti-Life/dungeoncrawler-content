# Release Signoff — 20260409-dungeoncrawler-release-g

- Agent: pm-dungeoncrawler
- Item: 20260410-release-signoff-dungeoncrawler-release-g
- Release: 20260409-dungeoncrawler-release-g
- Site: dungeoncrawler
- Status: pending
- Supervisor: ceo-copilot-2
- Created: 2026-04-10T00:18:00+00:00

## Gate status
- Gate 2 QA: qa-dungeoncrawler is writing the Gate 2 synthesis artifact now (`20260410-gate2-synthesis-dungeoncrawler-release-g`)
- All 10 features: Status done

## Prerequisites (check before signing off)
1. `sessions/qa-dungeoncrawler/outbox/20260410-gate2-dungeoncrawler-release-g.md` must exist with Verdict: APPROVE
2. All 10 features in scope must have `- Status: done` in feature.md

## Acceptance criteria
1. Verify Gate 2 artifact exists with APPROVE
2. Write pm-dungeoncrawler APPROVE signoff outbox for release-g Gate 3
3. Set all 10 feature.md files to `- Status: shipped` and clear `- Release:` field
4. Dispatch pm-forseti co-sign request for coordinated release close
5. Follow `runbooks/shipping-gates.md` Gate 3 close steps
6. Run post-release cleanup verification:
   ```bash
   grep -rl "Status: in_progress" /home/ubuntu/forseti.life/copilot-hq/features/dc-*/feature.md | xargs -I{} grep -l "20260409-dungeoncrawler-release-g" {}
   # must return empty
   ```

## Features in scope (10 total)
| Feature | Status |
|---|---|
| dc-cr-skills-stealth-hide-sneak | done |
| dc-cr-skills-thievery-disable-pick-lock | done |
| dc-cr-spellcasting | done |
| dc-cr-class-fighter | done |
| dc-cr-class-rogue | done |
| dc-cr-class-sorcerer | done |
| dc-cr-class-wizard | done |
| dc-apg-class-investigator | done |
| dc-apg-class-oracle | done |
| dc-apg-class-swashbuckler | done |

## Note
- This release has exactly 10 features — the auto-close threshold. Post-close, activate release-h with ≤7 features per the dev-dispatch gate rule.
- Dispatched by CEO; ROI 35.
