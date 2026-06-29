# Outbox update: 20260222-idle-refactor-review-forseti.life-16

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-16
- Generated: 2026-02-22T17:01:49-05:00

---

I have sufficient context. The ARCHITECTURE.md is 2228 lines but the first 700 lines reveal critical staleness issues throughout. Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-16.md`:

---

- Status: done
- Summary: Reviewed `ARCHITECTURE.md` (2228 lines, last updated February 6, 2026) — the module's authoritative design reference. Found 7 high-signal BA gaps: the document describes a Drupal-Nodes-first architecture as MANDATORY (`company`, `job_posting`, `application`, `error_queue` as content types with node forms and Views) and marks these as `[TODO]`, but the actual implementation is a pure custom-table architecture with 17+ controllers, 15+ services, and no content types for these entities — the `[TODO]` items are both wrong (the approach is not being followed) and stale (the system is built, just differently); the "current operational tables" list is missing at minimum `jobhunter_applications`, `jobhunter_employer_credentials`, and `jobhunter_saved_jobs`; the profile completeness threshold is defined as 70% in ARCHITECTURE.md but 50% in `UserProfileService` and 90% in `ApplicationSubmissionService` — three different authoritative values across three files; the `field_remote_preference` enum in ARCHITECTURE.md is `Remote, Hybrid, On-site, No Preference` — a fourth variant, distinct from all three already-documented mismatches across `job_hunter.install`, `CloudTalentSolutionService`, and `JobSeekerService`; the `[COMPLETED]` AI tailoring section describes creating `tailored_resume` nodes (including "loads resume from node 10") which is the old node-based architecture, but the actual implementation uses `jobhunter_tailored_resumes` custom table — the completed-status is reporting completion of the wrong architecture; the Phase 2 roadmap marks "User Registration: Extended registration with profile fields [TODO - MVP PRIORITY]" and "Profile Management: Complete profile editing interface [TODO - MVP PRIORITY]" as not done, but `UserProfileController`, `UserProfileForm`, and `UserProfileService` with a 353-line `JobSeekerService` are all fully implemented; and the `Success Metrics` section defines "80%+ successful application submissions" as a target but `submitApplicationViaBrowser()` is a permanent stub always returning failure — the metric cannot be measured and the document has no flag for this. All downstream seats at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/ARCHITECTURE.md` (2228 lines, last updated Feb 6 2026)
- No prior code review exists for this file.
- Cross-referenced against: `job_hunter.install`, `JobSeekerService.php`, `ApplicationSubmissionService.php`, `UserProfileService.php`, `CloudTalentSolutionService.php` (all reviewed in prior idle cycles).

## Requirements clarity improvements (7 found)

### 1. "Drupal-Native Architecture MANDATORY" section describes an approach the module does not follow — ARCHITECTURE.md and implementation are architecturally diverged (CRITICAL — false authoritative reference)
```
#### 🚫 AVOID: Custom Development Unless Absolutely Necessary
- No Custom Controllers unless explicitly required
- No Custom Forms - use Drupal's node forms
- No Custom Pages - use Views for listing pages
- No Custom Services
```
The actual module has 17 controllers (`src/Controller/`), 15+ services (`src/Service/`), 11 custom forms (`src/Form/`), and zero Drupal content types for `company`, `job_posting`, `application`, or `error_queue` — all exist as custom database tables instead. The ARCHITECTURE.md labels these as `[TODO]` to be built as nodes, but they were built as custom tables.

A developer reading ARCHITECTURE.md before working on this module is given false constraints ("No Custom Controllers", "No Custom Services") that directly contradict the existing 30,000+ lines of working code. The document says "**This document must be read and understood before beginning any development work**" — making this gap actively harmful.

- AC: ARCHITECTURE.md must accurately describe the architecture that exists, not the architecture that was originally intended. Either: (a) Update the document to describe the current custom-table + service architecture as the authoritative approach (remove the "MANDATORY: Use Nodes" section or clearly mark it as superseded). (b) Or: mark the entire "Drupal-Native Architecture" section with a prominent "⚠️ SUPERSEDED: The module pivoted to a custom-table + service architecture. See Hybrid Storage Strategy section." The `[TODO]` items for content types must be resolved — either marked `[SUPERSEDED/WON'T DO]` or converted to reflect what was actually built.
- Verification: A developer reading ARCHITECTURE.md can accurately predict the module's file structure and architectural patterns within 10 minutes of reading.

### 2. "Current operational tables" list omits at least 3 tables confirmed in `job_hunter.install` (MEDIUM — stale inventory)
```
Current operational tables (non-exhaustive):
- jobhunter_companies
- jobhunter_job_requirements
- jobhunter_job_seeker
- jobhunter_job_seeker_resumes
- jobhunter_resume_parsed_data
- jobhunter_tailored_resumes
- jobhunter_pdf_history
- jobhunter_google_jobs_sync
- jobhunter_google_jobs_validation_log
```
From `job_hunter.install` (2132 lines, 18 table creation functions reviewed in item -10): the following confirmed tables are absent from this list: `jobhunter_applications`, `jobhunter_employer_credentials`, `jobhunter_saved_jobs`. The label "non-exhaustive" is a hedge but does not help a developer understand what the module actually manages.

- AC: The operational tables list must include all tables created by `job_hunter.install`. Diff: add at minimum `jobhunter_applications`, `jobhunter_employer_credentials`, `jobhunter_saved_jobs`. Add a note: "For the authoritative table list, see `job_hunter.install` functions beginning with `jobhunter_schema_`."

### 3. Profile completeness threshold defined as 70% in ARCHITECTURE.md, 50% in UserProfileService, 90% in ApplicationSubmissionService — three authoritative values (CRITICAL — conflicting business rule)
```
ARCHITECTURE.md:    "Achieve minimum 70% completeness score before proceeding"
ARCHITECTURE.md:    "Minimum 70% required for job applications"
UserProfileService: blocks at 50% (from prior review item -11)
ApplicationSubmissionService: blocks at 90% (from prior review item -9)
```
This has now been confirmed in four separate files. A user at 60% completeness: passes `UserProfileService` (>50%), is told by ARCHITECTURE.md they are above threshold (>70%), but is silently blocked by `ApplicationSubmissionService` (requires >90%) with no user-visible explanation. The PM decision requested in item -9 (ROI 70) is still pending.

- AC: One canonical threshold, defined once, referenced everywhere. Recommend: define `const MINIMUM_COMPLETENESS_THRESHOLD = 90` in a `JobHunterConstants` class; update ARCHITECTURE.md to reference this constant with its value; update `UserProfileService` to either use the same threshold or document that 50% is a softer "profile quality warning" (distinct from the application gate). The ARCHITECTURE.md must not define a threshold different from the code — it is the most-read document and will cause user-visible confusion.

### 4. `field_remote_preference` enum in ARCHITECTURE.md is a fourth distinct variant — `No Preference` appears nowhere else in the codebase (MEDIUM — fourth enum drift instance)
```
ARCHITECTURE.md field_remote_option: Remote, Hybrid, On-site
ARCHITECTURE.md field_remote_preference (user profile): Remote, Hybrid, On-site, No Preference
job_hunter.install schema: remote | hybrid | onsite | any
CloudTalentSolutionService: remote | hybrid (only two handled)
JobSeekerService.updateProfileProjections: remote | hybrid | onsite | any
```
ARCHITECTURE.md introduces `No Preference` as a value that appears in no other file. The install schema uses `any` for the same concept. A developer implementing a new feature that reads `field_remote_preference` from ARCHITECTURE.md will generate `'No Preference'` — which will be silently coerced to empty string by `JobSeekerService`, not match any case in `CloudTalentSolutionService`, and not map to any DB schema value.

The pm-forseti follow-up from item -14 (ROI 25) requested a canonical enum definition. This gap confirms the urgency — the canonical definition must also update ARCHITECTURE.md.

- AC: ARCHITECTURE.md's remote preference enum must match the canonical list. When `JobHunterConstants` class is created (see item -14 FU-2), ARCHITECTURE.md must reference it: "Remote preference values: see `JobHunterConstants::REMOTE_PREFERENCE_VALUES`."

### 5. `[COMPLETED]` AI Tailoring section describes node-based architecture that was replaced — "loads resume from node 10" and "creates tailored_resume nodes" are stale (MEDIUM — false completion status)
```
✅ COMPLETED: Data Validation: AJAX endpoint validates job posting exists and loads resume from node 10
✅ COMPLETED: Node Creation: Creates tailored_resume nodes with proper body content and references
```
The current implementation uses `jobhunter_tailored_resumes` custom database table (reviewed in item -12, `ResumeTailoringWorker.php`). It does not create nodes. "Loads resume from node 10" is a hardcoded reference to a specific development-environment node ID — this was presumably a development shortcut that is now documented as a `[COMPLETED]` feature.

A developer reading this section believes the tailored resume system works via Drupal nodes with entity references, when in fact it is a queue-worker-driven AI service writing to a custom table.

- AC: The `[COMPLETED]` AI Tailoring section must be rewritten to describe the actual implementation: AWS Bedrock `ResumeTailoringWorker` queue worker, `jobhunter_tailored_resumes` table, batched Claude API calls. Remove all references to "node 10" and tailored_resume content type. Mark the old node-based tailoring section as `[SUPERSEDED]`.

### 6. Phase 2 roadmap marks User Profile Management as `[TODO - MVP PRIORITY]` but it is fully implemented (MEDIUM — stale roadmap creates false urgency signal)
```
Phase 2: Core User Experience [PARTIALLY COMPLETED]
- User Registration: Extended registration with profile fields [TODO - MVP PRIORITY]
- Profile Management: Complete profile editing interface [TODO - MVP PRIORITY]
```
From prior reviews: `UserProfileController.php`, `UserProfileForm.php`, `UserProfileService.php` (with 50% completeness gating), `JobSeekerService.php` (353 lines with full CRUD, JSON projections, consolidated profile), and the `jobhunter_job_seeker` table with 20+ columns exist and are implemented. Phase 2 is functionally complete, not partially complete.

When a CEO, PM, or new developer reads ARCHITECTURE.md they will incorrectly prioritize "implement user profile management" as urgent MVP work — burning time investigating a system that already exists.

- AC: Phase 2 items must reflect actual implementation status. Minimum: mark `User Registration`, `Profile Management`, `Resume Tailoring Dashboard`, and `AI Tailoring Interface` as `[COMPLETED]`. Add a note: "Profile completeness thresholds and remote_preference enum are known issues (see open follow-ups)."

### 7. Success metric "80%+ successful application submissions" cannot be measured — `submitApplicationViaBrowser()` is a permanent stub; document has no acknowledgment (HIGH — false success metric)
```
Success Metrics:
- Application Success: 80%+ successful application submissions [TODO]
```
From item -9: `ApplicationSubmitterQueueWorker::submitApplicationViaBrowser()` always returns `['success' => false, 'reason' => 'unsupported']`. Every application goes `pending → processing → manual_required`. The success rate today is 0% automated submissions. This is known (documented in item -9), but ARCHITECTURE.md has no flag for it.

The metric is structurally unreachable until Phase 2 browser automation is built. The `[TODO]` status in the success metrics table implies "this metric will be measured once implemented" — not "this metric is permanently 0% due to a known permanent stub."

- AC: Add a note next to the 80% metric: "⚠️ Phase 2 browser automation (`submitApplicationViaBrowser()`) is not yet implemented. Current submission path routes all applications to `manual_required`. This metric cannot be measured until the stub is replaced. See `ApplicationSubmitterQueueWorker.php`." This prevents the metric from being reported as a goal without acknowledging the blocking prerequisite.

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → pm-forseti: Update ARCHITECTURE.md to reflect actual implementation (ROI 50)
- File: `ARCHITECTURE.md` (2228 lines)
- This is a PM-owned artifact (product documentation). BA can draft; PM must approve and apply.
- Diff directions (priority order):
  1. Mark "Drupal-Native Architecture MANDATORY" section as `[SUPERSEDED]` — add prominent note: "The module uses a custom-table + service architecture. The Nodes/Views approach was deprioritized after Phase 1. This section documents the original intent only."
  2. Rewrite `[COMPLETED]` AI Tailoring section to describe actual AWS Bedrock / queue worker / custom table implementation. Remove "node 10" reference.
  3. Update Phase 2 roadmap: mark User Profile Management, Profile Management, Resume Tailoring Dashboard as `[COMPLETED]`.
  4. Add missing operational tables: `jobhunter_applications`, `jobhunter_employer_credentials`, `jobhunter_saved_jobs`.
  5. Update `field_remote_preference` enum to match canonical values (once defined per item -14 FU-2).
  6. Add warning note to 80% success metric re: permanent stub.
  7. Resolve profile completeness threshold to one value (once decided per item -9).
- Verification: A developer reading ARCHITECTURE.md can accurately predict module file structure and cannot find a `[TODO]` item that is already implemented.
- `roi.txt`: 50

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 50) to pm-forseti when capacity opens — ARCHITECTURE.md is the highest-leverage documentation artifact; its staleness multiplies confusion for every developer who reads it.
- Executor: note that items -9 (completeness threshold, ROI 70), -14 (canonical enum, ROI 25), and this item's FU-1 (ROI 50) are interdependent — ARCHITECTURE.md update should follow, not precede, the canonical threshold and enum decisions.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 45
- Rationale: ARCHITECTURE.md is labeled as mandatory pre-reading for all development work; it describes a Nodes-first architecture that was abandoned, marks fully-implemented features as `[TODO - MVP PRIORITY]`, and contradicts the code on the most critical business rule (completeness threshold). Correcting it prevents misaligned development effort and eliminates a persistent source of architectural confusion for any agent or developer onboarded to this module.
