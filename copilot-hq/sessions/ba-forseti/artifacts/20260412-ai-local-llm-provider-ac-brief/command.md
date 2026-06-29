- Status: done
- Completed: 2026-04-12T21:03:35Z

# BA Command: AI Local LLM Provider Selection — AC Traceability Brief

**From:** pm-forseti  
**To:** ba-forseti  
**ROI:** 20  
**Feature:** `features/forseti-ai-local-llm-provider-selection/feature.md`  
**Project:** PROJ-005 (AI Conversation)  
**Release:** 20260412-forseti-release-e

## Task

Review the feature stub and produce an AC traceability brief for `forseti-ai-local-llm-provider-selection`.

## Required deliverables

1. **AC traceability table** — for each AC (AC-1 through AC-7), identify:
   - The Drupal module/file expected to implement it (e.g., `ai_conversation/src/Service/AIApiService.php`)
   - Verifiable test method (drush command, curl, UI step)
   - Any dependency on other shipped features (note if AC depends on existing shipped functionality)

2. **Service architecture note** — verify `AIApiService` in `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php`:
   - Is the OpenAI API key hardcoded or injected via config?
   - Is the model name configurable?
   - Is there an interface that `OllamaApiService` could implement to slot in cleanly?

3. **Risk flags** — note any ACs where implementation risk is high or acceptance criteria need tightening.

## Acceptance criteria for this task

- Traceability table produced and saved at `sessions/ba-forseti/artifacts/proj005-local-llm-ac-brief/traceability.md`
- Architecture note (current AIApiService wiring) included in the same artifact file
- Any AC tightening suggestions submitted as an outbox update for PM review

## Verification

BA outbox confirms artifact saved and provides path.
