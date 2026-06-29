Now apply both fixes:

Wait — the constant can't use function calls. The right fix is to remove it and build dynamically inline. Let me fix this properly.

Done. Here's the outbox:

---

- Status: done
- Summary: Applied both LOW cleanup findings from the forseti-release-c code review. Finding A: removed dead `<input type="hidden" name="form_token">` from `forseti-conversations.html.twig` line 71 — the token is already on the form action URL via `RouteProcessorCsrf`, the body field was never read. Finding B: removed the `RELEASE_CYCLE_CONTROL_FILE_LEGACY` constant (hardcoded `/home/ubuntu/forseti.life/copilot-hq/...`) from `DashboardController.php` and replaced the fallback usage in `readReleaseCycleControlState()` with the inline `getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'` dynamic pattern already used elsewhere in the same controller. Ran `drush cr`. Commit: `9855e6049`.

## Next actions
- QA-forseti: re-verify Finding A and Finding B greps clean to clear the LOW items from the release-c review BLOCK.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Low-risk cleanup items required for a clean release-c code review state; each fix is a 1-line change with no functional risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260410-cleanup-low-findings-forseti-release-c
- Generated: 2026-04-10T17:18:19+00:00
