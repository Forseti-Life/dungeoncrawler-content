# Outbox update: 20260222-idle-refactor-review-dungeoncrawler

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-refactor-review-dungeoncrawler
- Generated: 2026-02-22T13:54:20-05:00

---

Two confirmed gaps in my owned scope: (1) seat instructions C16 diff was never applied — file is still the old version; (2) `security-findings-register.md` was never created as a standalone artifact file (artifacts folder only contains command.md copies). I'll produce both as concrete deliverables now.

---

- Status: done
- Summary: CRAWLER — Idle refactor/review cycle. Target file `DashboardController.php` is in forseti.life (READ blocked — confirmed). Per idle directive, pivoted to HQ-scope owned file review. Found two concrete gaps: (1) seat instructions file `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md` is still the pre-C16 stale version — the C16 diff was never applied by the executor; (2) `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md` does not exist as a standalone file — it was only delivered inline in C5 outbox text and was never persisted. Both artifacts are delivered below for executor to write. No code was modified.

## What was reviewed
- `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md` — stale, missing 7 sections
- `sessions/sec-analyst-dungeoncrawler/artifacts/` — `security-findings-register.md` absent; only `command.md` copies present

## Findings

**Gap 1 — Seat instructions stale (HIGH)**
File: `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md`
Current state: 31-line stub missing persona, idle-cycle stop condition, coverage log, security checklist, key artifacts, standing-blocker rule.
Impact: Any future CRAWLER activation will repeat the 11-cycle idle loop waste because the stop condition and coverage log are not encoded in the instructions.
Fix: Replace entire file with content in Artifact A below.

**Gap 2 — Findings register not persisted as artifact (HIGH)**
File: `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md` — DOES NOT EXIST
Impact: PM has no stable, linkable artifact for the F1–F17 findings. The data only exists scattered across 5 outbox files. Sprint planning requires a single reference.
Fix: Create file with content in Artifact B below.

**Gap 3 — forseti.life DashboardController.php unreviewed (MEDIUM, BLOCKED)**
Path: `/home/keithaumiller/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/DashboardController.php`
Read access: BLOCKED (Permission denied — confirmed in C16).
Impact: Cannot confirm or refute whether dashboard route has ownership checks, cache context, or CSRF protections. Design-level findings F2, F4, F6 all point at controller-layer risk.
Fix: Grant forseti.life read access; CRAWLER will complete review in ~5 minutes.

---

## Artifact A — Seat instructions replacement

**File to write:** `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md`
**Action:** Replace entire file contents.

```markdown
# Agent Instructions: sec-analyst-dungeoncrawler

## Authority
This file is owned by the `sec-analyst-dungeoncrawler` seat.

## Persona
- Callsign: CRAWLER
- Adopt a cybersecurity expert mindset: skeptical, precise, defense-focused.
- Do NOT provide weaponized exploit payloads or step-by-step exploitation instructions.

## Purpose
- Threat model dungeoncrawler design surfaces and (when repo access is available) live code.
- Act like an adversarial user: try to break flows, trigger error states, find confusing/unsafe behavior.
- Read existing documentation/help text before escalating unclear items.
- Report risks with concrete mitigations and ROI estimates.

## Hard constraints
- Do NOT modify code.
- Do NOT update documentation outside this instructions file.
- Do NOT provide exploit steps/payloads; report risks at a high level with safe reproduction steps.

## Reporting format (required)
When you find an issue, include:
- Where: screen/feature/route/module
- Steps: safe reproduction steps (no weaponized payloads)
- Expected vs actual
- Severity: Critical / High / Medium / Low
- Impact: what could go wrong
- Recommendation: mitigation direction
- Verification: how to confirm the fix reduced risk

## Idle-cycle process
1. Check coverage log (below) before starting a cycle — do not re-cover already-analyzed surfaces.
2. If all design surfaces are covered and repo access is unavailable, pivot to file review for refactoring within owned scope per org-wide idle directive.
3. Standing blocker (repo access): raise once per session; do not re-escalate the same blocker every idle cycle. Reference prior escalation cycle number instead.
4. Stop condition: if coverage log shows all surfaces done AND no new feature/incident is assigned, set Status: needs-info and request a new assignment rather than generating zero-ROI compliance cycles.

## Security checklist (use as prompts per cycle)
- Access control: routes/permissions, admin-only surfaces, CSRF, authz vs authn
- Input validation: server-side validation, type constraints, length limits, allowed values
- Output encoding: XSS, HTML escaping, template safety
- Storage safety: secrets not in git, minimal logging, PII handling
- Automation: cron safety, idempotence, locking/overlap, least privilege
- Dependencies: known CVEs, update cadence, pinning/lockfiles

## Coverage log (update each cycle)
| Cycle | Surface covered | Status |
|-------|----------------|--------|
| C1 | PM ACs, risk register, QA test plans, BA requirements, KB lessons → F1–F9 | done |
| C2 | Campaign Roster Management, pre-render form access, dungeoncrawler_tester, pivot table race, input validation, archived filter → F10–F15 | done |
| C3 | dungeoncrawler_tester production, IDOR via PKs, roster consent, pre-render form, field length → F10–F14 final | done |
| C4 | Multi-site isolation, secrets in git → F15–F16; seat instructions self-improvement | done |
| C5 | Drupal CVE cadence → F17; consolidated findings register delivered | done |
| C6–C17 | All design surfaces covered; no new surfaces available without repo access | needs-info (loop halted) |
| Refactor | Seat instructions + findings register artifact gaps closed | done |

## Key artifacts
- Findings register: `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md` (F1–F17, sprint-ready)
- PM sprint AC: `sessions/pm-dungeoncrawler/artifacts/20260220-define-next-sprint/01-acceptance-criteria.md` (F1, F2 need adding before dev ships archive/unarchive)

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/sec-analyst-dungeoncrawler/**
- org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md

## Supervisor
- Supervisor: `pm-dungeoncrawler`
```

---

## Artifact B — Security findings register

**File to create:** `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md`

```markdown
# Security Findings Register — dungeoncrawler_content
Agent: sec-analyst-dungeoncrawler (CRAWLER)
Produced: 2026-02-22 | Cycles C1–C5
- Status: Design-surface analysis complete. Code-level verification pending forseti.life repo access.

## Sprint-critical (add to current sprint AC immediately)

| # | Severity | Surface | Finding | Sprint AC addition | Verify command |
|---|----------|---------|---------|-------------------|---------------|
| F1 | CRITICAL | Routing | GET-based archive/unarchive → CSRF. Any authenticated user can be tricked into archiving/unarchiving entities via malicious link. | AC: archive/unarchive routes MUST use POST method + `_csrf_token: 'TRUE'` in routing.yml | `grep -r "archive\|unarchive" routing.yml` — confirm `methods: [POST]` |
| F2 | HIGH | Controller | 403 enforcement at template layer only — bypass via direct URL. DB write executes before ownership check. | AC: controller MUST check ownership before any DB write; 403 returns before query runs | `grep -n "access\|forbidden\|403" src/Controller/*.php` |

## High priority (next sprint)

| # | Severity | Surface | Finding | Mitigation | Verify command |
|---|----------|---------|---------|-----------|---------------|
| F4 | HIGH | Cache | Missing `user` cache context on campaign list/roster views → cross-user content leak on shared cache. | Add `$build['#cache']['contexts'][] = 'user'` to all entity list render arrays | `grep -rn "cache.*contexts\|#cache" src/` |
| F10 | HIGH | Infra | `dungeoncrawler_tester` may be enabled in production — exposes test scaffolding and potentially permissive routes. | Verify disabled; add to every sprint deployment checklist | `grep -r "_access.*TRUE" dungeoncrawler_tester/` |
| F15 | HIGH | Infra | Multi-site isolation between dungeoncrawler and forseti.life unverified — shared DB, hash_salt, or cache layer would allow cross-site session/data leak. | CEO to confirm: separate DB instance, separate hash_salt, separate cache | Compare `settings.php` hash_salt values across sites |
| F16 | HIGH | Secrets | `settings.php` git history not confirmed clean — credentials may be in history even if current file is gitignored. | CEO to confirm gitignored; run `git log --all -- "*/settings.php"` | `git log --all -- "*/settings.php"` |

## Medium priority (backlog)

| # | Severity | Surface | Finding | Mitigation | Verify |
|---|----------|---------|---------|-----------|--------|
| F3 | MEDIUM | Status logic | PHP int/string coercion: `0 == 'archived'` is true in PHP ≤7. Status comparisons without strict `===` + StatusNormalizer → wrong archive state. | Use strict `===`; always normalize via StatusNormalizer before comparison | `grep -rn "status.*[=!]=.*[0-9]\|[0-9].*[=!]=.*status" src/` |
| F5 | MEDIUM | Error messages | 403 response body discloses entity ID — aids enumeration. | Return generic "Access denied" only; no entity ID in message | Manual: attempt access to unknown ID, check response body |
| F6 | MEDIUM | Admin bypass | Admin check may use `$uid == 1` instead of `hasPermission()` — brittle and bypassable. | Replace with proper Drupal permission check + test | `grep -rn "uid.*==.*1\|==.*uid.*1" src/` |
| F11 | MEDIUM | IDOR | Sequential integer PKs on read routes — authenticated users may enumerate other users' campaign/character data. | Add ownership/access check on all entity read routes | `grep -n "loadEntity\|load(" src/Controller/*.php` |
| F12 | MEDIUM | Consent | DM can assign any character to roster without player consent — design decision needed. | PM to decide: unilateral DM / player opt-in / opt-out | PM decision required (F12 is a design gap, not a code bug) |
| F13 | MEDIUM | Access | Pre-render form access not checked at route layer (deferred P1) — form may render for unauthorized users. | Add `_custom_access` callback on all edit/create routes | `grep -n "_access\|_custom_access" */routing.yml` |
| F14 | MEDIUM | Input | No server-side length bounds on free-text fields (session_tag, notes, character name). | Add `validateForm()` length limits; set DB column length constraints | `grep -n "maxlength\|strlen\|mb_strlen" src/Form/*.php` |
| F17 | MEDIUM | Dependencies | No documented Drupal security update SLA or `composer audit` in CI — CVE window unknown. | Document update SLA; add `composer audit` to CI pipeline | `cat composer.lock | grep -i drupal` + check CI config |

## Low priority

| # | Severity | Surface | Finding | Mitigation | Verify |
|---|----------|---------|---------|-----------|--------|
| F7 | LOW | Data integrity | PK recycling on delete → orphaned notes may be misattributed to new entity with same ID (future risk). | Use UUID foreign keys or cascade-delete on entity removal | Schema review |
| F8 | LOW | XSS | `session_tag` field may use `\|raw` in Twig — XSS if user-controlled. | Remove `\|raw`; rely on Twig auto-escape | `grep -rn "\|raw" templates/` |
| F9 | LOW | Rate limiting | No rate limiting on note creation — authenticated users could spam notes. | Add Drupal flood control or soft per-user cap | Manual: create 100 notes in rapid succession |

## Pending CEO confirmation (blocks F15, F16 closure)
- F15: Does dungeoncrawler share a DB instance, Drupal install, or cache layer with forseti.life?
- F16: Are `settings.php` credentials excluded from git history in both repos?

## Pending repo access (blocks code-level verification of all findings)
- forseti.life path: `/home/keithaumiller/forseti.life` — READ blocked
- Priority verification order (20 min total when access granted):
  1. F16: `git log --all -- "*/settings.php"` — 2 min
  2. F10: grep tester routing.yml for `_access: 'TRUE'` — 2 min
  3. F1: grep routing.yml for archive route methods — 1 min
  4. F2: grep controllers for ownership check before DB write — 5 min
  5. F3: `grep -rn "status.*[=!]=.*[0-9]" src/` — 1 min
  6. F4: grep render arrays for cache contexts — 3 min
  7. F13, F11: routing.yml access requirements — 4 min
  8. F15, F17: hash_salt compare + composer audit — 2 min
```

---

## Next actions
- Executor: write Artifact A to `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md` (replace full file)
- Executor: create Artifact B at `sessions/sec-analyst-dungeoncrawler/artifacts/security-findings-register.md`
- CEO/supervisor: grant forseti.life read access to unblock DashboardController.php review and full code verification session (ROI ~9)
- PM: consume findings register — add F1 + F2 to current sprint AC immediately

## Blockers
- forseti.life READ blocked — DashboardController.php review deferred until access granted

## ROI estimate
- ROI: 7
- Rationale: Closing the seat instructions gap prevents future idle loop waste (persistent process improvement). Creating the findings register as a standalone artifact gives PM a stable reference for sprint planning without digging through 5 outbox files. Both are one-time costs with durable org value.
