Now I have a clear picture. Let me implement all the requirements:

Now I have enough context. Let me implement all the required features:

Now add the detection state and light level helper methods, plus in-world time tracking. First let me add the `startRound` in-world time tracking:

Now add detection state helpers and light level method before the closing brace at the end of CombatEngine:

Now add light level and detection state checks to `resolveAttack()`. Let me find the right insertion point:

Now I'll add detection/light checks after the flanking section and before `calculateDegreeOfSuccess`:

Now I need to also add `$degree = ...` back since I removed it. Let me check:

I see `$degree` is missing. Let me add it back:

Also update the final return to include `detection` info:

Now I need to check that `rollFlatCheck` exists on `combatCalculator` (CombatCalculator vs Calculator). Let me check:

Now update the `rollFlatCheck` calls in `resolveAttack()` to use the new protected method:

Now add `heroPointReroll()` to Calculator.php (REQ 2280) and `heroicRecoveryAllPoints()` to HPManager.php (REQ 2281):

Now add `heroicRecoveryAllPoints()` to HPManager.php (REQ 2281):

Now verify syntax by running drush cache rebuild:

Now run verification tests:

`in_world_seconds` column doesn't exist. Store it derived from round number — compute and return it rather than persisting a separate column:

Now update `startRound()` return to include `in_world_seconds`:

All verified. Now mark requirements as implemented and commit:

- Status: done
- Summary: Implemented REQs 2267–2282, 2286, and 2288 — senses/detection states/hero points for the DungeonCrawler module. Added sense precision constants, DEFAULT_SENSES (vision precise, hearing imprecise, smell vague), per-perceiver detection state helpers, light level resolver (bright/dim/dark with light source radii), and sense precision resolver supporting darkvision, greater darkvision, low-light vision, tremorsense, scent, and invisible flag. Integrated detection state + dim-light concealment checks into `resolveAttack()`. Added in-world time tracking (round × 6s) to `startRound()`. Added `heroPointReroll()` to Calculator and `heroicRecoveryAllPoints()` to HPManager. All 17 pending reqs verified via drush and marked implemented.

## Next actions
- Await next inbox item from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 60
- Rationale: Senses and detection are core to tactical PF2e play (visibility-based targeting, stealth encounters); hero point mechanics are a player agency system referenced across multiple encounter scenarios. Completing this batch keeps the ruleset coverage progressing at pace.

**Commit:** `d5c5e1679`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-senses-detection-hero-points
- Generated: 2026-04-06T22:50:48+00:00
