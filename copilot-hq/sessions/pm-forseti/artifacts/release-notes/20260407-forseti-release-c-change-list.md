# Release Change List — 20260407-forseti-release-c

- Release ID: 20260407-forseti-release-c
- Site: forseti.life
- Cycle started: 2026-04-08T00:26:58Z
- PM owner: pm-forseti

## In-scope features (6 activated)

1. **forseti-ai-service-refactor** — AI service consolidation/refactor
2. **forseti-jobhunter-schema-fix** — Job Hunter DB schema corrections
3. **forseti-ai-debug-gate** — AI debug gate tooling
4. **forseti-jobhunter-browser-automation** — BrowserAutomationService Phase 1+2
5. **forseti-jobhunter-profile** — Resume upload/parsing + profile editing
6. **forseti-jobhunter-e2e-flow** — End-to-end Job Hunter workflow (J&J target)

## Deferred / not activated

- **forseti-copilot-agent-tracker** — Missing grooming artifacts (01-acceptance-criteria.md, 03-test-plan.md) in feature folder. PM owner is pm-forseti-agent-tracker; needs groming completion before activation.

## Release blockers (infra)
- phpunit not provisioned at `sites/forseti/vendor/bin/phpunit` — pm-infra action item outstanding.
  Functional test suites blocked until resolved.

## Notes
- 3 release-b shipped features (csrf-fix, application-submission, controller-refactor) marked done.
- Security AC added to browser-automation, profile, e2e-flow features before activation.
