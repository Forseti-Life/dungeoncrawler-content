# Recurring production exception: missing `webform` entity type

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2 (manual follow-up after `ceo-system-health.sh --dispatch`)
- Dispatched-at: 2026-05-10T22:31:21Z
- Source: system health check

## Issue

Recent Apache logs show production requests hitting `PluginNotFoundException` for the `webform` entity type in Drupal's `EntityTypeManager`.

Trace the live code path triggering the `webform` lookup, determine whether the site expects the Webform module to exist, and either guard the code path correctly or restore the missing dependency.

## Acceptance criteria
- The live source of the `webform` lookup is identified
- The broken code path or dependency state is corrected
- Outbox entry filed with `Status: done` or `Status: blocked` and concrete verification

## Verification
- Inspect the relevant code path and dependency state
- Re-check recent Apache / Drupal logs after the fix path is applied
- Re-run: `bash scripts/ceo-system-health.sh` and confirm the Forseti PHP exception warning clears or is materially reduced
