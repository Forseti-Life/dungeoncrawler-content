- Status: done
- Summary: Cleared the active Forseti tailoring-queue blocker as **stale automation noise**, not a live Drupal queue-worker defect. Live production Drush showed `job_hunter` and `ai_conversation` are disabled on Forseti, so the legacy root crontab entries running `drush queue:run job_hunter_*` were invalid and generated repeated `Queue not found` errors. I removed the three stale `job_hunter_*` queue cron lines and updated `scripts/ceo-system-health.sh` so tailoring-queue enforcement is skipped when `job_hunter` is disabled on live Forseti. The queue-health check now passes without dispatching new `dev-forseti` blocker work.

## Evidence
- `crontab -l` no longer contains `vendor/bin/drush queue:run job_hunter_genai_parsing`
- `crontab -l` no longer contains `vendor/bin/drush queue:run job_hunter_job_posting_parsing`
- `crontab -l` no longer contains `vendor/bin/drush queue:run job_hunter_resume_tailoring`
- `bash scripts/ceo-system-health.sh | grep -nE 'Drupal Queue Health|Tailoring queue'`
  - `✅ PASS Tailoring queue check skipped: job_hunter module is disabled on live Forseti`

## Files changed
- `scripts/ceo-system-health.sh`

## Remaining note
- This resolves the false blocker/escalation loop. It does **not** re-enable the disabled Forseti application modules; if product intent is to restore `job_hunter` on live Forseti, that is a separate product/runtime decision.
