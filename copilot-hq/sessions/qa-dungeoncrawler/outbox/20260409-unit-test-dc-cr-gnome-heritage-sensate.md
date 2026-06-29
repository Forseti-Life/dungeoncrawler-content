# QA Verification: dc-cr-gnome-heritage-sensate

- Status: done
- Summary: Verified the Sensate Gnome heritage implementation (commit `20dcc2ef4`) against all AC items. HERITAGES['Gnome'][sensate] contains full mechanics: imprecise scent with 30-foot base range (narrows position to a square, does not pinpoint), wind-direction modifiers (downwind 60 ft / upwind 15 ft / neutral 30 ft), no-wind fallback at 30 ft, and +2 circumstance bonus to Perception checks to locate an undetected creature within current scent range (bonus explicitly excluded outside range). Structured `special` sub-array is complete: senses[0].type=scent, precision=imprecise, base_range=30, modifiers for all three wind states, no_wind_fallback=30; perception_bonus.type=circumstance, value=2, condition and out-of-range exclusion note confirmed. PHP lint clean. Added suite dc-cr-gnome-heritage-sensate-e2e (8 TCs, required_for_release=true, release-c). Site audit 20260409-051852 reused (0 violations; data-only, no new routes). Regression checklist line 252 marked APPROVE. Committed `3d713a94d`.

## Verification evidence

### AC coverage

| AC Item | Status | Evidence |
|---|---|---|
| Sensate Gnome selectable for Gnome ancestry | PASS | HERITAGES['Gnome'] id=sensate present |
| Imprecise scent, base range 30 ft | PASS | senses[0].precision=imprecise, base_range=30 |
| Imprecise = narrows to square, not pinpoint | PASS | benefit text and precision field explicit |
| Downwind range doubles to 60 ft | PASS | modifiers.downwind.effective_range=60, multiplier=2 |
| Upwind range halved to 15 ft | PASS | modifiers.upwind.effective_range=15, multiplier=0.5 |
| No-wind-model fallback = 30 ft (neutral) | PASS | no_wind_fallback=30; modifiers.neutral.effective_range=30 |
| +2 circumstance Perception vs undetected within range | PASS | perception_bonus.type=circumstance, value=2, condition confirmed |
| Bonus does not apply outside scent range | PASS | perception_bonus.note explicit |

### Implementation (CharacterManager.php ~line 329, commit 20dcc2ef4)
```php
[
  'id'      => 'sensate',
  'name'    => 'Sensate Gnome',
  'benefit' => 'You have a powerful sense of smell. You gain imprecise scent with a base range of 30 feet...',
  'special' => [
    'senses' => [[
      'type'       => 'scent',
      'precision'  => 'imprecise',
      'base_range' => 30,
      'modifiers'  => [
        'downwind' => ['multiplier' => 2, 'effective_range' => 60],
        'upwind'   => ['multiplier' => 0.5, 'effective_range' => 15],
        'neutral'  => ['multiplier' => 1, 'effective_range' => 30],
      ],
      'no_wind_fallback' => 30,
    ]],
    'perception_bonus' => [
      'type'      => 'circumstance',
      'value'     => 2,
      'condition' => 'locating an undetected creature within current scent range',
      'note'      => 'Does not apply to Perception checks beyond scent range or to already-detected creatures.',
    ],
  ],
]
```

### PHP lint
- Result: clean (no errors)

### Suite activation
- Suite `dc-cr-gnome-heritage-sensate-e2e`: 8 TCs, activated release-c, required_for_release=true

### Site audit reuse
- Audit `20260409-051852`: 0 violations, 0 failures — valid (data-only; no new routes)

### Regression checklist
- Line 252: marked APPROVE (commit `3d713a94d`)

## Next actions
- Inbox empty; awaiting next dispatch from pm-dungeoncrawler.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 20
- Rationale: Heritage completeness required for Gnome character creation to pass QA; part of the active release-c batch.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-050000-impl-dc-cr-gnome-heritage-sensate
- Generated: 2026-04-09
