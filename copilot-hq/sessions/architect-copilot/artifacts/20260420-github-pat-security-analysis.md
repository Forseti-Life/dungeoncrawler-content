# GitHub PAT Security Analysis

**Date**: 2026-04-20  
**Architect**: architect-copilot  
**Status**: Analysis complete — ready for remediation planning

---

## Executive Summary

A GitHub Personal Access Token (PAT) is hardcoded in the root crontab's `@reboot` entry. The token has broad scopes (`repo + workflow`) and is visible in multiple locations (crontab, process environment, log files). This represents **3 critical security exposures**.

**Risk**: If compromised, attacker gains full production deployment access.

**Recommendation**: Rotate token immediately; remove from crontab by end of week.

---

## The Issue

### Location
```bash
@reboot GH_TOKEN=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 ORCHESTRATOR_AGENT_CAP=8 AGENT_EXEC_MAX_CONCURRENT_BEDROCK=6 /home/ubuntu/forseti.life/copilot-hq/scripts/orchestrator-loop.sh start 60
```

### Token Details
- **Type**: Fine-grained Personal Access Token (PAT)
- **Owner**: keithaumiller@github.com (inferred)
- **Scopes**: `repo`, `workflow` (very broad)
- **File backup**: `/home/ubuntu/github.token` (0600 perms)
- **Age**: Unknown (likely weeks/months)
- **Rotation policy**: None documented

### Why It Exists

From 2026-04-19 CEO session (release cycle health check):

1. `gh` CLI requires `gh auth login` to function
2. `gh auth login` requires the PAT to have `read:org` scope
3. The token at `/home/ubuntu/github.token` has `repo + workflow` but **NOT** `read:org`
4. Therefore `gh auth login` fails silently
5. **Workaround**: Pass `GH_TOKEN=...` as environment variable to bypass `gh auth login`

This was a valid emergency fix on 2026-04-19, but is not appropriate for long-term production.

---

## Current Usage

### Primary: Deploy Pipeline (Gate 5 — Production)
- **Triggered by**: Orchestrator detects release feature set is complete
- **Action**: `gh workflow run deploy.yml --repo keithaumiller/forseti.life --ref main`
- **Frequency**: Every 5+ minutes (via `orchestrator-watchdog.sh`)
- **Impact**: Pushes code to main branch, deploys to production

### Secondary: Health Checks (Manual)
- **Script**: `scripts/ceo-release-health.sh`
- **Actions**: Query workflow state, enable/disable workflows
- **Frequency**: On-demand CEO operations
- **Impact**: Can modify workflow status

### Called From
1. `orchestrator/run.py` — subprocess call to trigger deploy
2. `scripts/orchestrator-loop.sh` — sets `GH_TOKEN` env var on startup
3. `scripts/ceo-release-health.sh` — reads from `/home/ubuntu/github.token`

---

## Security Issues

### 🔴 Critical Severity

#### 1. Hardcoded in Crontab
**Problem**: Token visible in plain text in `crontab -l`, `ps aux`, process listings.

**Visibility**:
- `crontab -l` output (readable by root)
- `ps aux | grep orchestrator` (readable by any user)
- `top` / `htop` (visible to system administrators)
- `/proc/{pid}/environ` (readable by any user while process runs)
- Server logs (if crontab output is logged)

**Risk**: Single point of exposure — compromise of token is immediate.

**Mitigation**: Remove from crontab; read from file instead.

---

#### 2. Broad Token Scope
**Problem**: Token has `repo + workflow` scopes — can do anything in the repository.

**What attacker can do**:
- ✓ Push code to any branch (including main)
- ✓ Create/delete/modify releases and tags
- ✓ Trigger and modify workflows
- ✓ Modify GitHub Actions secrets
- ✓ Access all private repo content
- ✓ Deploy to production
- ✓ Add/remove branch protections

**What attacker cannot do**:
- ✗ Access other org repos (token scoped to keithaumiller/forseti.life)
- ✗ Modify org settings (no `read:org` scope)
- ✗ Invite/remove org members

**Mitigation**: Reduce scope to `workflow-only` or use ephemeral tokens (Options 1–3 below).

---

#### 3. Predictable File Location
**Problem**: Standard location `/home/ubuntu/github.token` is the first place attackers check.

**Risk**: If root is compromised, token is immediately accessible.

**Mitigation**: Move to hidden directory (Option 4) or eliminate need for file (Options 1, 3).

---

### 🟡 Moderate Severity

#### 4. No Rotation Policy
**Problem**: Token age unknown; no documented rotation schedule.

**Risk**: Compromised token may go undetected for months/years.

**Mitigation**: Add quarterly rotation + alerts.

---

#### 5. Visible in Process Environment
**Problem**: Child processes inherit `GH_TOKEN` env var; readable via `/proc/{pid}/environ`.

**Risk**: Process inspection reveals token.

**Mitigation**: Use ephemeral/short-lived tokens (Options 1, 3).

---

#### 6. No Audit Trail
**Problem**: No way to distinguish legitimate token usage from compromised usage.

**Risk**: Unauthorized deployments may go undetected.

**Mitigation**: Use auditable auth method (Options 1, 3).

---

### 🟢 Low Severity

#### 7. Not in .gitignore
**Problem**: `/home/ubuntu/github.token` is not listed in `.gitignore`.

**Risk**: Token could be accidentally committed to git.

**Mitigation**: Add to `.gitignore` immediately.

---

## Blast Radius

If the token is compromised, an attacker gains:

### Immediate Access
- Push code to `main` branch
- Trigger deploy workflow → deploy to production
- Modify any GitHub Actions secrets
- Access all private repo content (code, issues, PRs)
- Create/delete releases (software supply chain attack)

### Time to Impact
- **Immediate**: Code push and deploy can happen in seconds
- **Detection**: May not be detected for hours/days
- **Recovery**: Requires manual rollback or emergency branch protection

### Scope
- **Limited to**: `keithaumiller/forseti.life` repo only
- **Does NOT affect**: Other org repos, org settings, org members

---

## Solution Options

### **Option 1: GitHub App + Installation Token** ⭐⭐⭐
**Complexity**: Medium | **Security**: Excellent | **Effort**: 4–6 hours

How it works:
1. Create GitHub App with only `workflows: write` permission
2. Server stores app ID + private key
3. On each deploy, exchange for short-lived installation token (~1 hour expiry)
4. Token auto-refreshes; never stored in crontab or logs

**Pros**:
- Minimal scope (workflows only, not repo access)
- Short-lived tokens (auto-refresh, no manual rotation)
- Fully auditable via GitHub (see app activity in UI)
- Can revoke immediately
- Recommended by GitHub for CI/CD

**Cons**:
- Requires private key storage (still needs to be protected, but can be encrypted)
- More complex setup (app registration, key management)
- Requires changes to orchestrator/run.py

---

### **Option 2: File + orchestrator.py Reads It** ⭐⭐
**Complexity**: Low | **Security**: Good | **Effort**: 1–2 hours

How it works:
1. Remove `GH_TOKEN=...` from crontab @reboot line
2. Keep token in `/home/ubuntu/github.token`
3. Update `orchestrator-loop.sh` to NOT set GH_TOKEN
4. Update `orchestrator/run.py` to read from `$GH_TOKEN_FILE` env var or config
5. Token rotated via GitHub secret management

**Pros**:
- Removes crontab exposure (primary risk)
- Minimal code changes
- GitHub manages token lifecycle
- Easier to audit (no crontab grep)

**Cons**:
- Still need to store token on disk
- File is accessible to root if compromised
- Requires manual token rotation

**RECOMMENDED for short-term (this week).**

---

### **Option 3: OIDC + AWS IAM Role** ⭐⭐⭐⭐
**Complexity**: High | **Security**: Perfect | **Effort**: 8–12 hours

How it works:
1. Configure GitHub → AWS OIDC trust relationship
2. Orchestrator uses AWS SigV4 to assume an IAM role
3. Role has `github:workflow_run` permission to trigger deploy workflow
4. No token stored anywhere; AWS STS handles credentials
5. Every auth attempt is logged in CloudTrail

**Pros**:
- **Zero secrets stored anywhere** (most important)
- Short-lived AWS credentials (auto-refresh, cryptographic signing)
- Full audit trail via CloudTrail
- Industry standard (recommended by AWS, GitHub)
- Can be scoped to specific repos/workflows

**Cons**:
- Requires AWS IAM setup
- More moving parts (OIDC provider, IAM role)
- Slightly higher latency (STS call vs direct token)

**RECOMMENDED for long-term (next month).**

---

### **Option 4: Move to ~/.ssh/ + Rotate Regularly** ⭐
**Complexity**: Very Low | **Security**: Okay | **Effort**: 30 min

How it works:
1. Keep token but move to `~/.ssh/github.token` (hidden directory)
2. Add quarterly rotation reminder to calendar
3. Remove from crontab; read from file only
4. Document in runbook

**Pros**:
- Minimal changes to codebase
- Removes crontab exposure
- Easier to remember to rotate

**Cons**:
- Still not ideal (file still accessible to root)
- Manual rotation required (human error risk)
- Not recommended as long-term solution

**INTERIM ONLY (not recommended long-term).**

---

## Immediate Actions (Complete Today)

### 1. Rotate the Token (2 min)
```bash
# GitHub: Settings → Personal access tokens → Select token → Regenerate
# (Do this in GitHub UI)
```

### 2. Update Token File (2 min)
```bash
# On server:
echo "ghp_<NEW_TOKEN_HERE>" > /home/ubuntu/github.token
chmod 0600 /home/ubuntu/github.token
```

### 3. Update Crontab (1 min)
```bash
crontab -e
# Find @reboot line, replace:
# OLD: @reboot GH_TOKEN=ghp_... ORCHESTRATOR_AGENT_CAP=8 ...
# NEW: @reboot ORCHESTRATOR_AGENT_CAP=8 AGENT_EXEC_MAX_CONCURRENT_BEDROCK=6 /home/ubuntu/forseti.life/copilot-hq/scripts/orchestrator-loop.sh start 60 ...
```

### 4. Add to .gitignore (1 min)
```bash
cd /home/ubuntu/forseti.life
echo "/home/ubuntu/github.token" >> .gitignore
echo "/home/ubuntu/.gh/" >> .gitignore
git add .gitignore
git commit -m "Add github token file to gitignore"
```

### 5. Restart Orchestrator (wait 1 min)
- Watchdog will detect PID change and restart
- Or manually: `kill $(cat copilot-hq/.orchestrator-loop.pid) && sleep 2`
- Wait for orchestrator to restart (check `ps aux | grep orchestrator`)

---

## Short-Term Remediation (This Week) — **RECOMMENDED PATH**

Implement **Option 2** (Move to file):

### Changes Required

#### 1. Update `orchestrator-loop.sh`
```bash
# BEFORE:
# @reboot GH_TOKEN=ghp_... orchestrator-loop.sh start 60

# AFTER:
# @reboot orchestrator-loop.sh start 60
# (Remove GH_TOKEN= from @reboot line)

# In run_orchestrator_once() function:
# Add: export GH_TOKEN_FILE="/home/ubuntu/github.token"
```

#### 2. Update `orchestrator/run.py`
```python
# In dispatch_deploy() or wherever deploy is triggered:
# BEFORE:
# token = os.environ.get('GH_TOKEN')

# AFTER:
# token_file = os.environ.get('GH_TOKEN_FILE', '/home/ubuntu/github.token')
# with open(token_file) as f:
#     token = f.read().strip()
```

#### 3. Update `scripts/ceo-release-health.sh`
```bash
# Already does this correctly:
# TOKEN="$(cat /home/ubuntu/github.token)"
# No changes needed.
```

### Verification
```bash
# After changes:
crontab -l | grep "GH_TOKEN"  # Should return nothing
ps aux | grep orchestrator    # GH_TOKEN should NOT appear in process env
cat /home/ubuntu/github.token # Should contain token
```

---

## Long-Term Remediation (Next Month)

### Path: Option 1 (GitHub App) OR Option 3 (OIDC)

**Option 1 Setup** (GitHub App):
1. Create GitHub App: Settings → Developer settings → GitHub Apps → New
2. Set permissions: Only `workflows: write`
3. Store app ID + private key on server (encrypted)
4. Modify orchestrator to exchange app ID + key for installation token
5. Deploy and test

**Option 3 Setup** (OIDC + AWS):
1. Configure GitHub OIDC provider in AWS IAM
2. Create IAM role with assume-role policy (trusts GitHub OIDC provider)
3. Attach policy: Allow `actions:CreateWorkflowDispatch` on target workflow
4. Modify orchestrator to assume role via AWS SigV4 signing
5. Deploy and test

---

## Documentation Required

### 1. Knowledge Base Lesson
**Path**: `knowledgebase/lessons/github-token-management.md`

Topics:
- Why GitHub tokens are high-value targets
- Scope of current token (repo + workflow)
- How to detect compromise
- Emergency rotation procedure
- Best practices for CI/CD auth

### 2. Runbook: GitHub Token Rotation
**Path**: `runbooks/github-token-rotation.md`

Steps:
1. Generate new token in GitHub UI
2. Update `/home/ubuntu/github.token`
3. Test deploy pipeline
4. Verify no log leaks
5. Document completion date

### 3. Architect Instructions Update
**Path**: `org-chart/agents/instructions/architect-copilot.instructions.md`

Add:
- GitHub token location and usage
- Deploy pipeline architecture
- Rotation schedule
- Incident response (if token is compromised)

---

## Summary Table

| Aspect | Status | Priority |
|---|---|---|
| Hardcoded in crontab | ⚠️ CRITICAL | Immediate |
| Broad token scope | ⚠️ CRITICAL | This week |
| File location predictable | ⚠️ CRITICAL | This week |
| No rotation policy | 🟡 MODERATE | This month |
| No audit trail | 🟡 MODERATE | Next month |
| Not in .gitignore | 🟢 LOW | Immediate |
| Solution ready | ✅ YES (Option 2) | Implement now |

---

## Next Steps

1. **Today**: Rotate token, update .gitignore, restart orchestrator
2. **This week**: Implement Option 2 (move to file, remove from crontab)
3. **Next month**: Plan & implement Option 1 or 3 (long-term solution)
4. **Ongoing**: Quarterly token rotation + monitoring

---

**Analysis completed by**: architect-copilot  
**Date**: 2026-04-20  
**Status**: Ready for implementation
