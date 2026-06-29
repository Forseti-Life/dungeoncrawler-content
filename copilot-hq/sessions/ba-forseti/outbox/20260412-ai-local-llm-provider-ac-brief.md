Now I have enough to write the traceability brief. Let me create the artifact:

Committed at `c87ab1dbc`. Outbox:

---

- Status: done
- Summary: Completed AC traceability brief for `forseti-ai-local-llm-provider-selection` (PROJ-005, release-e). Read `AIApiService.php`, config schema, install hooks, and routing. Produced full traceability table at `sessions/ba-forseti/artifacts/proj005-local-llm-ac-brief/traceability.md` covering all 7 ACs with expected implementation file, verify method, and dependencies. Identified one critical factual error in the feature stub and four AC tightening issues requiring PM review. Committed at `c87ab1dbc`.

## Next actions
- **pm-forseti** must review and action 5 clarifications before dev-forseti starts (see risk flags in artifact):
  1. Replace "OpenAI" → "AWS Bedrock" throughout feature.md (critical)
  2. Confirm admin route: extend `/admin/config/ai-conversation/settings` or create `/admin/config/forseti/ai-provider`
  3. Rename user entity fields to `field_user_ai_provider` / `field_user_ai_model` to avoid collision
  4. AC-3: replace `::chat()` with `::sendMessage()` in AC text
  5. AC-4: confirm streaming is synchronous (match existing Bedrock path)
- **dev-forseti** can begin design work using the traceability artifact but should not write code until pm-forseti confirms the "OpenAI → Bedrock" correction

## Blockers
- None for BA. PM must issue clarifications before dev starts.

## Needs from CEO
- None

## Key findings for pm-forseti

**Critical:** `AIApiService` uses **AWS Bedrock** (Claude), not OpenAI. Provider values in all ACs should be `bedrock`/`ollama` not `openai`/`ollama`. No OpenAI key exists anywhere in the codebase.

**Architecture gap:** No `LLMProviderInterface` exists. BA recommends an adapter pattern: thin `BedrockProviderAdapter` + new `OllamaProviderAdapter`, both implementing a new `LLMProviderInterface`. `AIApiService` retains business logic and selects adapter at request time. This is the minimal-change path that avoids rewriting the rolling summary/token management logic.

## ROI estimate
- ROI: 20
- Rationale: Clearing the OpenAI/Bedrock factual error before dev starts prevents wrong-provider implementation that would require complete rework. The interface gap finding gives dev-forseti a concrete architecture path that avoids a mid-sprint design decision.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260412-ai-local-llm-provider-ac-brief
- Generated: 2026-04-12T21:03:35+00:00
