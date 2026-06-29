Now I have a clear picture. Blockers 1-3 are already absent from the current working tree. Blocker 4 needs fixing: `PromptManager.php` still contains the Forseti-specific hardcoded prompt in `getBaseSystemPrompt()` and `getFallbackPrompt()`, and `initializeDefaultPrompt()` writes it to config at install time, overriding the generic YAML.

Now I'll fix Blocker 4 by replacing the Forseti-specific content in `PromptManager.php` with generic defaults, and update the comment in the install file.

Now update the stale "Forseti" comment in the install file:

Also update the docblock for `_ai_conversation_init_system_prompt()`:

Now run verification checks:

Good — the service definition is fine. Now commit and verify:

Commit hash: `5e9f8e553`. Now write the outbox:

- Status: done
- Summary: All 4 Phase 1 public-safety blockers resolved. Blockers 1–3 (HQ path coupling in AIApiService.php, stale `/home/keithaumiller` absolute path, and `thetruthperspective.logging` in ConfigurableLoggingTrait.php) were already absent from the current working tree — all three verification `grep` commands returned PASS with no matches. Blocker 4 (Forseti-specific install-time default prompt) was the active issue: `PromptManager::getBaseSystemPrompt()` contained the full Forseti persona (AmISafe, H3, crime mapping, St. Louis MPD) and `initializeDefaultPrompt()` wrote it to config at install time, overriding the generic YAML. Both `getBaseSystemPrompt()` and `getFallbackPrompt()` have been replaced with generic defaults consistent with `ai_conversation.settings.yml`. Stale "Forseti persona" comments in the `.install` file were also cleaned up. Commit `5e9f8e553` on `main`.

## Next actions
- QA (`qa-open-source`) should run the validation plan against this updated tree — all 4 Phase 1 blockers now clear
- AWS credential rotation confirmation is still outstanding (tracked separately, not in scope here)
- History scrub / BFG sanitized-extract step is a prerequisite before public push (separate runbook task)

## Blockers
- None for this task.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: Unblocks the drupal-ai-conversation public candidate freeze. Removing the Forseti persona from the default prompt is a hard requirement before the module can be published; without it the module ships as a site-specific artifact rather than a reusable Drupal module. Clears the last candidate-local blocker so QA validation and the BFG history scrub can proceed in parallel.

---
- Agent: dev-open-source
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-open-source/inbox/20260420-remediate-ai-conversation-candidate-phase1-blockers
- Generated: 2026-04-20T06:21:52+00:00
