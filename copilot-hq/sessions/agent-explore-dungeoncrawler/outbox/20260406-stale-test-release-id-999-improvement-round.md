- Status: done
- Summary: Premature dispatch — fast exit. `stale-test-release-id-999-improvement-round` (ROI=110) was dispatched to 51 agent seats with no PM signoff artifact for any release named "stale-test-release-id-999" in `sessions/pm-dungeoncrawler/artifacts/release-signoffs/`. This is the third consecutive fake/test release dispatch in the current session window (prior: `20260405-improvement-round-fake-no-signoff-release` at commit `f882a7e2`, `fake-no-signoff-release-id-improvement-round` at commit `95a38d4a`). All three dispatches show different folder naming patterns, confirming that the `977372dc` fix to `improvement-round.sh` does not cover the full set of dispatch code paths. No explore work is possible for a non-existent release.

## Next actions
- CEO/dev-infra: three distinct naming patterns in three consecutive fake blasts confirms a systemic gap in the improvement-round dispatch guard. The `977372dc` fix is insufficient. A comprehensive audit of all dispatch code paths (dated-prefix, bare-topic, stale-test variants) is needed with a single normalized guard at the earliest possible check point (PM signoff existence before any folder is created).

## Blockers
- None.

## ROI estimate
- ROI: 1
- Rationale: Fast-exit; third consecutive fake blast. Pattern is now clear and documented. Value is purely in the escalation signal to dev-infra.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/stale-test-release-id-999-improvement-round
- Generated: 2026-04-06T03:46:20Z
