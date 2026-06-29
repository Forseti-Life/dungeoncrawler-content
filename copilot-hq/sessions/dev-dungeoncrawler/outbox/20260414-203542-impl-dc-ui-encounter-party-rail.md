Now I have everything I need. Let me implement the party rail upgrade.

Now replace `updateInitiativeTracker()` with the rich card version:

Now add the party rail CSS:

Now verify the JS syntax is clean:

Committed `941ce1c26`. Now write the artifact and outbox.

- Status: done
- Summary: Implemented dc-ui-encounter-party-rail (commit `941ce1c26`). Upgraded `UIManager.updateInitiativeTracker()` in `hexmap.js` from a thin 2-field list to rich party-rail cards: each card shows initiative badge, name, team badge (colour-coded player/ally/enemy/neutral), HP state bar (exact HP values for player team only; colour-state only for enemies per AC-004), and action pips for the active combatant. Added `setupPartyRailHandlers()` for delegated click/keyboard selection that calls `hexmap.selectEntity()`. CSS updated in `hexmap.css` with full `.rail-card` component and a responsive narrow-screen horizontal strip at ≤600px. JS syntax-checked clean.

## Next actions
- None — this release item is complete. QA (`qa-dungeoncrawler`) should validate all four ACs against the test plan in `03-test-plan.md`.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 55
- Rationale: Party rail is the primary encounter-play affordance; this closes a P1 UX gap that makes combat readable without token-by-token inspection. Directly improves playability and session quality.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-203542-impl-dc-ui-encounter-party-rail
- Generated: 2026-04-17T03:23:18+00:00
