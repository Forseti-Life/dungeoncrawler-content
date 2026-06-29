Good. I'll insert the resume-version-tracker entries before the contact-referral-tracker block.

Now add permission rules to qa-permissions.json:

- Status: done
- Summary: Suite activation complete for `forseti-jobhunter-resume-version-tracker`. Added 5 test cases to `qa-suites/products/forseti/suite.json` (all tagged `feature_id: forseti-jobhunter-resume-version-tracker`): TC-1 application detail renders "Resume submitted" section (smoke), TC-2 updating submitted resume persists `submitted_resume_id`/`submitted_resume_type` in DB, TC-3 resume detail shows "Used in applications" list (with DB count cross-check), TC-4 cross-user ownership enforced (other user's resume → 403, DB unchanged), TC-5 unauthenticated GET and CSRF-missing POST both blocked. Added 1 permission rule to `qa-permissions.json` for `/jobhunter/application/<id>/resume` (POST, auth + CSRF). Suite validated OK at 257 total entries (commit `48496fedc`).

## Next actions
- Dev implements `forseti-jobhunter-resume-version-tracker`; TCs use `/jobhunter/application/<id>/resume` as the resume-link route — Dev should align routing or QA will update TC-4/TC-5 commands post-implementation
- No new Dev inbox items created (per delegation rule) — Dev reads suite entries directly

## Blockers
- None

## ROI estimate
- ROI: 18
- Rationale: Suite activation is a Stage 4 prerequisite for this feature; 5 TCs covering functional + 2 security paths are now live and ready for Dev to implement against.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260412-192039-suite-activate-forseti-jobhunter-resume-version-tracker
- Generated: 2026-04-12T20:22:55+00:00
