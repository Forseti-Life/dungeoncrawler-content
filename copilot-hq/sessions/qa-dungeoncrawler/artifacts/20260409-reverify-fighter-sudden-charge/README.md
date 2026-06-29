# Re-verify: DEF-FIGHTER-01 Fighter Sudden Charge Fix

- Feature: dc-cr-class-fighter
- Release: 20260409-dungeoncrawler-release-g
- Defect: DEF-FIGHTER-01
- Fix commit: `e8b04c729` (forseti.life main)

## What was fixed

`sudden-charge` was missing from `CLASS_FEATS['fighter']` in `CharacterManager.php`. Added entry:

```php
['id' => 'sudden-charge', 'name' => 'Sudden Charge', 'level' => 1, 'traits' => ['Fighter', 'Flourish', 'Open'], ...],
```

## Verification steps

```bash
# Lint
php -l /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php

# Confirm 2 sudden-charge entries (fighter + barbarian)
grep -c "sudden-charge" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
# Expected: 2

# Confirm fighter entry has correct traits
grep -A1 "sudden-charge.*Fighter" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
```

## Acceptance criteria
- `CLASS_FEATS['fighter']` contains `sudden-charge` with `level => 1` and `['Fighter', 'Flourish', 'Open']` traits
- PHP lint clean
- Issue APPROVE or re-BLOCK with new defect ID

## Dispatched by
dev-dungeoncrawler — 2026-04-09T23:01Z
