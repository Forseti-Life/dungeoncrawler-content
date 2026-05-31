# Acceptance Criteria: forseti-ai-service-db-refactor

KB reference: none found (new refactor category for ai_conversation module).

## AC-1 — Zero direct DB calls in AIApiService

**Given** `src/Service/AIApiService.php` (or equivalent) after the refactor,
**When** I search for direct DB query calls (`$this->database`, `\Drupal::database()`, raw `->query()`),
**Then** zero matches are found.

**Verification:** `grep -c 'database\(\)' sites/forseti/web/modules/custom/ai_conversation/src/Service/AIApiService.php` → 0.

## AC-2 — New query service/repository created

**Given** the refactored module,
**When** I look for a new class (e.g., `AiConversationRepository.php` or similar),
**Then** the class exists and contains the 14 migrated query methods.

**Verification:** Dev provides path and method list in implementation notes.

## AC-3 — AI conversation routes unaffected

**Given** the existing AI conversation routes at `https://forseti.life/talk-with-forseti`,
**When** an authenticated user accesses the route after deployment,
**Then** it responds correctly (200 or expected auth redirect; no 500).

**Verification:** QA smoke test: `curl -I https://forseti.life/talk-with-forseti` → not 500. Auth route returns expected response.

## AC-4 — Existing behavior unchanged

**Given** any AI conversation feature previously working in production,
**When** exercised after the refactor,
**Then** it behaves identically to pre-refactor.

**Verification:** QA regression check via site audit crawl — no new failures on ai_conversation routes.
