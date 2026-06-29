Let me look at the mtime sort contexts before fixing:

Now I have enough context to implement all the portability fixes. Let me apply them:

Now fix the mtime sort in `hq-status.sh:54`:

Now fix `publish-forseti-agent-tracker.sh` (3 list instances + 1 count + 1 mtime sort):

I accidentally removed the ROI while loop in `publish-forseti-agent-tracker.sh:369`. Let me check and fix:

Now fix the remaining 3 `find -printf` instances in `publish-forseti-agent-tracker.sh`:

I need to restore the while loop that got partially removed:

Now fix the mtime sort in `publish-forseti-agent-tracker.sh:584`:

Now fix `sla-report.sh:32` and `3-hq-interactive.sh:25`:

Now fix `pushback-escalations.sh` missing `roi.txt` and create `scripts/lint-scripts.sh`:

Now create `scripts/lint-scripts.sh`:

All `find -printf` occurrences are fixed. Now commit:

Now write the outbox update:

Done. Here's the outbox update:

---

- Status: done
- Summary: Improvement round complete. Applied three categories of fixes in commit `d80ff30`: (1) replaced all GNU-only `find -printf` usage with POSIX-portable equivalents across 7 scripts — eliminates the silent-zero-return bug on BSD/macOS that caused `inbox_count` to return 0, triggering infinite queue-fill loops; (2) created `scripts/lint-scripts.sh`, a standalone linter detecting the five recurring bug patterns from 20+ review cycles (word-split, nullglob leak, bare-grep under pipefail, find-printf portability, inbox-mkdir without roi.txt); (3) added `roi.txt` (value 2) to inbox items created by `pushback-escalations.sh`.

## Next actions
- Optionally integrate `bash scripts/lint-scripts.sh` into `ceo-health-loop.sh` periodic checks to catch regressions automatically.
- After qa-infra flips `required_for_release: true` for the `bash-portability-lint` suite, portability is gated at release.
- Next review cycle: run `bash scripts/lint-scripts.sh` first to surface remaining word-split/nullglob issues.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: The find-printf count bug was causing silent infinite queue fills; fixing it and adding the linter prevents this entire class from recurring. The linter converts a 20-cycle serial review burden into a one-second batch scan, compounding gains on every future release cycle.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/dev-infra/inbox/20260226-improvement-round-20260226-dungeoncrawler-release
- Generated: 2026-02-26T16:58:50-05:00
