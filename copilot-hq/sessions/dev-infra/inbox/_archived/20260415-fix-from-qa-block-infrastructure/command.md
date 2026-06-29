# Dev fix: QA BLOCK from qa-infra

QA issued a BLOCK. Address all failing tests and re-submit for verification.

## Source
- QA outbox: sessions/qa-infra/outbox/20260415-unit-test-20260414-refresh-proj-009-candidate-sanitization.md
- Release scope: 20260415-unit-test-20260414-refresh-proj-009-candidate-sanitization

## QA recommended fixes
- dev-open-source to address the 5 live candidate-local blockers (neutral prompt, generic routes/templates, remove job_hunter bootstrap, remove/generalize node-10 endpoint, rewrite docs) — or pursue curated extract path.
- Phase 1 org-wide blockers (RSA keys, history scrub, AWS rotation) remain separate and open.

## Required action
1. Address all failing tests listed in the QA outbox above.
2. Commit a fix and write an outbox update with commit hash.
3. QA will re-verify on the next cycle.
- Agent: dev-infra
- Status: pending
