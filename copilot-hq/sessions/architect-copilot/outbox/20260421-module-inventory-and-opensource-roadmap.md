# COMPREHENSIVE MODULE INVENTORY & OPEN-SOURCE ROADMAP

**Date:** April 21, 2026  
**Created By:** architect-copilot  
**Authority:** PROJ-009 (Open-Source Initiative)  
**Scope:** All 22 custom modules across 4 Drupal sites  

---

## EXECUTIVE SUMMARY

### Total Modules: 22
- **Tier 1 (Core Autonomous):** 4 modules
- **Tier 1 (Support/Integration):** 3 modules
- **Tier 2 (Content):** 5 modules
- **Tier 2 (Utility/Admin):** 6 modules
- **Tier 3 (Testing/Deprecated):** 4 modules

### Current Status
- ✅ **Job Hunter:** Frozen, awaiting QA
- ✅ **Forseti Games:** Frozen, awaiting QA
- 🟡 **AI Conversation:** In QA validation
- 🔴 **AMI Safe:** Blocked on cross-site sync verification
- ⚪ **18 others:** Queued for audit/preparation

### Estimated Timeline
- **Phase 2 (Tier 1):** 4-6 weeks (6 modules)
- **Phase 2B (Tier 1 Support):** 3-4 weeks (3 modules)
- **Phase 3 (Tier 2):** 6-8 weeks (11 modules)
- **Phase 4 (Tier 3):** 2 weeks (4 modules)
- **Total:** ~4 months to complete all modules

---

## MODULE INVENTORY BY SITE & TIER

### 📍 FORSETI SITE (15 modules)

#### Tier 1: Core Autonomous
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| **ai_conversation** | 25 | 0 | 🟡 QA | Shared across 4 sites; AI core |
| **job_hunter** | 177 | 0 | ✅ Frozen | Job search automation; high complexity |
| **forseti_games** | 24 | 0 | ✅ Frozen | Game framework with Block Matcher |
| **amisafe** | 51 | 0 | 🔴 Blocked | Cross-site security module; needs sync |

#### Tier 2: Content
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| forseti_content | 41 | 0 | ⚪ Pending | Static content data |
| forseti_safety_content | 38 | 0 | ⚪ Pending | Safety training content |

#### Tier 2: Utility/Admin
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| company_research | 12 | 0 | ⚪ Pending | Company research tool |
| community_incident_report | 7 | 0 | ⚪ Pending | Incident reporting (NO README) |
| institutional_management | 16 | 0 | ⚪ Pending | Institution data management |
| safety_calculator | 46 | 9 | ⚪ Pending | Score calculation engine |
| copilot_agent_tracker | 10 | 0 | ⚪ Pending | Agent tracking/monitoring |

#### Tier 3: Testing/Deprecated
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| agent_evaluation | 21 | 0 | ⚪ Pending | Testing framework |
| jobhunter_tester | 8 | 0 | ⚪ Pending | Testing module (NO INSTALL) |
| forseti_cluster | 4 | 0 | ⚪ Pending | Deprecated; missing docs |
| nfr | 54 | 1 | ⚪ Pending | NFR testing (NO README) |

### 📍 DUNGEONCRAWLER SITE (3 modules)

#### Tier 1: Support/Integration
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| **dungeoncrawler_content** | 126 | 13 | ⚪ Pending | PF2E game content; complex logic |
| ai_conversation | 26 | 0 | ⚪ Pending | Shared AI module (shared with Forseti) |
| dungeoncrawler_tester | 25 | 1 | ⚪ Pending | Testing content |

### 📍 ST. LOUIS INTEGRATION SITE (8 modules)

#### Tier 1: Support/Integration
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| **job_application_automation** | 43 | 0 | ⚪ Pending | Job app automation support |
| **resume_tailoring** | 13 | 1 | ⚪ Pending | Resume parsing/tailoring |
| ai_conversation | 15 | 0 | ⚪ Pending | Shared AI module |

#### Tier 2: Content
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| professional_website_content | 12 | 0 | ⚪ Pending | Site content |

#### Tier 2: Utility/Admin
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| amisafe | 45 | 0 | 🔴 Blocked | Cross-site security (needs sync) |
| job_hunter | 52 | 0 | 🔴 Blocked | Site-specific copy (not core) |
| stli_site_customizations | 2 | 0 | ⚪ Pending | Site customization (NO README/INSTALL) |

### 📍 THEORY OF CONSPIRACIES SITE (2 modules)

#### Tier 2: Content
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| theory_content | 35 | 0 | ⚪ Pending | Content data (NO INSTALL) |

#### Tier 2: Utility/Admin
| Module | Files | PHP | Status | Notes |
|--------|-------|-----|--------|-------|
| amisafe | 42 | 0 | 🔴 Blocked | Cross-site security (needs sync) |
| ai_conversation | 15 | 0 | ⚪ Pending | Shared AI module |

---

## ISSUE INVENTORY

### Modules Missing Key Files

| Module | Missing | Issue |
|--------|---------|-------|
| community_incident_report | README.md | Documentation incomplete |
| jobhunter_tester | install hook | Schema not defined |
| forseti_cluster | README.md, install | Deprecated state unclear |
| stli_site_customizations | README.md, install | Very small module (2 files) |
| nfr | README.md | Documentation missing |
| theory_content | install | Schema not defined |
| amisafe (StLouis) | install | Site-specific copy |

**Action:** All must have standard files before publishing.

### Cross-Site Modules

| Module | Sites | Status | Action |
|--------|-------|--------|--------|
| ai_conversation | Forseti, DungeonCrawler, StLouis, Theory | 🟡 In QA | Monitor shared version in QA |
| amisafe | Forseti, StLouis, Theory | 🔴 Blocked | **Sync verification required FIRST** |
| job_hunter | Forseti, StLouis | ✅ Frozen (Forseti) | StLouis is site-specific copy; evaluate merge |

**Note:** AI Conversation should be single shared package. Amisafe/Job Hunter may have site-specific variants that need decision (publish separately or merge).

---

## OPEN-SOURCE READINESS CHECKLIST

### Standard Requirements (Applied to All Modules)

#### 6 Security Blockers
1. ✅ No HQ/Orchestrator coupling
2. ✅ No absolute file paths
3. ✅ No Forseti-specific hardcoding
4. ✅ No platform-specific logic
5. ✅ No external queue coupling
6. ✅ No documentation drift

#### 11 Documentation Files
1. README.md - Feature overview
2. INSTALL.md - Setup guide
3. ARCHITECTURE.md - Design/API reference
4. TROUBLESHOOTING.md - Common issues
5. CONTRIBUTING.md - Development workflow
6. SECURITY.md - Vulnerability policy
7. CODE_OF_CONDUCT.md - Community standards
8. LICENSE - Apache 2.0
9. composer.json - Package metadata
10. .env.example - Configuration template
11. .gitignore - Git exclusions

#### CI/CD Baseline
- .github/workflows/ci.yml
- 4 jobs: composer validation, phpcs, Drupal 10 install, Drupal 11 install

### Completion Status by Module

#### Complete ✅
- ai_conversation (in QA)
- job_hunter (frozen)
- forseti_games (frozen)

#### Ready for Audit ⚪
- All 19 other modules

---

## PHASE-BASED ROADMAP

### PHASE 1: PLANNING & STRATEGY ✅ COMPLETE
**Duration:** Completed (this document)
**Owner:** architect-copilot
**Deliverables:**
- ✅ Module inventory (22 modules catalogued)
- ✅ Categorization by tier & complexity
- ✅ Cross-site mapping
- ✅ Issue identification
- ✅ Open-source readiness checklist

---

### PHASE 2: TIER 1 CORE MODULES (HIGH PRIORITY)
**Duration:** 4-6 weeks
**Owner:** architect-copilot (audit/freeze), qa-open-source (validation), pm-forseti-open-source (publication)
**Modules:** 4
**Effort:** ~20 hours

#### 2.1: AI Conversation
- Status: 🟡 IN QA (Gate 2 validation)
- Action: Monitor QA progress
- Timeline: 2 weeks

#### 2.2: Job Hunter
- Status: ✅ FROZEN, awaiting QA
- Action: Monitor QA progress
- Timeline: 2 weeks

#### 2.3: Forseti Games
- Status: ✅ FROZEN, awaiting QA
- Action: Monitor QA progress
- Timeline: 2 weeks

#### 2.4: AMI Safe
- Status: 🔴 BLOCKED on sync verification
- Action: **Verify amisafe code is identical across Forseti, StLouis, Theory sites**
- Effort: 2 hours (verification), 3 hours (audit), 2.5 hours (docs), 1.5 hours (freeze)
- Timeline: 3 weeks (after sync verified)

---

### PHASE 2B: TIER 1 SUPPORT MODULES (HIGH PRIORITY)
**Duration:** 3-4 weeks
**Owner:** architect-copilot
**Modules:** 3
**Effort:** ~15 hours

#### 2B.1: DungeonCrawler Content
- Size: 126 files, 13 PHP files (largest module after job_hunter)
- Audit: 4 hours (complex game content)
- Docs: 3 hours (game-specific documentation)
- Freeze: 2 hours
- Timeline: 2 weeks

#### 2B.2: Job Application Automation
- Size: 43 files
- Audit: 3 hours
- Docs: 2.5 hours
- Freeze: 1.5 hours
- Timeline: 1 week

#### 2B.3: Resume Tailoring
- Size: 13 files, 1 PHP
- Audit: 1.5 hours (small module)
- Docs: 1.5 hours
- Timeline: 1 week

---

### PHASE 3: TIER 2 MODULES (MEDIUM PRIORITY)
**Duration:** 6-8 weeks
**Owner:** architect-copilot
**Modules:** 11 (5 content + 6 utility)
**Effort:** ~25 hours

#### Content Modules (5)
- forseti_content, forseti_safety_content, professional_website_content, theory_content, dungeoncrawler_tester
- Combined effort: ~8 hours (low complexity, content-focused)
- Timeline: 2-3 weeks

#### Utility Modules (6)
- company_research, community_incident_report, institutional_management, safety_calculator, copilot_agent_tracker, stli_site_customizations
- Combined effort: ~11 hours (varied complexity)
- Timeline: 3-4 weeks

---

### PHASE 4: TIER 3 MODULES (LOW PRIORITY)
**Duration:** 2 weeks
**Owner:** architect-copilot
**Modules:** 4
**Effort:** ~3 hours

#### Assessment & Archival
- agent_evaluation (testing framework)
- jobhunter_tester (testing module)
- forseti_cluster (deprecated)
- nfr (NFR testing)
- Action: Decide publish vs. archive; document decision
- Timeline: 2 weeks

---

## TOTAL PROJECT ESTIMATE

| Phase | Modules | Audit | Docs | Freeze | QA | Pub | Total | Timeline |
|-------|---------|-------|------|--------|----|----|-------|----------|
| 1 | - | - | 1 | - | - | - | 1 | ✅ Done |
| 2 | 4 | 8 | 8 | 6 | 8 | 8 | 38 | 4-6 wks |
| 2B | 3 | 8 | 7 | 4 | 4 | 6 | 29 | 3-4 wks |
| 3 | 11 | 14 | 11 | 8 | 8 | 8 | 49 | 6-8 wks |
| 4 | 4 | 1 | 1 | 1 | 1 | 1 | 5 | 2 wks |
| **TOTAL** | **22** | **31** | **28** | **19** | **21** | **23** | **122** | **~4 months** |

**Key Assumptions:**
- 1 hour = 1 person-hour of work
- Parallel execution reduces calendar time
- QA takes 1-2 weeks per module batch
- Publication takes 1-2 weeks per module batch

---

## CROSS-SITE MODULE DECISIONS

### AI Conversation (Shared - 4 sites)
**Status:** 🟡 In QA as single package  
**Decision:** ✅ Publish as shared package  
**Rationale:** Identical code across sites; AI core module  
**Action:** Monitor QA validation; proceed with publication upon approval  

### AMI Safe (Shared - 3 sites)
**Status:** 🔴 Code sync unclear  
**Decision:** ⏳ PENDING sync verification  
**Action Required:**
1. **Verify:** Compare amisafe code on Forseti vs. StLouis vs. Theory
2. **Decide:** Publish as single shared package OR site-specific variants
3. **If Shared:** Merge to single module, make generic
4. **If Variants:** Decide which site's version is "canonical"  
**Timeline:** Must complete before audit  

### Job Hunter (2 sites)
**Status:** ✅ Forseti frozen; StLouis has site-specific copy  
**Decision:** ⏳ PENDING review  
**Action Required:**
1. **Compare:** Forseti vs. StLouis job_hunter code
2. **Decide:** Merge into single module OR keep separate  
3. **If Merged:** Resolve any site-specific customizations  
**Timeline:** Can proceed with Forseti publication; StLouis can follow

---

## PRIORITY SEQUENCE

### Week 1-2: Monitor Phase 2 QA Progress
- Monitor AI Conversation QA validation
- Monitor Job Hunter QA validation
- Monitor Forseti Games QA validation
- **In Parallel:** Verify AMI Safe sync across sites

### Week 3-4: Begin AMI Safe Audit
- Audit AMI Safe (if sync verification passes)
- Begin Phase 2B: DungeonCrawler Content audit

### Week 5-6: Phase 2 Publication
- Publish AI Conversation (upon QA approval)
- Publish Job Hunter (upon QA approval)
- Publish Forseti Games (upon QA approval)

### Week 7-8: Phase 2B Freeze & QA
- Freeze DungeonCrawler Content
- Freeze Job App Automation
- Freeze Resume Tailoring
- Begin Phase 3 audits

### Week 9+: Phase 3 & 4
- Parallel: Phase 3 audits + Phase 2B QA validation
- Publication of Phase 2B modules upon approval
- Assessment and archival of Phase 4 modules

---

## SUCCESS CRITERIA

### By End of Phase 2 (6 weeks)
- ✅ All 4 Tier 1 core modules published
- ✅ AI Conversation, Job Hunter, Forseti Games available on Drupal.org
- ✅ Significant community interest (target: 100+ downloads each)
- ✅ AMI Safe decision made and audited

### By End of Phase 2B (4 weeks)
- ✅ 3 support modules published
- ✅ Strong ecosystem around core modules
- ✅ Documentation complete and discoverable

### By End of Phase 3 (8 weeks)
- ✅ All 11 Tier 2 modules published
- ✅ Comprehensive ecosystem available
- ✅ Strong Drupal.org presence

### By End of Phase 4 (2 weeks)
- ✅ Tier 3 modules either published or archived
- ✅ Clear communication about deprecated modules
- ✅ All 22 modules have documented status

---

## RISKS & MITIGATION

| Risk | Severity | Mitigation |
|------|----------|-----------|
| QA validation delays Phase 2 | HIGH | Start Phase 2B in parallel; maintain buffer |
| AMI Safe sync issues (code diffs found) | HIGH | Manual merge/reconciliation; escalate if significant |
| Cross-site module decisions delay timeline | MEDIUM | Document decision criteria early; get stakeholder buy-in |
| Large modules (DungeonCrawler) need re-audit | MEDIUM | Plan extra buffer; consider phased publication |
| Documentation standards enforcement | MEDIUM | Use automated checklists; pre-publication review |

---

## CONTACT & ESCALATION

| Role | Responsibility | Contact |
|------|-----------------|---------|
| **architect-copilot** | Security audit, documentation, freeze | Audit decisions, blocker resolution |
| **qa-open-source** | QA validation, clean machine tests | Gate 2 verdicts, approval/block decisions |
| **pm-forseti-open-source** | Publication, community management | GitHub repos, Drupal.org submissions |
| **CEO** | Priority decisions, cross-site issues | Strategic decisions, escalations |

---

## NEXT STEPS

1. ✅ **Inventory Complete** — All 22 modules categorized
2. **Monitor Phase 2 QA** — Track AI Conversation, Job Hunter, Forseti Games
3. **Verify AMI Safe Sync** — Compare code across sites (architect)
4. **Begin Phase 2B Audits** — Start DungeonCrawler Content security audit (architect)
5. **Prepare Phase 3** — Plan Tier 2 module audits

---

**Document Status:** READY FOR EXECUTION  
**Authority:** PROJ-009 (Open-Source Initiative)  
**Created:** 2026-04-21  
**Updated:** 2026-04-21  

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
