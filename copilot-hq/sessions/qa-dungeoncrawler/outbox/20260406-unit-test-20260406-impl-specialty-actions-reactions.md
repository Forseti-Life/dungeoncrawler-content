Now let me run the site audit and create the verification artifact.

Site audit clean. Now create the verification artifact and update the checklist.

Now update the regression checklist:

Committed `a88fad0ef`. Now writing the outbox:

---

- Status: done
- Summary: Verified dev item `20260406-impl-specialty-actions-reactions` (commit 1810e9fe2). Verdict: BLOCK — 10/14 PASS, 3 medium gaps confirmed still open (no fix commits since roadmap QA BLOCK at 57d9e9639). GAP-2220 (MEDIUM): `avert_gaze_active` flag set in EPH but `CombatEngine.php` has zero references — +2 circumstance bonus to gaze saves is never applied. GAP-2227 (MEDIUM): `raise_shield` stores `shield_raised_ac_bonus` in entity_ref but CombatEngine reads the flat `combat_participants.ac` column — shield raised provides zero AC benefit in combat. DEF-2230 (MEDIUM): EPH line 1241 decrements `game_state['turn']['attacks_this_turn']` post-AoO despite comment saying "Do NOT decrement" — corrupts in-memory MAP counter if game_state is persisted at turn end. GAP-2225 (LOW): mount handler checks adjacency only, missing size≥1-larger and willing checks. Site audit clean (20260407-020452). Committed `a88fad0ef`.

## Next actions
- Dev-dungeoncrawler to fix: GAP-2220 (read avert_gaze_active in CombatEngine, apply +2), GAP-2227 (load shield_raised_ac_bonus from entity_ref in CombatEngine::resolveAttack), DEF-2230 (remove EPH line 1241 decrement — CombatEngine skip_map already maintains correct DB state)
- GAP-2225 (LOW): PM discretion whether to include in this release
- Awaiting next inbox dispatch from pm-dungeoncrawler

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: GAP-2227 means raise_shield is completely non-functional in combat (zero AC benefit) — high-value fighter action with zero effect is a visible gameplay defect; DEF-2230 corrupts MAP tracking for fighters using AoO. Both are release-blocking for any encounter that involves defensive actions or reactions.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-specialty-actions-reactions
- Generated: 2026-04-07T02:07:08+00:00
