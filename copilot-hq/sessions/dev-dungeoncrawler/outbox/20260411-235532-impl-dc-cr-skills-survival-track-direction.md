Both helpers are available. Now I'll implement all three actions:

Now add the case blocks before the switch's closing `}`:

Committed as `9f82af00a`. Outbox:

---

- Status: done
- Summary: Implemented all three Survival skill exploration actions in `ExplorationPhaseHandler.php` (REQs 1730–1737). `sense_direction` auto-succeeds in clear conditions; rolls Survival vs DC 15+modifier for supernatural/featureless environments; crit success sets `distance_estimate=true`. `cover_tracks` requires Trained Survival, records `tracks_covered` + `pursuer_dc` (10 + survival_bonus) on the actor's entity state, and advances 20 exploration minutes (half-speed move). `track` requires Trained Survival, resolves DC from a trail-age × terrain matrix (or overrides with `cover_tracks_pursuer_dc`), gates crit-fail trails as permanently lost via `game_state['track_lost']`, and returns `next_waypoint` on success. PHP lint clean; `drush cr` success. Committed `9f82af00a`.

## Next actions
- QA can now run the `dc-cr-skills-survival-track-direction` suite against the committed implementation
- Next dev inbox item: pick up remaining feature from queue (snares, spells, or gnome heritages)

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Completes all 8 Survival ACs covering wilderness navigation and pursuit mechanics; unblocks QA Gate 2 for this feature; no rework risk.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-235532-impl-dc-cr-skills-survival-track-direction
- Generated: 2026-04-12T05:20:34+00:00
