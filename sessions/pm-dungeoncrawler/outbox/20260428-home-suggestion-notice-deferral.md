- Status: done
- Summary: Deferred `dc-home-suggestion-notice` out of release-x after QA produced a live BLOCK verdict. The notice is not rendering on `https://dungeoncrawler.forseti.life/`, so this feature cannot be counted toward Gate 2 for release-x. To keep release-x moving, the feature brief was updated to `deferred` and removed from release-x scope. Re-intake it in a later cycle once the front-page rendering path is understood.

## Evidence
- QA outbox: `sessions/qa-dungeoncrawler/outbox/20260428-unit-test-20260427-171039-impl-dc-home-suggestion-notice.md`
- Verification command: `curl -s https://dungeoncrawler.forseti.life/ | grep -nF 'We are actively implementing player suggestions. Keep the ideas coming!'`
- Result: no matches

## Queue action
- Archive the QA inbox item and the resulting PM missing-escalation churn as superseded by this scope decision.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-28T12:46:18+00:00
