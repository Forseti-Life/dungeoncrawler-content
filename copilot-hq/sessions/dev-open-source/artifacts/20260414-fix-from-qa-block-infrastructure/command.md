- Status: done
- Completed: 2026-04-14T17:21:27Z

# Fix: PROJ-009 Phase 1 — ai_conversation public freeze blockers

Routed from dev-infra (mis-dispatched). You are the correct owner per module-ownership.yaml.

## Release id
20260414-fix-from-qa-block-infrastructure

## Source audit artifact
sessions/dev-open-source/artifacts/20260414-proj-009-phase1-security-gate-drupal-ai-conversation.md

## Site / scope
- Module: sites/forseti/web/modules/custom/ai_conversation
- Website scope: open-source (PROJ-009 Phase 1 public freeze gate)

## Required fixes (4 blockers from QA BLOCK)

### Code blockers (your scope):
1. HQ coupling — src/Service/AIApiService.php: remove/feature-flag HQ suggestion auto-queue behavior (writes to sessions/<pm>/inbox/...).
2. Absolute path — src/Service/AIApiService.php: remove stale absolute HQ fallback path /home/keithaumiller/copilot-sessions-hq.
3. Site-specific logging — src/Traits/ConfigurableLoggingTrait.php: replace thetruthperspective.logging dependency with module-local or generic logging config.
4. Site-specific prompt — config/install/ai_conversation.provider_settings.yml: replace Forseti-specific default/system prompt with a neutral public default.

### CEO/Board gate (not your scope — do not block on this):
- AWS credential rotation must be confirmed by CEO/Board before public push. Tracked separately.

## Definition of done
- All 4 code blockers resolved in the source tree.
- bash -n PASS on all modified PHP files (or equivalent lint/syntax check).
- No HQ-internal paths, session coupling, or site-specific config remain in the module.
- Commit hash(es) provided in your outbox with rollback steps.

## Verification
- Grep the module for: sessions/, /home/keithaumiller, thetruthperspective.logging, Forseti-specific prompt text.
- All grep hits must be eliminated before APPROVE.

## Notes
- QA stated: dev-open-source consumes the audit artifact directly. Do NOT create a new QA inbox item.
