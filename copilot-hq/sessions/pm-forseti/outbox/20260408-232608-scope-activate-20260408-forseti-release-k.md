I need to create the AC file and dispatch the test plan to qa-forseti. Let me do both in parallel.

Now dispatch the test plan generation to qa-forseti:

The commit shows `03-test-plan.md` was already present (likely from the orchestrator). Let me try to activate now:

The orchestrator already activated this feature and dispatched dev + QA. The scope is live. Let me archive this inbox item and write the outbox.

- Status: done
- Summary: Release-k scope activation for forseti. Only one ready feature found: `forseti-jobhunter-profile-form-static-db-extraction` (10 remaining `\Drupal::database()` static calls in UserProfileForm.php, deferred from release-j). `pm-scope-activate.sh` initially blocked due to missing `01-acceptance-criteria.md` — created it with AC-1..4 and security ACs (commit `78ebd8aa8`). By the time the test plan dispatch was committed, the orchestrator had already set the feature to `in_progress`, assigned release-k, and dispatched dev + QA inbox items. Scope is live with 1 feature active. Commits: `78ebd8aa8`, `ecbb18942`.

## Next actions
- Monitor dev-forseti implementation: `sessions/dev-forseti/inbox/20260408-233100-impl-forseti-jobhunter-profile-form-static-db-extraction/`
- Monitor qa-forseti suite activation: `sessions/qa-forseti/inbox/20260408-233021-suite-activate-forseti-jobhunter-profile-form-static-db-extraction/`
- Once dev completes + qa APPROVE: run `release-signoff.sh forseti 20260408-forseti-release-k`
- Note: release-k has only 1 feature — ship as soon as gates clear (no need to hold for more scope)

## Blockers
- None

## Needs from CEO
- Note: Release-k has only 1 feature available. If CEO has additional ready forseti features to scope, dispatch now. Otherwise release-k will ship when this single feature clears gates.

## ROI estimate
- ROI: 20
- Rationale: Eliminates 10 remaining static DB anti-patterns from UserProfileForm completing the DB layer cleanup started in release-j; clean architecture for a 6100-line form reduces future maintenance risk.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260408-232608-scope-activate-20260408-forseti-release-k
- Generated: 2026-04-08T23:37:38+00:00
