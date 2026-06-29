- Status: done
- Summary: Hardened PM signoff enforcement so release signoff now depends on explicit repo-state proof instead of ambiguous reminder text. Fixed orchestrator signoff reminders to include the exact release ID, exact `release-signoff.sh` command, explicit artifact-path proof, and `release-signoff-status.sh` verification. Also fixed dependency reminder dispatch so a signed primary PM now correctly triggers reminders for unsigned dependency PMs, deduplicates stale reminders across days, and no longer instructs PMs to look for a fake `- Status: approved` field inside the artifact. Updated `pm-dungeoncrawler` instructions to make signoff proof a hard gate, refreshed release-signoff test fixtures, ran the release-signoff regression suite, and corrected the live queue by creating `20260429-code-review-followup-20260412-dungeoncrawler-release-y` while archiving stale release-y signoff reminders that were prompting PMs to sign a still-blocked release.

## Verification
- `PYTHONPATH=/home/ubuntu/forseti.life pytest -q orchestrator/tests/test_signoff_reminder_dispatch.py orchestrator/tests/test_release_signoff_cross_team_qa.py scripts/tests/test_ceo_pipeline_remediate.py scripts/tests/test_release_signoff_notifications.py`
- Live queue now contains `sessions/pm-dungeoncrawler/inbox/20260429-code-review-followup-20260412-dungeoncrawler-release-y/`
- `python3 scripts/check-code-review-routing.py 20260412-dungeoncrawler-release-y` still blocks, so signoff reminders for that release were archived as stale

## Next actions
- Drive `pm-forseti` to write the real signoff artifact for `20260412-forseti-release-v`
- Keep `pm-dungeoncrawler` on Gate 1b follow-up until `python3 scripts/check-code-review-routing.py 20260412-dungeoncrawler-release-y` returns clean
