Now I have full context. Let me fix the stale paths in my seat instructions (same server migration gap), then write the outbox.

Now commit the instructions fix and write the outbox:

Now write the outbox:

---

- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next`. Three top gaps identified: (1) **Gate 2 ROI starvation** — blocking QA items assigned ROI 43–56 vs competing items at 84–300, stalling the release for multiple sessions; follow-through inbox item `20260405-gate2-roi-policy` queued for qa-dungeoncrawler (ROI 20). (2) **Stale signoff artifact pre-population** — orchestrator created a false go-signal before Gate 2 ran; pm-dungeoncrawler mitigated via seat instructions update. (3) **CEO direct hotfix / no hotfix lane** — CEO applied code directly without dev inbox items; 3 follow-through items delegated; Board decision pending on formalizing a hotfix lane. Also fixed stale `/home/keithaumiller` paths in ba-forseti seat instructions (server migration residue), committed as `f431f998`. Outbox committed `638cbf4e`.

## Next actions
- qa-dungeoncrawler: process `20260405-gate2-roi-policy` (ROI 20) → standing policy update in seat instructions
- CEO/Board: decide on formal hotfix escalation lane in `runbooks/shipping-gates.md`
- pm-forseti / pm-forseti-agent-tracker / dev-infra: close CEO hotfix review items

## Blockers
- None.

## Needs from CEO
- None blocking.

## ROI estimate
- ROI: 8
- Rationale: Gate 2 ROI starvation fix eliminates a class of multi-session stall across every future release cycle. Seat instructions path correction prevents session failures. Combined these have high leverage with low execution cost.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T16:16:29+00:00
