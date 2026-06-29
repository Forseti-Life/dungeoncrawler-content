# Phase 2: Content Extraction - Detailed Execution Plan

**Date Started:** 2026-04-20 20:32 UTC  
**Status:** Planning & Analysis Complete  

---

## Strategy

Given the scale of content (1.2 GB forseti-job-hunter, 821 MB dungeoncrawler-pf2e, etc.), extraction will be done in focused batches using git push operations rather than individual file API calls.

### Approach:
1. Clone each public repo locally
2. Copy source tree from private monorepo  
3. Commit with proper metadata
4. Push to GitHub (one batch per repo)

### Key Decisions:
- **Exclude patterns:** `.git/`, `node_modules/`, `vendor/`, `__pycache__/`, `*.log`, large media files
- **Batch strategy:** Tier 1 → Tier 4 → Tier 2 → Tier 3 (by priority and size)
- **Validation:** Check each repo builds independently after extraction
- **Documentation:** Add README enhancements in each repo post-extraction

---

## Extraction Sequence

### ✅ Analysis Complete
- Source paths verified (all 9 sources found)
- File counts analyzed
- Exclusion patterns defined

### ⏳ Ready to Execute (In order):

**Batch 1: Tier 1 - Core Products** (High Priority)
- [ ] forseti-job-hunter (32,843 → ~15K after filtering)
- [ ] dungeoncrawler-pf2e (30,144 → ~15K after filtering)

**Batch 2: Reference Content** (Independent, Lower Priority)  
- [ ] forseti-docs (241 files)
- [ ] dungeoncrawler-content (placeholder - needs content)
- [ ] forseti-platform-specs (placeholder - needs specs)

**Batch 3: Tier 2 - Libraries** (After Batch 1)
- [ ] forseti-shared-modules (1 file)
- [ ] forseti-mobile (215 files)
- [ ] forseti-meshd (1,366 files)
- [ ] h3-geolocation (150 files)

**Batch 4: Tier 3 - Tooling** (After Batch 2)
- [ ] copilot-hq (34,275 files)
- [ ] forseti-devops (65 files)

---

## Why This Sequence

1. **Tier 1 first:** Core products are highest priority for community
2. **Reference next:** Independent, don't block anything
3. **Libraries:** Depend on what communities request
4. **Tooling:** Most complex, least time-critical

---

## Implementation Constraints

### Size Considerations
- forseti-job-hunter: 1.2 GB (after git cleanup, ~600 MB typical)
- dungeoncrawler-pf2e: 821 MB (after git cleanup, ~400 MB typical)
- copilot-hq: 330 MB

GitHub has no strict repo size limit, but:
- Large repos take longer to clone
- Network bandwidth required
- Git history size grows

### Solution: Shallow Extraction
- Extract current state only (no git history from private repo)
- Create fresh git repo in public space
- This keeps repos lean and fast to clone

### File Count Challenges
- forseti-job-hunter: 32K files (huge node_modules!)
- After excluding patterns: ~15K files (still large)

### Solution: Smart Filtering
- Exclude node_modules/ - rebuild from package.json
- Exclude vendor/ - rebuild from composer.lock
- Exclude __pycache__ - rebuilt automatically
- Exclude .env - use .env.example templates

---

## Post-Extraction Validation Checklist

For each repository:
- [ ] Source code present and readable
- [ ] README updated with current status
- [ ] Build/install instructions work
- [ ] No secrets or credentials exposed
- [ ] Dependencies properly documented
- [ ] Test suite can run (if applicable)
- [ ] Documentation is accurate

---

## Stopping Points & Status Updates

**After Batch 1 (Tier 1):**
- Both core products in GitHub
- Validate builds
- Update status file
- Pause for review

**After Batch 2 (Reference):**
- Tier 4 repos ready
- Documentation hub live
- Status update

**After Batch 3 (Libraries):**
- All dev libraries available
- Library documentation complete
- Status update

**After Batch 4 (Tooling):**
- All repos extracted
- Phase 2 complete
- Move to Phase 3 (CI/CD setup)

---

## Current Status

✅ Analysis complete - all sources ready
⏳ Batch 1 extraction: Ready to execute
⏳ Batch 2-4: Queued

**Next Action:** Execute Batch 1 (Tier 1 core products extraction)

