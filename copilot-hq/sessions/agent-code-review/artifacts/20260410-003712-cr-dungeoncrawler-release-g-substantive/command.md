# Code Review — dungeoncrawler release-g (substantive)

- Site: dungeoncrawler
- Release id: 20260409-dungeoncrawler-release-g
- Base SHA: 96fd33c7a
- Head: HEAD (main)
- Scope: sites/dungeoncrawler/

## Context
The first code review for this release was a fast-exit (no code had landed yet). All 7 class features have now been implemented and QA-approved. A substantive review is required before the official push (Gate 1b).

## Commits since base SHA (96fd33c7a)
- 2948d6c31: feat(dungeoncrawler): complete Investigator CLASS_ADVANCEMENT milestones L3-L19
- 2a8d950ea: fix(dungeoncrawler): merge duplicate L11 key in swashbuckler CLASS_ADVANCEMENT
- 0058192ba: feat(dungeoncrawler): complete Fighter class mechanics
- 593e93ce0: feat(dungeoncrawler): complete Rogue class mechanics
- e8b04c729: Fix DEF-FIGHTER-01: add sudden-charge to CLASS_FEATS[fighter]
- 11fbf3abc: feat(dungeoncrawler): complete Sorcerer class mechanics
- 4f612f4a3: feat(dungeoncrawler): complete Wizard class mechanics

## Focus areas
- CharacterManager.php: new CLASS_FEATS entries for 7 classes (investigator, oracle, swashbuckler, fighter, rogue, sorcerer, wizard)
- Any new service methods added for class-specific mechanics (bloodlines, arcane_thesis, etc.)
- Security: no new routes or endpoints expected (data-only class arrays)
- PF2E compliance: verify feat/ability names match rulebook references in feature.md

## Acceptance criteria
- All MEDIUM+ findings dispatched to dev-dungeoncrawler inbox before push
- APPROVE verdict or explicit BLOCK with finding list
- Agent: agent-code-review
- Status: pending
