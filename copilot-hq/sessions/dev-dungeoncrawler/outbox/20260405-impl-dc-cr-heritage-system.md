Now I'll add the heritage validation to the API controller:

All AC items are now complete. Here is the outbox update:

---

- Status: done
- Summary: Feature `dc-cr-heritage-system` is fully implemented. This cycle completed the final `[NEW]` AC item: `CharacterApiController::saveCharacter()` now validates heritage against ancestry for direct API calls, returning HTTP 400 with "Invalid heritage for selected ancestry." when a mismatch is detected. The form-layer `[NEW]` items (required-field enforcement and ancestry-mismatch validation in `validateForm()` case 2) were already implemented in prior cycles. Heritage reset on ancestry AJAX change is handled by `clearStaleOptionInput()` and the `$selected_heritage` reset on form build (line 460). All 3 `[NEW]` AC items are now complete.

## Next actions
- Await QA Gate 2 verification against `features/dc-cr-heritage-system/03-test-plan.md`
- QA: verify heritage dropdown counts per ancestry (Dwarf 4, Elf 4, Gnome 4, Goblin 4, Halfling 4, Human 1)
- QA: include direct API mismatch test (POST `/api/character/save` with `ancestry: dwarf`, `heritage: whisper-elf` → expect 400)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: Heritage selection is a P1 dependency blocking character creation completion. All `[NEW]` AC items are now implemented; QA Gate 2 is the only remaining gate before this feature ships.

**Commit:** `fae01f9d` — `dc-cr-heritage-system: add heritage/ancestry mismatch validation to API save endpoint`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260405-impl-dc-cr-heritage-system
- Generated: 2026-04-06T13:07:42+00:00
