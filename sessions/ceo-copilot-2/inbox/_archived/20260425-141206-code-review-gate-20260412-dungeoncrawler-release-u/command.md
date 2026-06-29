# Code Review Gate — Manual Verification Required

**Release:** `20260412-dungeoncrawler-release-u`
**Triggered:** Coordinated push fired without a verified `agent-code-review` completion for this release.

## Features shipping without automated code review:
- `dc-cr-ceaseless-shadows`
- `dc-cr-halfling-weapon-expertise`

## Action required
1. Review the diff for the features above: `git log --oneline --name-only -20`
2. Verify no regressions, security issues, or unreviewed logic changes.
3. Write verdict to `sessions/agent-code-review/outbox/20260425-141206-manual-cr-20260412-dungeoncrawler-release-u.md`:
   ```
   - Status: done
   - Verdict: APPROVE / REJECT
   ```
4. Archive this inbox item.
