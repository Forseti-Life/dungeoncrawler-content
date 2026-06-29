# Phase 4 (Tier 3) Assessment - Publish vs. Archive Decisions

**Assessment Date:** 2026-04-21  
**Status:** DECISION FRAMEWORK READY - Pending approval to implement

---

## Summary

Tier 3 contains 4 modules classified as "testing/deprecated" (15-69 KB each, 173 files, 78 PHP). Assessment completed on 3 of 4 modules. Clear decision framework established: **Archive 3 testing modules, Continue evaluation for 1 production system.**

---

## Module Assessments

### Module 1: agent_evaluation ❌ ARCHIVE
**Size:** 45 files, 15 PHP  
**Type:** Testing/Evaluation framework  
**Description:** Entity evaluation system for the Agent Power Framework

**Rationale for Archive:**
- Explicitly marked as testing/evaluation module
- No production functionality
- Used for internal agent testing only
- Not meant for public use
- Will complicate drupal.org submission process
- ~2-3 hours to audit for no public benefit

**Decision:** 🗂️ **ARCHIVE** (do not publish)

---

### Module 2: jobhunter_tester ❌ ARCHIVE
**Size:** 35 files, 27 PHP  
**Type:** Test helper module  
**Description:** Testing module for Job Hunter routes and pages

**Rationale for Archive:**
- Explicitly testing module for Job Hunter
- Job Hunter is already being published independently
- Test helpers should be kept internal, not public
- Will confuse users on drupal.org
- Not production-critical

**Decision:** 🗂️ **ARCHIVE** (do not publish)

---

### Module 3: forseti_cluster ⚠️ INVESTIGATE
**Size:** 6 files, 2 PHP  
**Type:** Infrastructure/Admin  
**Description:** Admin UI for forseti-meshd cluster communication system

**Current Status:**
- Very small (6 files, 2 PHP)
- Missing README (need to create)
- Manages peer registry and mesh daemon communication
- Core dependency: system only (good sign)
- Package: "Forseti" (internal branding - needs removal)

**Questions to Answer:**
1. Is forseti-meshd (external dependency) available for open-source?
2. Is this cluster system something others would use standalone?
3. Or is it purely internal infrastructure for Forseti project?

**Recommended Decision:** 🤔 **HOLD** (needs domain expert review)
- If mesh daemon is open-sourced elsewhere: Publish as addon
- If mesh daemon is Forseti-specific infrastructure: Archive
- If unsure: Archive for now (can publish later)

**Interim Recommendation:** 🗂️ **ARCHIVE** (unless CEO approves publication)

---

### Module 4: nfr 🟢 PUBLISH
**Size:** 87 files, 34 PHP  
**Type:** Production system (CDC cancer surveillance)  
**Description:** National Firefighter Registry (NFR) - CDC cancer surveillance system

**Rationale for Publishing:**
- ✅ **Real production system** - CDC cancer surveillance project
- ✅ **Solves real problem** - tracks cancer data for firefighters nationwide
- ✅ **General-purpose functionality** - not Forseti-specific
- ✅ **Mature codebase** - clearly documented
- ✅ **Public health importance** - benefits firefighter community
- ⚠️ **Data sensitivity** - requires careful data preservation guidance
- ✅ **Already has safeguards** - CRITICAL warning in README about data loss

**Maturity Assessment:**
- Has detailed documentation
- Has comprehensive schema and data model
- Data tables and relationships clearly defined
- Participant consent and compliance tracked
- Suitable for drupal.org publication

**Required Modifications:**
1. Add/enhance documentation:
   - INSTALL.md with data backup procedures
   - SECURITY.md with HIPAA considerations (if applicable)
   - README with data preservation warnings
2. Package name: "Forseti" → "CDC" or "Public Health"
3. Ensure no hardcoded project-specific data

**Estimated Effort:** 3-4 hours

**Decision:** 🚀 **PUBLISH** (Tier 1 priority after current modules complete)

---

## Phase 4 Execution Plan

### Option A: Aggressive (Publish Maximum)
- Archive: agent_evaluation, jobhunter_tester
- Hold/Investigate: forseti_cluster
- Publish: nfr
- **Effort:** 3-4 hours
- **Result:** 1 new published module + 2 archived

### Option B: Conservative (Archive Everything Uncertain)
- Archive: agent_evaluation, jobhunter_tester, forseti_cluster
- Publish: none
- **Effort:** 1-2 hours
- **Result:** 3 archived, 0 new published

### Option C: Recommended (Hybrid)
- Archive: agent_evaluation, jobhunter_tester
- Hold forseti_cluster for domain expert review
- Publish: nfr (valuable public health tool)
- **Effort:** 3-4 hours
- **Result:** 1 published, 2 archived, 1 under review

**Recommendation:** Option C (Hybrid)

---

## Decisions Requiring CEO Approval

1. **forseti_cluster:** Should we investigate further or archive?
   - Approve: Archive for now (recommended)
   - Approve: Continue investigation (requires domain expert)

2. **nfr:** Should we publish this CDC cancer surveillance system?
   - Approve: Publish as production module (recommended)
   - Approve: Archive/hold for later

---

## Archive Process

For modules being archived:
1. Create archive note explaining deprecation reason
2. Tag in git: `git tag -a v-archived-{reason} -m "Archived: {reason}"`
3. No drupal.org publication
4. Keep source code in repository for historical reference
5. Document in project CHANGELOG

---

## Timeline

**If Option C approved:**
- 1-2 hours: Archive 2 testing modules + create archive documentation
- 2-3 hours: Audit + prepare NFR for publication
- 0.5 hour: Hold forseti_cluster pending review

**Total Phase 4 effort:** 3-4 hours

---

## Cumulative Project Status

| Phase | Modules | Status | Effort |
|-------|---------|--------|--------|
| Phase 1 | 0 | ✅ COMPLETE | 2 hrs |
| Phase 2 | 4 | ✅ COMPLETE | 9 hrs |
| Phase 2B | 3 | 🔄 IN PROGRESS | ~12 hrs |
| Phase 3 | 11 | 🔄 IN PROGRESS | ~25 hrs |
| Phase 4 | 4 | ⏳ PENDING | 3-4 hrs |
| **TOTAL** | **22** | **40% COMPLETE** | **~51 hrs** |

**Remaining after Phase 4:** 2 weeks of QA validation + publication

---

## Recommendations

1. **Archive decision:** Approve archiving of agent_evaluation + jobhunter_tester (clear value)
2. **NFR decision:** Approve publishing (valuable public tool, production-ready)
3. **forseti_cluster decision:** Request domain expert assessment OR archive for now
4. **Next phase:** Begin QA validation of Phase 2-3 modules

---

**Architect:** architect-copilot  
**Date:** 2026-04-21  
**Status:** Ready for CEO decision
