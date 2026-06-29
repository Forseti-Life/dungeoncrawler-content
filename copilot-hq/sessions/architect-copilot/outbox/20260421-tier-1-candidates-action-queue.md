# PROJ-009 Tier-1 Open-Source Candidates — Complete Action Queue

**Date:** April 21, 2026

**Status:** Queued (20 action items across 4 modules)

---

## EXECUTIVE SUMMARY

All custom Drupal modules have been inventoried. Four tier-1 open-source 
candidates identified and action items queued:

1. **✅ AI Conversation** — FROZEN & IN QA (4 pending items)
2. **⏳ Job Hunter** — QUEUED FOR AUDIT (5 pending items)
3. **⏳ Forseti Games** — QUEUED FOR AUDIT (5 pending items)  
4. **⏳ AMI Safe** — QUEUED FOR AUDIT (6 pending items, needs site sync check)

**Total Tier-1 Actions:** 20 items
**Total Tier-2/3 Modules:** 21 (blocked until tier-1 published)

---

## QUICK STATUS

| Candidate | Status | Progress | Owner | Items |
|-----------|--------|----------|-------|-------|
| AI Conversation | 🟡 IN PROGRESS | QA Validation | qa-open-source | 4 |
| Job Hunter | 🔴 QUEUED | Not started | architect-copilot | 5 |
| Forseti Games | 🔴 QUEUED | Not started | architect-copilot | 5 |
| AMI Safe | 🔴 QUEUED | Not started | architect-copilot | 6 |

**Expected Completion:** 
- Phase 1 (Freeze all 4): ~4 weeks
- Phase 2 (Public repos): ~1 week post-approval
- Phase 3 (Drupal.org): ~1-2 weeks post-repos

---

## TIER-1 CANDIDATES OVERVIEW

### 1. AI CONVERSATION (drupal-ai-conversation) — ✅ COMPLETE FREEZE

**Current Status:** Frozen candidate in QA validation

**Frozen Artifact:** 
- File: `drupal-ai-conversation-v1.0.0-freeze.tar.gz` (110 KB)
- Location: `sessions/architect-copilot/artifacts/`
- Contents: 55 module files + 11 documentation files (1,936 lines)

**Pending Actions:**
1. [ ] QA Gate 2 Validation (in_progress) — qa-open-source
   - 6 clean-machine tests
   - Documentation verification
   - CI baseline validation

2. [ ] Create Public GitHub Repo (pending) — pm-forseti-open-source
   - Create: `github.com/Forseti-Life/drupal-ai-conversation`
   - Branch protection on main

3. [ ] Publish to Drupal.org (pending) — pm-forseti-open-source
   - Submit to contrib modules

4. [ ] Announce to Community (pending) — pm-forseti-open-source
   - Forums, Discord, mailing lists

**Blocker Status:** ✅ ALL 6 CLEARED
- HQ coupling removed ✓
- Absolute paths eliminated ✓
- Site-specific logging removed ✓
- Forseti prompts neutralized ✓
- Suggestion automation verified local ✓
- Documentation reconciled ✓

**Next:** Monitor QA progress. Post-approval: Create public repo.

---

### 2. JOB HUNTER (drupal-job-hunter) — 🔴 QUEUED

**Overview:**
- Location: `sites/forseti/web/modules/custom/job_hunter/`
- Type: Platform module (autonomous job search agent)
- Used by: Forseti (primary), STLouisIntegration (shared)
- Complexity: HIGH (external integrations, autonomous behavior)

**Pending Actions (5 items):**

1. [ ] **Security & Sanitization Audit** (pending) — architect-copilot
   - Check for 6 standard blockers
   - Review external integrations (job boards, APIs)
   - Verify no HQ coupling
   - Audit job queue system (local-only?)
   - Review prompts and search algorithms
   - Document findings with commit evidence

2. [ ] **Verify Blockers Cleared** (pending) — architect-copilot
   - HQ/Session coupling check
   - Absolute paths check
   - Site-specific logging check
   - Platform-specific defaults check
   - External queue coupling check
   - Documentation drift check
   - Create blocker verification report

3. [ ] **Documentation Audit** (pending) — architect-copilot
   - Review current docs for Drupal.org standards compliance
   - Create/enhance README.md (440+ lines, 80 char wrap)
   - Create/enhance INSTALL.md (step-by-step)
   - Create ARCHITECTURE.md with:
     - Job search algorithm overview
     - External integration points
     - Service architecture
     - Hook documentation
     - Performance tuning guidance
   - Create TROUBLESHOOTING.md for:
     - Job board authentication
     - Search algorithm issues
     - Rate limiting solutions
     - Performance optimization
   - Verify CONTRIBUTING.md, SECURITY.md, CODE_OF_CONDUCT.md

4. [ ] **Freeze & Package Candidate** (pending) — architect-copilot
   - Extract to clean directory
   - Verify no credentials in any file
   - Create composer.json with Drupal metadata
   - Create .github/workflows/ci.yml (4 jobs)
   - Create FREEZE_PACKET.md handoff contract
   - Archive as `drupal-job-hunter-v1.0.0-freeze.tar.gz`

5. [ ] **QA Gate 2 Validation** (pending) — qa-open-source
   - Extract and verify all files
   - Run 6 clean-machine tests
   - Run CI baseline (composer, phpcs, D10/D11)
   - Verify documentation complete
   - APPROVE or BLOCK with evidence

**Estimated Timeline:** 2-3 weeks (once architect audit starts)

**Next:** Schedule architect security audit

---

### 3. FORSETI GAMES (drupal-forseti-games) — 🔴 QUEUED

**Overview:**
- Location: `sites/forseti/web/modules/custom/forseti_games/`
- Type: Platform module (game modules for user engagement)
- Used by: Forseti (primary only)
- Complexity: MEDIUM (content/engagement module)

**Pending Actions (5 items):**

1. [ ] **Security & Sanitization Audit** (pending) — architect-copilot
   - Check for 6 standard blockers
   - Review game state management
   - Verify user progress tracking is local-only
   - Audit scoring/leaderboard system
   - Check configuration for site-specific settings
   - Document findings with evidence

2. [ ] **Verify Blockers Cleared** (pending) — architect-copilot
   - Same 6-point checklist as Job Hunter
   - Create blocker verification report

3. [ ] **Documentation Audit** (pending) — architect-copilot
   - Create README.md with game overview
   - Create INSTALL.md with setup instructions
   - Create ARCHITECTURE.md with:
     - Game engine/state management overview
     - Hook points for extending games
     - Database schema documentation
     - Performance considerations
   - Create TROUBLESHOOTING.md for game-specific issues
   - Verify supporting documentation

4. [ ] **Freeze & Package Candidate** (pending) — architect-copilot
   - Same packaging process as Job Hunter

5. [ ] **QA Gate 2 Validation** (pending) — qa-open-source
   - Standard QA validation checklist

**Estimated Timeline:** 2-3 weeks (once architect audit starts)

**Next:** Schedule architect security audit

---

### 4. AMI SAFE (drupal-amisafe) — 🔴 QUEUED (SPECIAL: CROSS-SITE)

**Overview:**
- Locations: 
  - `sites/forseti/web/modules/custom/amisafe/`
  - `sites/stlouisintegration/custom/amisafe/`
  - `sites/theoryofconspiracies/custom/amisafe/`
- Type: Shared module (used across 3 sites)
- Purpose: Safety framework and incident reporting
- Complexity: HIGH (safety-critical + cross-site)
- **SPECIAL REQUIREMENT:** Site sync verification before freeze

**Pending Actions (6 items):**

**LANE 0 (MUST COMPLETE FIRST):**

0. [ ] **Site Sync Verification** (pending) — architect-copilot
   - Verify amisafe is IDENTICAL across all 3 sites
   - Run diff:
     ```
     diff -r sites/forseti/web/modules/custom/amisafe/ \
            sites/stlouisintegration/custom/amisafe/
     diff -r sites/forseti/web/modules/custom/amisafe/ \
            sites/theoryofconspiracies/custom/amisafe/
     ```
   - If differences found:
     - Identify canonical version (likely forseti)
     - Merge improvements from other sites
     - Align all 3 to canonical version
     - Test each site continues working
     - Commit changes across all 3 sites
   - Document sync verification report
   - **GATE:** All 3 sites must be identical before proceeding

**LANES 1-4 (After sync verification):**

1. [ ] **Security & Sanitization Audit** (pending) — architect-copilot
   - Check for 6 standard blockers (focused on safety-critical code)
   - Verify no cross-site configuration leaks
   - Audit safety decision logic (hardcoded vs configurable?)
   - Review permission system across 3 sites
   - Check incident types (site-specific or generic?)
   - Verify notification system is internal-only
   - Document findings with evidence

2. [ ] **Verify Blockers Cleared** (pending) — architect-copilot
   - Same 6-point checklist, applied to safety framework
   - Create blocker verification report

3. [ ] **Documentation Audit** (pending) — architect-copilot
   - Create README.md with safety framework overview
   - Create INSTALL.md with cross-Drupal setup
   - Create ARCHITECTURE.md with:
     - Safety incident model
     - Report system architecture
     - Integration points
     - Extensibility and hooks
   - Create TROUBLESHOOTING.md for incident reporting
   - Document that amisafe works on any Drupal site
   - Verify supporting documentation

4. [ ] **Freeze & Package Candidate** (pending) — architect-copilot
   - Same packaging as other modules

5. [ ] **QA Gate 2 Validation** (pending) — qa-open-source
   - Standard QA validation
   - Plus: Verify module works independently on fresh Drupal site
   - Verify no cross-site configuration expected

**Estimated Timeline:** 2-2.5 weeks (includes site sync check)

**Next:** Schedule architect security audit (after sync verification logic confirmed)

---

## BLOCKED TIER-2 MODULES (10 modules)

These modules are blocked until tier-1 candidates are published:

- copilot_agent_tracker (agent tracking)
- nfr (requirements tracking)
- agent_evaluation (agent framework)
- company_research (research tools)
- forseti_safety_content (safety content)
- community_incident_report (incident tracking)
- forseti_content (platform content)
- safety_calculator (safety metrics)
- job_application_automation (stli-specific)
- resume_tailoring (stli-specific)

**Rationale:** Tier-2 modules have platform dependencies or are site-specific. 
Tier-1 must establish open-source patterns first.

**Action:** No items queued. Awaiting tier-1 publication.

---

## RELEASE ROADMAP

### PHASE 1: FREEZE ALL 4 TIER-1 CANDIDATES (WEEKS 1-4)

**Week 1-2: Parallel Audits (Job Hunter + Forseti Games)**
- Architect audits Job Hunter security
- Architect audits Forseti Games security
- Parallel: Start AMI Safe site sync check

**Week 2-3: Documentation & Packaging**
- Architect documents Job Hunter
- Architect documents Forseti Games
- Architect starts AMI Safe sync → documentation

**Week 3: Freeze & QA Handoff**
- Freeze Job Hunter and Forseti Games
- Hand off to QA for validation
- Finish AMI Safe packaging

**Week 4: Complete AMI Safe QA**
- Freeze AMI Safe
- Hand off to QA for validation

**Gate:** All 4 modules frozen and QA-approved

### PHASE 2: PUBLIC REPOS & PUBLICATION (WEEKS 2-5, overlaps Phase 1)

**Immediate after AI Conversation QA approval:**
- Create 4 public GitHub repos
- Push v1.0.0 tags with frozen candidates
- Configure branch protection

**Immediate after all 4 approved:**
- Publish all 4 to Drupal.org
- Announce in community

**Gate:** All 4 modules published and discoverable on drupal.org

### PHASE 3: TIER-2 EVALUATION (WEEKS 6+)

- Assess which tier-2 modules suitable for publication
- Determine if copilot-agent-framework API needed
- Plan secondary release wave

---

## IMMEDIATE ACTION ITEMS

**Priority 1 (NOW):**
- [ ] Monitor AI Conversation QA progress

**Priority 2 (THIS WEEK):**
- [ ] Schedule Job Hunter audit with architect-copilot
- [ ] Schedule Forseti Games audit with architect-copilot
- [ ] Schedule AMI Safe sync check + audit with architect-copilot

**Priority 3 (ONCE ARCHITECT READY):**
- [ ] Start Job Hunter audit
- [ ] Start Forseti Games audit
- [ ] Start AMI Safe sync verification

---

## TRACKING

All actions tracked in SQL database:
- Module inventory: 25 modules across 3 sites
- Action items: 20 items for tier-1 candidates
- Dependencies: Tier-2/3 blocked on tier-1 completion

Access: Query module/action status at any time

---

## CONTACTS & OWNERS

**Architect:** architect-copilot@forseti.life
- Owns: Security audits, blocker verification, documentation, packaging

**QA:** qa-open-source@forseti.life
- Owns: Clean-machine tests, CI validation, APPROVE/BLOCK decisions

**PM:** pm-forseti-open-source@forseti.life
- Owns: Public repo creation, Drupal.org publication, community announcements

**CEO:** (oversight and escalations)

---

**Status:** Ready for execution

**Next:** Architect-copilot begins Job Hunter and Forseti Games audits

**No blockers preventing immediate start.**
