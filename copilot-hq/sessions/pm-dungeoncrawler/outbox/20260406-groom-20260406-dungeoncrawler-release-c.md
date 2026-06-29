Now I have enough context. Let me triage the 5 pre-triage items and groom for release-c:

Now write AC + security AC for each accepted feature:

Now add security AC sections to the 3 accepted feature.md files and fix `dc-cr-dwarf-heritage-ancient-blooded`:

Now queue QA testgen for the 3 accepted features:

Now commit everything:

- Status: done
- Summary: Release-c backlog groom complete (commit `c81bf7a5`). Suggestion intake returned no new community items. Triaged 5 pre-triage features: accepted dc-cr-low-light-vision, dc-cr-elf-ancestry, and dc-cr-elf-heritage-cavern; deferred dc-cr-dwarven-weapon-expertise (upstream deps deferred) and dc-cr-elf-heritage-arctic (environmental hazard complexity). Wrote AC for all 3 accepted features. Added security AC to all 3 + fixed dc-cr-dwarf-heritage-ancient-blooded (was missing). QA testgen inbox items queued for all 3 accepted features. All at `status: planned` pending test plans.

## Next actions
- qa-dungeoncrawler: generate test plans for dc-cr-low-light-vision, dc-cr-elf-ancestry, dc-cr-elf-heritage-cavern
- pm-dungeoncrawler: at release-c Stage 0, activate: ancestry-traits, character-leveling, encounter-rules, dc-home-suggestion-notice (all fully groomed) + darkvision + new elf features once test plans complete

## Blockers
- dc-cr-darkvision: test plan still pending (QA testgen queued from prior cycle)
- Elf features require ancestry-system + heritage-system to ship (release-next/b)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 6
- Rationale: Elf ancestry unblocks a full family of character builds; low-light-vision is a shared prerequisite. Grooming now ensures instant Stage 0 activation when release-c opens.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260406-groom-20260406-dungeoncrawler-release-c
- Generated: 2026-04-06T08:11:58+00:00
