This is a significant new feature — AWS Bedrock is the existing backend (not OpenAI as AC implies). I'll implement Bedrock + Ollama with proper provider selection. Let me start.

Good. Last hook is 8009 — I'll add 8010. Now I'll implement the feature:

Now create `OllamaApiService.php`:

Now create the `AiProviderSettingsForm.php`:

Now add the route and register services:

Now register OllamaApiService in services.yml and update AIApiService injection:

Now modify `AIApiService` to inject and use `OllamaApiService` and `user.data`. First check the constructor and the exact sendMessage method range:

Now add the Ollama and UserData properties and update the constructor:

Now add the provider resolution method and modify sendMessage. First find the exact sendMessage method location:

I'll add the provider resolution helper methods just before `buildBedrockClient`, then modify `sendMessage` to route to Ollama when selected:

Now modify `sendMessage` to use provider routing. Find its exact location in the updated file:

Now I'll modify the `sendMessage` to route to Ollama when provider=ollama, with fallback to Bedrock on connection errors:

Now add user provider preference via `hook_form_user_form_alter` and `hook_update_8010` in the module files. First check if the module file has any hooks:

Now remove the stale `PrivateTempStoreFactory` import from AIApiService, and run `drush cr`:

Services are wired. Now check the admin route:

Everything is wired. Commit all changes:

Now write the implementation notes and outbox:

All done. Here is the outbox update:

---

- Status: done
- Summary: Implemented Ollama as a selectable LLM provider for the ai_conversation module (release-i). Created OllamaApiService (Guzzle HTTP client, non-streaming), AiProviderSettingsForm (admin UI at /admin/config/forseti/ai-provider), and provider_settings config. Updated AIApiService with resolveProvider() routing (user.data → org default → bedrock fallback) and modified sendMessage() to branch to Ollama with RuntimeException fallback to Bedrock (AC-5). Added hook_form_user_form_alter for per-user provider/model preferences stored via user.data. AC deviation noted: feature brief says OpenAI+Ollama; live stack is Bedrock+Ollama — implemented accordingly. All services verified live via drush.

## Next actions
- QA (qa-forseti) to run test plan: verify /admin/config/forseti/ai-provider form, user preference fieldset on account edit, sendMessage() routing behavior, and Bedrock fallback on Ollama connect failure

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Enables self-hosted LLM inference removing cloud API dependency and cost. Foundational for users who want privacy or offline capability. Unblocks QA for release-i gate.

**Commits:**
- `b4a08887a` — feat(ai_conversation): Ollama provider implementation
- `290eed3f6` — docs: implementation notes

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260414-001359-impl-forseti-ai-local-llm-provider-selection
- Generated: 2026-04-14T00:40:30+00:00
