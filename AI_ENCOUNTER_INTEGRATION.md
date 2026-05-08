# AI Encounter Integration Blueprint

**Module**: dungeoncrawler_content  
**Version**: 1.0.0  
**Last Updated**: 2026-02-18  
**Status**: Phase 0/1 complete, ai_conversation backend provider wiring started, Phase 2/3 guarded implementation started, Phase 4 hardening started

## Overview

This blueprint defines how encounter orchestration in `dungeoncrawler_content` integrates with AI providers for tactical decision support, encounter narration, and non-player turn selection while preserving server-authoritative combat rules.

This document is the design artifact tracked by DCC-0224.

## Scope

### In scope

- AI-assisted NPC turn recommendation (never direct authoritative mutation)
- Encounter flavor text generation for room + turn context
- Provider abstraction for Gemini/Vertex-style model backends
- Campaign-safe context packaging (campaign ownership and encounter boundaries)
- Auditable request/response logging metadata (non-sensitive)

### Out of scope

- Replacing PF2e rules validation with model output
- Autonomous writes to encounter state without server-side validation
- Client-side direct model access

## Current Runtime Baseline

- Combat lifecycle APIs exist in `CombatEncounterApiController` (`/api/combat/start`, `/end-turn`, `/attack`, `/get`, `/set`, `/end`).
- Turn order and encounter state are persisted server-side.
- Character state APIs exist separately in `CharacterStateController`.
- AI image integration already uses provider-oriented service abstractions in this module.

## Target Integration Architecture

## 1) Encounter orchestration boundary

AI integration occurs at an orchestration layer between encounter state loading and action resolution:

1. Read encounter snapshot (campaign + participants + active turn).
2. Build constrained AI payload (allowed actions, tactical context, PF2e limits).
3. Request recommendation from provider service.
4. Validate recommendation against encounter rules.
5. Translate to canonical combat action payload.
6. Execute via existing server-authoritative combat pipeline.

## 2) Service contracts

### `EncounterAiIntegrationService` (planned)

- `buildEncounterContext(int $campaignId, int $encounterId): array`
- `requestNpcActionRecommendation(array $context): array`
- `requestEncounterNarration(array $context): array`
- `validateRecommendation(array $recommendation, array $context): array`

### `EncounterAiProviderInterface` (planned)

- `recommendNpcAction(array $context): array`
- `generateEncounterNarration(array $context): array`

Providers should map to existing AI provider patterns where practical.

## 3) Data contract: recommendation envelope

```json
{
  "version": "v1",
  "actor_instance_id": "npc-goblin-2",
  "recommended_action": {
    "type": "strike",
    "target_instance_id": "pc-kyra-1",
    "action_cost": 1,
    "parameters": {
      "weapon": "shortsword"
    }
  },
  "alternatives": [],
  "rationale": "Target is flanked and within melee reach.",
  "confidence": 0.72
}
```

Validation rules:

- `actor_instance_id` must match the active NPC turn.
- `action_cost` must fit remaining actions.
- `type` must map to server-supported action handlers.
- target and positioning must be valid in encounter state.

## Security and Governance

- Never include secrets, API keys, or user PII in AI payloads.
- Enforce campaign ownership checks before building context.
- Log provider, latency, token usage estimates, and validation outcome.
- Store prompt/response references in auditable metadata (redacted where needed).

## Phased Implementation Plan

### Phase 0 — Blueprint and route visibility (completed in this pass)

- Author this blueprint document.
- Add an architecture route/controller page summarizing integration boundaries.

### Phase 1 — Read-only orchestration scaffold (implemented)

- Create service interfaces and wire dependency injection.
- Add non-mutating endpoint for recommendation preview (admin or test permission).
- Return structured validation diagnostics without applying actions.

### Phase 2 — Controlled NPC auto-play integration

- Gate behind config flag.
- Execute only validated recommendations through existing combat action pipeline.
- Add failure fallback to deterministic rule-based action selection.

Current implementation state:

- `CombatEncounterApiController::autoPlayNonPlayerTurns()` now routes NPC turns through AI recommendation when `encounter_ai_npc_autoplay_enabled` is `true`.
- Encounter recommendation generation is now wired to the `ai_conversation` integration layer, with deterministic fallback for invalid/unavailable model output.
- Invalid/failed recommendation paths fall back to deterministic first-alive-player strike behavior.
- Default behavior remains unchanged until the config flag is enabled.

### Phase 3 — Encounter narration integration

- Add optional narration generation per round/turn.
- Persist narration events into encounter timeline metadata.

Current implementation state:

- Narration persistence is config-gated via `encounter_ai_narration_enabled`.
- Narration generation requests route through the `ai_conversation` integration layer, with deterministic fallback narration when response parsing fails.
- NPC turn loop logs narration timeline entries into `combat_actions` with `action_type = ai_narration` and JSON payload/result.
- Default behavior remains unchanged until narration toggle is enabled.

### Phase 4 — Hardening and observability

- Add functional coverage for campaign boundary validation.
- Add provider timeout/retry policy and circuit-breaker behavior.
- Add metrics dashboard panel (latency, rejection rate, fallback rate).

Current implementation state:

- DB-independent unit tests are in place for AI provider/integration behavior.
- ai_conversation-backed provider now uses bounded retry attempts and configurable token budgets for recommendation/narration calls.
- Architecture status page now shows last-24h operational metrics (fallback rate and average attempts) derived from ai_conversation usage logs.
- Operational metrics are exportable as CSV via `/architecture/encounter-ai-integration/metrics.csv`.
- Metrics panel and CSV export support selectable windows via query string (`?window=24h|7d|30d`).
- Encounter AI retries now share a `request_id` in usage context metadata, enabling request-level aggregation for observability.
- Remaining hardening coverage is functional/integration flow validation in Browser tests.

## Acceptance Checklist for DCC-0224

- [x] Blueprint page exists in `dungeoncrawler_content` docs.
- [x] Route-level visibility for integration architecture exists.
- [x] Service interfaces and orchestration scaffold merged.
- [x] Recommendation preview endpoint implemented.
- [x] Guarded NPC auto-play path integrated with deterministic fallback.
- [x] Guarded narration persistence path integrated into encounter timeline events.
- [x] Validation and fallback behavior covered by service-level unit tests.
- [ ] Functional/integration coverage for runtime encounter flow.

## Related Files

- `dungeoncrawler_content.routing.yml`
- `src/Controller/CombatEncounterApiController.php`
- `src/Controller/ControllerArchitectureController.php`
- `src/Controller/EncounterAiPreviewController.php`
- `src/Form/DungeonCrawlerSettingsForm.php`
- `dungeoncrawler_content.services.yml`
- `README.md`
