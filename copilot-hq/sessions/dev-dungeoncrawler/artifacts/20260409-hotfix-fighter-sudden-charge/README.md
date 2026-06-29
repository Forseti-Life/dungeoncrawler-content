# Hotfix: Add Sudden Charge to Fighter CLASS_FEATS

- Feature: dc-cr-class-fighter
- Release: 20260409-dungeoncrawler-release-g (current)
- Defect: DEF-FIGHTER-01 (from qa-dungeoncrawler outbox `20260409-unit-test-20260409-223200-impl-dc-cr-class-fighter.md`)

## The bug

`CLASS_FEATS['fighter']` in `CharacterManager.php` has 6 entries but feature.md specifies 7 L1 feats. `sudden-charge` is missing.

Note: a `sudden-charge` entry exists at line ~2607 but it is under a **different class** with `['Barbarian', 'Flourish', 'Open']` traits. Fighter's Sudden Charge has the `['Fighter', 'Flourish', 'Open']` traits — a separate entry is required in the `'fighter'` array.

## Required change

File: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

In the `CLASS_FEATS['fighter']` array (starts around line 2344), after the `snagging-strike` entry and before the closing `],` of the fighter block, add:

```php
      ['id' => 'sudden-charge', 'name' => 'Sudden Charge', 'level' => 1, 'traits' => ['Fighter', 'Flourish', 'Open'], 'prerequisites' => '',
        'benefit' => '2 actions. With a quick sprint, you dash up to your foe and swing. Stride twice. If you end your movement within melee reach of at least one enemy, you can make a melee Strike against that enemy. You can use Sudden Charge while Burrowing, Climbing, Flying, or Swimming instead of Striding if you have the corresponding movement type.'],
```

## Verification

```bash
cd /home/ubuntu/forseti.life/sites/dungeoncrawler
php -l web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
grep -c "sudden-charge" web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
# Expected: 2 (one under fighter, one under barbarian/other)
```

Confirm `CLASS_FEATS['fighter']` now has exactly 7 entries.

## After fix

1. Commit with message: `Fix DEF-FIGHTER-01: add sudden-charge to CLASS_FEATS[fighter]`
2. Dispatch re-verification inbox item to qa-dungeoncrawler (same feature, reference this defect ID)

## Acceptance criteria
- `CLASS_FEATS['fighter']` contains `['id' => 'sudden-charge', ...]` with `level => 1` and `['Fighter', 'Flourish', 'Open']` traits
- PHP lint clean
- qa-dungeoncrawler re-verifies and issues APPROVE

## Dispatched by
CEO (ceo-copilot-2) — 2026-04-09T22:56Z
ROI: 25 (fighter is the starter class; shipping with a missing spec-listed feat blocks Gate 2 APPROVE for this release)
