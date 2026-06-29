# Repository Architecture: Monorepo + 11 Public Repos

**Last Updated:** 2026-04-21  
**Status:** Active (ISSUE-025 documentation update)

## Overview

Forseti.Life operates a **hybrid repository model:**
- **1 Private Monorepo** — Source of truth for operational code (deployment target)
- **11 Public Repositories** — Open-source mirrors and community integration points
- **Single Deploy Source** — Coordinated releases push to production from monorepo only

This document clarifies the architecture to prevent confusion about integration flow and GitHub token requirements.

---

## Repository Map

### Private Monorepo (Operational Source)

| Repo | URL | Owner | Purpose |
|------|-----|-------|---------|
| **forseti.life** | https://github.com/keithaumiller/forseti.life | keithaumiller | Production source code; deployment target |

**Authentication:** GitHub PAT in `/home/ubuntu/github.token` (personal account)

**What lives here:**
- Drupal sites (forseti, dungeoncrawler)
- Orchestrator and release cycle automation
- CI/CD pipelines (GitHub Actions)
- Deployment scripts and infrastructure code
- Copilot HQ orchestration (copilot-hq/)
- This file and architecture documentation

---

### Public Repositories (11 repos in Forseti-Life Organization)

#### Tier 1: Core Products (Actively Deployed)

| Repo | URL | Synced From | Purpose |
|------|-----|-------------|---------|
| **forseti-job-hunter** | https://github.com/Forseti-Life/forseti-job-hunter | monorepo/sites/forseti/ | Job hunter Drupal module; public API |
| **dungeoncrawler-pf2e** | https://github.com/Forseti-Life/dungeoncrawler-pf2e | monorepo/sites/dungeoncrawler/ | DungeonCrawler Drupal module; PF2E rules engine |

#### Tier 2: Shared Infrastructure

| Repo | URL | Synced From | Purpose |
|------|-----|-------------|---------|
| **forseti-shared-modules** | https://github.com/Forseti-Life/forseti-shared-modules | monorepo/shared/ | Shared Drupal modules, utilities |
| **forseti-mobile** | https://github.com/Forseti-Life/forseti-mobile | monorepo/mobile/ | Mobile app source (Dart/Flutter) |
| **forseti-meshd** | https://github.com/Forseti-Life/forseti-meshd | monorepo/meshd/ | Mesh network daemon |
| **h3-geolocation** | https://github.com/Forseti-Life/h3-geolocation | monorepo/h3-geolocation/ | H3 geospatial library |

#### Tier 3: Infrastructure & Tooling

| Repo | URL | Synced From | Purpose |
|------|-----|-------------|---------|
| **copilot-hq** | https://github.com/Forseti-Life/copilot-hq | monorepo/copilot-hq/ | Orchestration, release cycle, agent instructions |
| **forseti-devops** | https://github.com/Forseti-Life/forseti-devops | monorepo/devops/ | DevOps scripts, CI/CD, infrastructure |
| **forseti-docs** | https://github.com/Forseti-Life/forseti-docs | monorepo/docs/ | User & contributor documentation |

#### Tier 4: Community & Reference

| Repo | URL | Synced From | Purpose |
|------|-----|-------------|---------|
| **dungeoncrawler-content** | https://github.com/Forseti-Life/dungeoncrawler-content | monorepo/content/ | PF2E content, rules references |
| **forseti-platform-specs** | https://github.com/Forseti-Life/forseti-platform-specs | monorepo/specs/ | Platform specifications, RFCs |

**Authentication:** GitHub PAT in `/home/ubuntu/github.token` (org access required)

---

## Integration Flow

### 1. Development & Testing (Monorepo)

```
Developer commits to keithaumiller/forseti.life (main branch)
    ↓
GitHub Actions CI/CD runs (lint, test, build)
    ↓
Code review gate (agent-code-review) gates merge
    ↓
Merged to main — ready for release
```

### 2. Release Cycle (Monorepo)

```
Release cycle orchestrator (orchestrator/run.py) activates
    ↓
Features scoped from feature/ branches into release metadata
    ↓
Dev & QA teams implement and test
    ↓
All gates pass (Gate 1, 2, R5 production)
    ↓
Coordinated push to GitHub Actions:
  • Deploys Drupal sites (forseti, dungeoncrawler) to production
  • Runs database migrations
  • Triggers post-deployment audit
```

### 3. Public Repo Sync (Post-Release or Ad-Hoc)

```
Code exists in monorepo (already tested & deployed to production)
    ↓
CEO or Dev team decides: extract to public repo
    ↓
Create PR in public repo (e.g., forseti-job-hunter)
    ↓
Community review & contribution (optional)
    ↓
Merge to public repo (no automatic sync; manual cherry-pick model)
    ↓
Public repo becomes reference; community PRs can be backported
```

**Key principle:** Public repos are NOT deployment targets. They are mirrors and integration points. The monorepo is always source of truth for production.

---

## Authentication & Git Remotes

### GitHub Personal Access Token (PAT)

**File:** `/home/ubuntu/github.token`  
**Permissions:** `repo`, `workflow`, `public_repo`, `read:org`, `write:org`  
**Scope:** Works for both personal account (keithaumiller) and organization (Forseti-Life)

**Used by:**
- Orchestrator (`orchestrator/run.py`) — trigger GitHub Actions workflows
- Health checks (`scripts/ceo-release-health.sh`) — query workflow status
- CI/CD automation — read/write to all repos
- Public repo management — sync, mirror, backport PRs

### Git Remotes (Monorepo)

```bash
# Primary (production deployment source)
git remote named "origin"
URL: https://github.com/keithaumiller/forseti.life.git
Auth: GH_TOKEN env var (exported by crontab from /home/ubuntu/github.token)

# Community mirror (reference for contributors)
git remote named "community"
URL: https://github.com/Forseti-Life/forseti.life.git
Auth: GH_TOKEN env var (same token, org access)
```

**Why no embedded tokens:**
- Security: tokens not stored in `git config` (readable by any process)
- Portability: works across machines that set `GH_TOKEN`
- Rotation: update token file once; all commands pick it up

**How it works:**
```bash
# In crontab or shell startup:
export GH_TOKEN=$(cat /home/ubuntu/github.token)

# Commands automatically use it:
gh api /user                    # Uses $GH_TOKEN
gh workflow run deploy.yml      # Uses $GH_TOKEN
git push origin main            # Uses credential.helper + GH_TOKEN
```

---

## Health Checks & Monitoring

### Deploy Workflow Status

**Script:** `bash scripts/ceo-release-health.sh`

**What it checks:**
- Deploy workflow (`deploy.yml`) enabled in GitHub Actions? ✅
- Coordinated push credentials valid? (GH_TOKEN auth check)
- Release cycle metadata current?
- PM signoffs recorded?
- Feature backlog empty?

**Example output:**
```
────────────────────────────────────────────────────────────────
  GitHub Actions: deploy.yml
────────────────────────────────────────────────────────────────
✅ PASS GitHub Actions deploy.yml enabled and responsive (workflow ID: 12345)
✅ PASS GH_TOKEN authentication valid
✅ PASS All PM signoffs present — coordinated push ready
```

### Multi-Repo Access Verification

**Script:** `bash scripts/multirepository-validator.sh` (if available)

**What it checks:**
- Can read all 11 Forseti-Life public repos
- Can read personal account monorepo
- Token has required scopes

**Run after PAT rotation:**
```bash
GH_TOKEN=$(cat /home/ubuntu/github.token) \
  gh repo list Forseti-Life --limit 20
```

Expected: List of all 11 repos

---

## Deployment & Release Model

### Current Release Cycles

1. **Monorepo Push (Coordinated)**
   - Triggers `deploy.yml` GitHub Actions workflow
   - Deploys Drupal sites to production
   - Runs post-deployment audit (Gate R5)
   - Public repos NOT pushed (no automatic sync)

2. **Public Repo Sync (Manual/As-Needed)**
   - Code review team extracts module or code to public repo
   - Tests integration in public repo environment
   - Merges PR; opens for community contributions
   - Changes can be backported or cherry-picked to monorepo next cycle

### Future Enhancement: Automated Public Repo Sync

**Planned (not yet implemented):**
- After coordinated push succeeds, automatically sync Tier 1 & 2 repos
- Trigger CI/CD in public repos to validate integration
- Community PRs automatically discovered and triaged

**Blocked by:** ISSUE-022 (GitHub PAT provisioning)

---

## Documentation & Configuration

### Key Files

| File | Purpose |
|------|---------|
| `.github/copilot-instructions.md` | Copilot CLI & orchestrator auth config |
| `.github/instructions/` | GitHub Actions workflows, deployment steps |
| `copilot-hq/scripts/ceo-release-health.sh` | Health check for releases & multi-repo access |
| `copilot-hq/org-chart/` | Org structure, agent assignments, permissions |
| `/home/ubuntu/github.token` | GitHub PAT (not in git; runtime secret) |

### Crontab Configuration

```bash
# Exported by crontab; makes GH_TOKEN available to all processes
export GH_TOKEN=$(cat /home/ubuntu/github.token)
```

Updates to `/home/ubuntu/github.token` automatically picked up on next crontab run.

---

## FAQ

**Q: How do I deploy code to production?**  
A: Coordinated push from monorepo (keithaumiller/forseti.life). Push goes to Drupal sites (forseti.life and dungeoncrawler). Public repos are NOT deployment targets.

**Q: Can I commit directly to a public repo and have it auto-deploy?**  
A: No. Public repos are mirrors. Deploy from monorepo only. Changes in public repos must be cherry-picked or backported to monorepo first.

**Q: What if I want to update forseti-job-hunter (the public repo)?**  
A: 
1. Make change in monorepo (`sites/forseti/` modules)
2. Test in monorepo release cycle
3. Extract/backport change to forseti-job-hunter public repo
4. Community can review and contribute there
5. Next release cycle: cherry-pick contributions back to monorepo if desired

**Q: How often are public repos synced?**  
A: Manually/as-needed (no automated sync yet). Dev team decides when to extract modules for community.

**Q: Do I need a different token for each repo?**  
A: No. Single PAT in `/home/ubuntu/github.token` works for personal account (keithaumiller) AND organization (Forseti-Life) with appropriate scopes.

**Q: What happens if the GitHub token expires?**  
A: Next coordinated push will fail (401 auth error). Board must provision new token; CEO deploys to `/home/ubuntu/github.token`. Current releases unaffected (already pushed); next release waits for token fix.

---

## Related Issues

- **ISSUE-022:** GitHub PAT invalid (awaiting Board provisioning)
- **ISSUE-025:** This document (multi-repo architecture clarification)

## Related Documents

- `.github/copilot-instructions.md` — Auth setup details
- `copilot-hq/org-chart/roles/ceo.instructions.md` — Multi-repository authority & decision rules
- `copilot-hq/runbooks/shipping-gates.md` — Release & deployment gates

