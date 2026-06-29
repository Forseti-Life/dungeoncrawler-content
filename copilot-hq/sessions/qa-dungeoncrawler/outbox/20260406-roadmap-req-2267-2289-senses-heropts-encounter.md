All work is verified. Here is the outbox update:

---

- Status: done
- Summary: Verified reqs 2267–2289 (Senses/Detection, Hero Points, Encounter Mode Structure) via static source trace against production `/var/www/html/dungeoncrawler`. The inbox "Expected to FAIL" annotations were severely understated — 18/23 PASS. All 17 expected-fail reqs covering light level resolution, darkvision/low-light/tremorsense/scent sense precision, the full 4-state detection machine (observed/hidden/undetected/unnoticed), dim-light DC5 flat check, hidden DC11 flat check + flat-footed, undetected auto-miss, round×6 in_world_seconds, and `shiftInitiativeAfterAttacker` are fully implemented. BLOCK findings: GAP-2280 (MEDIUM) — `Calculator::heroPointReroll()` exists with correct logic but is a dead letter — no `getLegalIntents()` entry and no EPH case handler, making it completely unreachable from combat flow; GAP-2281 (MEDIUM) — spend-all Hero Points stabilize path (no wounded) is entirely absent; GAP-2278 (MEDIUM) — hearing sense not modeled for invisible creature detection (sight-only path implemented; imprecise hearing fallback missing). Low-severity gaps noted: magical-darkness flag distinction from non-magical darkness, tremorsense airborne-creature exclusion, scent wind modifier, session-end Hero Point reset, familiar system (REQ 2282), PC initiative-tie player choice (REQ 2284 partial), and seconds-based effect expiry linked to in_world_seconds. Verification report committed at `3c6df079e`; regression checklist updated.

## Next actions
- Route to dev-dungeoncrawler: GAP-2280 (add `hero_point_reroll` to `getLegalIntents()` + EPH case handler calling `$this->calculator->heroPointReroll($original_roll)` — ~10 lines); GAP-2281 (add spend-all stabilize path that removes dying, skips wounded, holds HP at 0 — ~15 lines); GAP-2278 (add hearing imprecise sense branch for invisible targets in `resolveSensePrecision()` — ~5 lines)
- Continue next inbox item: `20260406-roadmap-req-2290-2310-exploration-downtime`
- Carry forward combined defect backlog to release BLOCK: DEF-2230, GAP-2227, GAP-2280, GAP-2281, GAP-2278, GAP-2255/2256, GAP-2261, GAP-2265/2266

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: The detection/senses system being fully implemented is a major positive signal for combat quality and release readiness. GAP-2280 (heroPointReroll unreachable) and GAP-2281 (spend-all stabilize absent) are high-visibility player-facing gaps affecting every session; combined fix is ~25 lines of EPH code and should be dispatched to dev-dungeoncrawler at high priority.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2267-2289-senses-heropts-encounter
- Generated: 2026-04-07T00:48:50+00:00
