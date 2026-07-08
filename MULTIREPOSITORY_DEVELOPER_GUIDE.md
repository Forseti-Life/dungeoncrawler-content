# Multi-Repository Developer Guide

**Last Updated:** April 21, 2026  
**Architecture Version:** 2.0 (Broken-Out Repos)

---

## Quick Overview

Forseti.life uses a **hybrid architecture**:
- **Private monorepo** (`/home/ubuntu/forseti.life`) — Operational deployment source
- **11 public repos** (Forseti-Life organization) — Community open-source

This guide covers working with both simultaneously.

---

## Repository Structure

### Private Operational Monorepo

```
/home/ubuntu/forseti.life/
├── copilot-hq/           # Organizational model & release orchestration
├── sites/                # Drupal site configs (production sites)
├── forseti/              # Job Hunter source (extracted to public repo)
├── dungeoncrawler/       # D&D assistant source (extracted to public repo)
├── forseti-mobile/       # Mobile app source (extracted to public repo)
├── forseti-meshd/        # Mesh network source (extracted to public repo)
├── h3-geolocation/       # Geolocation library (extracted to public repo)
├── shared/               # Shared modules (extracted to public repo)
├── prod-config/          # Production credentials & config (NEVER PUBLIC)
├── script/               # Deployment & maintenance scripts
├── orchestrator/         # Release cycle orchestrator
└── README.md             # This file (updated 2026-04-21)
```

### Public Repositories (Forseti-Life Organization)

#### Tier 1: Core Products
- `forseti-job-hunter` — Job search platform
- `dungeoncrawler-content` — D&D campaign assistant

#### Tier 2: Developer Libraries
- `forseti-shared-modules` — Reusable Drupal modules
- `forseti-mobile` — iOS/Android applications
- `forseti-meshd` — Peer-to-peer mesh networking
- `h3-geolocation` — Geospatial integration

#### Tier 3: Operations & Tooling
- `copilot-hq` — Organizational & release management
- `forseti-devops` — DevOps automation & deployment
- `forseti-docs` — Central documentation hub

#### Tier 4: Content & Reference
- `dungeoncrawler-content` — Game rules & content data
- `forseti-platform-specs` — Architecture specifications

---

## Developer Workflows

### Workflow 1: Internal Development (Private Monorepo)

**Use Case:** You're modifying code for the next release, testing locally, then deploying.

```bash
# 1. Clone private monorepo
cd /home/ubuntu/forseti.life

# 2. Create feature branch
git checkout -b feature/my-feature

# 3. Make changes in whichever directories
vim forseti/modules/job-search/search.module
vim orchestrator/run.py

# 4. Test locally on dev machine or staging server
./script/verify-setup.sh

# 5. Commit with descriptive message
git add -A
git commit -m "feat: improve job search relevance" \
  -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"

# 6. Push to origin (personal account)
git push origin feature/my-feature

# 7. Create PR for review (on keithaumiller/forseti.life)
# Then merge and deploy via orchestrator
```

### Workflow 2: Public Contribution (Public Repo)

**Use Case:** External contributor wants to improve forseti-job-hunter.

```bash
# 1. Fork the public repo on GitHub
# Visit: https://github.com/Forseti-Life/forseti-job-hunter/fork

# 2. Clone your fork
git clone https://github.com/YOUR_USERNAME/forseti-job-hunter.git
cd forseti-job-hunter

# 3. Create feature branch
git checkout -b fix/job-listing-pagination

# 4. Make changes
vim src/Plugin/Block/JobListingBlock.php
# ... write code ...

# 5. Test
./script/test.sh

# 6. Commit and push to your fork
git add -A
git commit -m "fix: improve job listing pagination"
git push origin fix/job-listing-pagination

# 7. Create pull request to Forseti-Life/forseti-job-hunter
# Via GitHub UI: https://github.com/Forseti-Life/forseti-job-hunter/pull/new/main

# 8. Wait for review and merge (maintainers will integrate back to monorepo)
```

### Workflow 3: Cross-Repo Contribution (Dependent Public Repos)

**Use Case:** You're improving a library (e.g., forseti-shared-modules) that's used by a product (forseti-job-hunter).

```bash
# 1. Clone both repos locally
git clone https://github.com/Forseti-Life/forseti-shared-modules.git
git clone https://github.com/Forseti-Life/forseti-job-hunter.git

# 2. Make changes to the library
cd forseti-shared-modules
git checkout -b feature/add-cache-layer
vim src/Cache/CacheManager.php

# 3. Test locally or link to job-hunter
cd ../forseti-job-hunter
composer require --dev ../forseti-shared-modules

# 4. Test integration
./script/test.sh

# 5. Push both repos and create PRs
# First: forseti-shared-modules PR
# Second: forseti-job-hunter PR (linking to first PR)

# 6. Maintainers coordinate merges and integration to monorepo
```

### Workflow 4: Bug Fix in Production (Monorepo → Public Repos)

**Use Case:** Production issue found; fix in private monorepo, backport to public repo.

```bash
# 1. Fix in private monorepo
cd /home/ubuntu/forseti.life
git checkout -b hotfix/security-vulnerability
vim forseti/modules/user/auth.module
# ... fix security issue ...

# 2. Create hotfix PR and merge to main
git add -A
git commit -m "fix: close authentication bypass"
git push origin hotfix/security-vulnerability
# Merge via GitHub → Deploy via orchestrator

# 3. Extract hotfix to public repo
cd /path/to/public/forseti-job-hunter
git pull origin main
# Manually apply same changes from monorepo
vim src/User/Auth.php
# ... apply same fix ...
git add -A
git commit -m "fix: close authentication bypass (backport from monorepo)"
git push origin hotfix/security-vulnerability
# Create PR and merge

# 4. Verify both repos have the same fix
git log --oneline | head -3
# Check public repo for matching commit message
```

---

## Setting Up for Development

### Prerequisites

```bash
# Ensure you have:
- git
- composer (for Drupal/PHP work)
- docker (for running locally)
- GitHub CLI (gh)
```

### One-Time Setup: Git Configuration

```bash
# Configure git globally
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Configure credential caching (for GitHub token)
git config --global credential.helper store

# Optional: SSH keys (alternative to token)
ssh-keygen -t ed25519 -C "your.email@example.com"
cat ~/.ssh/id_ed25519.pub  # Add to GitHub SSH keys
```

### Clone Public Repos for Reference

```bash
# Create a workspace directory
mkdir -p ~/forseti-workspace
cd ~/forseti-workspace

# Clone all public repos (shallow clone to save space)
for repo in forseti-job-hunter dungeoncrawler-content forseti-shared-modules \
            forseti-mobile forseti-meshd h3-geolocation copilot-hq \
            forseti-devops forseti-docs dungeoncrawler-content forseti-platform-specs; do
  git clone --depth 1 https://github.com/Forseti-Life/$repo.git
done

# Navigate to a specific repo
cd forseti-job-hunter
```

---

## Authentication

### GitHub Token (Centralized, Private Monorepo Only)

**Location:** `/home/ubuntu/github.token`  
**Used by:** Orchestrator, CI/CD scripts, health checks  
**Scope:** Personal account + Forseti-Life organization access

```bash
# Verify token is valid (requires access to system)
export GH_TOKEN=$(cat /home/ubuntu/github.token)
gh api /user
gh api orgs/Forseti-Life
```

### SSH Keys (Public Repos, Recommended)

```bash
# Generate SSH key (if not already done)
ssh-keygen -t ed25519 -C "your.email@example.com"

# Add to GitHub: Settings → SSH and GPG keys → New SSH key
cat ~/.ssh/id_ed25519.pub

# Clone using SSH (no token needed)
git clone git@github.com:Forseti-Life/forseti-job-hunter.git
```

### HTTPS + Token (Alternative)

```bash
# If using HTTPS, store token in git credential store
echo "https://USERNAME:$GITHUB_TOKEN@github.com" > ~/.git-credentials
git config --global credential.helper store

# Then clone normally
git clone https://github.com/Forseti-Life/forseti-job-hunter.git
```

---

## Testing Strategy

### Public Repo (Standalone Testing)

```bash
# Each public repo has its own test suite
cd forseti-job-hunter

# Run tests
./script/test.sh
npm test          # If JavaScript project
php vendor/bin/phpunit  # If PHP project
pytest            # If Python project

# Check code quality
./script/lint.sh

# Run locally in Docker
docker-compose up -d
docker-compose run web bash -c "npm test"
```

### Monorepo (Integration Testing)

```bash
cd /home/ubuntu/forseti.life

# Run full test suite
./script/verify-setup.sh

# Run specific site tests
docker-compose -f sites/forseti-prod/docker-compose.yml run web npm test

# Test orchestrator logic
cd orchestrator && python3 -m pytest tests/ -v
```

### Before Opening a PR

```bash
# 1. Update your branch
git fetch origin
git rebase origin/main

# 2. Run all relevant tests
./script/test.sh

# 3. Check code style
./script/lint.sh

# 4. Verify no security issues
npm audit
composer audit
pip install safety && safety check

# 5. Run one more full test
npm test
# or
pytest
# or
./vendor/bin/phpunit

# 6. If all green, push and create PR
git push origin feature/my-feature
```

---

## Dependency Management

### Public Repos (Independent)

Each public repo manages its own dependencies:

```bash
# JavaScript projects
npm install
npm update
npm audit

# PHP projects (Drupal modules)
composer install
composer update
composer require vendor/package

# Python projects
pip install -r requirements.txt
pip install -e .  # For development
```

### Cross-Repo Dependencies

If `forseti-job-hunter` depends on `forseti-shared-modules`:

```bash
# Option 1: Via Composer (PHP)
cd forseti-job-hunter
composer require "forseti-life/forseti-shared-modules:dev-main"

# Option 2: Via package.json (JavaScript)
cd forseti-job-hunter
npm install --save "git://github.com/Forseti-Life/forseti-shared-modules.git#main"

# Option 3: Local development (symlink)
cd forseti-job-hunter
npm link ../forseti-shared-modules
```

### Monorepo Dependencies

All repos are subdirectories; dependencies are managed at workspace level:

```bash
cd /home/ubuntu/forseti.life

# Install all dependencies
./script/quick-start.sh

# Or per-directory
cd forseti && npm install
cd ../dungeoncrawler && npm install
cd ../shared && composer install
```

---

## Release Process

### For Public Repos

1. **Maintainer** reviews and approves PRs
2. **Maintainer** merges to `main`
3. **GitHub Actions** automatically:
   - Runs tests and linting
   - Generates API docs
   - Creates GitHub Release (tagged version)
   - Publishes to package registry (npm, PyPI, etc.)

### For Private Monorepo

1. **CEO orchestrator** coordinates multi-team release
2. **Dev agents** implement features → create PRs
3. **Code review** gate reviews all changes
4. **QA gate** validates
5. **PM gate** approves
6. **Coordinated push** to `main` (all teams together)
7. **Deploy workflow** runs via GitHub Actions
8. **Production audit** validates deployment

### Integration: Public → Private

Changes in public repos → integrated to monorepo in next release cycle:

```bash
# 1. Upstream PR merged to Forseti-Life/forseti-job-hunter
# 2. CEO manually integrates changes to private monorepo:

cd /home/ubuntu/forseti.life/forseti
git remote add public https://github.com/Forseti-Life/forseti-job-hunter.git
git fetch public main
git merge public/main  # or cherry-pick specific commits

# 3. Resolve conflicts (if any)
git add -A
git commit -m "Integrate public forseti-job-hunter changes"

# 4. Next orchestrator release cycle deploys to production
```

---

## Troubleshooting

### Can't Clone Public Repo

```bash
# Check GitHub token
gh auth status

# If token expired, regenerate at: https://github.com/settings/tokens
# Store in ~/.git-credentials or use SSH keys instead

# Verify network access
curl -I https://github.com
ping github.com
```

### Public Repo Out of Sync with Monorepo

```bash
# 1. Check monorepo version
cd /home/ubuntu/forseti.life/forseti
git log --oneline | head -3

# 2. Check public repo version
cd /path/to/forseti-job-hunter
git log --oneline | head -3

# 3. If public is behind, cherry-pick commits
git remote add mono /home/ubuntu/forseti.life/forseti
git fetch mono main
git log mono/main --oneline | head -5
git cherry-pick COMMIT_HASH  # Pick specific commits to port forward

# 4. Push to public repo
git push origin main
```

### Tests Failing Locally but Passing in CI

```bash
# 1. Check environment differences
echo "Local: $(npm --version), Node: $(node --version)"
echo "CI uses: Node 18.x, npm 9.x"

# 2. Clear cache and reinstall
rm -rf node_modules package-lock.json
npm install

# 3. Run same test command as CI
npm run lint && npm run test

# 4. Check for system-specific issues (Docker)
docker-compose run web npm test
```

### Merge Conflicts Between Monorepo and Public Repos

```bash
# When integrating public changes back to monorepo

# 1. Identify conflicts
git status  # Shows "both modified" files

# 2. Review each conflict
git diff forseti/modules/job-search/search.module

# 3. Manually resolve (keep both versions? prefer one?)
vim forseti/modules/job-search/search.module
# Edit until conflict markers (<<<, ===, >>>) are removed

# 4. Test the merge
./script/verify-setup.sh

# 5. Complete merge
git add -A
git commit -m "Merge public forseti-job-hunter changes, resolve conflicts"
```

---

## Best Practices

### For All Developers

1. **Keep branches short-lived** — Merge within 24-48 hours
2. **Write descriptive commits** — Future-you will thank you
3. **Test before pushing** — No surprise failures
4. **Document changes** — Update README if functionality changes
5. **Review your own PR first** — Catch obvious issues before review

### For Public Repo Contributors

1. **Follow CONTRIBUTING.md** — Each repo has guidelines
2. **Sign commits** — Use GPG signature (git commit -S)
3. **Link issues** — Reference GitHub issues in PRs (#42)
4. **Keep PRs focused** — One feature per PR
5. **Respond to feedback** — Be respectful and engaged

### For Maintainers (Internal Team)

1. **Merge public PRs regularly** — Don't let backlog grow
2. **Keep documentation updated** — Especially for monorepo changes
3. **Coordinate cross-repo changes** — Communicate timing
4. **Backport hotfixes** — Security fixes go to all versions
5. **Monitor CI/CD** — Fix failing builds quickly

---

## Resources

- **GitHub Organization:** https://github.com/Forseti-Life
- **Private Monorepo:** `/home/ubuntu/forseti.life`
- **Documentation Hub:** `/home/ubuntu/forseti.life/docs/`
- **Architecture Specs:** `/home/ubuntu/forseti.life/forseti-platform-specs`
- **Contribution Guide:** Each repo has `CONTRIBUTING.md`
- **Code of Conduct:** Each repo has `CODE_OF_CONDUCT.md`
- **Security Policy:** Each repo has `SECURITY.md`

---

## Questions?

- Check the specific repo's README.md
- Review the repo's issues and discussions
- Open a GitHub issue with detailed information
- For private monorepo questions, contact the internal team

---

**Last Updated:** April 21, 2026  
**Maintained by:** Forseti Architects & Copilot Team
