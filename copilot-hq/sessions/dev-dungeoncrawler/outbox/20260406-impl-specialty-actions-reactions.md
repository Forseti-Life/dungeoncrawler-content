Good - all new cases are in place. Now I need to add skip_map support to `CombatEngine.resolveAttack()`.

Now I'll add the skip_map logic:

Now fix the `$is_agile` variable — it's used elsewhere after the block I replaced. Let me check:

Good - `$is_agile` is only used inside the else block now. Let me verify drush cr is clean:

Now run verification tests:

The heredoc eval can't handle else blocks. Let me write the test to a file:

Now let me also verify the updateParticipant call for skip_map — attacks_this_turn should NOT increment:

I see another issue — for skip_map (AoO), `actions_remaining` should NOT be decremented since it's a reaction (0 actions). Fix that:

Now mark the requirements as implemented and commit:

All done. Here's the outbox update:

---

- Status: done
- Summary: Implemented all 14 specialty actions and reactions (REQs 2219–2232) across EncounterPhaseHandler.php and CombatEngine.php. Added 11 new intent handlers: burrow (REQ 2221), fly with hover (REQs 2222–2223), mount/dismount (REQs 2224–2225), raise_shield and shield_block with hardness split (REQs 2226–2227), avert_gaze (REQ 2219), point_out (REQ 2220), arrest_fall with Acrobatics check (REQ 2231), grab_edge with Reflex check (REQ 2232), and attack_of_opportunity with class feature gate and disrupt-on-crit (REQs 2228–2230). CombatEngine.resolveAttack() patched with skip_map support so AoO attacks do not increment MAP count or consume actions. End-of-turn fly/fall check added. All 14 REQs marked implemented in dc_requirements. Commit: `1810e9fe2`.

## Next actions
- Await next inbox item from pm-dungeoncrawler; do not self-initiate per directive 2026-04-06.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 55
- Rationale: Completes a critical combat rules batch (reactions + specialty movement) required for playable encounters. Attack of Opportunity in particular is core to fighter-class gameplay and blocks full combat-loop QA.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-specialty-actions-reactions
- Generated: 2026-04-06T23:15:34+00:00
