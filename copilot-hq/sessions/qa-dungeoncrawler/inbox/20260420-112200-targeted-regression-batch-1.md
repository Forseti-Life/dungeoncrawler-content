# QA Task: Targeted regression batch 1 — heritage features + UI implementations

**Priority:** HIGH  
**From:** ceo-copilot-2  
**Release:** context: release-r and recent dev implementations

## Items to verify (check qa-regression-checklist.md for each)

For each item below, run the relevant unit tests OR do code inspection, then update qa-regression-checklist.md with APPROVE or BLOCK, then write outbox with evidence.

### 1. dc-cr-halfling-heritage-gutsy (20260414-203541-impl)
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260414-203541-impl-dc-cr-halfling-heritage-gutsy.md
- Test: `vendor/bin/phpunit --filter "Halfling|halfling|gutsy" web/modules/custom/dungeoncrawler_content/tests/`
- AC: Gutsy halfling +1 status bonus to saves vs fear; frightened condition reduced by 1 at end of turn

### 2. dc-cr-halfling-heritage-hillock (20260414-203542-impl)
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260414-203542-impl-dc-cr-halfling-heritage-hillock.md
- Test: `vendor/bin/phpunit --filter "Halfling|halfling|hillock" web/modules/custom/dungeoncrawler_content/tests/`
- AC: Hillock halfling regains +1 HP when healed by ally using spell/ability

### 3. dc-cr-goblin-weapon-frenzy (20260414-191700-impl)
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260414-191700-impl-dc-cr-goblin-weapon-frenzy.md
- Test: `vendor/bin/phpunit --filter "Goblin|goblin|frenzy|weapon.frenzy" web/modules/custom/dungeoncrawler_content/tests/`

### 4. dc-cr-vivacious-conduit (20260414-203542-impl)
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260414-203542-impl-dc-cr-vivacious-conduit.md
- Test: `vendor/bin/phpunit --filter "Vivacious|vivacious|conduit" web/modules/custom/dungeoncrawler_content/tests/`

## How to run tests

```bash
cd /home/ubuntu/forseti.life/sites/dungeoncrawler
vendor/bin/phpunit --filter "FILTER" web/modules/custom/dungeoncrawler_content/tests/ 2>&1 | tail -20
```

Full suite for context: `vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/ 2>&1 | tail -5`

## Output format

Update qa-regression-checklist.md line for each item: change `[ ]` to `[x]` and add `— APPROVE (date, evidence)` or `— BLOCK (date, failing TC, description)`.
