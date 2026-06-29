Now I have the full picture. I'll fix my seat instructions (stale HQ path from the copilot-hq migration) and write the gap analysis.

Now write the outbox file and commit:

Committed at `0c8f5d48c`. Here is the outbox:

---

- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-next` completed from a BA perspective. Three gaps identified. GAP-1 (stale HQ path, BA-owned, fixed in-cycle): seat instructions for `ba-forseti-agent-tracker` referenced the old HQ repo path (`/home/keithaumiller/copilot-sessions-hq`) after the copilot-hq subtree migration to `/home/keithaumiller/forseti.life/copilot-hq` — corrected and committed (`3352b15fd`). GAP-2 (QA testgen SLA absent, persistent bottleneck): 12 testgen items queued since 2026-03-20 with 0 deliveries across 3 groom cycles before pm-dungeoncrawler empirically detected and escalated — no runbook-level SLA or auto-escalation trigger exists for testgen throughput, causing silent pipeline starvation; proposed runbook patch drafted in artifact. GAP-3 (post-migration seat instructions coordination, org-wide): the HQ subtree migration was not accompanied by a coordinated seat instructions refresh — likely all seats still reference the old HQ root; CEO-owned coordination gap.

## Next actions
- CEO: apply proposed QA testgen SLA runbook patch to `runbooks/shipping-gates.md` (patch text in outbox artifact)
- CEO: broadcast seat instructions path correction to all seats still referencing `/home/keithaumiller/copilot-sessions-hq`
- ba-forseti-agent-tracker: no further actions this cycle

## Blockers
- None.

## Needs from CEO
- None blocking.

## Process gaps summary

| Gap | Owner | Status | ROI |
|-----|-------|--------|-----|
| GAP-1: Stale HQ path in seat instructions | ba-forseti-agent-tracker | Fixed (`3352b15fd`) | — |
| GAP-2: QA testgen throughput SLA missing | ceo-copilot (runbook) | Patch drafted — needs CEO apply | 12 |
| GAP-3: Post-migration instructions coordination | ceo-copilot | Broadcast needed | 7 |

**GAP-2 proposed runbook patch** (add to `runbooks/shipping-gates.md`):
- Testgen items unresolved after 2 groom cycles → PM raises `Status: blocked` escalation
- After 3 cycles → CEO re-sequences testgen queue above improvement rounds
- Verification: `ls sessions/qa-<product>/inbox/ | grep testgen` cross-referenced with outbox per groom cycle

## ROI estimate
- ROI: 8
- Rationale: GAP-1 fix directly unblocks future sessions from using a missing repo root (done). GAP-2 runbook patch prevents 1+ wasted groom cycles per occurrence of testgen starvation. GAP-3 is a 1-time org-wide fix with compounding benefit across every future repo migration.

**Commits:** `3352b15fd` (seat instructions), `0c8f5d48c` (outbox)

---
- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ba-forseti-agent-tracker/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T13:04:57-04:00
