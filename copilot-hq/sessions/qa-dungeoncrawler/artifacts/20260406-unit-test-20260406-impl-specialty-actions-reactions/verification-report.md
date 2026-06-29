# Verification Report: Unit Test — 20260406-impl-specialty-actions-reactions (Reqs 2219–2232)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: 1810e9fe2 (2026-04-06T23:15 UTC)
- Verdict: BLOCK — 10/14 PASS, 4 medium/low gaps remain open (no fix commits since roadmap QA)

## Scope
Targeted unit-test for completed dev item `20260406-impl-specialty-actions-reactions`.
Reqs 2219–2232: specialty actions (avert_gaze, point_out, burrow, fly/hover, mount/dismount,
raise_shield, shield_block, arrest_fall, grab_edge) and Attack of Opportunity reaction.
Primary sources: `EncounterPhaseHandler.php`, `CombatEngine.php`.

## Prior report reference
Roadmap verification: `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-roadmap-req-2219-2232-specialty-reactions.md`
- QA BLOCK (12/14): commit `a23bee5af` at 2026-04-06T23:48 UTC
- QA re-verify (10/14): commit `57d9e9639` at 2026-04-06T23:52 UTC (added GAP-2220 + GAP-2227)

No fix commits have landed for specialty-actions gaps since 57d9e9639.
Fix commit 663dbd92a (01:06 UTC) addressed only GAP-2278/2280/2281 (senses/hero points).

## Per-req status (post-roadmap-QA source verification)

| REQ | Description | Status |
|---|---|---|
| 2219 | avert_gaze: flag set + expires start of next turn | PASS |
| 2220 | point_out: hidden→observed for allies flag+expiry | PASS |
| 2221 | burrow: stored in entity_ref + movement points consumed | PASS |
| 2222 | fly: fall-if-unused end-of-turn check | PASS |
| 2223 | hover: fly without fall requirement | PASS |
| 2224 | grab_edge: Reflex save wired | PASS |
| 2225 | mount: adjacency check present; **size≥1-larger + willing checks absent** | PARTIAL |
| 2226 | point_out: correct getLegalIntents + case handler | PASS |
| 2227 | raise_shield: flag+expiry+broken-guard; **CombatEngine reads flat ac col — shield AC bonus = 0** | PARTIAL |
| 2228 | attack_of_opportunity: class_feature gate | PASS |
| 2229 | AoO: crit + manipulate trigger → disrupt | PASS |
| 2230 | AoO: skip_map in CombatEngine (DB col); EPH line 1241 decrements in-mem game_state | PARTIAL |
| 2231 | shield_block: hardness split | PASS |
| 2232 | shield_raised guard | PASS |

## Open gaps

### GAP-2220 (MEDIUM) — avert_gaze +2 circumstance bonus never consumed
- `avert_gaze_active` set at EPH line 1049, cleared at line 1797
- `CombatEngine.php` has **0 references** to `avert_gaze_active`
- Effect: activating avert_gaze provides the flag+expiry but +2 circumstance bonus to gaze saves is never applied in resolveAttack
- Fix path: read `avert_gaze_active` flag from attacker entity_ref in CombatEngine and apply +2 to saving throw vs gaze attacks

### GAP-2227 (MEDIUM) — shield_raised AC bonus not applied in combat
- `raise_shield` stores `shield_raised_ac_bonus` in `entity_ref`
- `CombatEngine.php` reads `$target['ac']` from flat `combat_participants.ac` column — **0 references to shield_raised or entity_ref AC fields**
- Effect: raise_shield action grants zero AC benefit during combat
- Fix path: CombatEngine::resolveAttack() must load target's entity_ref, check `shield_raised_ac_bonus`, and add to effective AC before comparing to attack roll

### DEF-2230 (MEDIUM) — EPH AoO handler decrements game_state attacks_this_turn post-strike
- EPH line 1241: `$game_state['turn']['attacks_this_turn'] = max(0, ($game_state['turn']['attacks_this_turn'] ?? 1) - 1);`
- Comment above says "REQ 2230: Do NOT decrement attacks_this_turn" — directly contradicted by the decrement
- CombatEngine correctly skips DB increment when skip_map is set (line 619-621); however EPH in-memory game_state is now N-1
- If game_state is persisted at turn end, subsequent same-turn actions will see corrupted MAP counter
- Fix path: remove the EPH line 1241 decrement entirely; rely on CombatEngine's skip_map to maintain correct state

### GAP-2225 (LOW) — mount missing size and willing checks
- Mount handler (EPH lines 964-990): checks adjacency only
- PF2e rule: mount must be at least 1 size category larger AND willing
- No check for `size` field comparison or `willing` flag on mount entity_ref
- Low severity: gameplay only; adjacency is primary guard

## Site audit
- Run: 20260407-020452
- Result: CLEAN — 0 errors, 0 permission violations, 0 config drift
- Evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260407-020452/`

## KB reference
- Stale-annotation pattern: all inbox "Expected FAIL" annotations for this release cycle understated implementation (see prior session history). Roadmap QA commit timestamps consistently post-date dev commit timestamps — verify source directly.

## Verdict
BLOCK: 3 medium gaps (GAP-2220, GAP-2227, DEF-2230) must be resolved before release. GAP-2225 is low and may be accepted at PM discretion.
