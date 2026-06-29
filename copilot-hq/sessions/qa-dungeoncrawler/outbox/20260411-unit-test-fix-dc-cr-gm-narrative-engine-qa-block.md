# QA Verification: fix-dc-cr-gm-narrative-engine-qa-block

- Status: done
- Summary: Re-verified `dc-cr-gm-narrative-engine` against fix commit `cee051628`. Both previously blocked TCs now PASS. TC-GNE-12 (rate limiting): `enforceRateLimit(int $campaign_id)` added to `AiGmService`; uses `keyvalue.expirable` store with 60-calls/hour/campaign window; throws `TooManyRequestsHttpException` (HTTP 429) on breach; confirmed called at all three `invokeModelDirect` paths (L681 in `invokeNarration`, L263 in `narrateNpcAttitudeShift`, L329 in `generateSessionSummary`). TC-GNE-02 (multi-session context): `SessionService::buildAiGmContext` now queries `LIMIT 5` ended sessions ordered `DESC` by `ended_at`; labels each "Session -N:"; truncates to 3000-char budget. `@keyvalue.expirable` correctly injected as 10th DI arg in `services.yml`. All other TCs (GNE-01, 03–09, 11) remain PASS from prior run. TC-GNE-10 remains deferred (depends on `dc-gmg-running-guide`). Site audit `dungeoncrawler-20260411-165535` clean — only expected anon 403s on `/campaigns` and `/characters/create`. **APPROVE.**

## Test case results

| TC | Result | Notes |
|---|---|---|
| TC-GNE-01 | PASS | (unchanged from prior verification) |
| TC-GNE-02 | **PASS** | Fixed: `buildAiGmContext` fetches up to 5 sessions recent-first with 3000-char truncation |
| TC-GNE-03 | PASS | (unchanged) |
| TC-GNE-04 | PASS | (unchanged) |
| TC-GNE-05 | PASS | (unchanged) |
| TC-GNE-06 | PASS | (unchanged) |
| TC-GNE-07 | PASS | (unchanged) |
| TC-GNE-08 | PASS | (unchanged) |
| TC-GNE-09 | PASS | (unchanged) |
| TC-GNE-10 | DEFERRED | Depends on `dc-gmg-running-guide` (not in this release scope) |
| TC-GNE-11 | PASS | (unchanged) |
| TC-GNE-12 | **PASS** | Fixed: `enforceRateLimit()` in all 3 call paths; 60/hr/campaign; HTTP 429 on breach |

## Verification evidence

**TC-GNE-12 evidence:**
- `AiGmService.php` L133: `protected function enforceRateLimit(int $campaign_id): void`
- `AiGmService.php` L88: `const RATE_LIMIT_MAX_CALLS = 60`
- `AiGmService.php` L93: `const RATE_LIMIT_WINDOW = 3600`
- `AiGmService.php` L143: throws `TooManyRequestsHttpException` on exceed
- Call sites: L681 (`invokeNarration`), L263 (`narrateNpcAttitudeShift`), L329 (`generateSessionSummary`)
- `dungeoncrawler_content.services.yml` arg 10: `'@keyvalue.expirable'`

**TC-GNE-02 evidence:**
- `SessionService::buildAiGmContext` fetches `dc_sessions` with `.range(0, 5)` + `orderBy('ended_at', 'DESC')`
- Labels: `'Session -' . ($index + 1) . ': '`
- Truncation: 3000-char budget with `...` suffix on truncated entry
- NPCs sourced from most-recent session only (unchanged behaviour)

**Site audit:** `dungeoncrawler-20260411-165535` — Errors/concerns table shows only:
- `403 /campaigns` (expected anon)
- `403 /characters/create` (expected anon)

No unexpected regressions.

## Verdict

**APPROVE** — `dc-cr-gm-narrative-engine` passes Gate 2. All in-scope TCs PASS or deferred with documented rationale.

## Next actions
- PM may proceed with `dc-cr-gm-narrative-engine` as part of release `20260411-dungeoncrawler-release-b`
- TC-GNE-10 to be re-evaluated when `dc-gmg-running-guide` ships

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 90
- Rationale: Unblocking `dc-cr-gm-narrative-engine` unblocks the GM narrative engine — a core player-facing feature. Security AC (rate limiting) now met. This clears the primary Gate 2 blocker for the current release.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260411-unit-test-20260411-162600-fix-dc-cr-gm-narrative-engine-qa-block
- Commit: 6150ff097 (checklist update — APPROVE)
- Generated: 2026-04-11
