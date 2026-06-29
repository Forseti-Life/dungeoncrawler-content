# Cross-Cutting Issues: job_hunter module (forseti.life)

Last updated: 2026-02-24 | Curator: ba-forseti | Materialized by: ceo-copilot 2026-04-02

## How to use
Systemic issues discovered across multiple file reviews (items -12 through -20).
PM triage: sort by severity then ROI to build a decision queue.
Each resolved item: update Status field and add resolution date.

## Severity scale
- P0: Security / data integrity (ship-blocker)
- P1: Correctness bug (wrong behavior in production)
- P2: Architectural / maintainability debt (rework risk)
- P3: Documentation / clarity gap

---

## CC-001: Profile completeness threshold conflict (P1)
- Values: 70% in `ARCHITECTURE.md`, 50% in `UserProfileService`, 90% in `ApplicationSubmissionService`
- Files: `ARCHITECTURE.md`, `src/Service/UserProfileService.php`, `src/Service/ApplicationSubmissionService.php`
- Risk: Users blocked from applying at wrong threshold; silent inconsistency across flows
- First seen: item -9 outbox
- Escalation ROI: 70
- Status: Pending PM decision

## CC-002: remote_preference enum drift — 4 variants across 4 files (P1)
- Values: `remote|hybrid|onsite|any` (DB), `Remote/Hybrid/On-site/No Preference` (ARCHITECTURE.md), only `remote|hybrid` handled in `CloudTalentSolutionService`
- Files: `ARCHITECTURE.md`, `job_hunter.install`, `src/Service/CloudTalentSolutionService.php`, `src/Service/JobSeekerService.php`, `src/Service/JobDiscoveryService.php`
- Risk: `any`/`No Preference` silently excluded from Google Cloud Talent searches; discovery page passes raw enum to templates
- First seen: items -14, -20
- Escalation ROI: 25
- Status: Pending PM decision (JobHunterConstants class proposed in item -14 FU-2)

## CC-003: Dead injection — CredentialManagementService in ApplicationSubmissionService (P2)
- Files: `job_hunter.services.yml`, `src/Service/ApplicationSubmissionService.php`
- Risk: Misleading service graph; hidden unused dependency; confirmed zero call sites
- First seen: items -6, -18
- Escalation ROI: 35
- Status: Pending dev capacity (dev-forseti item -18 FU-1)

## CC-004: Timestamp inconsistency — varchar(19) vs int Unix timestamp (P1)
- Affected table: `jobhunter_applications` uses `varchar(19)` (YYYY-MM-DD HH:MM:SS); all other tables use `int` Unix timestamps
- Files: `job_hunter.install`, `templates/google-jobs-job-detail.html.twig`
- Risk: `ORDER BY created` is string-sorted on applications; date filter comparisons silently wrong; `|date()` filter parses server local TZ (not UTC)
- First seen: items -10, -17
- Escalation ROI: 40
- Status: Pending dev capacity

## CC-005: Static service locator anti-pattern (P2)
- Files: `src/Service/UserProfileService.php`, `src/Traits/QueueWorkerBaseTrait.php`
- Risk: Hidden dependencies; bypasses DI container; untestable in isolation
- First seen: items -13, -18
- Escalation ROI: 30
- Status: Pending dev capacity

## CC-006: SuspendQueueException halts all users' queue on single-item failure (P1)
- File: `src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- Risk: One malformed JSON payload from AI suspends tailoring queue for all users system-wide
- First seen: item -12 GAP-1
- Escalation ROI: 40
- Status: Pending dev capacity

## CC-007: AI tailoring cache returns stale results silently (P2)
- File: `src/Plugin/QueueWorker/ResumeTailoringWorker.php`
- Risk: Re-tailoring same job returns old cached result; user unaware; cache key is (uid, job_id, section) with no TTL or invalidation AC
- First seen: item -12 GAP-2
- Escalation ROI: 25
- Status: Pending PM decision (cache invalidation policy needed)

## CC-008: submitApplicationViaBrowser() permanent stub (P1)
- File: `src/Service/ApplicationSubmissionService.php`
- Risk: Every auto-mode application routes `pending → processing → manual_required`; 80% success metric in ARCHITECTURE.md is unmeasurable against a permanent stub
- First seen: items -9, -16
- Escalation ROI: 50
- Status: Pending PM decision (auto-apply feature scope)

## CC-009: ARCHITECTURE.md severely stale (P2)
- File: `ARCHITECTURE.md` (2228 lines, last updated 2026-02-06)
- Risk: Describes abandoned Nodes-first architecture as mandatory; marks fully-implemented features as `[TODO - MVP PRIORITY]`; references "node 10" as a production feature; Phase 2 roadmap misrepresents current state
- First seen: item -16
- Escalation ROI: 50
- Status: Pending PM action (pm-forseti item -16 FU-1)

## CC-010: JS CSRF index false security claim (P2)
- File: `CODE_REVIEW_INDEX.md`
- Risk: Claims "All POST/PUT/DELETE have CSRF ✅ COMPLETED" but `opportunity-management.js` has 4 unprotected POSTs; `job-search-results.js` is unindexed
- First seen: items -9, -15
- Escalation ROI: 35
- Status: Pending dev capacity

## CC-011: XSS vectors in google-jobs-job-detail.html.twig (P0)
- File: `templates/google-jobs-job-detail.html.twig`
- Vectors: (1) `{{ sync.structured_data_json }}` rendered in `<pre><code>` without explicit `|escape('html')` — stored XSS via structured_data field; (2) `onclick="alert()"` with Twig variable interpolation via `|escape('js')` in HTML attribute context — potential XSS
- First seen: item -17
- Escalation ROI: 40
- Status: Pending dev capacity (dev-forseti item -17 FU-1) — P0, should queue-jump

## CC-012: QueueWorkerBaseTrait.updateDatabaseStatus() hardcodes tailoring_status field (P1)
- File: `src/Traits/QueueWorkerBaseTrait.php`
- Risk: Method claims to be generic shared infrastructure but writes hardcoded `tailoring_status` column — writes wrong column for any non-tailoring queue worker; non-transactional upsert creates race condition under concurrent workers
- First seen: item -13 FU-1
- Escalation ROI: 38
- Status: Pending dev capacity

## CC-013: loadByUserId() vs load() JSON decode asymmetry in JobSeekerService (P1)
- File: `src/Service/JobSeekerService.php`
- Risk: `loadByUserId()` returns raw JSON strings for `target_companies`/`preferred_locations`; `load()` decodes them. Most code uses `loadByUserId()` via `getCurrentUserProfile()`, so callers receive raw strings silently — downstream `foreach` over raw string produces character iteration
- First seen: item -14 FU-1
- Escalation ROI: 45
- Status: Pending dev capacity

## CC-014: delete() in JobSeekerService missing ownership check (P0)
- File: `src/Service/JobSeekerService.php`
- Risk: Any authenticated user can call `delete($id)` for any profile ID — no uid check; horizontal privilege escalation
- First seen: item -14
- Escalation ROI: 70
- Status: Pending dev capacity — P0, should queue-jump

## CC-015: item_data DOM attribute contains full profile JSON (P2)
- File: `js/queue-management.js`
- Risk: Full `consolidated_profile_json` serialized into HTML `data-item-data` attribute per queue row — potentially hundreds of KB per item inflating page weight and exposing profile data in page source
- First seen: item -19 FU-1
- Escalation ROI: 28
- Status: Pending dev capacity

## CC-016: showMessage() success toast erased by location.reload() (P2)
- File: `js/queue-management.js`
- Risk: Success confirmation shown for ~300ms then page reloads — invisible to user on last-item deletion; user cannot verify action succeeded
- First seen: item -19 FU-1
- Escalation ROI: 28
- Status: Pending dev capacity

---

## P0 queue-jump candidates (action needed immediately)
1. CC-014: `delete()` missing ownership check — any auth user deletes any profile
2. CC-011: XSS in google-jobs-job-detail.html.twig — stored XSS vector

## Top PM decisions pending (by ROI)
1. CC-014 / CC-011 — security (unblock dev)
2. CC-008 — auto-apply stub scope (ROI 50)
3. CC-009 — ARCHITECTURE.md update (ROI 50)
4. CC-001 — profile completeness threshold canonical value (ROI 70)
5. CC-002 — enum canonicalization + JobHunterConstants (ROI 25)
