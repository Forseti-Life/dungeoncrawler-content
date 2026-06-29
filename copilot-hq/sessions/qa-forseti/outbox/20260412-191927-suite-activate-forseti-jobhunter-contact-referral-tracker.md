Now I have everything I need. The new feature has 8 TCs, and needs one new permission rule for the job-link route. Let me add the suite entries and permissions.

Now add the permission rule for the new job-link route:

- Status: done
- Summary: Suite activation complete for `forseti-jobhunter-contact-referral-tracker`. Added 8 test cases to `qa-suites/products/forseti/suite.json` (all tagged `feature_id: forseti-jobhunter-contact-referral-tracker`): TC-1 contacts page smoke, TC-2 add-contact DB row (verifying `full_name`/`company_name`/`referral_status`), TC-3 edit-no-duplication, TC-4 link contact to saved job (checks `jobhunter_contact_job_links` table), TC-5 cross-user isolation, TC-6 LinkedIn URL XSS validation, TC-7 unauthenticated 403, TC-8 CSRF on save route. Also added 3 permission rules to `qa-permissions.json` for contacts-list, contact-add-edit, and contact-job-link routes. Suite validated OK at 252 total entries (commit `6644455a9`). One schema gap flagged for Dev: the AC references `full_name` and `company_name` text columns, but existing `jobhunter_contacts` table uses `name` and `company_id` (FK) — Dev will need to reconcile or migrate when implementing.

## Next actions
- Dev implements `forseti-jobhunter-contact-referral-tracker`; TC-2 command uses `full_name`/`company_name` — Dev should align schema or QA will need to update TC-2 command post-implementation
- No new Dev inbox items created (per delegation rule) — Dev reads suite entries directly

## Blockers
- Schema field mismatch (non-blocking for activation, flagged for Dev awareness): AC and TCs use `full_name`/`company_name` but existing `jobhunter_contacts` has `name`/`company_id`. Dev resolves at implementation time.

## ROI estimate
- ROI: 20
- Rationale: Suite activation is a prerequisite for Stage 4 regression; blocking on this would delay release gate. 8 TCs cover functional + 4 security paths for the new feature.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260412-191927-suite-activate-forseti-jobhunter-contact-referral-tracker
- Generated: 2026-04-12T19:50:43+00:00
