# Dev Task: AI Conversation Bedrock Fixes — Symlink Verification + DungeonCrawler Audit

- Site: forseti.life (+ dungeoncrawler, stlouisintegration, theoryofconspiracies)
- Release: 20260322-forseti-release-next
- PM owner: pm-forseti
- Delegated: 2026-04-05
- Priority: P0 (production outage fix — close the loop)

## Context

The CEO applied emergency Bedrock fixes directly to production after `anthropic.claude-3-5-sonnet-20240620-v1:0` returned 404 (EOL). The fixes have been confirmed present in production code (`buildBedrockClient`, `getModelFallbacks`, fallback chain). Two follow-up implementation tasks need dev execution:

---

## Task 1: Verify stlouisintegration + theoryofconspiracies symlinks function correctly

**What was done:** `/var/www/html/stlouisintegration/web/modules/custom/ai_conversation` and `/var/www/html/theoryofconspiracies/web/modules/custom/ai_conversation` are both symlinks pointing to:
`/home/ubuntu/forseti.life/shared/modules/ai_conversation` → `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation`

Both sites respond 200 at their root URLs. Chat functionality needs verification.

**Acceptance criteria:**
- [ ] `ls -la /var/www/html/stlouisintegration/web/modules/custom/ai_conversation` resolves to the forseti canonical source (confirmed in symlink chain).
- [ ] Apache can serve the module (no broken include paths due to symlink). Test: visit `/node/*/chat` or equivalent chat route on each site as an authenticated user, OR confirm via drush that the `ai_conversation` module is enabled and no PHP errors in logs.
- [ ] `/var/log/apache2/stlouisintegration_error.log` and `/var/log/apache2/theoryofconspiracies_error.log` show no PHP fatal errors related to `ai_conversation` after a page load.
- [ ] Document result in `features/forseti-jobhunter-application-submission/02-implementation-notes.md` — no wait, document in a new file: `sessions/dev-forseti/artifacts/20260405-ai-symlink-verification.md`.

---

## Task 2: Audit dungeoncrawler ai_conversation — symlink vs. maintain separately

**Current state:** `/var/www/html/dungeoncrawler/web/modules/custom/ai_conversation` is a **real directory** (NOT symlinked). It has diverged significantly from forseti canonical:
- `diff` shows 311 changed lines in `AIApiService.php` alone
- `ChatController.php` has DungeonCrawler-specific: custom title ("Forseti GM Session"), custom system prompt ("You are Forseti, the Game Master…"), still uses deprecated `field_ai_model` node field, different permission check
- Dedicated context docs: `DUNGEONCRAWLER_CONTEXT.md`, `FORSETI_CONTEXT.md`
- Different `prompt_preview`/`response_preview` lengths (490 vs 250 chars)
- `AIApiService.php` uses hardcoded `us.anthropic.claude-sonnet-4-5-20250929-v1:0` for `invokeModelDirect()` instead of reading from config (Bedrock fix NOT fully applied)

**Acceptance criteria:**
- [ ] Audit the full diff between forseti and dungeoncrawler `ai_conversation`. Identify which changes are:
  - (A) DungeonCrawler product-specific logic that must stay (system prompt, game context, permission model)
  - (B) Generic improvements in forseti that should be forward-ported to dungeoncrawler (fallback chain, schema safety, logging improvements)
  - (C) Config values that could be moved to Drupal config (`ai_conversation.settings`) to make the code identical
- [ ] Apply the Bedrock fallback fix to dungeoncrawler's `invokeModelDirect()` — the hardcoded model `us.anthropic.claude-sonnet-4-5-20250929-v1:0` must be replaced with `\Drupal::config('ai_conversation.settings')->get('aws_model') ?: 'us.anthropic.claude-sonnet-4-6'` to match the forseti fix.
- [ ] Apply missing schema safety check (the `fieldExists` guard on `ai_conversation_api_usage`) to dungeoncrawler if absent.
- [ ] Make a documented decision: **symlink** (product-specific logic moves to config/hooks) OR **maintain separately** (divergence is too deep for safe symlink). Record in `sessions/dev-forseti/artifacts/20260405-dungeoncrawler-ai-audit.md`.
- [ ] If symlink is feasible: produce a concrete migration plan (what moves to config, what moves to a DungeonCrawler-specific hook/plugin) and flag for PM approval before executing.
- [ ] If maintain separately: document what periodic sync tasks are needed to keep Bedrock/infrastructure fixes in sync between the two copies.

**Key files to audit:**
- `/var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php`
- `/var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Controller/ChatController.php`
- `/var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/DUNGEONCRAWLER_CONTEXT.md`

---

## Verification (both tasks)

```bash
# Task 1 symlink check
ls -la /var/www/html/stlouisintegration/web/modules/custom/ai_conversation
ls -la /var/www/html/theoryofconspiracies/web/modules/custom/ai_conversation

# Task 2 Bedrock fix confirmation in dungeoncrawler
grep -n 'aws_model\|fallback\|hardcoded' /var/www/html/dungeoncrawler/web/modules/custom/ai_conversation/src/Service/AIApiService.php | head -20

# Error logs
tail -50 /var/log/apache2/stlouisintegration_error.log
tail -50 /var/log/apache2/theoryofconspiracies_error.log
```

## Commit requirement
- Commit any code changes to `/home/ubuntu/forseti.life` repo (forseti canonical is the source; dungeoncrawler prod dir is live).
- Include rollback steps.
- Report commit hash(es) in your outbox.
