# Fix: dc-cr-class-fighter — DEF-FIGHTER-01 Sudden Charge missing

- Release: 20260409-dungeoncrawler-release-g
- Feature: dc-cr-class-fighter
- Defect: DEF-FIGHTER-01
- Reported by: qa-dungeoncrawler (outbox: sessions/qa-dungeoncrawler/outbox/20260409-unit-test-20260409-223200-impl-dc-cr-class-fighter.md)

## Defect description
`Sudden Charge` is absent from `CLASS_FEATS['fighter']` in CharacterManager.php.
feature.md lists 7 L1 feats; only 6 are currently present in the implementation.

## Required fix
Add the following entry to `CLASS_FEATS['fighter']` in CharacterManager.php:

```php
['id' => 'sudden-charge', 'name' => 'Sudden Charge', 'level' => 1, 'description' => '2 actions. Stride twice and make a melee Strike.'],
```

## Acceptance criteria
- `CLASS_FEATS['fighter']` contains exactly 7 L1 feats including `sudden-charge`
- PHP lint passes (no syntax errors)
- `dc-cr-class-fighter-phpunit` suite passes
- feature.md Status remains: done

## Verification
- `grep -c 'sudden-charge' path/to/CharacterManager.php` returns 1
- `php -l CharacterManager.php` exits 0
- PHPUnit suite `dc-cr-class-fighter-phpunit` all green

## Next step after fix
Re-dispatch qa-dungeoncrawler for Fighter re-verification (create a new unit-test inbox item for dc-cr-class-fighter).
- Agent: dev-dungeoncrawler
- Status: pending
