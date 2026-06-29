Now I have everything I need. Let me implement the changes:

Now add the new public methods before `narrateRoomEntry`. First, let me find where to insert:

Now add the fallback method and update `buildSystemPrompt` with new trigger extras:

Now add the fallback method before `stripMarkdownFences`:

Now update services.yml to add the 3 new constructor args to `ai_gm`:

Now lint and run drush cr:

Clean. Now update the feature.md, commit, and write the outbox:

Committed at `9b3bfcb11`. Here's the outbox:

---

- Status: done
- Summary: Implemented the AI GM Narrative Engine (`dc-cr-gm-narrative-engine`) by extending `AiGmService` with five new methods covering all six acceptance criteria. AC-001 (`assembleGmContext`) assembles full GM context including active NPC roster with attitudes, prior-session summaries (recent-first), quest hooks, current location, and rolling session context via `AiSessionManager`. AC-003 (`narrateNpcAttitudeShift`) narrates NPC softening on Diplomacy success, persists the attitude update via `NpcPsychologyService::updateProfile`, and uses per-NPC session keys for continuity. AC-005 (`generateSessionSummary`) AI-generates an end-of-session narrative summary and persists it via `SessionService::endSession`, with a template fallback. AC-006 adds `resolveEncounterForNarrative` (delegates to `EncounterBalancer` by party level/size/difficulty/theme) and `resolveNpcForScene` (queries active NPC profiles by role). Three new services injected into `AiGmService` constructor; `services.yml` updated. `buildSystemPrompt` extended with guidance for `npc_attitude_shift` and `session_summary` triggers. PHP lint clean, `drush cr` success. AC-002 and AC-004 were already covered by existing `narrateRoomEntry`/`flushNarration` and `narrateEncounterStart` infrastructure.

## Next actions
- QA-dungeoncrawler to verify against `03-test-plan.md`
- No further dev work needed for this feature

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: This is the core storytelling layer that differentiates DungeonCrawler as a GM-run game experience. Completing it unblocks NPC dialogue, session continuity, and GM tools integration for the release.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-160724-impl-dc-cr-gm-narrative-engine
- Generated: 2026-04-11T16:15:31+00:00
