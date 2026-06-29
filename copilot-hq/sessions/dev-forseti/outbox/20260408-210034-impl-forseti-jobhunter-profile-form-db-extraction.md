AC-1 already passes (0). Let me check if the work was already done or is still pending:

Now I have a clear picture. Let me implement:
1. Create `UserProfileRepository.php` with 2 methods
2. Register it in services.yml
3. Wire into `UserProfileForm.php`

Now register in services.yml:

Now update `UserProfileForm.php` — add use statement, replace property, update constructor, update create(), replace two call sites:

Now update the constructor signature and body:

Now replace the two DB call sites:

Now verify:

All ACs pass. Now commit and write implementation notes:

---

- Status: done
- Summary: Implemented `forseti-jobhunter-profile-form-db-extraction` for release-j. Created `UserProfileRepository.php` in `src/Repository/` with two methods (`updateParsedResumeData`, `getConsolidatedProfileJsonRow`), registered it as `job_hunter.user_profile_repository` in services.yml, and updated `UserProfileForm.php` to inject and delegate to the repository — removing the `$this->database` property and both direct query call sites. The `$database` parameter is retained in the constructor only for the subforms (`EducationHistorySubform`, `ResumeUploadSubform`) which are out of scope. All ACs verified: grep→0, php -l clean, drush cr success, service resolves.

## Next actions
- None — inbox item complete. Awaiting next dispatch.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 10
- Rationale: Removes the last `$this->database` calls from the largest form in `job_hunter` (6100 lines), completing the DB-layer separation pattern for the form layer and reducing future regression risk.

**Commits:**
- Forseti repo: `c664d0b47` — extract UserProfileRepository, wire into UserProfileForm
- HQ repo: `66bd83bde` — implementation notes

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-210034-impl-forseti-jobhunter-profile-form-db-extraction
- Generated: 2026-04-08T21:38:24+00:00
