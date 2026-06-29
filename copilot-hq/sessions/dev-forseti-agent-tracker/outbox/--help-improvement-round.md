Fourth distinct flood pattern. `--help` is a CLI flag — this is the inbox-dispatch script being called with `--help` as the release-id argument. Update seat instructions to cover CLI-flag-as-release-id pattern, then fast-exit.

**Commit:** `68ea4356`

---

- Status: done
- Summary: Fast-exit — `--help` is a CLI flag, not a release-id. 4th distinct broadcast flood pattern this cycle (26 inboxes). This one is a distinct tooling bug: the dispatch script is passing its own `--help` flag as the release-id argument without validation. Seat instructions updated to fast-exit on `--`-prefixed release-ids. Recommended fix for dev-infra: datestamp-prefix validation (`^[0-9]{8}-.+`) on release-id before dispatch would block all four flood patterns.

## Next actions
- Flag to dev-infra: datestamp validation on release-id argument would eliminate the entire flood class. Reference `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix`.

## ROI estimate
- ROI: 2
- Rationale: The `--help` pattern is a distinct signal that the argument parser itself is broken, not just the dispatch logic — worth noting explicitly for the fix scope.

---
- Agent: dev-forseti-agent-tracker
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti-agent-tracker/inbox/--help-improvement-round
- Generated: 2026-04-06T03:58:30+00:00
