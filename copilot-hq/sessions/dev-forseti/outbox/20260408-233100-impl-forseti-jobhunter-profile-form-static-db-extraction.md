10 calls confirmed. Now read each one in context:

Good, I have full context now. Let me check the form's current constructor and then plan all changes:

I have a complete picture. Now I'll implement all changes — 15 new repository methods + 16 edits to UserProfileForm:

Now update UserProfileForm.php — starting with the `refreshStep2Callback` block:

Now remove the `$connection = \Drupal::database();` at line 1483 (now the first remaining one in refreshStep2Callback):

Now update the 4 helper callers + remove the `$connection = \Drupal::database();` lines at 1771, 1817, 1868, 2002. Let me read each block:

Now I'll make all the helper call site updates in one batch of edits:

Now the `parseResumeSubmit` block (line ~2002):

Now the `deriveOnlinePresenceFromResumes` block (~2701). Let me read it first:

Now replace the `connection->update('jobhunter_job_seeker')` in `deriveOnlinePresenceFromResumes`:

Now the `buildConsolidatedJsonAndApplyToProfile` block (~4520). Let me read it:

Now `normalizeResumeParsedDataStatuses` (~4886), `ensureResumeConsolidationUpToDate` (~4916), and `applyConsolidatedToProfileFields` (~5154):

Now the `applyConsolidatedToProfileFields` block (~5154). Let me read it:

Now update the 3 private helper definitions:

Now the `storeParsedResults` helper definition:

Now verify the result — check remaining static calls and syntax:

0 static calls remaining, syntax clean. Now run drush cr:

All checks pass. Now commit:

Now write the implementation notes and outbox:

- Status: done
- Summary: Extracted all 10 `\Drupal::database()` static calls from `UserProfileForm.php` into `UserProfileRepository.php`. Added 15 new repository methods covering resume CRUD, parsed data upserts, profile consolidation reads/writes, and status normalization. Updated 3 private helpers (`loadResumeRecord`, `storeExtractedText`, `storeParsedResults`) to drop `$connection` params and delegate to the repo. All ACs verified: 0 static DB calls remain, PHP syntax clean, `drush cr` succeeded.

## Next actions
- QA can verify via the feature test plan (`features/forseti-jobhunter-profile-form-static-db-extraction/03-test-plan.md`)
- No follow-on dev work required for this inbox item

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: DB-layer separation removes tight coupling that has caused repeated maintenance friction; all 10 remaining static calls extracted in one cycle, unblocking QA Gate 2 for release-k.

---
**Commits:**
- Forseti repo: `6e5634668`
- HQ repo: `23cbb876a`

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-233100-impl-forseti-jobhunter-profile-form-static-db-extraction
- Generated: 2026-04-08T23:51:58+00:00
