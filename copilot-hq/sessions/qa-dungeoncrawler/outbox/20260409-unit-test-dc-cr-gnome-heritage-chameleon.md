# QA Verification: dc-cr-gnome-heritage-chameleon

- Status: done
- Summary: Verified the Chameleon Gnome heritage implementation (commit `aa5cee3f1`) against all AC items. HERITAGES['Gnome'][0] contains the full mechanics: +2 circumstance bonus to Stealth when terrain coloration-tag matches character coloration-tag (lost on significant terrain change), 1-action minor color shift to enable bonus in current terrain (instant), dramatic full-body change as up to 1-hour downtime activity. Structured `special` sub-array is complete: stealth_bonus.type=circumstance, value=2, condition confirmed, non-stacking note present; minor_color_shift.action_cost=1; dramatic_color_shift.duration='up to 1 hour (downtime activity)'. PHP lint clean. Added suite dc-cr-gnome-heritage-chameleon-e2e (8 TCs, required_for_release=true, release-c). Site audit 20260409-051852 reused (0 violations; data-only heritage, no new routes). Regression checklist line 251 marked APPROVE. Committed `9ac8f7826`.

## Verification evidence

### AC coverage

| AC Item | Status | Evidence |
|---|---|---|
| Heritage selectable for Gnome ancestry | PASS | HERITAGES['Gnome'][0] id=chameleon present |
| +2 circumstance Stealth in matching terrain | PASS | special.stealth_bonus.type=circumstance, value=2 |
| Condition: terrain-tag matches coloration-tag | PASS | special.stealth_bonus.condition confirmed |
| Bonus lost on significant terrain change | PASS | benefit text explicit |
| Bonus terrain-specific (not generic) | PASS | condition guard enforces terrain-match requirement |
| Non-stacking circumstance bonus | PASS | special.stealth_bonus.note confirms only highest applies |
| 1-action minor color shift | PASS | special.minor_color_shift.action_cost=1 |
| Dramatic shift = up to 1 hour downtime | PASS | special.dramatic_color_shift.duration='up to 1 hour (downtime activity)' |

### Implementation (CharacterManager.php ~line 307, commit aa5cee3f1)
```php
[
  'id'      => 'chameleon',
  'name'    => 'Chameleon Gnome',
  'benefit' => 'Your skin, hair, and eyes shift to match your surroundings. When you are in terrain whose color or pattern roughly matches your current coloration, you gain a +2 circumstance bonus to all Stealth checks. This bonus is lost immediately when the environment\'s coloration or pattern changes significantly. You can spend 1 action to make minor localized color shifts to enable the bonus in your current terrain (instant). A dramatic full-body coloration change to match a very different terrain takes up to 1 hour as a downtime activity.',
  'special' => [
    'stealth_bonus' => [
      'type'      => 'circumstance',
      'value'     => 2,
      'condition' => 'terrain-tag matches character coloration-tag',
      'note'      => 'Multiple circumstance bonuses to Stealth do not stack; only the highest applies.',
    ],
    'minor_color_shift' => [
      'action_cost' => 1,
      'effect'      => 'Enables stealth bonus in current terrain by making localized color adjustments.',
    ],
    'dramatic_color_shift' => [
      'duration' => 'up to 1 hour (downtime activity)',
      'effect'   => 'Changes base coloration to match a significantly different terrain type.',
    ],
  ],
]
```

### PHP lint
- Result: clean (no errors)

### Suite activation
- Suite `dc-cr-gnome-heritage-chameleon-e2e`: 8 TCs, activated release-c, required_for_release=true

### Site audit reuse
- Audit `20260409-051852`: 0 violations, 0 failures — valid (data-only; no new routes)

### Regression checklist
- Line 251: marked APPROVE (commit `9ac8f7826`)

## Next actions
- Inbox empty; awaiting next dispatch from pm-dungeoncrawler.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Heritage completeness is required for Gnome character creation to pass QA; this was the final targeted unit test item in the current release-c batch.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-050000-impl-dc-cr-gnome-heritage-chameleon
- Generated: 2026-04-09
