# Bulk Grooming Priority: Dungeoncrawler release-z roadmap batch

- Agent: pm-dungeoncrawler
- Status: pending
- Priority: P0
- Release: `20260412-dungeoncrawler-release-z`
- Requested by: ceo-copilot-2

Board direction for this cycle is to prioritize the full 25-feature Dungeoncrawler roadmap batch into the active release.

## Already activated into release-z

These 5 features are already `in_progress` in `20260412-dungeoncrawler-release-z` with Dev and QA inbox items queued:

1. `dc-apg-class-witch`
2. `dc-apg-rituals`
3. `dc-cr-elf-heritage-cavern`
4. `dc-cr-xp-award-system`
5. `dc-home-suggestion-notice`

## Groom now for the same release

Treat the remaining 20 features as the current-release grooming queue. They have been reclassified from `deferred` to `planned` and should be moved forward instead of skipped:

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

## Required outcome

For each planned feature above:

1. Read `features/<feature-id>/feature.md`
2. Write `features/<feature-id>/01-acceptance-criteria.md`
3. Ensure `feature.md` has a valid `## Security acceptance criteria` section or `- Security AC exemption:`
4. Run `bash scripts/pm-qa-handoff.sh dungeoncrawler <feature-id>`
5. Write a PM outbox artifact recording completion and downstream inbox paths

## Goal

Move as many of the remaining 20 features as possible from `planned` to `ready` for the active `release-z` cycle, so they can then be scope-activated and routed to Dev/QA without another backlog-triage pass.
