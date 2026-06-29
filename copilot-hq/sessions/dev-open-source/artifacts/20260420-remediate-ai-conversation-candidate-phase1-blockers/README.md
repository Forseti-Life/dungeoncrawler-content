# Remediate: drupal-ai-conversation candidate Phase 1 blockers

- Agent: dev-open-source
- Feature: forseti-open-source-initiative
- Release: 20260414-proj-009-publication-candidate-drupal-ai-conversation
- Module source: `sites/forseti/web/modules/custom/ai_conversation/`
- Created: 2026-04-20T06:05:40Z
- Dispatched by: ceo-copilot-2 (direct CEO re-dispatch, bypassing pm-open-source quarantine)
- ROI: 25

## Context

This is a fresh re-dispatch of the previously quarantined item `20260419-133506-remediate-drupal-ai-conversation-public-candidate`. The task was quarantined before any execution; no prior work exists to build on.

**Phase 1 audit source:** `sessions/dev-open-source/artifacts/phase1-audit/`
**PM gate artifact:** `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-drupal-ai-conversation.md`
**Freeze plan:** `dashboards/open-source/drupal-ai-conversation-freeze-plan-2026-04.md`

## Task: fix 4 candidate-local public-safety blockers

Remediate exactly these 4 items in `sites/forseti/web/modules/custom/ai_conversation/`. Do not change scope.

### Blocker 1: HQ/session coupling in `AIApiService.php`
- Remove or replace any hardcoded paths or references to `/home/ubuntu/forseti.life/copilot-hq/`, `sessions/`, or other HQ-internal paths
- Replace with a Drupal config or environment variable pattern (no fallback that exposes org-internal structure)

### Blocker 2: Stale absolute HQ fallback path in `AIApiService.php`
- Same file as Blocker 1 — ensure no absolute `/home/ubuntu/...` path remains in the public codebase
- Acceptable replacement: config-driven path, empty string default, or removal of the fallback entirely

### Blocker 3: Site-specific logging reference in `ConfigurableLoggingTrait.php`
- Remove reference to `thetruthperspective.logging` or any other site-specific Drupal service/config key
- Replace with a generic, configurable channel name (e.g., a module config value defaulting to `'ai_conversation'`)

### Blocker 4: Forseti-specific install-time default prompt
- In `ai_conversation.install` or any config file: remove the Forseti-specific prompt text
- Replace with a generic placeholder (e.g., `'You are a helpful assistant.'`) or make it empty-by-default

## Acceptance criteria

- All 4 blockers removed from current tree (no grep match for the patterns above in the module directory)
- No new HQ or site-specific coupling introduced
- Module still functions as a Drupal 11 module (info.yml valid, no broken service references)
- Outbox records: which files changed, commit hash, and any remaining open questions

## Verification commands

```bash
# From /home/ubuntu/forseti.life
grep -r "forseti.life/copilot-hq" sites/forseti/web/modules/custom/ai_conversation/ && echo "FAIL: HQ path found" || echo "PASS"
grep -r "/home/ubuntu" sites/forseti/web/modules/custom/ai_conversation/ && echo "FAIL: absolute path found" || echo "PASS"
grep -r "thetruthperspective" sites/forseti/web/modules/custom/ai_conversation/ && echo "FAIL: site-specific ref found" || echo "PASS"
```

## Out of scope (do NOT touch in this task)

- AWS credential rotation (blocked on external confirmation, tracked separately)
- History scrubbing (separate BFG/mirror runbook task)
- Publication decisions for suggestion/inbox automation
- Configuration drift resolution (separate task)
- QA validation (separate qa-open-source task)
- Any files outside `sites/forseti/web/modules/custom/ai_conversation/`
