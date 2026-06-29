Good. The heritage POST route `/dungeoncrawler/character/{id}/heritage` is not yet in routing — it's a new route to implement. The existing heritage flow is embedded in the character creation form. Let me write the test plan now.

- Status: done
- Summary: Created `features/dc-cr-elf-heritage-cavern/03-test-plan.md` with 13 TCs covering: unit test for Cavern Elf heritage catalog entry restricted to Elf only, functional tests for the low-light→darkvision sense swap at creation time and across save/reload, ancestry restriction and one-heritage-per-character enforcement, cross-character ownership guard, an invalid-heritage failure-mode TC, ACL (anon blocked on character create form), and a Playwright E2E creation flow. Two TCs (TC-EC-08 heritage removal revert, TC-EC-09 anon POST block) are flagged implementation-dependent pending PM confirmation that the dedicated POST `/dungeoncrawler/character/{id}/heritage` route will be implemented — this route does not currently exist in routing. Also flagged to PM: `FeatEffectManager` has no `cavern` case yet and a `removeSense()` helper may need to be added. Ran `qa-pm-testgen-complete.sh`, feature → ready, PM notified. Committed as `98cd9fba5`.

## Next actions
- PM should confirm whether the `/dungeoncrawler/character/{id}/heritage` POST route is in scope for this feature (unlocks TC-EC-08 and TC-EC-09)
- Dev should note the `FeatEffectManager` cavern case gap and the potential need for a `removeSense()` helper
- No further action for this inbox item

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Cavern Elf is a core PF2E heritage option (one of 4 elf heritages) and the most mechanically complex because it swaps senses rather than adding them. The implementation gap notes (no FeatEffectManager case, no removeSense helper) add real risk if not surfaced pre-implementation.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-081055-testgen-dc-cr-elf-heritage-cavern
- Generated: 2026-04-06T13:57:22+00:00
