# Runbook: DungeonCrawler Roadmap Requirements Audit

**Owned by**: `pm-dungeoncrawler`  
**Supervised by**: CEO  
**Triggered by**: CEO or Board request, or post-release improvement round  

---

## Purpose

This runbook defines the process for systematically auditing ALL requirements on
`https://dungeoncrawler.forseti.life/roadmap`, ensuring none remain at "Not Started"
(i.e., `pending` in the DB) without an active plan.

Every requirement must end up in one of two states:
- **`implemented`** — code exists and QA has verified it; `dc_requirements.status = 'implemented'`
- **In the feature pipeline** — a `features/dc-*/` feature file exists AND `dc_requirements.feature_id` is set to that feature's work-item-id

> ⚠️ **Coverage is only machine-verifiable if `feature_id` is set in the DB.** Without it, "in the
> pipeline" is PM assertion only and cannot be queried. See the open dev task:
> `dc_requirements` needs a `feature_id VARCHAR(64)` column — once added, the completion check
> becomes: `SELECT * FROM dc_requirements WHERE status='pending' AND feature_id IS NULL`
> Until that column exists, the SQL audit tracker (below) is the only per-cycle coverage record.

---

## Why Not One-at-a-Time from the Web Page

The roadmap webpage is the *display* layer. The canonical source of truth is the
`dc_requirements` table in the `dungeoncrawler` MySQL database.

```sql
-- Total requirements by status
SELECT status, COUNT(*) FROM dc_requirements GROUP BY status;

-- Pending work, grouped by book and chapter (your audit work queue)
SELECT book_id, chapter_key, MIN(chapter_title) as title,
       COUNT(*) as total, SUM(status='pending') as pending
FROM dc_requirements
GROUP BY book_id, chapter_key
ORDER BY book_id, chapter_key;
```

**Current totals (2026-04-07 baseline — updated post-audit-pass3):**

> ⚠️ These numbers go stale. Always re-query before starting an audit cycle:
> `sudo mysql dungeoncrawler -e "SELECT status, COUNT(*) FROM dc_requirements GROUP BY status;"`

| book | chapter | pending | implemented |
|------|---------|---------|-------------|
| core | ch03 (Classes) | 904 | 3 |
| core | ch04 (Skills) | 188 | 10 |
| core | ch05 (Feats) | 24 | 0 |
| core | ch06 (Equipment) | 161 | 0 |
| core | ch07 (Spells) | 135 | 0 |
| core | ch09 (Playing the Game) | 1 | 241 |
| core | ch10 (Game Mastering) | 80 | 7 |
| core | ch11 (Crafting & Treasure) | 154 | 0 |
| apg | ch01–ch06 | 595 | 0 |
| gmg | ch01–ch04 | 150 | 0 |
| gng | ch01–ch05 | 30 | 0 |
| som | ch01–ch05 | 30 | 0 |
| gam | s01–s06 | 36 | 0 |
| b1–b3 | all | 54 | 0 |
| **TOTAL** | | **~2536** | **261** |

---

## Working Discipline: One Feature at a Time

**Do not attempt to batch or parallelize audit work.** Each section must be fully resolved
before moving to the next. "Resolved" means one of:
- Audit tracker row updated to `implemented` or `covered` with `feature_id` set
- A blocker recorded with an explicit reason and a follow-up item dispatched

**Why:** Bulk passes look complete but leave unresolved handoffs, missing feature_ids, and
dependency gaps that are invisible until the next audit cycle. Serial work produces a complete,
verifiable artifact row by row.

**Working order within a chapter:**
1. Query the DB for all sections in the chapter (use the Bulk Status Query below)
2. Load them into the SQL audit tracker as `pending` rows
3. Work top-to-bottom through the tracker — one row at a time
4. Do not start a new row until the current row's status is updated in the tracker
5. At natural break points (end of chapter, end of session), commit the tracker artifact

---

## Dependency Mapping

Feature dependencies must be tracked whenever they are discovered — not deferred to later.
The canonical dependency map is **`features/dc-feature-index.md`** (the `Depends on` column).

**When a dependency is identified during audit:**
1. Add `- Depends on: <feature-id>, <feature-id>` to the feature stub's header fields
2. Update the feature index `Depends on` column for that row
3. If the blocking feature does not yet have a stub, create it first (or note it as a blocker
   in the audit tracker with `status = blocked` and `notes = "blocked on <feature-id>"`)

**Dependency detection triggers:**
- Track B Step 1: when checking if an existing feature covers this section, also note what
  that feature depends on — if its dependencies are unplanned, create stubs for them now
- Track B Step 3: when creating a new stub, ask: "can this be built without any other feature
  being live first?" If not, identify and record the blocking features
- Common dependency patterns in DungeonCrawler:
  - Any class feature → depends on `dc-cr-character-class`, `dc-cr-character-leveling`
  - Any heritage/ancestry feat → depends on the ancestry feature + `dc-cr-ancestry-feat-schedule`
  - Any spellcasting feature → depends on `dc-cr-spellcasting`
  - Any equipment-based feature → depends on `dc-cr-equipment-system`
  - Any skill action → depends on `dc-cr-skill-system`

**Dependency status values in audit tracker:**
- `covered` — feature has a stub and dependencies are mapped
- `blocked` — feature cannot be stubbed yet; blocking feature identified in `notes`

The feature index is maintained by `ba-dungeoncrawler` but PM must flag any new dependencies
discovered during audit for BA to formally record at the end of the cycle.

---

### Track A — Code Verification (QA-first)
**Use when**: The relevant engine/service is known to be at least partially implemented.

Examples: `core/ch09` (combat rules — CombatEngine, HPManager, etc.),
`core/ch03` individual classes where a class-specific service may exist.

**How to determine Track A vs B:** Default to Track B unless you have direct knowledge (from prior
release work, a BA service map, or the PM Context table below) that a relevant service exists.
When uncertain, dispatch a BA service-discovery item first:
`"Does a service exist in dungeoncrawler_content/src/Service/ for <chapter topic>? List service names."`
BA returns yes/no → proceed with Track A or B accordingly.

**Steps:**
1. Confirm the relevant service(s) exist in
   `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/`
2. Dispatch a QA inbox item (see **QA Batch Format** below)
3. QA returns PASS or BLOCK per requirement group
4. **PASS** → QA outbox includes the PM handoff line (see QA Batch Format); PM runs drush and confirms.
5. **BLOCK** → find or create `features/dc-*/` file (with `DB sections` field set), record in audit tracker as `covered` with `feature_id` set, dispatch dev inbox item

**Track A → Track B transition (partial chapters):**
For chapters where some reqs are implemented and others are not (e.g., `core/ch04` Skills,
`core/ch10` GM tools): run Track A first to identify and mark all already-implemented reqs,
then treat remaining BLOCK items as Track B work — create/link feature files for each gap
and dispatch dev items. Do not batch the whole chapter as Track B just because it has gaps.

### Track B — Feature Pipeline (Dev-first)
**Use when**: No relevant service exists for the chapter's content.

Examples: `apg/ch02` (APG classes — no class-specific services exist),
`gmg/ch01` (GM tools — no GM narrative pipeline implemented),
`b1/s02` (Bestiary monsters — no monster AI implemented).

**Steps:**
1. Check if a `features/dc-*` file already covers this area
2. If a feature exists → **before** marking it covered, verify it has all required fields.
   Many pre-existing stubs are missing `Work item id`, `DB sections`, and `Depends on`.
   If any required field is missing, add them now (same commit as the audit tracker update).
   Then record in the audit tracker: `status = covered`, `feature_id = <work-item-id>`.
3. If no feature exists → create `features/dc-*/feature.md` stub (PM-owned, PM creates)

   **Required fields for a new feature stub:**
   ```
   - Work item id: dc-<product>-<short-name>
   - Website: dungeoncrawler
   - Module: dungeoncrawler_content
   - Status: planned          # planned | ready | in_progress | done | deferred
   - Priority: P<N>
   - PM owner: pm-dungeoncrawler
   - Dev owner: dev-dungeoncrawler
   - QA owner: qa-dungeoncrawler
   - Source: <PF2E book reference>
   - Category: game-mechanic  # or: ui | content | infra
   - Created: <YYYY-MM-DD>
   - DB sections: core/ch03/Alchemist, core/ch03/Alchemist-Feats  # book/chapter/section rows this covers
   - Depends on:              # comma-separated feature ids; blank if none
   ```
   See any existing `features/dc-*/feature.md` for a complete example.
   The `DB sections` field is required — it is the only record linking this feature to specific
   `dc_requirements` rows until a `feature_id` column is added to the DB.

4. Dispatch BA inbox item for requirements analysis + acceptance criteria
5. Dispatch dev inbox item after BA delivers AC + test plan
6. **After dev ships and QA approves:** QA outbox must include a PM handoff line:
   `PM action required: run drush roadmap-set-status implemented --book=X --chapter=Y --section="Z"`
   PM creates a self-inbox item, runs drush, confirms in outbox. PM owns this step — do not leave it
   to dev or QA to assume PM will notice.

---

## QA Batch Format (Track A)

Dispatch to `sessions/qa-dungeoncrawler/inbox/<date>-roadmap-req-<book>-<chapter>-<section>`:

```markdown
- command: |
    Roadmap requirements verification: <book> <chapter> — <section>
    
    Verify requirements <req_id_start>–<req_id_end> for section "<section>"
    against the production codebase at https://dungeoncrawler.forseti.life
    
    For each requirement:
    - Locate the implementing service/method in the codebase
    - Run a drush probe to verify it works at runtime
    - Return PASS (with service name + method) or BLOCK (with gap ID + description)
    
    Rulebook reference: /home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/<file>.md
    (To find the right file: ls the references/ directory and match by book/chapter name.)
    
    Your outbox must end with one of these two blocks — no exceptions:
    
    ALL PASS:
    ---
    PM action required: run drush roadmap-set-status implemented \
      --book=<book> --chapter=<chapter> --section="<section>"
    ---
    
    ANY BLOCK:
    ---
    PM action required: create feature pipeline item for gap <gap-id> covering <section>
    Gap description: <what is missing>
    ---
- Agent: qa-dungeoncrawler
- Status: pending
- roi: <N>
```

**Batch sizing guideline:**
- Group by `section` within a chapter (natural boundary)
- Aim for 10–30 requirements per batch (QA's sweet spot for one outbox response)
- Core ch03 classes: one batch per class (Alchemist ~115 reqs → split into sub-sections)

---

## Bulk Status Query (to find what's pending per section)

```bash
sudo mysql dungeoncrawler -e "
SELECT book_id, chapter_key, section, COUNT(*) as reqs
FROM dc_requirements
WHERE status='pending' AND book_id='core' AND chapter_key='ch03'
GROUP BY section ORDER BY section;
"
```

---

## Drush Update Commands (after QA PASS)

> **Path note:** `/var/www/html/dungeoncrawler` is the deployed production Drupal root
> (where drush commands should run to affect the live DB). The development source is at
> `/home/ubuntu/forseti.life/sites/dungeoncrawler/`. Use the production path for all
> `roadmap-set-status` drush commands; use the source path for code reading/editing.

```bash
cd /var/www/html/dungeoncrawler

# Always dry-run first
./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life \
  dungeoncrawler:roadmap-set-status implemented \
  --book=core --chapter=ch03 --section="Alchemist" --dry-run

# Apply
./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life \
  dungeoncrawler:roadmap-set-status implemented \
  --book=core --chapter=ch03 --section="Alchemist"
```

---

## SQL Audit Tracker (mandatory — must be committed as artifact)

**This is not optional.** Every audit cycle must produce a committed coverage artifact so the
next cycle can verify what was claimed, not re-scan from scratch.

**At cycle start**, create and populate the tracker:

```sql
CREATE TABLE roadmap_audit (
    id TEXT PRIMARY KEY,  -- e.g. "core-ch03-Alchemist"
    book_id TEXT,
    chapter_key TEXT,
    section TEXT,
    req_count INTEGER,
    track TEXT,           -- 'A' (QA-first) or 'B' (feature-pipeline)
    status TEXT DEFAULT 'pending',  -- pending / qa_dispatched / qa_done / dev_dispatched / implemented / covered / blocked
    feature_id TEXT,      -- dc-cr-* feature file if Track B (required when status=covered)
    notes TEXT
);
```

Load the queue:
```bash
sudo mysql dungeoncrawler -e \
  "SELECT book_id, chapter_key, section, COUNT(*) FROM dc_requirements WHERE status='pending' GROUP BY book_id, chapter_key, section ORDER BY book_id, chapter_key, section;" \
  > /tmp/pending_reqs.txt
```

**At cycle end**, export and commit the tracker. The session SQL tool does not write files
directly — query it and paste results into a file manually, or use the bash tool to dump:
```bash
# The session SQL tracker lives in the Copilot session DB; export via the SQL tool:
# Run: SELECT * FROM roadmap_audit ORDER BY book_id, chapter_key, section;
# Copy output to:
mkdir -p /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/artifacts
# Save as: sessions/pm-dungeoncrawler/artifacts/roadmap-audit-<YYYYMMDD>.tsv

cd /home/ubuntu/forseti.life
git add -f copilot-hq/sessions/pm-dungeoncrawler/artifacts/roadmap-audit-<YYYYMMDD>.tsv
git commit -m "audit: roadmap coverage artifact <YYYYMMDD>"
```

The committed artifact is the **evidence of coverage** for every section claimed as "in the pipeline."
Without it, coverage claims are unverifiable.

---

## Completion Criteria

### Per-section complete
A section is fully audited when every `dc_requirements` row for it is in one of these states:
- `status = 'implemented'` — QA-verified in code
- `status = 'pending'` AND the audit tracker has `status = 'covered'` with `feature_id` set — planned

To check whether any rows in a section are neither implemented nor covered in the tracker:
```sql
-- dc_requirements side: any still pending?
SELECT COUNT(*) FROM dc_requirements
WHERE book_id='core' AND chapter_key='ch03' AND section='Alchemist' AND status='pending';
```
A non-zero result is **acceptable** only when the audit tracker shows all of those rows as
`covered` with a valid `feature_id`. Zero means fully implemented — done.
A non-zero result with no tracker entry is an **open gap**.

### Whole-roadmap audit complete
The full roadmap audit is complete when **every section** satisfies the per-section criteria above.

**Once `feature_id` column exists in `dc_requirements`** (open dev task), the definitive check is:
```sql
-- Returns 0 rows when all pending reqs have a feature mapped
SELECT book_id, chapter_key, section, COUNT(*) as uncovered
FROM dc_requirements
WHERE status = 'pending' AND (feature_id IS NULL OR feature_id = '')
GROUP BY book_id, chapter_key, section
ORDER BY book_id, chapter_key;
```

**Until `feature_id` column is added**, coverage must be verified against the committed audit
tracker artifact (`sessions/pm-dungeoncrawler/artifacts/roadmap-audit-<date>.tsv`):
- Every row must have `status = implemented` OR (`status = covered` AND `feature_id` is set)
- Any row with `status = pending` at cycle end is an uncovered gap

The PM outbox for the audit cycle must record:
- Total implemented count (from DB)
- Total pending count (from DB)
- Count of sections mapped to feature files (from audit tracker)
- Any explicitly deferred sections (with Board/CEO decision reference)
- Link to committed audit tracker artifact

### Audit cycle triggers
A new audit pass should be run when any of the following occur:
- A release cycle closes (post-release improvement round)
- A new book's requirements are imported into `dc_requirements`
- The CEO requests a coverage gap check
- QA auto-site audit returns new BLOCK findings with no mapped feature file

---

## PM Context: What Each Book Covers

| Book | Relevance to DungeonCrawler | Track default |
|------|-----------------------------|--------------|
| core/ch03 | All 12 core classes — no class-specific services exist yet | B (per class) |
| core/ch04 | Skills — `SkillSystem` partially implemented | A then B for gaps |
| core/ch05 | General feats — no feat engine yet | B |
| core/ch06 | Equipment — `InventoryManagementService` partial | A then B for gaps |
| core/ch07 | Spells — no spellcasting service | B |
| core/ch09 | Combat/encounter rules — CombatEngine ~95% | A (1 req remaining: REQ 2093) |
| core/ch10 | GM tools — `AiGmService` partial | A then B |
| core/ch11 | Crafting/treasure — no crafting service | B |
| apg | APG classes/archetypes/spells — no APG-specific services | B |
| gmg | GM mastery tools — GMG narrative not implemented | B |
| gng/som/gam | Guns, magic, gods — not in current roadmap scope | B (deferred) |
| b1–b3 | Bestiaries — no monster AI/stat block engine | B (deferred) |

---

---

## Release Queue Process (Post-Audit)

After the audit is complete (all sections mapped to feature stubs), the ongoing CEO/PM
task is to keep the **release ready pool** stocked. This process runs continuously —
pick one feature at a time, never batch.

### How to queue the next roadmap feature

**Entry point:** `https://dungeoncrawler.forseti.life/Roadmap`

1. **Find the first ❌ Not Started item** on the roadmap page (reading top-to-bottom).
   This is the highest-priority unstarted requirement.

2. **Identify the feature stub** that covers it:
   - Check the audit tracker: `SELECT feature_id FROM roadmap_audit WHERE section = '<section>';`
   - Or grep: `grep -r "DB sections:.*<section>" features/dc-*/feature.md`

3. **Check grooming status** of the feature stub:
   ```bash
   ls features/<feature-id>/
   # Need: feature.md + 01-acceptance-criteria.md + 03-test-plan.md
   grep "^- Status:" features/<feature-id>/feature.md
   ```

4. **Dispatch based on status** — roadmap features follow the same suggestion process flow:

   | Feature status | Artifacts present | Action |
   |---|---|---|
   | `planned` | none | PM inbox: write AC, then run `pm-qa-handoff.sh` |
   | `planned` | AC only | Run: `bash scripts/pm-qa-handoff.sh dungeoncrawler <feature-id>` |
   | `ready` | AC + test plan | Run: `bash scripts/pm-scope-activate.sh dungeoncrawler <feature-id>` |
   | `in_progress` | all | Already in release — move to next item |
   | `deferred` | varies | Note dependency blocker; skip to next Not Started item |

   **The pipeline is identical to the community suggestion flow:**
   ```
   feature.md (accepted) → PM writes AC → pm-qa-handoff.sh → QA writes test plan
     → qa-pm-testgen-complete.sh → pm-scope-activate.sh → in_progress
   ```
   Roadmap features are pre-accepted (the feature stub = the triage decision). Skip
   `suggestion-intake.sh` / `suggestion-triage.sh` — go straight to AC.

5. **PM inbox task format** (when feature is `planned` with no AC):
   ```
   sessions/pm-dungeoncrawler/inbox/<date>-groom-<feature-id>
   ```
   Contents must instruct PM to:
   - Read the feature brief + live roadmap requirements for the section
   - Write `features/<feature-id>/01-acceptance-criteria.md`
   - Ensure `## Security acceptance criteria` is in `feature.md`
   - Run `bash scripts/pm-qa-handoff.sh dungeoncrawler <feature-id>`
   - Write outbox confirming completion

6. **Commit and push** the new inbox item (sessions/** requires `git add -f`).

### Working discipline (same as audit)
- One feature at a time. Dispatch the groom task, commit, then move to the next.
- Do not pre-groom multiple features speculatively.
- Dependencies: if the feature is blocked (dep not yet `done`), skip to the next Not
  Started item that has all deps satisfied.

### Dependency check before dispatching
Before dispatching, verify deps are done:
```bash
grep "^- Depends on:" features/<feature-id>/feature.md
# Then for each dep: grep "^- Status:" features/<dep-id>/feature.md
```
If a dep is not `done` or `shipped`, note the blocker in the groom task and still
dispatch — PM can groom now and scope-activate later when deps ship.

---

## Related runbooks and references
- `runbooks/pf2e-requirements-extraction.md` — how BA extracts requirements from source books
- `runbooks/intake-to-qa-handoff.md` — feature pipeline (Track B) detail
- `runbooks/shipping-gates.md` — Gate 2 QA evidence requirements
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — Roadmap maintenance section
