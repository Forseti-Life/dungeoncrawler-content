Now I have everything I need. Let me implement both features.

Now update AIApiService.php - add the property, update the constructor, and replace the 3 `\Drupal::database()` call sites:

Now replace the 3 `\Drupal::database()` call sites in `trackApiUsage`, `getCachedApiResponse`, and `clearCachedResponse`:

Now replace `getCachedApiResponse` and `clearCachedResponse` bodies:

Now add the job_hunter update hook 9039:

Now validate and commit:

All syntax clean, zero `\Drupal::database()` calls remain in AIApiService. Now commit:

Now write the outbox:

Done. Here's the outbox response:

---

- Status: done
- Summary: Implemented both features that caused QA Gate 2 BLOCK on `20260407-forseti-release-b`. (1) Created `AIConversationStorageService` encapsulating all `ai_conversation_api_usage` table operations, registered it as `ai_conversation.storage`, and injected it into `AIApiService` — all 3 `\Drupal::database()` calls removed; callers unaffected. (2) Added `job_hunter_update_9039()` adding `age_18_or_older varchar(3)` to `jobhunter_job_seeker` (the correct actual table — QA AC references incorrect alias `job_hunter_profile`). All files passed `php -l`. Commit: `6d7a2d42e`.

## Next actions
- QA re-runs Gate 2 verification against `6d7a2d42e`
- QA note: use `jobhunter_job_seeker` not `job_hunter_profile` in the schema check command
- `drush updb -y && drush cr` required on deploy

## Blockers
- None.

## ROI estimate
- ROI: 85
- Rationale: Unblocks release gate closure. Schema fix also silently drops user data until applied.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-fix-from-qa-block-forseti
- Generated: 2026-04-08T00:33:56+00:00
