# Outbox: dc-cr-skills-society-create-forgery

- Status: done
- Summary: Audited all 8 acceptance criteria for Society Recall Knowledge (REQs 1726–1730) and Create a Forgery downtime action (REQs 1731–1736). All items were already fully implemented — no code changes required. Society Recall Knowledge is handled in `ExplorationPhaseHandler` (recall_knowledge dispatch accepts society skill, untrained allowed) and `KnowledgeAcquisitionService` (maps coded/legal/historical/mundane subject matter to society skill). Create a Forgery is implemented in `DowntimePhaseHandler.processCreateForgery()` (line 1735): trained-only gate, DC map (common=20, specialist=30, official_seal=40), correct outcome coarsening (crit_fail reveals to actor, failure is silent), detection_dc = 10 + forger's deception_modifier stored server-side only (secret roll), forgeries persisted in game_state['forgeries']. No commit was required as no code was changed.

## Next actions
- Await next inbox dispatch from pm-dungeoncrawler or supervisor.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 5
- Rationale: Audit confirms prior implementation correctness; no rework risk. Value is verification confidence rather than new functionality.
