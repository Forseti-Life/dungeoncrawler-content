All 4 defects confirmed still open. The dev commit `85dedb946` predates the QA roadmap BLOCK — the roadmap QA tested post-dev code and found the defects remain. Now create the artifact.

- Status: done
- Summary: Verified dev item 20260406-impl-hp-dying-fixes (commit 85dedb946). Verdict: BLOCK — 22/28 PASS, 2 high-severity defects remain open. The roadmap QA (c42dbb089, 2026-04-06T23:20) was performed AFTER this dev commit and already found these defects; source inspection confirms they are still unpatched. DEF-2151 (HIGH): `HPManager.php` line 81 stores `$base_hp - $remaining_damage` with no `max(0,...)` clamp — HP goes negative in DB. DEF-2154/2155 (HIGH): `applyDamage` line 136 calls `applyCondition('dying', 1)` directly, bypassing `applyDyingCondition()` — crits yield dying 3 instead of dying 2+wounded; normal kills bypass wounded addition entirely. GAP-2166 (MEDIUM): doomed instant-death not evaluated at `applyDyingCondition` call site. GAP-2178 (MEDIUM): `regeneration_bypassed` never auto-set in `resolveAttack` for fire/acid damage. Committed 28c3b039b.

## Next actions
- Dev must fix DEF-2151 (1-line max clamp) and DEF-2154/2155 (route `applyDamage` kills through `applyDyingCondition`) — these are HIGH severity and affect every combat encounter
- Fix paths in `sessions/qa-dungeoncrawler/artifacts/20260406-unit-test-20260406-impl-hp-dying-fixes/verification-report.md`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 55
- Rationale: DEF-2151/2154/2155 are foundational combat bugs — negative HP and wrong dying track application corrupt every kill/death event in every encounter; highest priority fix in the queue.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-hp-dying-fixes
- Generated: 2026-04-07T01:49:07+00:00
