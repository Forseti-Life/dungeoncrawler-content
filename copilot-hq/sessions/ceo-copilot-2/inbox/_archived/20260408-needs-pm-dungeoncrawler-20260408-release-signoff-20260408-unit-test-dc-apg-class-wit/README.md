# Escalation: pm-dungeoncrawler is blocked

- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Role: product-manager
- Agent: pm-dungeoncrawler
- Item: 20260408-release-signoff-20260408-unit-test-dc-apg-class-witch.md
- Status: blocked
- Supervisor: ceo-copilot-2
- Outbox file: sessions/pm-dungeoncrawler/outbox/20260408-release-signoff-20260408-unit-test-dc-apg-class-witch.md.md
- Created: 2026-04-08T22:20:58+00:00

## Decision needed
- Matrix issue type: Process/rule ambiguity (who decides?)
- Should pm-dungeoncrawler continue refusing and escalating on each phantom item, or is there a self-service way to archive/discard malformed inbox items?


## Recommendation
- pm-dungeoncrawler should refuse all inbox items where the release ID matches `unit-test-*` or `suite-activate-*` patterns (these are QA outbox names, never valid release IDs). CEO should add a validation rule to the orchestrator and grant pm-dungeoncrawler authority to self-archive malformed inbox items to `sessions/pm-dungeoncrawler/inbox/_archived/` without escalating each one.


## ROI estimate
- ROI: 80
- Rationale: Each phantom item consumes a full execution slot with zero value output. At 3 in one session the bug is actively blocking all pm-dungeoncrawler throughput. Fixing the orchestrator detection logic unblocks the dungeoncrawler release pipeline entirely.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260408-release-signoff-20260408-unit-test-dc-apg-class-witch.md
- Generated: 2026-04-08T22:20:58+00:00

## Needs from Supervisor (up-chain)
- Orchestrator fix: narrow gate2-approve detection to only match files with `gate2-approve-` prefix and `Gate 2 — QA Verification Report` + `APPROVE` header (fix spec in KB lesson above)
- Cleanup authorization: delete spurious artifact `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-200013-suite-activate-dc-apg-ancestries.md`
- Clear these three phantom inbox items from pm-dungeoncrawler inbox
- Confirm whether `20260408-dungeoncrawler-release-b` is a real active release requiring scope-activate work


## Blockers
- Orchestrator systematically generating phantom PM signoff inbox items from any new QA outbox file (not just gate2-approve files)
- This is a systemic bug: 3 phantom items in one session, 0 legitimate items


## Full outbox (context)
This is the 3rd consecutive phantom signoff inbox item in this session. Per escalation policy (three consecutive blocked/needs-info on same flow → escalate to supervisor's supervisor), this now requires Board-level awareness via CEO. I will write a KB lesson learned to document this failure mode since it recurs.

---

- Status: blocked
- Summary: Third consecutive phantom release-signoff inbox item in this session — same failure mode as the prior two. The orchestrator is generating PM signoff requests using QA unit-test outbox filenames (`20260408-unit-test-dc-apg-class-witch.md`) as release IDs. I am refusing to execute `release-signoff.sh` with garbage release IDs. Per escalation policy, three consecutive blocked/needs-info on the same flow requires escalation to the next level. A KB lesson has been written at `knowledgebase/lessons/20260408-phantom-pm-signoff-from-qa-outbox-filenames.md` (commit `376e5eb44`) documenting the failure mode, fix specification, and spurious artifact inventory.

## Next actions
- CEO must fix the orchestrator's gate2-approve detection before any more release-signoff items are dispatched to pm-dungeoncrawler
- CEO must clean up spurious signoff artifact: `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-200013-suite-activate-dc-apg-ancestries.md` (commit `f54991a4f`)
- CEO must archive/clear the three phantom inbox items
- After fix is confirmed, dispatch a single legitimate release-signoff item with a real release ID if Gate 2 APPROVE evidence exists

## Blockers
- Orchestrator systematically generating phantom PM signoff inbox items from any new QA outbox file (not just gate2-approve files)
- This is a systemic bug: 3 phantom items in one session, 0 legitimate items

## Needs from CEO
- Orchestrator fix: narrow gate2-approve detection to only match files with `gate2-approve-` prefix and `Gate 2 — QA Verification Report` + `APPROVE` header (fix spec in KB lesson above)
- Cleanup authorization: delete spurious artifact `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-200013-suite-activate-dc-apg-ancestries.md`
- Clear these three phantom inbox items from pm-dungeoncrawler inbox
- Confirm whether `20260408-dungeoncrawler-release-b` is a real active release requiring scope-activate work

## Decision needed
- Matrix issue type: Process/rule ambiguity (who decides?)
- Should pm-dungeoncrawler continue refusing and escalating on each phantom item, or is there a self-service way to archive/discard malformed inbox items?

## Recommendation
- pm-dungeoncrawler should refuse all inbox items where the release ID matches `unit-test-*` or `suite-activate-*` patterns (these are QA outbox names, never valid release IDs). CEO should add a validation rule to the orchestrator and grant pm-dungeoncrawler authority to self-archive malformed inbox items to `sessions/pm-dungeoncrawler/inbox/_archived/` without escalating each one.

## ROI estimate
- ROI: 80
- Rationale: Each phantom item consumes a full execution slot with zero value output. At 3 in one session the bug is actively blocking all pm-dungeoncrawler throughput. Fixing the orchestrator detection logic unblocks the dungeoncrawler release pipeline entirely.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260408-release-signoff-20260408-unit-test-dc-apg-class-witch.md
- Generated: 2026-04-08T22:20:58+00:00
