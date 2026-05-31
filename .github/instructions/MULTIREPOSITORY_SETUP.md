# Multi-Repository Setup & GitHub Token Configuration

**Purpose:** Configure git remotes and GitHub authentication for Forseti.Life broken-out repository model.

**Components:**
- 1 private monorepo (keithaumiller/forseti.life) — deployment source
- 11 public repos (Forseti-Life org) — open-source mirrors

---

## Prerequisites

1. **GitHub Account:** Access to keithaumiller personal account + Forseti-Life organization
2. **GitHub PAT (Personal Access Token)**
   - Scopes: `repo`, `workflow`, `public_repo`, `read:org`, `write:org`
   - Stored in: `/home/ubuntu/github.token` (not in git; runtime-only)
3. **System Permissions:** Write access to `/home/ubuntu/`

---

## Initial Token Setup (One-Time)

### 1. Generate GitHub PAT

On GitHub (https://github.com/settings/tokens/new):

```
Name: Forseti.Life Orchestration
Scopes:
  ✓ repo (full control of private repositories)
  ✓ workflow (manage GitHub Actions workflows)
  ✓ public_repo (access public repositories)
  ✓ read:org (read organization)
  ✓ write:org (write organization)
Expiration: 90 days (rotate before expiration)
```

### 2. Save Token Locally (Not in Git)

```bash
# Create token file (read-only by user)
echo "ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxx" > /home/ubuntu/github.token
chmod 600 /home/ubuntu/github.token

# Verify no token in git config
git config --global credential.helper
# Expected output: (empty, or non-token cache)
```

**Important:** Never commit the token file. It's `.gitignore`d.

### 3. Set Up Environment Variable

**For interactive shells:**
```bash
# Add to ~/.bashrc or ~/.zshrc
export GH_TOKEN=$(cat /home/ubuntu/github.token)
```

**For crontab:**
```bash
# In crontab or script that schedules tasks
0 * * * * export GH_TOKEN=$(cat /home/ubuntu/github.token) && /home/ubuntu/forseti.life/scripts/release-health-check.sh
```

**For GitHub Actions workflows:**
- Use GitHub Secrets, not this token file
- (Orchestrator runs locally; workflows have separate auth)

---

## Git Remote Configuration

### Primary Remotes

```bash
cd /home/ubuntu/forseti.life

# Main deployment source
git remote add origin https://github.com/keithaumiller/forseti.life.git

# Community reference (optional)
git remote add community https://github.com/Forseti-Life/forseti.life.git
```

### Verify Remotes

```bash
git remote -v
```

Expected output:
```
origin    https://github.com/keithaumiller/forseti.life.git (fetch)
origin    https://github.com/keithaumiller/forseti.life.git (push)
community https://github.com/Forseti-Life/forseti.life.git (fetch)
community https://github.com/Forseti-Life/forseti.life.git (push)
```

### No Embedded Tokens

Git commands automatically use `$GH_TOKEN` environment variable for authentication (via `credential.helper`). **Do NOT add tokens to `.git/config`** — this would expose them to any process on the system.

---

## Public Repository References

These 11 repos are accessible via GH_TOKEN:

### Tier 1: Core Products
- `Forseti-Life/forseti-job-hunter`
- `Forseti-Life/dungeoncrawler-pf2e`

### Tier 2: Shared Infrastructure
- `Forseti-Life/forseti-shared-modules`
- `Forseti-Life/forseti-mobile`
- `Forseti-Life/forseti-meshd`
- `Forseti-Life/h3-geolocation`

### Tier 3: Infrastructure & Tooling
- `Forseti-Life/copilot-hq`
- `Forseti-Life/forseti-devops`
- `Forseti-Life/forseti-docs`

### Tier 4: Community & Reference
- `Forseti-Life/dungeoncrawler-content`
- `Forseti-Life/forseti-platform-specs`

**List all 11 repos:**
```bash
export GH_TOKEN=$(cat /home/ubuntu/github.token)
gh repo list Forseti-Life --limit 20
```

---

## Testing Token & Remote Access

### 1. Verify Token Scopes

```bash
export GH_TOKEN=$(cat /home/ubuntu/github.token)
gh api /user
```

Expected: JSON object with `login`, `name`, `id` (200 OK)

### 2. Verify Organization Access

```bash
gh api orgs/Forseti-Life
```

Expected: JSON object with org details (200 OK)

### 3. Verify Git Push

```bash
cd /home/ubuntu/forseti.life
git pull origin main
git status
git push --dry-run origin main
```

Expected: No auth errors; connection successful

### 4. Verify Public Repo Access

```bash
gh repo view Forseti-Life/forseti-job-hunter
gh repo view Forseti-Life/copilot-hq
```

Expected: JSON with repo details for each (200 OK)

---

## Token Rotation (Every 90 Days)

1. Generate new token on GitHub
2. Update `/home/ubuntu/github.token` file
3. Run health check to verify:
   ```bash
   bash scripts/ceo-release-health.sh | grep "GitHub"
   ```
4. Commit old token deletion (mark in team log, do not commit token itself)

---

## Troubleshooting

### "401 Unauthorized" during `git push`

```bash
# Check token is set
echo $GH_TOKEN

# Check token is valid
curl -H "Authorization: token $GH_TOKEN" https://api.github.com/user

# Re-export if needed
export GH_TOKEN=$(cat /home/ubuntu/github.token)
git push origin main
```

### "Fatal: could not read credentials"

```bash
# Token file permissions issue
ls -la /home/ubuntu/github.token
# Should be: -rw------- (600)

# Fix permissions
chmod 600 /home/ubuntu/github.token

# Re-export and retry
export GH_TOKEN=$(cat /home/ubuntu/github.token)
```

### "Repository not found" for public repo

```bash
# Verify org access
gh api orgs/Forseti-Life

# Verify repo exists
gh repo view Forseti-Life/forseti-job-hunter

# If 404: repo may not exist or token lacks org access
```

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│  DEVELOPER COMMITS                                              │
│  git push origin main                                           │
└──────────────────────────┬──────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│  PRIVATE MONOREPO                                               │
│  keithaumiller/forseti.life                                     │
│  • Drupal sites (forseti, dungeoncrawler)                       │
│  • Orchestrator (orchestrator/run.py)                           │
│  • Copilot HQ (copilot-hq/)                                     │
└──────────────────────────┬──────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│  GITHUB ACTIONS (deploy.yml)                                    │
│  Auth: $GH_TOKEN from /home/ubuntu/github.token                │
│  • Lint, test, build                                            │
│  • Deploy to production                                         │
│  • Run post-deploy audit (Gate R5)                              │
└──────────────────────────┬──────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│  PRODUCTION DEPLOYMENT                                          │
│  • Drupal sites live at forseti.life, dungeoncrawler.life       │
│  • Database migrated                                            │
│  • Audit completed                                              │
└──────────────────────────┬──────────────────────────────────────┘
                           ↓
        ┌──────────────────┴──────────────────┐
        ↓                                     ↓
   ┌────────────────────┐          ┌─────────────────────┐
   │ PUBLIC REPOS       │          │ OPTIONAL:           │
   │ (11 Forseti-Life)  │          │ Community PRs       │
   │ • forseti-*        │          │ Cherry-pick to      │
   │ • dungeoncrawler-* │          │ monorepo next cycle │
   │ • copilot-hq       │          └─────────────────────┘
   │ • etc.             │
   │ (Manual or         │
   │  future: automated │
   │  sync)             │
   └────────────────────┘
```

---

## Related

- `REPOSITORY_ARCHITECTURE.md` — High-level architecture overview
- `.github/copilot-instructions.md` — Orchestrator setup
- `scripts/ceo-release-health.sh` — Health monitoring
