The improvement is clear: add a `Depends on` field to the feature stub template and add a `depends_on` column to the index. This lets PM/Dev see the implementation dependency graph without reading prose. I'll implement this now by updating the template in the feature index header and adding depends-on lines to the stubs that already have implicit dependencies.

The right improvement here is to add a `Depends on` field to the feature stub template (for future stubs) and update the seat instructions to mandate filling it during scan. This is minimal, surgical, and avoids retroactive edits to all 33 stubs (which would require reading each one carefully — better left to PM/Dev triage). I'll implement the template addition and seat instructions update.

Now add the `Depends on` field to the three stubs that already have documented dependencies, and add a `Depends on` column to the index:

The `Depends on` field needs the `Category` and `Created` lines. The existing file seems to be missing them in its frontmatter block. Let me check what's actually in the file:

Good. Now add to the other two stubs:

The Category and Created fields got removed. Let me fix that:

Now update the crafting stub to restore Category and Created:

The crafting stub has a corrupted Created line — the source text got merged into it. Let me fix that:

Now add `Depends on` column to the feature index for the 3 affected stubs:

Now update each row in the index to add the Depends on column. First let me see all rows:

Now I need to add the `Depends on` column to every row. Only 3 rows have dependencies; the rest get an empty value:

Now update seat instructions to mandate the `Depends on` column in the index update:

Now commit and write the outbox:

- Status: done
- Summary: Added a `Depends on` field to the feature stub template and the feature index, making implementation dependencies machine-readable without requiring Dev to read prose. The improvement was implemented by (1) updating the stub frontmatter template in seat instructions to include an optional `Depends on` line, (2) backfilling the three stubs whose implementation hints already documented dependencies (dc-cr-crafting, dc-cr-gm-narrative-engine, dc-cr-xp-rewards), (3) adding a `Depends on` column to all 33 rows in `dc-feature-index.md`, and (4) updating the post-scan checklist in seat instructions to require populating the new column. Committed at `c2bfd86`.

## Next actions
- Next scan: resume PF2E Core Rulebook at line 601 (Chapter 1 continues: ability scores, ability modifiers, proficiency system, hero points, traits). 23 feature slots remain in release `20260227-dungeoncrawler-release-b`.
- PM triage: `dc-feature-index.md` now has a `Depends on` column — recommend PM use it to sequence triage passes (foundation features first).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 6
- Rationale: The dependency field costs ~5 minutes per scan chunk to maintain and directly reduces Dev's stub-reading time when sequencing implementation. It also enables PM to triage foundation features (no deps) ahead of dependent ones — a multiplier on the value of the 33 stubs already created.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260227-improvement-round-20260226-dungeoncrawler-release-c
- Generated: 2026-02-27T09:29:39-05:00
