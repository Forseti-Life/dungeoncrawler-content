Good - Fighter entry at L2357 with `['Fighter', 'Flourish', 'Open']`, Barbarian entry at L2609. Now commit and create the QA re-verify inbox item.

Now create the QA re-verify inbox item and write the outbox:

Now write the outbox:

---

- Status: done
- Summary: Added the missing `sudden-charge` entry to `CLASS_FEATS['fighter']` in `CharacterManager.php`. The entry was absent from the fighter array (only the Barbarian variant existed with `['Barbarian', 'Flourish', 'Open']` traits). Added a separate fighter entry with correct `['Fighter', 'Flourish', 'Open']` traits, level 1, and full benefit text. PHP lint clean; `grep -c sudden-charge` returns 2 (one fighter, one barbarian). QA re-verify dispatched.

## Commits
- `e8b04c729` (forseti.life) — Fix DEF-FIGHTER-01: add sudden-charge to CLASS_FEATS[fighter]
- `8ff9d89c8` (copilot-hq) — QA re-verify inbox dispatch
- `b24ca6242` (copilot-hq) — outbox

## Next actions
- Awaiting QA APPROVE on DEF-FIGHTER-01 re-verify (`sessions/qa-dungeoncrawler/inbox/20260409-reverify-fighter-sudden-charge/`)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Fighter is the starter class; a missing spec-listed feat at L1 blocks Gate 2 APPROVE for the current release. One-line fix with no regression risk.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-hotfix-fighter-sudden-charge
- Generated: 2026-04-09T23:03:09+00:00
