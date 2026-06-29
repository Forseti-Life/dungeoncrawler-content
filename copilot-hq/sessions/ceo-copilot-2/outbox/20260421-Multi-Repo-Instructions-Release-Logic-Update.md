# Update Complete: Multi-Repository Instructions & Release Cycle Analysis

**Date:** April 21, 2026  
**Scope:** Organization-wide instructions, role definitions, release cycle push logic  
**Status:** ✅ COMPLETE  
**Commits:** 2 (068546208, a8b3ea863)

---

## Executive Summary

Successfully updated all instruction layers (org-wide → role → seat) to reflect the broken-out multi-repository architecture. Conducted comprehensive analysis of the release cycle push/merge logic and documented multi-repo integration roadmap (Phase 1-4).

**Current State:** Private monorepo push logic works correctly for single-repo deployment. Public repos (11 in Forseti-Life org) are ready for community contribution but not yet integrated into release cycle.

**Next Action:** Awaiting Board GitHub PAT provisioning to enable multi-repo validation and public repo sync planning.

---

## Part 1: Instruction Updates

### Updated Files

#### 1. org-chart/org-wide.instructions.md (+92 lines)

**New Section:** "Multi-Repository Architecture (Broken-Out Repos)"

Content:
- Architecture overview (private monorepo vs 11 public repos)
- Developer responsibilities for private vs public work
- Git remotes configuration (no embedded tokens)
- Release & deployment operational notes (current single-repo, future multi-repo)
- Validation & health check commands
- Documentation authority & cross-references

Impact: All roles now understand the architecture and can reference consistent documentation.

#### 2. org-chart/roles/ceo.instructions.md (+78 lines)

**New Section:** "Multi-Repository Architecture Authority"

Content:
- Private monorepo authority (push, merge, orchestrator control)
- Public repos authority (11 repos, Forseti-Life organization)
- Release cycle & push logic breakdown (IMPORTANT: current single-repo → future multi-repo)
- GitHub token management (provisioning, validation, rotation workflow)
- Git remotes configuration (no embedded tokens policy)
- Decision authority for public repo backports & integration

Impact: CEO has clear authority boundaries and documented procedures for multi-repo governance.

#### 3. runbooks/RELEASE_CYCLE_PUSH_LOGIC.md (NEW, +446 lines)

**Comprehensive Analysis Document**

Sections:
1. **Current Implementation (Single-Repo)**
   - Flow diagram: PM Signoff → Orchestrator → GitHub Deploy → Post-Push
   - Key functions with line numbers
   - Current state analysis (what works ✅ / what's missing ❌)

2. **Proposed Architecture (Multi-Repo Ready)**
   - Phase 1-4 rollout plan (Phase 1 done ✅, awaiting Board 🔵)
   - Enhancement options for each phase
   - Future public repo sync design

3. **Decision Framework**
   - Automatic vs Manual Sync (Recommendation: Manual first, Phase 3)
   - Which Repos to Sync (Recommendation: Tier 1 first)
   - Token Provisioning & Rotation (Board → CEO workflow)

4. **Risk Assessment & Mitigation**
   - High risk: Automatic merge of synced repos (Mitigation: Manual review)
   - Medium risk: Out-of-sync public repos (Mitigation: Backport coordination)
   - Low risk: Double-deploy (Mitigation: Markers already implemented)

5. **Testing Strategy**
   - Unit tests (existing)
   - Integration tests (future)
   - Manual validation (before each release)

Impact: Clear roadmap for scaling from single-repo to multi-repo architecture with documented decision points and risk mitigations.

---

## Part 2: Release Cycle Push Logic Analysis

### Current Implementation

**Location of Logic:**
```
Entry:    orchestrator/run.py:1217 (_coordinated_push_step)
Main:     orchestrator/release_cycle.py:547 (run_coordinated_push_step)
Post:     scripts/post-coordinated-push.sh
```

**Flow:**
```
1. PM Signoff Ready
   ↓
2. Orchestrator checks all teams ready + code review gate
   ↓
3. Write marker file (idempotency guard)
   ↓
4. Generate release notes
   ↓
5. GH CLI: gh workflow run deploy.yml --repo keithaumiller/forseti.life --ref main
   ↓
6. GitHub Actions runs deploy.yml
   ↓
7. Post-push: scripts/post-coordinated-push.sh
   ├─ File team signoffs
   ├─ Advance cycles
   └─ Schedule Gate R5 audit
```

### Current Capabilities (Working ✅)

- ✅ Multi-team synchronization (both teams must be ready)
- ✅ Marker-based idempotency (prevents double-deploy)
- ✅ Code review gate enforcement
- ✅ Team cycle advancement
- ✅ GitHub CLI integration
- ✅ Post-push automation

### Current Limitations (Not Implemented ❌)

- ❌ No multi-repository awareness
- ❌ Push only to personal account (keithaumiller/forseti.life)
- ❌ No public repo sync mechanism
- ❌ No CI/CD coordination across Tier 1-3 repos
- ❌ No GitHub token validation before push
- ❌ No token rotation procedure documented

---

## Part 3: Multi-Repository Integration Roadmap

### Phase 1: Token Validation & Documentation (✅ DONE)

**Completed (April 21):**
- Documentation updated in all instruction layers
- Validation scripts created (`multirepository-validator.sh`)
- Health checks enhanced (`ceo-release-health.sh`)
- GitHub token requirements documented
- Issues tracked (ISSUE-022 escalated, ISSUE-025 opened)

### Phase 2: GitHub PAT Provisioning (⏳ AWAITING BOARD)

**Board Action Required:**
- Generate new Personal Access Token
- Scopes: `repo`, `workflow`, `public_repo`, `read:org`, `write:org`
- Access: keithaumiller personal account + Forseti-Life organization
- Status in `issues.md`: ISSUE-022 (🔵 ESCALATED)

**CEO Execution (After Board Provides):**
```bash
echo "NEW_TOKEN" > /home/ubuntu/github.token
chmod 600 /home/ubuntu/github.token

# Validate
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /user
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api orgs/Forseti-Life

# Full test
bash copilot-hq/scripts/multirepository-validator.sh --full
bash copilot-hq/scripts/ceo-release-health.sh
```

### Phase 3: Manual Backport Coordination (📅 NEXT RELEASE CYCLE)

**Planned Steps:**
1. Document manual public repo backport process
2. File inbox items for post-push public repo sync reviews
3. CEO coordinates backports from monorepo → public repos
4. Establish sync cadence & review process
5. Create PR templates & backport runbook

**Example Workflow:**
- Monorepo release deployed ✅
- CEO reviews public repos for sync need
- Files "Review Tier 1 public repo updates" item to inbox
- Dev/BA backports changes to public repo
- Public repo CI passes
- Merged and available to community

### Phase 4: Automated Sync & CI/CD (🔮 Q2/Q3 2026)

**Planned Enhancements:**
- Automate public repo sync after coordinated push (Tier 1 repos)
- Trigger CI/CD workflows in public repos
- Setup versioning scheme for Tier 2 libraries
- Coordinate third-party integrations
- Monitor and report sync health

---

## Decision Framework

### Decision 1: Sync Strategy

| Option | Approach | Pros | Cons | Recommendation |
|--------|----------|------|------|-----------------|
| **A** | Automatic | Fast, reduced overhead | Risk of breaking CI | ✅ Phase 3 Manual |
| **B** | Manual Review | Safe, explicit control | Slower, CEO work | ← Phase 3 Start |
| **C** | Hybrid | Fast for hotfixes, manual for features | Complex logic | Future (Phase 4) |

**Recommendation:** Start with Option B (manual review), transition to Option A after 3+ cycles prove stable.

### Decision 2: Which Repos Sync

| Option | Repos | Pros | Cons | Recommendation |
|--------|-------|------|------|-----------------|
| **A** | Tier 1 only | Main focus, lowest risk | Limits community | ✅ Phase 3 Start |
| **B** | Tier 1-2 | Includes libraries | More complex | Phase 4 |
| **C** | All tiers | Complete sync | Risky for ops tooling | Not recommended |

**Recommendation:** Start with Option A (Tier 1: forseti-job-hunter, dungeoncrawler-pf2e), expand to Tier 2 after Tier 1 stable.

### Decision 3: Token Rotation

**Procedure:**
- Annual: Revoke old, generate replacement
- On Compromise: Immediate revoke + regenerate
- Ceremony: Board generates → CEO validates → Deploys

**Validation Commands:**
```bash
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /user                    # Personal access
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api orgs/Forseti-Life       # Org access
bash copilot-hq/scripts/multirepository-validator.sh --full               # Full validation
```

---

## Risk Assessment

### High Risk: Automatic Merge of Synced Public Repos
- **Issue:** Could break public repo CI/CD
- **Likelihood:** Medium (if logic has bugs)
- **Impact:** High (public repo broken, community blocked)
- **Mitigation:** Manual review phase (Phase 3), comprehensive testing before automation

### Medium Risk: Out-of-Sync Public Repos
- **Issue:** Public repos drift from monorepo over time
- **Likelihood:** High (happens naturally if sync not coordinated)
- **Impact:** Medium (community confused, integration problems)
- **Mitigation:** Documented backport coordination, regular sync audits

### Medium Risk: Invalid GitHub Token
- **Issue:** 401 errors block push
- **Likelihood:** Low (Board controls token)
- **Impact:** High (release blocked)
- **Mitigation:** Health checks validate before push, Board provides backup token

### Low Risk: Double-Deploy
- **Issue:** Same release pushed twice
- **Likelihood:** Very Low (markers prevent this)
- **Impact:** Low (orchestrator already handles it)
- **Mitigation:** Idempotency already implemented via marker files

---

## Testing Strategy

### Unit Tests (Existing)
- `orchestrator/tests/test_release_cycle_control.py` — Release state machine
- `orchestrator/tests/test_release_cycle_handoff.py` — Cycle advancement

### Integration Tests (Future, Phase 3-4)
```python
# tests/test_multirepo_release_sync.py
test_coordinated_push_triggers_personal_account()
test_coordinated_push_validates_github_token()
test_public_repo_sync_creates_pr()
test_public_repo_sync_respects_branch_protection()
```

### Manual Validation (Before Each Release)
```bash
# Pre-push validation
bash copilot-hq/scripts/ceo-release-health.sh

# Post-push validation
bash copilot-hq/scripts/multirepository-validator.sh --full

# Verify public repo access
GH_TOKEN=$(cat /home/ubuntu/github.token) gh repo list Forseti-Life --limit 20
```

---

## Files Updated/Created

### Documentation
- ✅ `org-chart/org-wide.instructions.md` — Org-wide rules (+92 lines)
- ✅ `org-chart/roles/ceo.instructions.md` — CEO authority (+78 lines)
- ✅ `runbooks/RELEASE_CYCLE_PUSH_LOGIC.md` — Release logic analysis (NEW, +446 lines)

### Infrastructure (Previous Commit)
- ✅ `copilot-hq/scripts/multirepository-validator.sh` — New validator
- ✅ `copilot-hq/scripts/ceo-release-health.sh` — Enhanced health check
- ✅ `README.md` — Multi-repo section added
- ✅ `MULTIREPOSITORY_DEVELOPER_GUIDE.md` — Developer guide (NEW)
- ✅ `copilot-hq/issues.md` — Issues tracked

### Git Commits
- ✅ Commit `068546208`: "Infrastructure: Update for broken-out multi-repository architecture"
- ✅ Commit `a8b3ea863`: "Documentation: Update instructions for multi-repository architecture"

---

## Immediate Next Steps

### For Board
1. Generate new GitHub PAT with scopes: repo, workflow, public_repo, read:org, write:org
2. Provide token to CEO

### For CEO (After Board Provides Token)
1. Store token: `echo "TOKEN" > /home/ubuntu/github.token`
2. Validate: `bash copilot-hq/scripts/multirepository-validator.sh --full`
3. Verify health: `bash copilot-hq/scripts/ceo-release-health.sh`
4. Document result in session outbox

### For Next Release Cycle
1. Plan Phase 3 manual backport process
2. File "Review public repo sync" inbox item template
3. Coordinate with PM/Dev/BA on backport cadence
4. Test backport process with one Tier 1 repo

---

## Questions Answered

**Q: What's the current state of push logic?**  
A: Works correctly for single-repo deployment. Pushes to keithaumiller/forseti.life via GitHub Actions. No multi-repo awareness yet.

**Q: Which files implement the push logic?**  
A: orchestrator/run.py:1217 (entry), orchestrator/release_cycle.py:547 (main), scripts/post-coordinated-push.sh (post-push).

**Q: Can we push to public repos now?**  
A: Not yet — GitHub token needs Board provisioning with org access (ISSUE-022). After that, manual coordination needed first (Phase 3).

**Q: When will automatic public repo sync work?**  
A: Phase 4 (Q2/Q3 2026), after manual Phase 3 proves the process reliable.

**Q: What's the recommended sync strategy?**  
A: Manual review first (Phase 3), then automate Tier 1 only (Phase 4), expand to Tier 2 after proven stable.

---

## Summary

✅ **Complete:** All instruction layers updated for multi-repo architecture  
✅ **Complete:** Release cycle push logic analyzed and documented  
✅ **Complete:** Multi-repo integration roadmap defined (Phase 1-4)  
✅ **Complete:** Decision framework documented with recommendations  
✅ **Complete:** Risk assessment with mitigations  
🔵 **Awaiting Board:** GitHub PAT provisioning (ISSUE-022)  
📅 **Planned:** Phase 2-4 implementation (starting after PAT)

---

**Document Owner:** CEO-copilot  
**Status:** READY FOR PHASE 2 (Board PAT Provisioning)  
**Next Review:** After token deployed and validated
