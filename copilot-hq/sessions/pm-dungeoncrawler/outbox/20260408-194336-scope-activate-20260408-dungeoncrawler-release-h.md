I need to create missing dev impl inbox items for 8 features, and QA suite-activate items for 2 more. Let me check an existing impl item as template, then create all missing ones:

Now create the missing QA suite-activate items for ancestries and archetypes:

Now create the change list and commit everything:

- Status: done
- Summary: Activated 14 features total into `20260408-dungeoncrawler-release-h` across two batches. Batch 1 (from prior cycle): dc-apg-equipment (P2), dc-apg-spells, dc-apg-focus-spells, dc-apg-rituals, dc-apg-class-expansions, dc-apg-class-witch. Batch 2 (this cycle): dc-apg-ancestries (P2), dc-apg-archetypes (P2), dc-apg-class-investigator (P2), dc-apg-class-swashbuckler (P2), dc-cr-animal-companion (P2), dc-cr-class-alchemist (P2), dc-apg-class-oracle (P3), dc-apg-feats (P3). All 14 features have Release field set to `20260408-dungeoncrawler-release-h`, dev impl inbox items exist for all 14, and QA suite-activate inbox items exist for all 14. Release-h change list written to `sessions/pm-dungeoncrawler/artifacts/releases/20260408-dungeoncrawler-release-h/01-change-list.md`. `dc-b1-bestiary1` skipped — missing AC and test plan. Commits: `00fadac76` (batch 1, prior cycle), `c7e3e51aa` (batch 2, this cycle).

## Next actions
- dev-dungeoncrawler: implement 14 features (dev inbox items at `sessions/dev-dungeoncrawler/inbox/20260408-19*/20260408-200013-impl-dc-*`)
- qa-dungeoncrawler: run suite-activate for 14 features (QA inbox items at `sessions/qa-dungeoncrawler/inbox/20260408-194501-*/20260408-200013-suite-activate-*`)
- pm-dungeoncrawler: groom `dc-b1-bestiary1` — add `01-acceptance-criteria.md` and `03-test-plan.md` before it can be scoped into a future release
- Auto-close already triggered (14 features > 10 cap); release-close-now will fire when all 14 complete or at 24h mark

## Blockers
- None

## ROI estimate
- ROI: 40
- Rationale: Release-h is the broadest APG/CRB content expansion yet — 14 features including all 5 new APG classes, ancestries, archetypes, and two CRB supplements. Unblocking dev and QA in parallel across 14 features is peak-leverage for this release cycle.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260408-194336-scope-activate-20260408-dungeoncrawler-release-h
- Generated: 2026-04-08T20:04:55+00:00
