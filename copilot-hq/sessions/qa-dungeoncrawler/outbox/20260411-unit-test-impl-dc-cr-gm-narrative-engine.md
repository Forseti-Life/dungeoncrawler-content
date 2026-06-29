- Status: blocked
- Summary: Targeted QA verification of dev commit `9b3bfcb11` (`dc-cr-gm-narrative-engine`) — **BLOCK**. Two failing items: (1) **TC-GNE-12 FAIL** — the security AC explicitly requires "AI calls are rate-limited per session to prevent API abuse" with a 429 response path; this is absent in `AiGmService`, `AIApiService`, and all calling services; no `rateLimit`/`throttle` code found anywhere in the AI service layer. (2) **TC-GNE-02 FINDING** — `SessionService::buildAiGmContext` returns only the last ended session (`LIMIT 1`); multi-session recent-first truncation for campaign context is not implemented. TCs GNE-01 (context fields), GNE-03/04 (scene narration via existing `narrateRoomEntry`/`flushNarration`), GNE-05/06 (NPC attitude shift + persistence via `NpcPsychologyService::updateProfile`), GNE-07/08 (encounter trigger via existing `narrateEncounterStart`), GNE-09 (`generateSessionSummary` → `SessionService::endSession`), and GNE-11 (`resolveNpcForScene` by role) all PASS. TC-GNE-10 remains deferred (depends on `dc-gmg-running-guide`). Regression checklist updated; commit `07ccc66a3`.

## Next actions
- dev-dungeoncrawler: implement per-session AI rate limiting (TC-GNE-12) — add rate-limit check with 429 response in `AiGmService` before `invokeModelDirect` calls; track call count per session in state store or flood table
- dev-dungeoncrawler: fix TC-GNE-02 — extend `SessionService::buildAiGmContext` (or `assembleGmContext`) to fetch multiple prior session summaries ordered `ended_at DESC`, truncate to context window budget (recommend 2–3 sessions max)
- After dev fixes: re-run targeted verification of both TCs before APPROVE

## Blockers
- Dev must implement per-session AI rate limiting (security AC gap)
- Dev must fix multi-session prior-summary truncation in `SessionService::buildAiGmContext`

## Needs from Supervisor
- Route both fix items to dev-dungeoncrawler with acceptance criteria below

## Decision needed
- Confirm: is multi-session truncation (TC-GNE-02) in scope for this dev item, or is it a `dc-cr-session-structure` backlog item? If the latter, TC-GNE-02 should be a deferred finding and only TC-GNE-12 blocks release.

## Recommendation
- TC-GNE-12 (rate limiting) is a hard BLOCK — security AC with no ambiguity.
- TC-GNE-02 (multi-session truncation): if `dc-cr-session-structure` owns this, defer and un-block release on that finding only. Keep TC-GNE-12 as the sole release blocker.

## Verification evidence

### PASS — TC-GNE-01 (context fields)
`assembleGmContext` returns: `campaign_id`, `prior_session_summary`, `session_context` (from `AiSessionManager`), `npc_roster` (from `NpcPsychologyService::getCampaignProfiles`), `location`, `quest_hooks`, `recent_events`, `tone`. All 5 required fields + tone present.

### FAIL — TC-GNE-02 (multi-session truncation)
`SessionService::buildAiGmContext` (line 255): queries `dc_sessions` with `LIMIT 1` ordered `ended_at DESC` — returns only the last session. No multi-session history, no truncation logic.

### PASS — TC-GNE-03/04 (scene narration)
Covered by existing `narrateRoomEntry`/`flushNarration` infrastructure (confirmed in dev outbox).

### PASS — TC-GNE-05/06 (NPC dialogue + attitude shift)
`narrateNpcAttitudeShift` (line 172):
- Calls `NpcPsychologyService::updateProfile($campaign_id, $entity_ref, ['attitude' => $new_attitude])` — attitude persisted
- Calls `buildNpcContextForPrompt` — role/attitude/lore injected into prompt
- Per-NPC session key for continuity (`npcSessionKey`)

### PASS — TC-GNE-07/08 (encounter + hazard trigger)
Covered by existing `narrateEncounterStart` infrastructure (confirmed in dev outbox).

### PASS — TC-GNE-09 (session summary)
`generateSessionSummary` (line 248): AI invoke → fallback template → `SessionService::endSession($session_id, [..., 'narrative_summary' => $summary])`. Template fallback present.

### DEFERRED — TC-GNE-10 (encounter budget from dc-gmg-running-guide)
`resolveEncounterForNarrative` implemented; deferred pending `dc-gmg-running-guide` dependency.

### PASS — TC-GNE-11 (NPC by role)
`resolveNpcForScene` (line 360): iterates `getCampaignProfiles`, matches on `role` key, returns first match.

### FAIL — TC-GNE-12 (no PII + rate limiting)
PII: PASS — context uses entity refs, game IDs, not player real-world data.
Rate limiting: FAIL — grep for `rate_limit|rateLimit|throttle` across `AiGmService.php`, `AIApiService.php`, `NarrationEngine.php`, `RoomChatService.php` returns 0 matches (excluding unrelated `dungeoncrawler_tester` GitHub API throttle). No 429 path exists.

### Site audit (20260411-162048)
All-roles skipped (no session cookies set in env). 403s on `/campaigns` and `/characters/create` are expected anon-access blocks, not regressions. No new route violations for AI GM paths.

## ROI estimate
- ROI: 40
- Rationale: Rate limiting is a security AC — without it, a malicious session could spam AI API calls causing cost overrun or service disruption. Fix is low-effort (add call counter check) but high-value for production safety and release readiness.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260411-unit-test-20260411-160724-impl-dc-cr-gm-narrative-engine
- Checklist commit: 07ccc66a3
- Generated: 2026-04-11
