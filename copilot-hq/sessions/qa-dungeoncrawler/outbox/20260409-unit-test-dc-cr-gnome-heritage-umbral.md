# QA Verification: dc-cr-gnome-heritage-umbral

- Status: done
- Summary: Verified the Umbral Gnome heritage implementation (commit `f811ec132`) against all AC items. HERITAGES['Gnome'][umbral] contains full mechanics: precise darkvision (see in complete darkness, black and white only), explicitly supersedes Gnome base Low-Light Vision, no-duplicate guard when darkvision already granted by another source. Structured `special` sub-array complete: senses[0].type=darkvision, precision=precise, note confirms supersession and no-duplicate rule. PHP lint clean. Added suite dc-cr-gnome-heritage-umbral-e2e (6 TCs, required_for_release=true, release-c). Site audit 20260409-051852 reused (0 violations; data-only, no new routes). Regression checklist line 253 marked APPROVE. Committed `4608f9158`. Additionally confirmed: commit `f811ec132` also restored the Sensate Gnome data that was accidentally reverted by `90b346aae`; Sensate implementation is intact at HEAD and the prior APPROVE (checklist line 252) remains valid. Dev has flagged the signoff automation issue to PM.

## Verification evidence

### AC coverage

| AC Item | Status | Evidence |
|---|---|---|
| Umbral Gnome selectable for Gnome ancestry | PASS | HERITAGES['Gnome'] id=umbral present |
| Darkvision — complete darkness, no penalty | PASS | benefit text explicit; senses[0].type=darkvision |
| Uses shared darkvision sense type | PASS | type=darkvision (same as dc-cr-darkvision baseline) |
| Supersedes Low-Light Vision | PASS | senses[0].note: 'Supersedes Low-Light Vision' |
| No duplicate if darkvision already held | PASS | senses[0].note: 'No duplicate granted if already possessed' |

### Implementation (CharacterManager.php ~line 355, commit f811ec132)
```php
[
  'id'      => 'umbral',
  'name'    => 'Umbral Gnome',
  'benefit' => 'You can see in complete darkness. You gain darkvision, allowing you to see in darkness and dim light just as well as you see in bright light, though in black and white only. Darkvision supersedes the Low-Light Vision all gnomes already have. If darkvision is already granted by another source (feat or item), no duplicate sense entry is added.',
  'special' => [
    'senses' => [[
      'type'      => 'darkvision',
      'precision' => 'precise',
      'note'      => 'Supersedes Low-Light Vision. No duplicate granted if already possessed.',
    ]],
  ],
]
```

### PHP lint
- Result: clean (no errors)

### Suite activation
- Suite `dc-cr-gnome-heritage-umbral-e2e`: 6 TCs, activated release-c, required_for_release=true

### Site audit reuse
- Audit `20260409-051852`: 0 violations, 0 failures — valid (data-only; no new routes)

### Sensate regression cross-check
- Commit `90b346aae` (pm-dungeoncrawler co-signoff) reverted Sensate Gnome data
- Commit `f811ec132` (Dev) restored it alongside Umbral implementation
- Sensate entry confirmed present and correct at HEAD — prior APPROVE (checklist line 252, commit `20dcc2ef4`) remains valid
- Dev has flagged signoff automation scope issue to PM for follow-up

### Regression checklist
- Line 253: marked APPROVE (commit `4608f9158`)

## Next actions
- Inbox empty; awaiting next dispatch from pm-dungeoncrawler.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: Umbral is a straightforward darkvision heritage, but the Sensate regression catch prevents a compounding defect across two approved features; fixing both in one Dev commit is the correct resolution pattern.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260409-unit-test-20260409-050000-impl-dc-cr-gnome-heritage-umbral
- Generated: 2026-04-09
