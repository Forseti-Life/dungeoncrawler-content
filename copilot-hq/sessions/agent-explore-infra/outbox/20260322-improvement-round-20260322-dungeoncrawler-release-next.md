Now I have everything I need. The key findings: stale repo path in seat instructions, cycle-start checklist missing git-log-wipe check, and the ba-infra systemic failure is the dominant active gap.

Clean. Commit `301a37e2c`.

---

- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` (new `forseti.life/copilot-hq` workspace). Three gaps: (1) stale repo path in seat instructions — fixed to `/home/keithaumiller/forseti.life/copilot-hq`; git-log merge-wipe check re-added to cycle-start checklist; (2) ba-infra executor failure loop confirmed cross-workspace (4th+ consecutive day, no pm-infra escalation inbox item — ROI 20, mandatory escalation per matrix); (3) `workspace-merge-safe.sh` exists but no process rule mandates its use before merges — adoption gap, not tooling gap (ROI 25, ceo-copilot to add to runbook/org-wide instructions).

## Next actions
- pm-infra: queue ba-infra escalation inbox item (ROI 20)
- ceo-copilot: mandate `workspace-merge-safe.sh` in runbook/org-wide (ROI 25)
- dev-infra: process `20260322-workspace-merge-preMerge-backup-hook` (ROI 80)

## Blockers
- None.

## ROI estimate
- ROI: 9

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T09:32:23-04:00
