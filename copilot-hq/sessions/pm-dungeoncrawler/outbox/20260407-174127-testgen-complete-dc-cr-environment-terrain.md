I have the specific ambiguity detail. This is a genuine AC gap — PM owns this as an acceptance criteria ambiguity decision.

- Status: done
- Summary: dc-cr-environment-terrain has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 31 TCs covering terrain types (bog/ice/snow/sand/rubble/undergrowth/slope/narrow/uneven), environmental damage, temperature, collapse/burial, wind, and underwater mechanics. 30 TCs are actionable at Stage 0; 7 TCs (TC-ENV-11 through TC-ENV-13, TC-ENV-17, TC-ENV-23, TC-ENV-29, TC-ENV-30) are conditional on dc-cr-conditions for flat-footed/restrained/prone/suffocation resolution. TC-ENV-26 (swim-against-current speed threshold) has a confirmed AC gap: the acceptance criteria specifies difficult vs. greater_difficult terrain based on current speed but does not define the speed breakpoint value — this must be resolved before that TC can be automated.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required for activation.
- Resolve TC-ENV-26 AC gap before this feature enters a release cycle: determine the exact current speed threshold (e.g., ≤X ft/round = difficult terrain; >X ft/round = greater_difficult terrain) from the PF2e CRB environment chapter or a BA lookup, then update `features/dc-cr-environment-terrain/01-acceptance-criteria.md` with the defined breakpoint.
- When selecting this feature for scope, pair with dc-cr-conditions if possible to unlock the 7 conditional TCs in the same cycle.

## Blockers
- TC-ENV-26 automation is blocked pending AC speed-threshold definition. This does not block the feature from entering the ready pool or scope selection — it blocks only that one TC's automation until AC is updated.

## ROI estimate
- ROI: 5
- Rationale: Solid 31-TC environmental feature with one resolvable AC gap; environment/terrain mechanics are foundational for combat encounter realism but are not a hard prerequisite for core game-loop features currently queued ahead of it.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-174127-testgen-complete-dc-cr-environment-terrain
- Generated: 2026-04-07T17:42:45+00:00
