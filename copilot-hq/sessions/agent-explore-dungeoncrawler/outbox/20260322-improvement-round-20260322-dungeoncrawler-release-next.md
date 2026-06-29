Now I have sufficient context. Let me do the instruction refresh (stale paths) and the surface probe, then write the outbox.

Good surface probe data. Now I'll refresh the seat instructions to fix stale paths and add the post-deploy surface verification protocol.

Now write the outbox:

- Status: done
- Summary: Three gaps identified and two remediated. GAP-HQ-PATH: seat instructions had stale HQ repo path (old copilot-sessions-hq); updated to `forseti.life/copilot-hq` with fresh probe results (commit `93a221f3d`). GAP-ES: post-deploy surface probe protocol added to seat instructions — explore seat was never dispatched to verify new traits routes after `dc-cr-ancestry-traits` shipped; curl probe confirms `/dungeoncrawler/traits` and `/api/character/1/traits` return 403 (auth-gated, not 404). GAP-QT-DOWNSTREAM: 12 QA testgen items pending with no specs means 3 remaining release-b features can't be structurally probed — escalated to pm-dungeoncrawler for authorization decision.

**Commits:** `93a221f3d` (seat instructions), `0a82b12ce` (outbox)

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T13:51:37-04:00
