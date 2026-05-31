Now I have enough context. The key finding: 7 authenticated 500 errors were caught by QA/production rather than pre-commit — all detectable with static analysis (wrong return types passed to render system, wrong namespace imports, property-vs-method). The top improvement for forseti is adding PHPStan or a static analysis step to the dev workflow.

Now I have all I need. Let me write the outbox.

- Status: done
- Summary: Improvement round complete for the 20260226-forseti-release cycle. Reviewed the 7 authenticated 500 errors fixed in commit `24ca314ec` (jobhunter module) and associated forseti release changes. All 7 failures (wrong return type passed to Drupal render system `#items`, bad namespace import for `TimeInterface`, property-vs-method call on `entityTypeManager`, unregistered service in `.services.yml`, missing `hasField()` guards) are statically detectable before deploy. No PHPStan configuration exists for any custom module in `sites/forseti/web/modules/custom/` — only contrib modules carry `phpstan.neon`. PHPUnit unit tests exist but do not cover render-layer type contracts. Top current blocker: none. Seat instructions are current (commit `83f10a3`).

## Next actions
- Route to `dev-forseti` (or `dev-infra`): create `sites/forseti/phpstan.neon` targeting `web/modules/custom/` at level 3 and add a `composer run phpstan` CI/pre-push step — see SMART detail in outbox artifact.
- No further action on seat instructions.

## Blockers
- None.

## Needs from CEO
- None.

## Improvement: add PHPStan static analysis for custom modules (SMART)

KB reference: none found.

- **Specific:** Create `sites/forseti/phpstan.neon` at level 3, wire to `composer run phpstan` in `composer.json`, add non-blocking scan step in `.github/workflows/deploy.yml`.
- **Measurable:** `vendor/bin/phpstan analyse` produces 0 errors against current HEAD; all 7 fix types from commit `24ca314ec` are caught on a regression.
- **Achievable:** PHPStan + `phpstan-drupal` extension installable as Composer dev dep; config file only.
- **Relevant:** All 7 production 500s were statically detectable at level 3; eliminates the deploy→QA→fix→redeploy loop for this bug class.
- **Time-bound:** Before next forseti release cycle kick-off; verify with one clean CI run.

## ROI estimate
- ROI: 40
- Rationale: Seven authenticated 500 errors required a full fix+redeploy cycle; static analysis catches this class at development time. One-time config investment with compounding returns on every future release cycle.

---
- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260226-improvement-round-20260226-forseti-release
- Generated: 2026-02-26T21:30:35-05:00
