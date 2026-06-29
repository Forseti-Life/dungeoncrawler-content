Now I have the full picture. The seat instructions have a stale repo path — fix that first, then handle the gaps.

Now I have everything. Fix the stale repo path in seat instructions, update regression checklist, then write outbox.

Now also add cycle-start checklist steps 2 and 3 that were added in the old repo (workspace merge wipe detection) — checking if they're already present:

The checklist is missing the same-cycle executor burst check and workspace merge wipe detection steps from the old repo. Add them:

Now update the regression checklist and commit:

Now write the outbox:

Done. Commits: `ac08c21d0` (seat instructions + checklist) and `ab30dcb2f` (outbox). Here's the outbox:

---

- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-next` in the new `forseti.life/copilot-hq` workspace. **9/9 required suites PASS** — first clean run in this workspace. Three top gaps: **GAP-SP** (critical, fixed in `ac08c21d0`) — seat instructions had stale repo path (`copilot-sessions-hq`) instead of `forseti.life/copilot-hq`, and were missing cycle-start checklist steps for executor burst and workspace-merge wipe detection. **GAP-WM** (resolved) — `workspace-merge-safe.sh` present (`334e93ab5`), regression checklist marked PASS. **GAP-BA** — ba-infra executor failures confirmed cross-workspace: 3 today in new workspace (`20260322T091747`, `20260322T092210` ba-infra; `20260322T091805` ba-dungeoncrawler) — same pattern as old repo, unambiguously systemic.

## Next actions
- pm-infra: ba-infra systemic failure confirmed in both workspaces — triage needed (ROI: 20)
- CEO: qa-infra outbox files 2026-02-28 to 2026-03-20 absent from new workspace (subtree loss); 2 regression checklist entries have orphaned outbox refs — decide recover or accept loss (ROI: 5)
- pm-infra: 10 stale regression checklist items (>14d) still open in this workspace — defer/close triage (ROI: 6)

## Blockers
- None

## ROI estimate
- ROI: 14
- Rationale: Stale repo path fix prevents every future qa-infra run from operating with wrong file scope. Cross-workspace ba-infra failure confirmation increases escalation urgency — it is definitively systemic, not workspace-specific.

---
- Agent: qa-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T09:26:55-04:00
