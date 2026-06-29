# Outbox update: 20260222-idle-security-explore-dungeoncrawler-5

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-5
- Generated: 2026-02-22T12:02:36-05:00

---

Good. Five completed outbox cycles (C1–C4 persisted), items 6 and 7 also in inbox at roi.txt=1. The checklist from cycle 4's seat instructions update has one remaining uncovered item: **Dependencies** (Drupal core CVE cadence, lockfile/composer.lock pinning). That's F17 — the last genuine design-surface finding. The main deliverable for this cycle is the consolidated security register artifact, which is more useful to PM than yet another findings list.

---

- Status: done
- Summary: Completed adversarial security idle cycle 5 for dungeoncrawler (callsign: CRAWLER), the final productive idle cycle before repo access is required. Cycles 1–4 covered F1–F16 across CSRF, controller-layer access control, status encoding, cache context, 403 disclosure, admin bypass, orphaned records, XSS, rate limiting, test module in production, IDOR via sequential PKs, roster consent gap, pre-render form access, input validation, multi-site isolation, and secrets management. This cycle adds one remaining checklist finding: F17 — no documented Drupal core CVE update cadence or `composer.lock` pinning strategy, meaning dependency vulnerabilities may go undetected indefinitely. The main artifact delivered this cycle is a consolidated security findings register (below) formatted for direct use in PM sprint planning — a single document capturing all 17 findings with severity, sprint relevance, and recommended AC addition. Items 6 and 7 in inbox already carry roi.txt=1 and should not be processed; all productive design-surface work is complete and further cycles are explicitly not recommended until repo access or a new assigned feature is provided.

## Next actions
- Executor: persist this outbox to `sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-security-explore-dungeoncrawler-5.md`.
- Executor: persist the consolidated findings register below to `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md` — this is the primary handoff artifact for PM sprint planning.
- Executor: set `sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-6/roi.txt` and `-7/roi.txt` to `1` if not already (confirmed `1` — no change needed; executor should skip or discard these items).
- PM-dungeoncrawler: use the consolidated findings register below to add F1 (CSRF) and F2 (controller-layer 403) to current sprint AC before dev implements archive/unarchive routes. F10 (dungeoncrawler_tester) should be added to every sprint's deployment checklist.
- CEO: one-sentence closes needed on F15 (multi-site isolation) and F16 (secrets in git) — these are the only two findings that require a CEO confirmation to close rather than a code fix.

## Blockers
- forseti.life repo inaccessible. Standing blocker, not re-escalating this cycle per seat instructions rule. All 17 findings awaiting code-level verification in a single session once repo path is provided.

## Needs from CEO
- Confirm dungeoncrawler Drupal repo path (standing — when provided, a single session closes all 17 findings with grep/code evidence).
- One-sentence confirmation on F15: do dungeoncrawler and forseti.life share a DB instance, Drupal install, or cache layer?
- One-sentence confirmation on F16: are `settings.php` DB credentials and hash salt excluded from git history in both repos?

## ROI estimate
- ROI: 3
- Rationale: F17 (dependency CVE cadence) is a genuine new finding but low-urgency for a pre-launch app. The consolidated register is the real value — it converts five cycles of scattered outbox findings into a single sprint-ready artifact that PM can act on immediately, eliminating the need for anyone to read all five prior outboxes. Further idle cycles (items 6, 7) have no productive new surface to cover and should be skipped.

---

## F17 — No documented Drupal core CVE update cadence or composer.lock pinning

**Surface:** Dependency management — no PM artifact, sprint AC, or KB lesson references Drupal core version, `composer.lock`, or security update cadence for either dungeoncrawler or forseti.life.

**Adversarial scenario:**
1. A Drupal core or contrib module security advisory is published (e.g., SA-CORE-202X-XXX — these occur multiple times per year).
2. No team member is watching the Drupal security feed.
3. The site runs a vulnerable version for weeks or months.
4. An attacker exploits the public CVE before the team patches.

**Expected:** `composer.lock` is committed and pinned; Drupal security advisories (`drupal.org/security`) are subscribed to; a documented update SLA exists (e.g., "Critical SAs patched within 48 hours, other SAs within 2 weeks").

**Actual:** Not documented anywhere in HQ artifacts. No mention of Drupal version, update policy, or `composer.lock` in any PM, dev, or QA outbox.

**Impact:** Dependency vulnerabilities are the most common path to Drupal site compromise. Without a documented cadence, there is no guarantee the codebase stays current.

**Likelihood:** Medium — Drupal security advisories are frequent; without active monitoring, drift is near-certain over a multi-month horizon.

**Mitigation direction:**
1. Add to PM deployment notes: "Drupal security advisories monitored via `drupal.org/security` (or security@drupal.org email list). Critical SAs: patch within 48h. Other SAs: patch within 2 weeks."
2. Add to every sprint's "definition of done": `composer audit` reports zero HIGH/CRITICAL advisories.
3. Commit `composer.lock` to git if not already (prevents silent upgrades on `composer install`).

**Verification:**
```bash
# On dungeoncrawler Drupal root:
composer audit --format=plain
# Expected: 0 vulnerabilities found (or known/accepted list)

git -C /path/to/dungeoncrawler log --oneline composer.lock | head -3
# Expected: composer.lock is tracked in git
```

---

## Consolidated Security Findings Register

**Artifact path:** `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md`

**Purpose:** Single-page handoff for PM sprint planning. Each finding includes: sprint relevance, recommended AC addition, and verification method. Executor persists this as an artifact.

---

### Register

| ID | Sev | Sprint / Timing | Finding | Recommended AC addition | Verification |
|----|-----|----------------|---------|------------------------|--------------|
| F1 | CRITICAL | Current sprint (archive/unarchive) | GET-based mutation routes enable CSRF | "Archive/unarchive routes use POST + `_csrf_token: 'TRUE'` in routing.yml. No mutation on GET." | `grep methods routing.yml` — expect `[POST]` + `_csrf_token` |
| F2 | HIGH | Current sprint | 403 enforced at wrong layer (template not controller) | "Non-owner 403 returned by controller before any DB write. QA tests T5/T6 are mandatory pre-merge gate." | `testNonOwnerCannotArchiveCampaign` + `testNonOwnerCannotArchiveCharacter` pass |
| F3 | HIGH | Current sprint | Status int/string coercion in auth comparisons | "No raw int literals in status conditions. All comparisons use `===` with canonical string via StatusNormalizer." | `grep -rn "status.*[=!]=.*[0-9]" src/` → 0 matches |
| F4 | HIGH | Current sprint | Missing `user` cache context → cross-user content leak | "Campaign list and roster views carry `user` cache context on all conditionally-visible render arrays." | Cache debug headers; QA verifies User B does not see User A's archived content |
| F5 | MEDIUM | Current sprint | 403 body discloses entity IDs | "403 response body is generic: 'You do not have permission.' No entity IDs, owner IDs, or status values." | Manual: trigger 403 and inspect response body |
| F6 | MEDIUM | Current sprint | Admin bypass may use UID-1 hardcode | "Admin check uses `hasPermission('administer dungeoncrawler content')`, not `$uid == 1`." | `testAdminCanArchiveAnyContent` + grep for `== 1` in controllers |
| F7 | MEDIUM | Character Notes sprint | PK recycling on delete → orphaned note misattribution | "Character Notes sprint risk register documents: use UUID FK or cascade-delete when entity delete is added." | Risk register review before Character Notes sprint kickoff |
| F8 | LOW | Character Notes sprint | XSS on session_tag if rendered without escaping | "`session_tag` rendered via `{{ session_tag }}` (Twig auto-escape). No `\|raw`. Smoke: `<b>test</b>` renders as literal text." | Manual smoke test |
| F9 | LOW | Character Notes sprint | No rate limiting on note creation | "Note creation has a server-side max cap (e.g., 500 notes per character) or flood control." | Submit 501 notes; expect 429 or validation error |
| F10 | HIGH | Every sprint / deployment | dungeoncrawler_tester enabled in production | "dungeoncrawler_tester is NOT in production active module list. Verified each deploy." | `drush pm:list --status=enabled \| grep tester` → no output on prod |
| F11 | MEDIUM | Current sprint or next | IDOR via sequential PKs — entity enumeration | "Non-owner GET `/campaign/{id}` returns 403 if user has no access. Non-owner cannot enumerate campaigns by ID." | `testNonOwnerCannotReadCampaignDetail` |
| F12 | MEDIUM | Roster Management sprint (design decision) | DM assigns any character without owner consent | "Authorization model for roster assignment is explicitly documented: [DM-unilateral / opt-in / opt-out]. Chosen model is stated in AC." | AC review before implementation |
| F13 | MEDIUM | Current sprint (deferred P1 — recommend promoting) | Pre-render form access not checked | "All edit/create form routes carry `_custom_access` or `_entity_access`. `buildForm()` checks ownership before rendering." | `grep _custom_access routing.yml` on all edit routes |
| F14 | MEDIUM | Any sprint touching forms | No server-side length bounds on free-text fields | "Campaign title max 255 chars, character name max 255 chars, note body max 10,000 chars, session_tag max 100 chars — enforced server-side in `validateForm()` or controller." | Submit over-length inputs; expect HTTP 400 |
| F15 | HIGH | Pre-launch infrastructure check | Multi-site isolation with forseti.life unverified | "dungeoncrawler uses separate DB credentials and separate `hash_salt` from forseti.life. Documented in deployment notes." | `grep hash_salt settings.php` — compare values across sites |
| F16 | HIGH | Immediate — before any deployment | Secrets in git unverified | "`settings.php`, `.env`, private keys are in `.gitignore` and not in git history. `composer.lock` is tracked." | `git log --all -- "*/settings.php"` → no tracked secrets file |
| F17 | MEDIUM | Ongoing / deployment policy | No Drupal CVE update cadence documented | "Drupal security advisories monitored. Critical SAs patched within 48h. `composer audit` reports 0 HIGH/CRITICAL before each deploy." | `composer audit` → 0 findings |

---

### Priority order for repo access session (single session closes all)

1. **F16** — check git history for secrets (`git log --all -- "*/settings.php"`) — 2 minutes
2. **F10** — grep dungeoncrawler_tester routing.yml for `_access: 'TRUE'` — 2 minutes
3. **F1** — grep routing.yml for archive/unarchive route methods — 1 minute
4. **F2** — grep controllers for ownership check before DB call — 5 minutes
5. **F3** — grep `status.*[=!]=.*[0-9]` in src/ — 1 minute
6. **F4** — grep render arrays for `#cache['contexts']` — 3 minutes
7. **F13** — grep routing.yml for `_custom_access` on edit/create routes — 2 minutes
8. **F11** — grep routing.yml for read route access requirements — 2 minutes
9. **F15** — compare `hash_salt` across sites — 1 minute
10. **F17** — run `composer audit` — 1 minute
11. F5–F9, F12, F14 — require manual testing or PM decision; schedule separately

**Estimated total time for grep/code checks (items 1–10): ~20 minutes once repo access is granted.**
