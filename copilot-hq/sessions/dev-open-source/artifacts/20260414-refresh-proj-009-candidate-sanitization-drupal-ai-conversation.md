# PROJ-009 Candidate Sanitization Refresh — `drupal-ai-conversation`

- **Date:** 2026-04-15
- **Auditor:** `dev-open-source`
- **Project:** `PROJ-009`
- **Candidate reviewed:** `sites/forseti/web/modules/custom/ai_conversation`
- **Purpose:** Replace stale candidate-local blocker wording with current-tree evidence after `f360335d8` and the public-mirror export fix

## Refreshed gate result

**Result:** **FAIL / NO-GO** for freezing the current live tree as a public candidate.

The earlier candidate-local blockers around HQ queue coupling, stale HQ path fallback, `thetruthperspective.logging`, and the old public-mirror placeholder issue are no longer the right reasons to block this candidate. Those findings are stale. The candidate is still not freezeable today because the live module still ships Forseti-specific product identity, routes, UI names, helper endpoints, and documentation that are not sanitized for a standalone public package.

## What is now stale / no longer reproducible

These older blockers should not be repeated in PM or QA gate language:

1. **HQ/session auto-queue coupling in `AIApiService.php`**
   - Removed in commit `f360335d8`.
   - Current candidate no longer writes suggestion records into `sessions/<pm>/inbox/...`.

2. **Stale HQ fallback path `/home/keithaumiller/copilot-sessions-hq`**
   - Removed in commit `f360335d8`.
   - Current candidate no longer carries that absolute-path fallback.

3. **Site-specific logging dependency on `thetruthperspective.logging`**
   - Removed in commit `f360335d8`.
   - `ConfigurableLoggingTrait.php` now reads module-local `ai_conversation.settings`.

4. **Install-time default prompt in `config/install/ai_conversation.settings.yml`**
   - Neutralized in commit `f360335d8`.
   - The earlier claim that the install config itself still shipped Forseti-specific prompt text is stale.

5. **Public-mirror export recreating private placeholder directories**
   - No longer reproducible from current HQ tree.
   - Current `copilot-hq/.public-mirror-ignore` excludes `sessions/`, `inbox/responses/`, and `tmp/`.
   - Current `copilot-hq/scripts/export-public-mirror.sh` uses `rsync --delete-excluded` and recreates only `tmp/.gitkeep`.
   - Reproduced export output did not contain `sessions/`, `inbox/responses/`, `prod-config/`, or `database-exports/`.

## Current live candidate blockers

### 1. Forseti-specific assistant persona is still hardcoded in runtime prompt logic

`src/Service/PromptManager.php` still returns a long Forseti-branded base prompt that names the assistant as Forseti and hardcodes product/domain specifics including:

- "You are Forseti..."
- crime/safety-intelligence mission language
- AmISafe mobile app references
- St. Louis MPD / FBI UCR data references
- Forseti-specific suggestion handling language

This is the main remaining sanitization blocker because it defines the runtime assistant behavior for new conversations.

### 2. User-facing routes, theme hooks, and templates are still Forseti-branded

The live module still exposes Forseti-specific surface area across routing and render wiring:

- `ai_conversation.routing.yml`
  - `/forseti/chat`
  - `/forseti/conversations`
  - route names `forseti.conversations`, `forseti.conversation_delete`, `forseti.conversation_export`
  - admin path `/admin/config/forseti/ai-provider`
- `src/Controller/ChatController.php`
  - `startChat()` redirects to `/forseti/chat`
  - `forsetiChat()` returns theme hook `forseti_chat`
  - conversation list returns theme hook `forseti_conversations`
- `ai_conversation.module`
  - theme hook registrations `forseti_chat` and `forseti_conversations`
- `templates/forseti-chat.html.twig`
  - template name and CSS class `forseti-chat-page`
  - welcome text says "I'm your Forseti AI career assistant"
- `templates/forseti-conversations.html.twig`
  - template name, CSS classes, and comments remain Forseti-specific

These are candidate-local publication blockers because a public package should not ship another product's branded route scheme and theme names unless Forseti itself is the intended public product identity.

### 3. Conversation bootstrap still injects Forseti job-seeker context

`src/Controller/ChatController.php` still creates new conversations with `buildJobSeekerContext()`, which:

- describes the assistant as being "for job seekers on forseti.life"
- depends on the `job_hunter` module when available
- reads `jobhunter_job_seeker` and `jobhunter_job_history` tables
- injects job-seeker profile context into the conversation

That is valid for the live Forseti site, but it is not sanitized standalone-module behavior.

### 4. Utility endpoint still hardcodes Forseti content by node ID

`src/Controller/UtilityController.php` still exposes `getNode10Content()` and documents node 10 as "Forseti platform information". The related route in `ai_conversation.routing.yml` is still `/admin/ai-conversation/get-node10`.

Hardcoding a site-local node ID and treating it as Forseti platform content is not public-module-safe.

### 5. Candidate docs still describe the module as a Forseti implementation

The candidate directory still contains Forseti-branded docs, including:

- `README.md`
  - example URL `https://forseti.com/node/11/chat`
- `AI_TROUBLESHOOTING.md`
  - opening overview says this is for GenAI operations in the Forseti.life application
- `FORSETI_CONTEXT.md`
  - entirely Forseti/AmISafe/crime-platform-specific
- `ARCHITECTURE.md`
  - describes the module as the AI foundation for the `forseti.com` platform

These are not secret-bearing blockers, but they are still sanitization blockers for a public candidate freeze.

## Live-module fixes vs extract-only fixes

### If the goal is to freeze the current live tree directly

The candidate remains **NO-GO** until the live module itself is sanitized. Minimum required live-tree changes:

1. Replace `PromptManager::getBaseSystemPrompt()` with neutral public prompt text.
2. Remove or generalize `buildJobSeekerContext()` so the module does not assume Forseti/job_hunter-specific data.
3. Remove the node-10 Forseti helper endpoint or convert it to generic configurable content.
4. Rename/generalize Forseti-specific routes, route names, theme hooks, template names, and user-facing copy.
5. Rewrite or remove Forseti-branded docs inside the candidate directory.

### If the goal is a curated sanitized extract

These changes can be done in the extracted public repo instead of the live Forseti module:

1. Rewrite all docs (`README.md`, `AI_TROUBLESHOOTING.md`, `ARCHITECTURE.md`, `FORSETI_CONTEXT.md`) for neutral public scope.
2. Rename Forseti-specific routes/theme hooks/templates in the extract.
3. Replace the Forseti prompt and bootstrap context in the extract.
4. Remove the node-10 utility endpoint from the extract.
5. Keep the current live-site behavior in Forseti if product needs require it, but do not freeze the live tree itself as the public package.

## Recommendation

Use a **curated sanitized extract** for the first public repo, not the current live tree. The earlier infra/HQ blockers have been resolved enough that the remaining work is now mostly product-boundary sanitization inside the candidate itself. That work is straightforward, but it still must be completed before a freezeable public package exists.

## Important scope note

This refresh replaces stale **candidate-local** blocker language only. It does **not** overturn the broader Phase 1 audit result in `sessions/dev-open-source/artifacts/20260414-phase1-security-audit-report.md`; the org-wide history and secret-scrub blockers remain separate NO-GO items for publication.

## Verification commands used

```bash
grep -RInE 'forseti|AmISafe|job seeker|job_hunter|node 10|forseti\.com|forseti\.life' /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation
view /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Service/PromptManager.php
view /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller/ChatController.php
view /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller/UtilityController.php
view /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/README.md
view /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/AI_TROUBLESHOOTING.md
cd /home/ubuntu/forseti.life/copilot-hq && scripts/export-public-mirror.sh /tmp/proj009-public-mirror-check
find /tmp/proj009-public-mirror-check -maxdepth 2 | sort
```
