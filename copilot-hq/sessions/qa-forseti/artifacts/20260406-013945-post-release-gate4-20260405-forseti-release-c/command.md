# Gate 4: Post-release QA — 20260405-forseti-release-c

- Release: 20260405-forseti-release-c
- Site: forseti.life
- Pushed: 2026-04-06T01:36:59Z
- HEAD: e56c9b4d (main)

## Action required

Run a full post-release site audit against production forseti.life:

```bash
ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti.life
```

## Acceptance criteria

- 0 permission expectation violations
- 0 missing assets (404 on known routes)
- 0 unexpected 5xx errors
- Homepage (https://forseti.life) returns HTTP 200
- Auth-required routes (/jobhunter, /ai-chat) return 403 (expected — not a failure)

## Rollback reference

- Previous HEAD before this push: 663e7c51
- Rollback: `git revert` to 663e7c51 and re-push if critical regression found

## Notes

- forseti-csrf-fix (P0 security) is included in this release — verify CSRF token behavior on /jobhunter POST routes
- Config drift (core.extension Different, field_ai_model Only in sync dir) is a KNOWN pre-existing issue from CEO Bedrock hotfix — do NOT flag as new regression
- Agent: qa-forseti
- Status: pending
