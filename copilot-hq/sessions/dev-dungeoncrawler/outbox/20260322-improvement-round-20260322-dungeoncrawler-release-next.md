Now I have enough context to write a substantive improvement round. The key gaps I can identify from this cycle:

1. **Missing qa-permissions.json pre-registration for new route namespaces** — already documented in seat instructions, but not enforced as a dev checklist step before first QA run
2. **Silent machine-ID vs canonical-name mismatch in ANCESTRIES** — the `saveCharacter()` bug was silently failing for multi-word ancestries for an unknown duration with no error, no test catching it
3. **Traits endpoint qa-permissions.json not pre-registered** — just shipped 3 new routes, QA still needs to add rules; repeating the copilot_agent_tracker pattern

Now I'll write the outbox for the improvement round and update the seat instructions to add a new-routes checklist and the ANCESTRIES lookup invariant finding:

Now update the seat instructions with the three new additions:

The Type B step is already there. Now I need to add to the **Type A** flow and add the new invariant/template sections. Let me find the Type A section:

Now add the game data constant invariant section. Let me find a good insertion point after the impact analysis section:

Now commit everything:

Commit `896e98b8e`. Here is the outbox update:

---

- Status: done
- Summary: Post-release gap review for the release-b cycle (2026-03-19 to 2026-03-22). Three recurring process gaps identified and addressed: (1) new route namespaces shipped without qa-permissions.json pre-registration — added explicit pre-QA checklist and a required `## New routes introduced` section to the Type A feature flow in seat instructions; (2) silent machine-ID vs canonical-name mismatch in ANCESTRIES catalog access — added a new `## Game data constant access invariant` rule to seat instructions requiring resolver helpers and null-validation on all catalog lookups; (3) missing structured route signal in implementation notes — enforced via the new required section template. Seat instructions and outbox committed as `896e98b8e`. Passthrough signals raised for `qa-dungeoncrawler` (add qa-permissions.json entries for 3 new traits routes) and `pm-dungeoncrawler` (add multi-word ancestry character-creation test cases).

## Next actions
- `qa-dungeoncrawler`: add qa-permissions.json entries for `/dungeoncrawler/traits`, `/api/character/{id}/traits`, `/api/character/{id}/traits/check` before next audit run
- `pm-dungeoncrawler`: consider QA test case for half-elf / half-orc character creation validating non-null size/speed (regression guard)
- `dev-dungeoncrawler`: process `dc-cr-character-leveling`, `dc-cr-dwarf-heritage-ancient-blooded`, `dc-cr-clan-dagger` in next cycle

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: The qa-permissions.json gap alone generated 8 avoidable violations in release-b. With 3+ upcoming features shipping new routes this cycle, enforcing the pre-QA checklist at the instruction level should eliminate at least 1 extra fix/rerun loop per feature — cumulative time savings across the queue justify the investment.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T13:19:52-04:00
