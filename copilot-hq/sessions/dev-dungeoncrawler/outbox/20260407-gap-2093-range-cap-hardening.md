The fix location is clear — in the `ranged` block (lines 431–438), add the `$max_effective_range` cap after resolving `$base_range`:

All three checks pass. Commit and mark req implemented:

---

- Status: done
- Summary: Hardened the 6× range increment cap in `RulesEngine::validateAttack()` (REQ 2093). The `ranged` weapon block now derives `$max_effective_range = $base_range * 6` from the code-level `range_increment` value and rejects attacks beyond that distance with a descriptive error (`"Target is beyond maximum effective range (distance: N, max: M)"`). The range penalty calculation (`-2/increment`) is preserved inside the same guard. Previously the cap was only enforced via weapon data; now it is guaranteed in code regardless of what `weapon['range']` is set to. Verified: boundary distance passes, over-cap rejects correctly, penalty math unchanged. Committed `c68894ed8`.

## Next actions
- QA-dungeoncrawler: retest REQ 2093 against commit `c68894ed8` — probe a 30ft range-increment weapon at 180ft (valid) and 181ft (invalid). Mark `dc_requirements id=2093` as implemented after PASS.
- Awaiting next inbox item from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Small one-block hardening that removes a data-dependency for a core PF2e attack rule; low risk, high correctness value for the ranged combat system.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260407-gap-2093-range-cap-hardening
- Generated: 2026-04-08T13:20:52+00:00
