Feature is fully pre-implemented. Check the `name`/`company_id` columns vs. AC-4 (the schema has some legacy + new columns):

Good — everything is confirmed pre-implemented. Let me now write the implementation notes and signal QA.

Real gaps identified:
- `email` column missing from `jobhunter_contacts` (AC-4)
- `linkedin_url` in schema but not in form or save logic (SEC-4)

I'll fix both now.

Last hook is 9059 — I'll add `hook_update_9060` to add the `email` column. Then fix form and save logic.

Now I'll add the `email` column hook, update the form, and update contactSave:

Now update `contactForm` to add email and linkedin_url fields:

Now add the email and linkedin_url fields to the form HTML:

Now add email and linkedin_url handling to `contactSave()`. Find the right place — after the notes/referral_status lines in the $fields array:

Now add email and linkedin_url validation after the existing validators, and before the company check:

Now find the `$fields` array in contactSave and add email + linkedin_url conditionally:

Now run the database update and clear cache:

Both columns are now live. Let me commit and produce the outbox:

Now write the implementation notes and produce the outbox:

---

- Status: done
- Summary: `forseti-jobhunter-contact-tracker` is now fully implemented and AC-complete. The core CRUD was pre-built; two gaps were identified and resolved: AC-4 (`email` column missing from `jobhunter_contacts`) was added via `hook_update_9060` (ran cleanly — column verified live), and SEC-4 (`linkedin_url` in schema but never read from POST or saved) was fixed by updating both `contactForm` and `contactSave` — email is validated via `filter_var(FILTER_VALIDATE_EMAIL)`, LinkedIn URL validated to contain `linkedin.com`, both written conditionally with schema field guards, and neither appears in watchdog logs (SEC-5). Cache cleared.

## Next actions
- Signal qa-forseti for Gate 2 on `forseti-jobhunter-contact-tracker`:
  - AC-1: POST save form → verify row in `jobhunter_contacts` with email column
  - AC-2: GET `/jobhunter/contacts` → HTTP 200
  - AC-3: "Contacts at this company" section surfaces on saved-job detail
  - AC-4: `DESCRIBE jobhunter_contacts` → email + linkedin_url both present (✅ confirmed)
  - AC-5: Edit/delete contact; cross-user isolation
  - SEC-2: CSRF token on save + delete POST routes
  - SEC-4: Invalid email + non-linkedin URL rejected at POST
- Signal qa-forseti for Gate 2 on `forseti-jobhunter-company-interest-tracker` (if not yet done)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 28
- Rationale: All gaps resolved in-cycle; feature is now AC-complete and ready for QA Gate 2 without any known blockers. Unblocks release-i from dev queue.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260414-001318-impl-forseti-jobhunter-contact-tracker
- Generated: 2026-04-14T00:29:36+00:00
