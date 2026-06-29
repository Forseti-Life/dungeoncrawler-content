# Release Cycle Push Logic & Multi-Repository Integration

**Last Updated:** April 21, 2026  
**Scope:** Coordinated push mechanism, GitHub Actions integration, multi-repo considerations  
**Authority:** CEO-copilot

---

## Current Implementation (Single-Repo)

### Flow Diagram

```
PM Signoff                                  GitHub Deployment
(artifact/release-signoffs/)
       |
       v
[orchestrator/run.py: _coordinated_push_step()]
       |
       +-- Check all team signoffs ready? → YES
       |
       +-- Verify code review gate: check_code_review_gate()
       |
       +-- Write marker file: tmp/auto-push-dispatched/<combined_key>.pushed
       |
       v
[orchestrator/release_cycle.py: run_coordinated_push_step()]
       |
       +-- Read product-teams.json for coordinated teams
       |
       +-- Load active release IDs from tmp/release-cycle-active/<team>.release_id
       |
       +-- Verify all required teams have signoffs
       |
       +-- Generate combined_key from team release IDs
       |
       +-- Write marker if first time this combined_key pushed
       |
       +-- Generate release notes
       |
       v
[GH CLI: gh workflow run deploy.yml]
       |
       +-- --repo keithaumiller/forseti.life (PERSONAL ACCOUNT)
       |
       +-- --ref main
       |
       v
GitHub Actions (deploy.yml in personal repo)
       |
       +-- Build, test, deploy to production
       |
       v
[scripts/post-coordinated-push.sh]
       |
       +-- File team-scoped signoffs
       |
       +-- Advance team release cycles
       |
       +-- Schedule Gate R5 (production audit)
       |
       v
Release Complete (waiting for next cycle)
```

### Key Functions

**1. Orchestrator Entry Point**  
File: `orchestrator/run.py:1217`
```python
def _coordinated_push_step(log: List[Any]) -> None:
    release_cycle.run_coordinated_push_step(log, REPO_ROOT)
```

**2. Coordinated Push Logic**  
File: `orchestrator/release_cycle.py:547`
```python
def run_coordinated_push_step(log: List[Any], repo_root: Path) -> None:
    # 1. Read product-teams.json for active, coordinated-release teams
    # 2. Check tmp/release-cycle-active/<team>.release_id for each team
    # 3. Verify sessions/<pm_agent>/artifacts/release-signoffs/<release_id>.md exists
    # 4. If all teams ready, proceed to push
    # 5. Build combined_key from all team release IDs
    # 6. Write marker file: tmp/auto-push-dispatched/<combined_key>.pushed
    # 7. Generate release notes
    # 8. Trigger: gh workflow run deploy.yml --repo keithaumiller/forseti.life --ref main
```

**3. Post-Push Automation**  
File: `scripts/post-coordinated-push.sh`
```bash
#!/bin/bash
# 1. Run pre-push validation (already committed? all escalations valid?)
# 2. File team-scoped release signoffs (if not already done)
# 3. Write marker: tmp/auto-push-dispatched/<combined_key>.<team_id>.advanced
# 4. Seed next_release_id for each team (advance cycle)
# 5. Dispatch Gate R5 production audit (1h SLA)
```

---

## Current State Analysis

### What Works
✅ **Coordinated Push Logic:**
- Multi-team synchronization (both teams must be ready)
- Marker-based idempotency (same teams/versions won't re-deploy)
- Code review gate enforcement
- Team cycle advancement after push

✅ **GitHub Actions Trigger:**
- Uses GitHub CLI (`gh workflow run`) — requires valid token
- Points to personal account (`keithaumiller/forseti.life`)
- Passes `main` branch for deployment

✅ **Post-Push Automation:**
- Files team signoffs
- Advances cycles
- Schedules production audit

### What's Missing
❌ **Multi-Repository Awareness:**
- No logic to sync public repos (Forseti-Life org repos)
- No CI/CD trigger for public Tier 1 repos
- No backport coordination documented
- All deployment assumes single monorepo

❌ **Public Repo Integration:**
- No mechanism to propagate monorepo changes to public repos
- No way to coordinate CI/CD across Tier 1-3 repos
- No safeguards for security/hotfix backports

❌ **GitHub Token Handling:**
- Assumes single token works for all repos
- No validation that token has `read:org` + `write:org` scopes
- No documentation of token rotation procedure

---

## Proposed Architecture (Multi-Repo Ready)

### Phase 1: Token Validation (Now)

**File:** `scripts/ceo-release-health.sh` (DONE)

**Adds:**
```bash
validate_multirepository_access() {
  # Check personal account access
  GH_TOKEN=$(cat /home/ubuntu/github.token)
  gh api /user
  
  # Check organization access
  gh api orgs/Forseti-Life
  
  # List all public repos
  gh repo list Forseti-Life
  
  # Report git remotes
  git remote -v
}
```

**Validator Script:** `scripts/multirepository-validator.sh` (DONE)

---

### Phase 2: Documentation (Now)

**Files Updated:**
- ✅ `org-chart/org-wide.instructions.md` — Multi-repo architecture section
- ✅ `org-chart/roles/ceo.instructions.md` — Multi-repo authority & GitHub token mgmt
- ✅ `MULTIREPOSITORY_DEVELOPER_GUIDE.md` — Developer workflows
- ✅ `README.md` — Architecture overview + health check commands

---

### Phase 3: Release Logic Enhancement (Future)

**Proposed Changes to `orchestrator/release_cycle.py`:**

```python
def run_coordinated_push_step(log: List[Any], repo_root: Path) -> None:
    # ... existing logic ...
    
    # STEP 1: Trigger deploy in personal account (current behavior)
    rc, out = _run([
        "gh", "workflow", "run", "deploy.yml",
        "--repo", "keithaumiller/forseti.life",
        "--ref", "main"
    ], timeout=60)
    
    log.append({
        "step": "coordinated_push",
        "personal_repo_trigger": {"rc": rc, "out": out}
    })
    
    # STEP 2 (NEW): Sync to public repos + trigger CI
    # - Determine which repos are affected by this release
    # - For each public Tier 1 repo:
    #   - Pull latest from Forseti-Life org
    #   - If out of sync, file sync PR or trigger manual review
    # - For Tier 2 repos (libraries):
    #   - Check if any version bumps needed
    #   - File PRs for library updates
    # - For Tier 3 repos (ops):
    #   - Sync orchestrator changes (copilot-hq)
    #   - No auto-deployment; manual review
    
    # log.append({
    #     "step": "public_repo_sync",
    #     "tier_1_sync": [...],
    #     "tier_2_updates": [...],
    #     "tier_3_updates": [...]
    # })
```

**Proposed Changes to `scripts/post-coordinated-push.sh`:**

```bash
# After team cycles advanced and Gate R5 scheduled:

# NEW STEP: Public repo backport check
if [ "$SYNC_PUBLIC_REPOS" = "1" ]; then
    echo "=== Checking public repo sync ==="
    
    # For each Tier 1 repo affected:
    for repo in forseti-job-hunter dungeoncrawler-pf2e; do
        # Check if sync PR exists or if manual sync is needed
        # File inbox item for PM/BA to review and coordinate
    done
fi
```

---

## Decision Points for Multi-Repo Integration

### Decision 1: Automatic vs. Manual Sync

**Option A (Automatic):**
- Orchestrator automatically syncs public repos after coordinated push
- Creates PR if out of sync, auto-merges if CI passes
- Pro: Fast, reduces manual overhead
- Con: Risk of breaking public repo CI, harder to debug

**Option B (Manual):**
- Post-push automation files "review public repo sync" item to CEO inbox
- CEO manually reviews, approves backports
- Pro: Safe, explicit control, better for hotfixes
- Con: Slower, requires CEO involvement every cycle

**Recommendation:** Option B initially (Phase 3), move to Option A after 3+ cycles prove reliability.

### Decision 2: Which Repos Sync Automatically?

**Option A:** Tier 1 only (core products)
- Automatically sync: forseti-job-hunter, dungeoncrawler-pf2e
- Manual review for Tier 2-4
- Rationale: Core products are main community focus

**Option B:** Tier 1-2 (products + libraries)
- Automatically sync: Tier 1 + Tier 2 repos
- Manual review for Tier 3-4 (ops/reference)
- Rationale: Libraries are dependencies, need quick updates

**Option C:** All tiers
- Automatically sync everything
- Pro: Fastest, simplest logic
- Con: Risk of breaking ops tooling in public repos

**Recommendation:** Option A initially, expand to Option B after Tier 1 is stable.

### Decision 3: Token Provisioning & Rotation

**Current Status:**
- ⏳ Board needs to provision new PAT
- Scopes: repo, workflow, public_repo, read:org, write:org
- Target: keithaumiller personal + Forseti-Life organization

**Rotation Policy (Recommend):**
- Annual: Revoke old, generate new
- On compromise: Immediate revoke + regenerate
- Ceremony: Board generates → CEO validates → updates `/home/ubuntu/github.token`

**Procedure:**
```bash
# CEO workflow after Board provides new token:
echo "NEW_TOKEN" > /home/ubuntu/github.token
chmod 600 /home/ubuntu/github.token

# Validate
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /user
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api orgs/Forseti-Life

# Full validation
bash copilot-hq/scripts/multirepository-validator.sh --full
```

---

## Testing Strategy

### Unit Tests (Existing)
- `orchestrator/tests/test_release_cycle_control.py` — Release state machine
- `orchestrator/tests/test_release_cycle_handoff.py` — Cycle advancement

### Integration Tests (Future)
```python
# tests/test_multirepo_release_sync.py
def test_coordinated_push_triggers_personal_account():
    """Verify coordinated push triggers deploy.yml in keithaumiller/forseti.life"""
    ...

def test_coordinated_push_validates_github_token():
    """Verify push checks token has org access before syncing"""
    ...

def test_public_repo_sync_creates_pr():
    """Verify public repo sync creates PR if out of date"""
    ...

def test_public_repo_sync_respects_branch_protection():
    """Verify sync doesn't bypass branch protection rules"""
    ...
```

### Manual Validation (Before Each Release)
```bash
# Before initiating coordinated push:
bash copilot-hq/scripts/ceo-release-health.sh

# After push completes:
bash copilot-hq/scripts/multirepository-validator.sh --full
```

---

## Risk Assessment

### High Risk
- ❌ Automatic merge of synced public repos (could break CI)
  - *Mitigation:* Manual review (Phase 3 Option B)
  
- ❌ Invalid GitHub token (401 errors on push)
  - *Mitigation:* Health checks validate token before push
  - *Status:* ⏳ Waiting on Board to provision valid token

### Medium Risk
- ⚠️ Out-of-sync public repos (drift over time)
  - *Mitigation:* Manual backport coordination documented
  
- ⚠️ Hotfix backport delay (security fixes lag)
  - *Mitigation:* CEO authority to prioritize security backports

### Low Risk
- ✅ Marker-based idempotency prevents double-deploy
  - *Status:* Already implemented

---

## Timeline

**Phase 1 (NOW - April 21):** ✅ DONE
- Documentation updated
- Validation scripts created
- Instructions refreshed
- Board escalation filed

**Phase 2 (Awaiting Board):** ⏳ PENDING
- Board provisions new GitHub PAT
- CEO deploys token and validates
- Health checks report ✅ PASS

**Phase 3 (Next Release Cycle):** 📅 PLANNED
- Manual public repo backport process documented
- CEO reviews post-push for sync needs
- File inbox items for public repo updates

**Phase 4 (Q2/Q3 2026):** 🔮 FUTURE
- Automated public repo sync (Option B)
- CI/CD coordination across Tier 1 repos
- Public repo versioning scheme finalized

---

## Files to Review

**Implementation Files:**
- `orchestrator/run.py` (line 1217) — Entry point
- `orchestrator/release_cycle.py` (line 547) — Main logic
- `scripts/post-coordinated-push.sh` — Post-push automation
- `scripts/pre-push-validation.sh` — Pre-deployment checks

**Documentation Files:**
- `org-chart/org-wide.instructions.md` — Org-wide rules
- `org-chart/roles/ceo.instructions.md` — CEO authority
- `runbooks/shipping-gates.md` — Release gates R1-R5
- `MULTIREPOSITORY_DEVELOPER_GUIDE.md` — Developer workflows

**Configuration Files:**
- `org-chart/products/product-teams.json` — Team definitions
- `.github/workflows/deploy.yml` — Personal account workflow
- `orchestrator/requirements.txt` — Dependencies

---

## Open Issues Tracking

**ISSUE-022:** GitHub PAT Invalid
- Status: 🔵 ESCALATED TO BOARD
- Blocking: Cannot validate multi-repo access without valid token

**ISSUE-025:** Infrastructure Updates for Multi-Repo
- Status: 🔴 OPEN
- Component: Release cycle push logic
- Sub-task: Document push/merge coordination model
- Sub-task: Plan Phase 3 (manual backport coordination)

---

## Glossary

- **Monorepo:** Single repository at `/home/ubuntu/forseti.life` (private, deployment source)
- **Coordinated Push:** Orchestrator-triggered deployment when all PM signoffs ready
- **Public Repos:** 11 repos in Forseti-Life organization (community, open-source)
- **Tier 1-4:** Repository tiers (1=products, 2=libs, 3=ops, 4=reference)
- **Backport:** Cherry-pick commit from one repo to another (production → public)
- **Sync:** Bring two repos in line (monorepo → public)
- **GH CLI:** GitHub command-line interface (`gh` command)
- **GH_TOKEN:** Environment variable for GitHub authentication

---

## Questions & Next Steps

1. **For Board:** Provision new GitHub PAT with org access (BLOCKING)
2. **For CEO:** Validate token and run health checks (After PAT arrives)
3. **For Dev/PM:** Review multi-repo developer guide; incorporate into seat instructions
4. **For Architecture:** Plan Phase 3-4 (public repo sync automation)

---

**Document Owner:** CEO-copilot  
**Last Reviewed:** April 21, 2026  
**Status:** READY FOR IMPLEMENTATION
