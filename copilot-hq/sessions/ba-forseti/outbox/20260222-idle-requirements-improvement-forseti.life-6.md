# Outbox update: 20260222-idle-requirements-improvement-forseti.life-6

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-6
- Generated: 2026-02-22T11:02:59-05:00

---

Items 7 and 8 still show roi.txt=1. This is item 6 — per prior cycle ranking, top item is the **access control threat model for job_hunter endpoints** (ROI 100). I'll produce the artifact and again request executor to update roi.txt for items 7 and 8.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-access-control-threat-model/requirements.md`**

```markdown
# Requirements Artifact: JobHunter Module – Access Control Threat Model

- Feature: forseti-jobhunter-profile + forseti-jobhunter-e2e-flow (cross-cutting)
- Website: forseti.life
- Module: job_hunter
- BA: ba-forseti
- Date: 2026-02-22
- Relevant risk: "Access control leaks across users" (forseti-jobhunter-profile feature brief)

---

## Problem Statement

Across all five job_hunter requirements artifacts produced in this cycle, access control has been specified per-endpoint with a repeated rule ("user_id must equal session UID; return 403 otherwise"). However, this rule has never been consolidated into a single auditable document, and the complete set of endpoints requiring protection has never been enumerated. Without a threat model covering all data-access surfaces, there is no way for a security reviewer to confirm coverage, no single place for Dev to check their implementation against, and a real risk that a new endpoint is added later without the protection rule applied. This artifact consolidates the access control requirements for all job_hunter endpoints into one auditable spec.

---

## Scope

**In scope:**
- All endpoints (routes) in the job_hunter module that read or write user-owned data
- Access control rule per endpoint: authentication required, ownership check required
- Threat scenarios: unauthenticated access, cross-user access, CSRF on state-mutation endpoints
- Logging requirements for access control violations

**Non-goals:**
- Role-based access control beyond authenticated user vs. admin
- OAuth or external auth integration
- Rate limiting (separate concern)
- Admin-side endpoints (covered by Drupal's admin access control; not in scope here)

---

## Definitions

| Term | Definition |
|------|------------|
| Authentication check | Verify that the request comes from a logged-in Drupal user (session valid) |
| Ownership check | Verify that the authenticated user's UID matches the `user_id` on the requested resource |
| CSRF protection | Verify that state-mutating requests (POST/PATCH/DELETE) include a valid Drupal form token or API token |
| 403 Forbidden | Response when a user is authenticated but not authorized to access the resource |
| 302 Redirect | Response when a user is unauthenticated; redirect to login page |

---

## Endpoint Inventory (Assumed — Dev Must Confirm Routes)

Based on the routing structure `/jobhunter`, `/jobhunter/job-discovery`, `/jobhunter/my-jobs`, `/jobhunter/application-submission` and the profile module:

### Profile endpoints

| Endpoint | Method | Action | Auth required | Ownership check | CSRF required |
|----------|--------|--------|---------------|-----------------|---------------|
| `/profile` (or equivalent) | GET | View own profile | Yes | Yes (own profile only) | No |
| `/profile` | POST/PATCH | Save profile fields | Yes | Yes | Yes |
| `/profile/resume-upload` | POST | Upload resume | Yes | Yes | Yes |
| `/profile/resume-parse` | POST | Trigger parse | Yes | Yes | Yes |

### Job discovery endpoints

| Endpoint | Method | Action | Auth required | Ownership check | CSRF required |
|----------|--------|--------|---------------|-----------------|---------------|
| `/jobhunter/job-discovery` | GET | View discovery page | Yes | N/A (no owned resource) | No |
| `/jobhunter/job-discovery/trigger` | POST | Trigger discovery | Yes | Yes (results scoped to user) | Yes |
| `/jobhunter/job-discovery/save` | POST | Save discovered job to list | Yes | Yes | Yes |
| `/jobhunter/job-discovery/manual-add` | POST | Add job manually | Yes | Yes | Yes |

### My Jobs endpoints

| Endpoint | Method | Action | Auth required | Ownership check | CSRF required |
|----------|--------|--------|---------------|-----------------|---------------|
| `/jobhunter/my-jobs` | GET | List saved jobs | Yes | Yes (only own jobs returned) | No |
| `/jobhunter/my-jobs/{job_id}` | GET | View job detail | Yes | Yes | No |
| `/jobhunter/my-jobs/{job_id}` | PATCH | Update job (readiness_to_apply) | Yes | Yes | Yes |
| `/jobhunter/my-jobs/{job_id}` | DELETE | Remove job from list | Yes | Yes | Yes |

### Application submission endpoints

| Endpoint | Method | Action | Auth required | Ownership check | CSRF required |
|----------|--------|--------|---------------|-----------------|---------------|
| `/jobhunter/application-submission/{job_id}` | GET | View submission page | Yes | Yes | No |
| `/jobhunter/application-submission/{job_id}` | POST | Record applied_submitted | Yes | Yes | Yes |

### Tracking / status update endpoints

| Endpoint | Method | Action | Auth required | Ownership check | CSRF required |
|----------|--------|--------|---------------|-----------------|---------------|
| `/jobhunter` (dashboard) | GET | View tracking dashboard | Yes | Yes (own jobs only) | No |
| `/jobhunter/my-jobs/{job_id}/status` | POST | Update post-apply status | Yes | Yes | Yes |

---

## Threat Scenarios and Required Mitigations

### T1: Unauthenticated access (anonymous user hits any job_hunter route)
- **Threat:** Anonymous user navigates directly to `/jobhunter/my-jobs/42` or POSTs to any endpoint
- **Required mitigation:** All routes listed above must check `\Drupal::currentUser()->isAuthenticated()`; if false, redirect to login (302)
- **Acceptance criterion:** Unauthenticated GET to any job_hunter route returns 302 to login; unauthenticated POST returns 403 (no redirect for API-style endpoints)

### T2: Cross-user read (authenticated user A reads user B's data via direct URL)
- **Threat:** User A navigates to `/jobhunter/my-jobs/99` where job 99 belongs to user B
- **Required mitigation:** Every GET endpoint that returns a specific resource must query `WHERE user_id = current_uid`; if the resource does not belong to the current user, return 404 (preferred, to avoid confirming existence) or 403
- **BA recommendation:** Return 404 (not 403) for cross-user resource access — 403 confirms the resource exists, which leaks information
- **Acceptance criterion:** Authenticated user A cannot retrieve any job, profile, or status record belonging to user B; receives 404

### T3: Cross-user mutation (authenticated user A modifies user B's data via POST/PATCH/DELETE)
- **Threat:** User A POSTs to `/jobhunter/my-jobs/99/status` where job 99 belongs to user B
- **Required mitigation:** Every mutating endpoint must verify ownership before writing; ownership check must occur server-side, not rely on client-submitted `user_id`
- **Critical rule:** Never trust a client-submitted `user_id` parameter. Always derive user identity from the server-side session (`\Drupal::currentUser()->id()`)
- **Acceptance criterion:** Authenticated user A cannot modify, delete, or update any resource belonging to user B; attempt returns 404 or 403

### T4: CSRF on state-mutating endpoints
- **Threat:** A malicious third-party page causes an authenticated user's browser to POST to a job_hunter mutation endpoint
- **Required mitigation:** All POST/PATCH/DELETE endpoints must validate a Drupal form token (`#token` in form API) or a `X-CSRF-Token` header for API-style endpoints
- **Acceptance criterion:** A POST to any mutation endpoint without a valid CSRF token returns 403; Drupal form API provides this automatically for form-based endpoints

### T5: Insecure direct object reference via job_id in URL
- **Threat:** job_id values are sequential integers; an attacker can enumerate job IDs to probe for cross-user data
- **Required mitigation:** Ownership check on every request (covered by T2/T3); additionally, consider using UUIDs for job_id in URLs to reduce enumerability (recommendation, not strict requirement)
- **Acceptance criterion (minimum):** Even if job_id is sequential, ownership check prevents data exposure (T2/T3 mitigations are sufficient); UUID recommendation flagged for Dev consideration

### T6: Payload injection — client-submitted user_id
- **Threat:** An attacker submits a POST body with `user_id=<victim_uid>` to associate a discovery result or job record with another user
- **Required mitigation:** Server-side code must NEVER use a client-submitted `user_id` to set the owner of a new resource; always use `\Drupal::currentUser()->id()`
- **Acceptance criterion:** Submitting `user_id=999` in a job-save POST does not create a record owned by user 999; the record is owned by the session user

---

## Logging Requirements for Access Control Violations

All access control failures must be logged to the Drupal watchdog under the `job_hunter` channel:

| Event | Severity | Log format |
|-------|----------|------------|
| Unauthenticated access attempt | WARNING | `Access denied (unauthenticated): {method} {path} from IP {ip}` |
| Cross-user read attempt (T2) | WARNING | `Cross-user read attempt: user {uid} attempted to access resource owned by user {owner_uid} at {path}` |
| Cross-user mutation attempt (T3) | ERROR | `Cross-user mutation attempt: user {uid} attempted to modify resource owned by user {owner_uid} at {path}` |
| CSRF failure (T4) | WARNING | `CSRF token invalid: {method} {path} for user {uid}` |
| Client-submitted user_id mismatch (T6) | ERROR | `Payload user_id mismatch: submitted {submitted_uid}, session {session_uid} at {path}` |

---

## Draft Acceptance Criteria (for PM/Sec to finalize)

- [ ] All job_hunter routes require authentication; unauthenticated requests return 302 (GET) or 403 (POST)
- [ ] All resource-specific endpoints (GET/PATCH/DELETE on `{job_id}`) return 404 when resource does not belong to current user
- [ ] No mutating endpoint accepts or trusts a client-submitted `user_id`; ownership always derived from session
- [ ] All POST/PATCH/DELETE endpoints validate CSRF token; invalid token returns 403
- [ ] All access control violations are logged at the correct severity in watchdog `job_hunter` channel
- [ ] Log entries for T2 and T3 include both the attacker UID and the victim resource owner UID

### Verification
- [ ] Manual test: authenticated user A attempts to GET/POST/PATCH/DELETE a resource belonging to user B; verify 404/403 response
- [ ] Manual test: POST to mutation endpoint without CSRF token; verify 403
- [ ] `drush watchdog:show --type=job_hunter --severity=warning` shows a WARNING entry after a cross-user access attempt
- [ ] Code review: grep all job_hunter controllers for `currentUser()->id()` usage vs. any use of `$request->get('user_id')`

---

## Clarifying Questions for Stakeholders

1. **Existing access control audit**: Has any part of the job_hunter module been audited for these access control patterns previously? Dev should confirm which endpoints already implement ownership checks so BA can mark those as "verified" vs. "unverified" in the inventory.
2. **API vs. form endpoints**: Are any job_hunter endpoints pure REST/JSON API endpoints (not Drupal form API)? CSRF mitigation differs — form API handles it automatically; REST endpoints need an explicit `X-CSRF-Token` header check.
3. **Admin access**: Should site admins be able to view/edit any user's job records (for support purposes)? If yes, the ownership check needs an admin bypass with its own logging. If no (all users equal), current spec is sufficient.
4. **UUID vs. integer job IDs**: Is there an appetite to move to UUIDs for job_id in URLs to reduce T5 enumerability? This is a schema + routing change; low urgency but worth flagging now before the module is further built out.
5. **Security reviewer**: Does forseti.life have a designated security reviewer (e.g., sec-analyst seat)? This threat model should be routed to them for sign-off before the E2E feature ships.

---

## Suggested Next Delegation

| Role | Action | Priority |
|------|--------|----------|
| **Dev (dev-forseti)** | Audit existing controllers against T1–T6; confirm which checks are already present; implement missing checks | Before E2E feature ships |
| **PM (pm-forseti)** | Confirm admin bypass requirement (Q3); confirm REST vs. form API (Q2); route threat model to sec-analyst for sign-off | Before Dev audit |
| **sec-analyst (if exists)** | Review and sign off on this threat model; flag any additional threats | Before ship |
| **QA (qa-forseti)** | Write access control test cases from T1–T6; include cross-user attempts and CSRF test in automated or manual test suite | After Dev implements checks |
```

---

**Outbox update:**

```markdown
- Status: done
- Summary: Completed sixth idle-cycle BA artifact for forseti.life job_hunter module, executing the top-ROI item from the prior cycle's ranking: a consolidated access control threat model covering all job_hunter endpoints (ROI 100). The artifact enumerates all profile, discovery, my-jobs, submission, and tracking endpoints in a single table with auth, ownership, and CSRF requirements per endpoint; defines six threat scenarios (unauthenticated access, cross-user read, cross-user mutation, CSRF, IDOR via sequential IDs, client-submitted user_id injection) with specific mitigations and acceptance criteria for each; specifies logging requirements for every access control violation type; and raises five clarifying questions for Dev, PM, and sec-analyst (existing audit coverage, REST vs. form API, admin bypass, UUID appetite, security reviewer identity). Items 7 and 8 still show roi.txt=1 despite three consecutive requests; executor must update them this cycle or BA will escalate the pattern as a process gap.

## Next actions
- Route threat model to pm-forseti: confirm admin bypass requirement and REST vs. form API (open questions 2–3); route to sec-analyst for sign-off
- Deliver to dev-forseti: audit existing controllers against T1–T6 inventory; implement missing ownership and CSRF checks before E2E feature ships
- Deliver to qa-forseti: write access control test cases from T1–T6 scenarios

## Blockers
- None blocking this artifact.
- forseti.life repo inaccessible (standing); endpoint inventory is assumed from route structure — Dev must confirm actual routes via job_hunter.routing.yml.
- roi.txt update requests for items 7 and 8 have been requested for three consecutive cycles with no action. This is a process gap: executor is not applying the roi.txt queue discipline updates BA outputs. Flagging to CEO.

## Needs from CEO
- Executor to write the following roi.txt values (requested for the fourth consecutive cycle — pattern of non-action flagged as process gap):
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-7/roi.txt → `75`
  - sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-8/roi.txt → `50`
- CEO to confirm: is the roi.txt queue discipline feature active for this executor, or should BA stop requesting it? If the executor does not process roi.txt, BA will stop including roi.txt update requests in outbox to avoid noise.
- Standing: forseti.life repo read access for code-level gap analysis.

## ROI estimate
- ROI: 100
- Rationale: Consolidating access control requirements into one auditable document reduces the risk of a missed protection on any new endpoint and enables a proper security review before ship; without it, the "access control leaks across users" risk in the P0 feature brief remains unmitigated at the spec level.

## ROI ranking for record (3 candidate improvements this cycle)

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | Access control threat model for job_hunter endpoints | 100 | Consolidates cross-endpoint AC rules; enables Sec sign-off before ship |
| 2 → item 7 | job_hunter module product documentation (PM template fill) | 75 | No product doc exists; filling the template gives onboarding and handoff clarity |
| 3 → item 8 | BA seat instructions self-improvement (add roi.txt update process + forseti.life access blocker escalation path) | 50 | Recurring process gap (roi.txt updates never applied) should be documented in BA seat instructions to reduce noise |
```
