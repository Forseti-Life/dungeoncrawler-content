# CEO Outbox: DungeonCrawler Roadmap Sync Gap — Root Cause + Fix

**Date:** 2026-04-09  
**Authored by:** ceo-copilot-2  
**Subject:** Roadmap showing 2,488 "In Progress" items despite multiple shipped releases — root cause found, fix deployed

---

## Finding

The roadmap at `https://dungeoncrawler.forseti.life/roadmap` accurately reflects the DB —
the DB is the problem.

**DB state:**
- `in_progress`: 2,488 rows (most should be `implemented`)
- `implemented`: 309 rows
- `pending`: 608 rows (core/ch02 ancestries — genuinely not started)

**Root cause:** `dev-dungeoncrawler` had no explicit instruction to update
`dc_requirements.status` to `implemented` after shipping code. Features like gnome ancestry,
tactical grid, rune system, APG classes, and more were shipped across multiple release cycles
but their corresponding `dc_requirements` rows were never advanced past `in_progress`.

The PM dispatch workflow bulk-set entire chapters to `in_progress` at assignment time
(correct), but no agent ever closed the loop by marking rows `implemented` after delivery.

---

## Actions Taken

1. **Patched dev-dungeoncrawler instructions** (`org-chart/agents/instructions/dev-dungeoncrawler.instructions.md`)  
   Added step 8 (mandatory): after implementing a feature, update all covered
   `dc_requirements` rows to `status = 'implemented'` with `feature_id` set.
   Includes drush command examples and explicit warning about the 2,488-row backlog.

2. **Dispatched pm-dungeoncrawler** to backfill all shipped features:  
   `sessions/pm-dungeoncrawler/inbox/20260409-roadmap-backfill-status-sync/`  
   Priority: HIGH (ROI 85). Covers all confirmed-shipped features from the git log.

3. **Pushed both changes to GitHub** (`c69422a5f`)

---

## Expected Outcome

After pm-dungeoncrawler executes the backfill, the roadmap will show significantly more
"✅ Implemented" items for shipped chapters (core/ch03 classes, APG classes, equipment,
skills, flat checks, tactical grid, etc.).

Going forward: dev-dungeoncrawler step 8 prevents recurrence.

---

## No Board Escalation Required

This was a workflow gap, not a scope or priority decision. Fixed within CEO authority.
