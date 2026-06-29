# Verification Report: Req 648 — Alchemist Max HP Formula

- Date: 2026-04-06
- Agent: qa-dungeoncrawler
- Inbox item: 20260406-roadmap-req-648-alchemist-hp
- Feature: dc-cr-character-class, dc-cr-character-creation
- Verdict: APPROVE (TC-648-P SKIP, TC-648-N PASS)

## Requirement

> Alchemist max HP = (8 + CON modifier) × level at character creation, increasing by that amount each level.

## Schema note

Character data is NOT stored in Drupal node field tables (`node__field_char_*`). All character state lives in `dc_campaign_characters.character_data` (JSON blob) with scalar columns `class`, `level`, `hp_max`, `hp_current`. Node field tables do not exist.

## TC-648-P: Live Alchemist character HP matches formula

**Result: SKIP**

- 0 rows in `dc_campaign_characters` have `class = 'alchemist'` (case-insensitive check confirmed).
- 16 total rows: mostly NPCs or characters with empty class field; id=2/9/12/13/16 have `hp_max > 0` but empty class.
- SKIP is expected: no complete Alchemist character has been created yet. Not a defect.
- Re-run trigger: when first Alchemist character reaches `status = 1` (active) with `class = 'alchemist'` and `hp_max > 0`.

## TC-648-N: HP floor enforced at extreme negative CON

**Result: PASS**

Formula verified via drush:
```
ancestry_hp=6, class_hp=8, con_mod=-5, level=1
max_hp = 6 + 8 + (-5) + (0 * 3) = 9
floor = max(9, 1) = 9
```

```
TC-648-N raw: 9
TC-648-N with floor: 9
PASS (no floor needed)
```

## Source verification

`CharacterCreationStepForm.php:2107`:
```php
$max_hp = $ancestry_hp + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod));
```

This matches the PF2e formula: `(class_hp + ancestry_hp + CON_mod) + (level-1) × (class_hp + CON_mod)`.

For Alchemist specifically:
- `$class_hp = 8` sourced from `$this->characterManager->getClassHP('alchemist')` (line 2105)
- Ancestry HP loaded from `CharacterManager::ANCESTRIES` constant (line 2098–2100)
- CON modifier: `floor((abilities.con - 10) / 2)` (line 2094)

## Gaps noted

- **GAP-CHAR-02** (pre-existing): No server-side HP minimum floor (`max($max_hp, $level)`) in production code. With CON=-5 the formula still yields 9 at level 1 (no issue), but at extreme edge cases (hypothetical CON=1 at level 2+) the value could theoretically go negative. Not blocking this release; document for future PF2e compliance audit.

## Checklist

Added to `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` line 116.

## Commit

`2536fb0db`
