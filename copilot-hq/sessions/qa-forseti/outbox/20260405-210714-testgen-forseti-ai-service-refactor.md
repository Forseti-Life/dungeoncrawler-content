Now I have enough context. `AIConversationStorageService` doesn't exist yet (pre-implementation), `AIApiService.php` still has 3 direct `\Drupal::database()` calls (AC says 14 — those may include other DB patterns), and no test directory exists. This is next-release grooming only — no `suite.json` edits.

---

- Status: done
- Summary: Next-release grooming test plan for `forseti-ai-service-refactor` is complete. Created `features/forseti-ai-service-refactor/03-test-plan.md` with 8 test cases: TC-01/02/03 are static grep checks (verify `AIConversationStorageService` exists, `AIApiService` has 0 direct `\Drupal::database()` calls, and dependency injection is wired); TC-04/05 are PHPUnit Functional (conversation history parity and new record persistence post-refactor); TC-06 is a PHPUnit Unit mock test (typed exception on DB failure); TC-07/08 use the existing `role-url-audit` suite for chat surface smoke check and anon no-regression. No edits were made to `suite.json` or `qa-permissions.json` per grooming-only policy. Pre-implementation state confirmed: `AIConversationStorageService` does not exist yet (expected), `AIApiService` has 3 direct DB calls (AC cites 14 total queries; Dev must audit all patterns). PM notified via `qa-pm-testgen-complete.sh`; feature status updated to `ready`.

## Next actions
- Stage 0 Dev prerequisite: create `web/modules/custom/ai_conversation/tests/src/Unit/` and `tests/src/Functional/` directories before TC-04 through TC-06 can be activated
- Stage 0 activation: 3 new suite entries (`forseti-ai-service-refactor-static`, `-unit`, `-functional`) added to `suite.json`
- Dev must rebase on `main` (post-Bedrock fix `a4a4e8bf`) before starting this feature

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Matches source BA inventory ROI (AI-R1 = 8); pure internal refactor with no user-visible change; test plan ensures behavior parity and prevents future regression where DB logic re-couples into the service layer.

**Commits:**
- `280610a2` — test plan + feature.md status=ready

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260405-210714-testgen-forseti-ai-service-refactor
- Generated: 2026-04-06T09:50:02+00:00
