# Forseti Release: BA Coverage + Edge-Case Supplement

**Seat:** ba-forseti-agent-tracker
**Generated:** 2026-02-26
**Release context:** 2026-02-26 forseti.life release cycle

---

## Active feature coverage summary

| Feature ID | Priority | Status | PM ACs exist? | BA edge-case supplement | QA test plan exists? |
|---|---|---|---|---|---|
| forseti-jobhunter-e2e-flow | P0 (ROI 1000) | ready | Yes (inline in feature.md) | **See below** | Yes (generated 2026-02-26) |
| forseti-jobhunter-profile | P0 | ready | Yes (pm-review.md) | See below | Yes (generated 2026-02-26) |
| forseti-copilot-agent-tracker | P1 | in_progress | Yes (01-acceptance-criteria.md) | See below | Unknown |

---

## forseti-jobhunter-e2e-flow — BA Edge-Case Supplement

**Scope:** End-to-end job discovery → save → apply/track flow on `/jobhunter` dashboard for J&J data roles.  
**Stage break constraint:** System must NOT create a J&J portal account. Application link may be opened; that is the terminal action.

### Happy path (minimum verifiable sequence)
1. User navigates to `/jobhunter` dashboard.
2. User searches for a J&J data role (or manually adds one by URL/title).
3. Job is saved to the user's job list with title, company, link, date-saved.
4. User advances to "ready-to-apply" state; system records state change + timestamp.
5. User marks job as "applied/submitted"; system records applied-date + confirmation link.
6. Job appears in tracking list with current status and history visible.
7. No step requires creating an account on `careers.jnj.com`.

### Edge cases (for QA verification)

#### Discovery / save
| Edge case | Expected behavior |
|---|---|
| J&J job URL is valid but returns 404 (job removed) | System saves the URL with a "job may be removed" warning; does not fail silently |
| Duplicate job save (same URL saved twice) | System deduplates or warns; no duplicate record |
| Manual add with missing required field (no title or URL) | Validation error — clear message, no partial save |
| Search returns 0 results | Empty state message with manual-add fallback, no crash |
| Very long job title (>255 chars) | Truncated or validated before save; no DB overflow |

#### State transitions
| Edge case | Expected behavior |
|---|---|
| User skips "ready-to-apply" and jumps directly to "applied" | System either enforces step sequence OR records applied-date and infers intermediate state |
| User marks applied, then tries to "undo" to ready-to-apply | System either allows with audit entry OR prevents with clear message |
| Same job applied to twice (duplicate submission record) | System warns or deduplicates; does not create corrupt state |
| Applied date in the future (clock skew or manual entry) | Accepted with warning, or system uses server time exclusively |

#### Permissions / access control
| Edge case | Expected behavior |
|---|---|
| Anonymous user accesses `/jobhunter` | Redirect to login (403 or redirect — not a raw error) |
| User A views User B's job list via UID guessing | Forbidden (403); no data leakage |
| User with permission `access job hunter` but no profile record | Graceful empty state, not a PHP error |

#### Automation / queue
| Edge case | Expected behavior |
|---|---|
| Queue worker fails mid-job (e.g., network timeout during job fetch) | Job remains in queue or moves to failed state; user sees status, not a blank dashboard |
| Queue runs against a job that was manually deleted | Worker handles missing record gracefully; no orphan queue items |
| Playwright script `jobhunter-workflow-step1-6-data-engineer.mjs` runs and a step fails | Script exits with non-zero code and clear step label; not a silent pass |

### Failure modes (must not regress)
- Step navigation breaks (step 2 → 3 → 4 do not advance or lose state).
- Applied status is not persisted across page reload.
- Job list pagination fails on >10 jobs.
- Stage break violated: system auto-submits to J&J portal.

### Verification method (minimum)
```
# Check job save persists
# Navigate to /jobhunter, add a job, reload page — job must appear.

# Check stage break
# Verify no HTTP requests to careers.jnj.com (or any external submit endpoint)
# are made automatically — only a link is opened.

# Check QA test plan
cat sessions/qa-forseti/artifacts/<test-plan-path>/test-plan.md
```

---

## forseti-jobhunter-profile — BA Edge-Case Supplement

**Scope:** Resume upload/parsing, consolidated profile editing, profile completeness on `/jobhunter/profile/edit`.

### Happy path (minimum verifiable sequence)
1. User navigates to `/jobhunter/profile` — redirects to `/jobhunter/profile/edit`.
2. User uploads a PDF resume (≤ size limit). Upload succeeds with confirmation.
3. Profile fields (skills, work auth, professional info) are editable and save correctly.
4. `consolidated_profile_json` in `jobhunter_job_seeker` table reflects saved values.
5. Profile completeness percentage is shown and updates on save.
6. User can delete their uploaded resume. Resume is removed; confirmation shown.

### Edge cases (for QA verification)

#### Resume upload
| Edge case | Expected behavior |
|---|---|
| File exceeds size limit | Validation error before upload; clear size-limit message |
| File is not PDF or Word (e.g., .exe, .jpg) | Rejected; clear unsupported-format error |
| File is 0 bytes | Rejected; clear error |
| Upload succeeds but parsing queue fails | File is saved; user sees "parsing in progress" or "parse failed" — not a blank form |
| User uploads a second resume (overwrites first) | Prior resume removed or versioned; no orphan files |

#### Profile data
| Edge case | Expected behavior |
|---|---|
| `consolidated_profile_json` becomes malformed (e.g., truncated write) | Form reload does not crash (PHP error); fallback to empty/default |
| User saves with no skills/work-auth filled in | Partial profile saved; completeness % reflects missing fields |
| User visits profile page with no profile record yet | Empty form renders (not a PHP 500 or redirect loop) |

#### Access control
| Edge case | Expected behavior |
|---|---|
| User accesses `/jobhunter/profile/edit` without `access job hunter` | 403 or redirect |
| User guesses another user's profile URL (`/jobhunter/profile/edit/2`) | 403; no data returned |
| Resume download link is guessable by another user | Returns 403; file is not accessible without ownership check |

### Failure modes (must not regress)
- CSS library not loaded — form is unusable on mobile.
- Profile completeness calculation returns inconsistent values before/after save.
- Resume delete removes file but leaves orphan DB record (or vice versa).

---

## forseti-copilot-agent-tracker — BA Edge-Case Supplement

**Scope:** Admin UI (`/admin/reports/copilot-agent-tracker`) and POST API (`/api/copilot-agent-tracker/event`).

### Edge cases (for QA verification)

#### API endpoint
| Edge case | Expected behavior |
|---|---|
| POST with missing `agent_id` | 400 with clear field-level error |
| POST with missing `summary` | 400 with clear field-level error |
| POST with malformed JSON body | 400; not a 500 |
| POST from anonymous user | 403 |
| POST with valid payload from authorized user | 201/200; event appears in dashboard within one page load |
| Extremely long `summary` field (>10k chars) | Truncated or validated; no DB overflow |

#### Dashboard
| Edge case | Expected behavior |
|---|---|
| No agents have posted events yet | Empty state message; not a blank page |
| Agent detail page for unknown agent_id | 404 or graceful "no data" message |
| Events table with >50 entries | Only latest 50 shown; no performance regression |

### Failure modes (must not regress)
- Anonymous user can access `/admin/reports/copilot-agent-tracker` (should be 403).
- Events are not append-only (existing events are overwritten instead of added).
- Agent status upsert creates duplicate records instead of updating.

---

## Coverage gap analysis

| Feature | Gap | Action needed |
|---|---|---|
| forseti-jobhunter-e2e-flow | ACs are high-level; no structured failure-mode list existed before this cycle | QA should use edge cases above as test plan supplement |
| forseti-jobhunter-profile | PM ACs cover happy path; cross-user access scenarios need explicit test coverage | QA should use edge cases above |
| forseti-copilot-agent-tracker | PM ACs exist; edge cases above fill the gap for API boundary and auth scenarios | Dev should use edge cases above during implementation |

**Verification:** `grep -c "Edge case" sessions/ba-forseti-agent-tracker/artifacts/forseti-release-coverage/forseti-release-ba-coverage.md` returns ≥ 3.
