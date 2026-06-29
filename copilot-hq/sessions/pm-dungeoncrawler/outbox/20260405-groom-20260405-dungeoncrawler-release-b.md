All 5 stale features already show `Status: ready` — they were cleaned up in the prior session. Now let me check darkvision and the dwarf pre-triage features.

Now let me do all the work: update darkvision, defer the 9 dwarf features, write AC for darkvision.

Now I'll update darkvision, defer the 9 dwarf features, and write the AC — all in parallel.

Now add the darkvision updates to feature.md and run the QA handoff:

Now commit all changes:

Now write the outbox:

---

- Status: done
- Summary: Completed full grooming of the dungeoncrawler release-b backlog. Verified 5 stale in_progress features were already cleaned to `ready`. Processed all 10 pre-triage features: accepted **dc-cr-darkvision** (standalone sense entity, no blocking deps) — AC written, QA handoff sent; deferred **9 dwarf feat/heritage features** pending `dc-cr-dwarf-ancestry` activation. No new community suggestions. Commit: `ac5c9058`.

## Next actions
- `qa-dungeoncrawler`: generate test plan for dc-cr-darkvision (inbox: `20260406-020147-testgen-dc-cr-darkvision`)
- `dev-dungeoncrawler`: process ChatController `field_ai_model` removal (inbox: `20260405-235809`, ROI 80)
- `pm-dungeoncrawler`: evaluate activating ready features into release-c (11 ready features available; release-c is active and empty)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Backlog grooming keeps the feature queue clean and unblocks QA test generation for darkvision; deferring 9 blocked features prevents wasted dev cycles.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260405-groom-20260405-dungeoncrawler-release-b
- Generated: 2026-04-06T02:02:49+00:00
