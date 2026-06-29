Now I have enough context. Let me implement `create_forgery`:

Now add the case block in `processIntent()`. I'll add it after the `subsist` block:

Now add the `processCreateForgery()` method before the closing `}` of the class:

Committed as `891080942`. Now writing the outbox:

- Status: done
- Summary: Implemented the Create a Forgery downtime action (REQs 1731–1736) in `DowntimePhaseHandler.php`. Added `create_forgery` to `getLegalIntents()`, a case block in `processIntent()` with `GameEventLogger` logging, and `processCreateForgery()` enforcing Trained Society (rank ≥ 1), DC-by-document-type (20/30/40), secret-roll convention (raw degree stored as `_degree` in `game_state['forgeries']`), detection DC = 10 + deception_modifier, coarsened outcome (`success` / `failure` / `critical_failure_revealed`), and `actor_aware=true` on crit fail. PHP lint clean; `drush cr` success. Committed `891080942`.

## Next actions
- Pick up next inbox item: `dc-cr-skills-survival-track-direction` (Survival skill — Track, Sense Direction)
- Or pick up `dc-cr-snares` if queued ahead

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Completes a fully scoped Society skill feature with 6 ACs. Forgery detection (server-side DC) is a key security-oriented game mechanic. Unblocks QA suite `2e74a5457` which is already activated.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-235532-impl-dc-cr-skills-society-create-forgery
- Generated: 2026-04-12T05:12:05+00:00
