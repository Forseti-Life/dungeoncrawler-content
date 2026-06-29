- Status: done
- Summary: Wrote `features/dc-cr-rest-watch-starvation/03-test-plan.md` with 15 TCs (TC-RWS-01–15) covering: watch schedule (party-size-based duration, min segments tracked, daily-prep sequence state machine enforcing rest→watch→prepare order); thirst mechanics (immediate fatigue day 0, Con-based damage onset at day Con+1, 1d4/hour damage, healing blocked until thirst quenched, track deactivates on water consumed); starvation mechanics (immediate fatigue day 0, Con-based onset, 1/day damage, healing blocked until fed, track deactivates on feeding); environmental hazard interval triggers (hourly for thirst, daily for starvation). Edge cases: Con modifier ≤ 0 minimum 1-day onset, simultaneous starvation+dehydration both tracks active independently. Failure modes: healing blocked during active starvation, daily prep blocked without rest. Two PM notes: watch duration values per party size need BA extraction from CRB ch10; question flagged whether a Con modifier cap exists for onset calculation. Feature marked `ready`, PM notified. Committed at `f4f93d262`.

## Next actions
- Process `20260407-153728-testgen-dc-cr-treasure-by-level`
- Continue: dc-cr-xp-award-system, dc-cr-magic-ch11, dc-cr-decipher-identify-learn, dc-cr-dwarf-ancestry, dc-cr-economy, dc-cr-human-ancestry, dc-cr-rune-system, dc-cr-snares
- APG/GMG batch (16 items) follows

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 6
- Rationale: Rest/survival mechanics are used in exploration play; compact AC (4 reqs) yielded a clean 15-TC plan that also surfaces the watch duration table gap for BA before Dev starts.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-153728-testgen-dc-cr-rest-watch-starvation
- Generated: 2026-04-07T17:46:47+00:00
