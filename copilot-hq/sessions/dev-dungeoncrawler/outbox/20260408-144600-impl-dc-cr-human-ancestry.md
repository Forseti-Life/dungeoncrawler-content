Now I have a clear picture. Let me implement the gaps:

1. Add `skilled`, `half-elf`, `half-orc` heritages to `HERITAGES['Human']`
2. Add `special` metadata (extra skill, extra skill feat, bonus language slots) to `ANCESTRIES['Human']`
3. Fix `adapted-cantrip` prerequisites
4. Expose `special` in `AncestryController::buildAncestryItem()`
5. Update the tree comment

Now let's look at the current HERITAGES['Human'] section to add the missing heritages:

Now fix the `adapted-cantrip` prerequisites:

Now update the tree comment near the top of the HERITAGES constant to reflect the 4 Human heritages:

Now update `AncestryController::buildAncestryItem()` to expose the `special` field:

Now run `drush cr` and verify:

All verified. Commit:

- Status: done
- Summary: Implemented `dc-cr-human-ancestry` by adding the three missing Human heritages (`skilled`, `half-elf`, `half-orc`) to `HERITAGES['Human']` — each with correct metadata (vision_override, cross_ancestry_feat_pool, extra skill grants). Added `special` key to `ANCESTRIES['Human']` capturing the extra trained skill, extra skill feat, and bonus language per Int modifier rules. Fixed `adapted-cantrip` prerequisites from empty to `Spellcasting class feature`. Exposed `special` in `AncestryController::buildAncestryItem()`. All changes verified via `drush php:eval`. Committed `ad583e8da`.

## Next actions
- Notify `qa-dungeoncrawler` to run TC-HUM-01 through TC-HUM-18 against `ad583e8da`
- Note for QA: `vision_override` and `cross_ancestry_feat_pool` are data-layer fields; the character-sheet renderer will need to read them to apply low-light vision when half-elf/half-orc heritage is selected — if TC-HUM-14/15 require live vision application during character creation, a follow-on dev ticket is needed

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: Human is the most commonly played PF2e ancestry; correctly exposing all 4 heritages and bonus rules unblocks QA gate and closes the feature for release. Missing heritages would have caused failed AC checks that QA would have bounced back.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-144600-impl-dc-cr-human-ancestry
- Generated: 2026-04-08T15:02:20+00:00
