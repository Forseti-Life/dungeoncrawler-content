Now I have enough context. Let me implement all athletics actions. I'll add them to the EncounterPhaseHandler and also update the ExplorationPhaseHandler for Climb/Swim/High Jump/Long Jump in exploration context.

Now I have enough context to implement all the athletics actions. Let me implement them:

Now add the new action types to `getLegalIntents()`:

Now add the new action types to `getActionCost()`:

Now extend the `processEscape()` to accept athletics bonus as an alternative (TC-ATH-01):

Now I need to add the `processGrapple()` and `calculateFallingDamage()` helper methods. Let me also add `calculateFallingDamage` near the end of the class, and `processGrapple` near `processEscape`:

Now I need to add the `calculateFallingDamage` usage in the `arrest_fall` case (it currently manually computes fall damage — let me check if I need to update it to use the new helper):

Good. Now let me also update the `arrest_fall` case to use `calculateFallingDamage()` for consistency. Let me find its location in the new line numbering:

The `arrest_fall` case uses its own inline calculation which handles the failure-only variant (not a normal fall). That's fine — it's a different check than just `calculateFallingDamage`. I'll leave it as-is.

Now also need to update `grab_edge` to also apply the prone condition and handle fall damage (TC-ATH-52 says Grab an Edge reaction reduces or eliminates fall damage). The existing code already handles the success/failure logic correctly. The `calculateFallingDamage` helper I added is used from `climb` case. Let me verify the `climb` case references it properly:

Good. Now let me also handle the `stride` tracking for High Jump/Long Jump — I need to update `processStride` to record `last_stride_ft`. Let me check what stride does:

I need to track `last_stride_ft` in the `stride` case in `processIntent()`. Let me find where stride is processed:

Now update the `processEndTurn` to handle the swim sink rule (REQ 1648: if no Swim action taken by end of turn, character sinks 10 ft, unless it's their entry turn):

`applyFallDamage` already exists in HPManager. I should use it in the `climb` case instead of my manual calculation. Let me update the climb critical failure to use HPManager's method:

`applyFallDamage` in HPManager already applies prone and returns hp_result. Let me update the `climb` case to use it instead of the manual approach:

Now add the swim sink rule in `processEndTurn`. I need to add it after the fly check:

Now verify the PHP syntax is clean:

Now let me also run drush cr to verify the module loads:

All 9 new athletics actions are registered. Let me do a quick functional test on a few key ones:

All action costs are correct. Now let me commit and provide the outbox update:

- Status: done
- Summary: Implemented all 9 new Athletics skill actions (Climb, Force Open, Grapple, High Jump, Long Jump, Shove, Swim, Trip, Disarm) plus extended Escape to accept `athletics_bonus` as alternative. All actions registered in `getLegalIntents()` with correct action costs, MAP applied to all Attack-trait actions, conditions applied per PF2e degrees, swim sink rule added to `processEndTurn`, High/Long Jump prerequisite stride tracking added. 53 TCs covered. Committed `40e060a3d`.

## Next actions
- QA to run suite against dc-cr-skills-athletics-actions tag

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 7
- Rationale: Completes the full Athletics action suite for DungeonCrawler combat. Unlocks all 53 TCs and enables QA gate verification for release f.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-144600-impl-dc-cr-skills-athletics-actions
- Generated: 2026-04-08T15:23:57+00:00
