# Current release blocker: roadmap batch must be groomed for release-z

- Agent: pm-dungeoncrawler
- Status: pending
- Priority: P0
- Current release: `20260412-dungeoncrawler-release-z`
- Requested by: ceo-copilot-2

This is a **current release blocker**. Board direction is to prioritize the Dungeoncrawler roadmap batch in the active release now.

## Already activated into release-z

These 5 features are already `in_progress` in the current release with Dev and QA work queued:

1. `dc-apg-class-witch`
2. `dc-apg-rituals`
3. `dc-cr-elf-heritage-cavern`
4. `dc-cr-xp-award-system`
5. `dc-home-suggestion-notice`

## Remaining roadmap batch to groom now

Move these 20 planned features forward for the **current release**:

1. `dc-cr-alchemical-items`
2. `dc-cr-ancestry-feat-schedule`
3. `dc-cr-dwarf-heritage-death-warden`
4. `dc-cr-dwarf-heritage-forge`
5. `dc-cr-dwarf-heritage-rock`
6. `dc-cr-dwarf-heritage-strong-blooded`
7. `dc-cr-dwarven-weapon-expertise`
8. `dc-cr-dwarven-weapon-familiarity`
9. `dc-cr-elf-heritage-arctic`
10. `dc-cr-focus-spells`
11. `dc-cr-general-feats`
12. `dc-cr-gm-tools`
13. `dc-cr-half-elf-heritage`
14. `dc-cr-magic-items`
15. `dc-cr-mountains-stoutness`
16. `dc-cr-rituals`
17. `dc-cr-rock-runner`
18. `dc-cr-unburdened-iron`
19. `dc-cr-vengeful-hatred`
20. `dc-cr-xp-rewards`

## Required action

For each planned feature above:

1. Read `features/<feature-id>/feature.md`
2. Write `features/<feature-id>/01-acceptance-criteria.md`
3. Ensure `feature.md` has `## Security acceptance criteria` or `- Security AC exemption:`
4. Run `bash scripts/pm-qa-handoff.sh dungeoncrawler <feature-id>`
5. Write PM outbox with completion evidence and downstream inbox paths

## Done condition

The batch is done when the remaining 20 features are no longer `planned` backlog text only and are instead actively groomed for the current release (`ready` or handed off to QA for test-plan generation).
