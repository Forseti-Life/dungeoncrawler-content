Good — `/dungeoncrawler/character/{id}/senses` route does NOT exist yet (the AC specifies a new route). The `CharacterStateService` already populates `state['senses']` from `effects['senses']` — that's the existing path for reading senses on the character state endpoint. Now I have enough context to write the test plan.

- Status: done
- Summary: Created `features/dc-cr-low-light-vision/03-test-plan.md` with 14 TCs covering: unit tests for the LLV boolean flag in character effects (TC-LLV-01/02), dim-light concealment bypass active for LLV characters and baseline concealment intact for normal-vision characters (TC-LLV-03/04), dual-flag resolution where darkvision wins (TC-LLV-05), LLV vs. darkvision distinction (TC-LLV-06), functional tests for persistence across save/reload (TC-LLV-07), ancestry-switch clearing and re-applying the correct sense (TC-LLV-08), non-existent character returning structured 404 not 500 (TC-LLV-09), client-write block (TC-LLV-10), cross-character read blocked (TC-LLV-13), and a Playwright E2E for character-sheet senses display (TC-LLV-14). Two TCs (TC-LLV-11/12) are flagged implementation-dependent pending the new `/dungeoncrawler/character/{id}/senses` route. Flagged three implementation gaps to PM: (1) new `/senses` route doesn't exist yet, (2) `AncestryController` stores vision as a plain string instead of calling `addSense()`, (3) `HexMapController` lighting checks need LLV integration. Ran `qa-pm-testgen-complete.sh`, feature → ready, PM notified. Committed as `7d4d73c62`.

## Next actions
- PM/Dev should decide at Stage 0: implement dedicated `/senses` GET route or expose via existing state endpoint
- Dev must wire `AncestryController` to call `FeatEffectManager::addSense('low-light-vision', ...)` instead of plain string
- Dev must add LLV check to `HexMapController` lighting resolution path
- No further action for this inbox item

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 38
- Rationale: LLV is the elf default sense and gates both the elf ancestry and elf heritage features (dc-cr-elf-ancestry, dc-cr-elf-heritage-cavern) from being fully verified. Three concrete implementation gaps were surfaced that Dev needs to know pre-coding.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-081055-testgen-dc-cr-low-light-vision
- Generated: 2026-04-06T14:04:43+00:00
