I have all the information needed. Let me write the artifact and outbox.

- Status: done
- Summary: Verified 4 REQs from Core Ch09 (Attack Rolls and Core Check Mechanics) against the production codebase. REQ 2079 (typed bonus stacking — highest wins) and REQ 2082 (typed penalty stacking — worst wins) both fully PASS: `BonusResolver::resolve()` and `BonusResolver::resolvePenalties()` implement the PF2e rules precisely, both REQs are cited in the docblock, and the resolver is wired into `CombatCalculator` and `Calculator` for all check/attack resolution. REQ 2091 (AoO does not incur or increment MAP) PASS: `CombatEngine::resolveAttack()` reads `skip_map=TRUE` from the AoO handler, sets `map_penalty=0`, and does not store an incremented `attacks_this_turn`. REQ 2093 (maximum effective range = 6× range increment) PARTIAL: the range increment penalty (−2/increment) is correctly applied in `RulesEngine.php`, but the 6× cap is not programmatically derived from `range_increment` — the cap relies on `weapon['range']` being set correctly in weapon data (GAP-2093, LOW risk).

## Next actions
- GAP-2093 (LOW): `RulesEngine.php` line ~432 — add `$max_effective_range = $base_range * 6` guard before accepting distance on ranged attacks; forward to dev-dungeoncrawler as hardening patch within `dc-cr-encounter-rules` scope (no new feature pipeline item needed)
- Continue roadmap REQ audits for remaining Ch09 sections if dispatched

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Bonus/penalty stacking and MAP rules are foundational to all attack resolution — confirming full compliance eliminates regression risk on core combat math. The 6× range cap gap is low-risk (data-level enforcement present) but the fix is one line and should be routed to dev-dungeoncrawler while in scope.

---

**Commits:** `f52ef8ee8` (artifact + checklist), `ef76ca167` (outbox)

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-roadmap-req-core-ch09-combat-checks
- Generated: 2026-04-07T03:12:46+00:00
