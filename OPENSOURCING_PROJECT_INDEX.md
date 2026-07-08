# Forseti.Life Open-Sourcing Project: Master Index

**Project Status:** ✅ Phase 1 Complete — GitHub repositories created  
**Date:** 2026-04-20  
**Organization:** https://github.com/Forseti-Life  

---

## What This Project Is

The Forseti.Life open-sourcing project is splitting the private operational monorepo into **11 purpose-built public repositories** on GitHub. This allows community members to use, contribute to, and build upon Forseti projects while keeping the operational infrastructure private.

---

## Key Documents

### Strategic Planning
- **MONOREPO_SPLIT_PLAN_EXECUTIVE.md** — Complete open-sourcing strategy
  - Repository tier system (products → libraries → tools → reference)
  - Content extraction roadmap
  - Community engagement model
  - Success metrics

### Operations Reference
- **GITHUB_ORGANIZATION_REFERENCE.md** — How to work with the repos
  - Repository quick reference
  - Content extraction checklist
  - Contribution workflows
  - Maintenance cadence
  - GitHub Actions setup

### This Document
- **OPENSOURCING_PROJECT_INDEX.md** — You are here
  - Master index of all planning documents
  - Quick reference to what's been done
  - Next steps

---

## Repositories Created

### 🎯 Tier 1: Core Products (2 repos)
**These are the main user-facing products.**

| Repo | Purpose | URL |
|------|---------|-----|
| **forseti-job-hunter** | Job search platform | https://github.com/Forseti-Life/forseti-job-hunter |
| **dungeoncrawler-content** | PF2E campaign management | https://github.com/Forseti-Life/dungeoncrawler-content |

### 📚 Tier 2: Libraries & Services (4 repos)
**These are libraries and services for integration.**

| Repo | Purpose | URL |
|------|---------|-----|
| **forseti-shared-modules** | Drupal modules | https://github.com/Forseti-Life/forseti-shared-modules |
| **forseti-mobile** | iOS/Android apps | https://github.com/Forseti-Life/forseti-mobile |
| **forseti-meshd** | Mesh network | https://github.com/Forseti-Life/forseti-meshd |
| **h3-geolocation** | Geospatial integration | https://github.com/Forseti-Life/h3-geolocation |

### 🛠️ Tier 3: Tooling & Operations (3 repos)
**These are tools for advanced users and operators.**

| Repo | Purpose | URL |
|------|---------|-----|
| **copilot-hq** | Organizational governance | https://github.com/Forseti-Life/copilot-hq |
| **forseti-devops** | DevOps automation | https://github.com/Forseti-Life/forseti-devops |
| **forseti-docs** | Documentation hub | https://github.com/Forseti-Life/forseti-docs |

### 📋 Tier 4: Content & Reference (2 repos)
**These are reference data and specifications.**

| Repo | Purpose | URL |
|------|---------|-----|
| **dungeoncrawler-content** | Game rules data | https://github.com/Forseti-Life/dungeoncrawler-content |
| **forseti-platform-specs** | Architecture specs | https://github.com/Forseti-Life/forseti-platform-specs |

---

## Content Extraction Timeline

### Phase 1: Week 1 (Core Products Launch)
- [ ] Extract forseti/ → forseti-job-hunter
- [ ] Extract dungeoncrawler/ → dungeoncrawler-content
- [ ] Add comprehensive README templates
- [ ] Setup GitHub Actions CI
- **Goal:** Public availability of core products

### Phase 2: Week 2 (Developer Libraries)
- [ ] Extract shared/modules → forseti-shared-modules
- [ ] Extract forseti-mobile → forseti-mobile
- [ ] Extract forseti-meshd → forseti-meshd
- [ ] Setup dependency linking
- **Goal:** Developers can integrate libraries

### Phase 3: Week 3 (Operations & Docs)
- [ ] Extract copilot-hq → copilot-hq
- [ ] Extract script/ + deployment/ → forseti-devops
- [ ] Extract docs/ → forseti-docs
- [ ] Create documentation index
- **Goal:** Complete operations documentation

### Phase 4: Ongoing (Reference Data)
- [ ] Extract game data → dungeoncrawler-content
- [ ] Extract specs → forseti-platform-specs
- [ ] Publish API documentation
- **Goal:** Complete reference library

---

## File Structure in Repos

Each repository is initialized with this template:

```
├── README.md                        # Project overview, quick start
├── CONTRIBUTING.md                  # How to contribute
├── CODE_OF_CONDUCT.md               # Community standards
├── LICENSE                          # MIT License
├── SECURITY.md                      # Security reporting policy
├── .gitignore                       # Exclude secrets, builds
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.md
│   │   ├── feature_request.md
│   │   └── documentation.md
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── workflows/
│       ├── lint.yml
│       ├── test.yml
│       └── security.yml
├── docs/
│   ├── README.md
│   ├── ARCHITECTURE.md
│   ├── API.md
│   └── DEPLOYMENT.md
└── [Product-specific source code]
```

---

## Key Principles

### 1. Independence
Each repo is independently useful. You can use any repo without needing others.

### 2. Clarity
Each repo has a clear purpose and target audience defined in its README.

### 3. Discoverability
GitHub topics are set on each repo for searching and finding projects.

### 4. Security
All public repos exclude secrets, credentials, and private configuration.

### 5. Documentation
Each repo includes comprehensive README, API docs, and architecture guides.

### 6. Community
Clear contribution paths, governance, and community engagement models.

---

## Private vs. Public

### What Stays in Private Monorepo
- Production credentials and API keys
- Database backups and exports
- Operational session data
- Site-specific configuration
- Internal analytics

### What Goes to Public Repos
- Source code (clean, production-ready)
- Documentation (setup, API, architecture)
- Configuration templates (.env.example)
- Community examples
- Contributing guidelines

### How They Relate
1. Changes made in public repos
2. PR created and reviewed
3. Merged into public repo
4. Integrated back into private monorepo for deployment

---

## Community Engagement

### Finding Projects
1. Visit https://github.com/Forseti-Life
2. Browse repositories by category (Tier 1-4)
3. Search by topic (job-search, pathfinder, drupal, etc.)

### Contributing
1. Read repository README and CONTRIBUTING.md
2. Look for "good-first-issue" or "help-wanted" labels
3. Submit pull request following templates
4. Respond to code review
5. Your changes merged!

### Reporting Issues
1. Search existing issues first
2. Use appropriate issue template (bug, feature, docs)
3. Provide reproducible examples
4. Maintainers will respond within SLA

---

## Success Metrics

### Engagement
- ⭐ Stars — Community interest
- 👥 Contributors — Participation
- 💬 Issues/discussions — Engagement
- 🔗 Pull requests — Contribution rate

### Adoption
- 📦 Downloads — Usage rate
- 🔄 Integrations — Ecosystem
- 🚀 Deployments — Real-world use
- 📈 Growth — Community momentum

---

## Next Steps to Implement

### Immediate (Ready to do)
1. **Add README templates** to each repo
2. **Setup GitHub Actions** for CI/CD
3. **Create issue templates** (bug, feature, docs)

### Short-term (Ready to do)
1. **Extract content** from monorepo (Phase 1-4)
2. **Validate functionality** in public repos
3. **Setup dependency linking** between repos

### Medium-term (Ready to do)
1. **Publish documentation** hub
2. **Announce** project to communities
3. **Invite** early contributors
4. **Monitor** feedback and iterate

---

## Documentation Links

### Strategic Documents
- MONOREPO_SPLIT_PLAN_EXECUTIVE.md — Full strategy document
- GITHUB_ORGANIZATION_REFERENCE.md — Operations guide

### Repository Details
Each repository will include:
- README.md — Project overview and quick start
- CONTRIBUTING.md — How to contribute
- docs/ARCHITECTURE.md — System design
- docs/API.md — API documentation (if applicable)

---

## Important Notes

✅ **What's Done:**
- GitHub organization created
- 11 repositories initialized
- Repository structure designed
- Documentation written
- Topics set for discoverability

✅ **What's Not Done Yet:**
- Content extraction from monorepo
- GitHub Actions workflows
- Community template setup
- Public announcement

✅ **No Local Changes:**
- Private monorepo remains unchanged
- Production credentials remain private
- Ready for extraction on your schedule

---

## Quick Links

### GitHub Organization
- **Organization:** https://github.com/Forseti-Life

### Tier 1 (Core Products)
- **forseti-job-hunter:** https://github.com/Forseti-Life/forseti-job-hunter
- **dungeoncrawler-content:** https://github.com/Forseti-Life/dungeoncrawler-content

### Tier 2 (Libraries)
- **forseti-shared-modules:** https://github.com/Forseti-Life/forseti-shared-modules
- **forseti-mobile:** https://github.com/Forseti-Life/forseti-mobile
- **forseti-meshd:** https://github.com/Forseti-Life/forseti-meshd
- **h3-geolocation:** https://github.com/Forseti-Life/h3-geolocation

### Tier 3 (Tooling)
- **copilot-hq:** https://github.com/Forseti-Life/copilot-hq
- **forseti-devops:** https://github.com/Forseti-Life/forseti-devops
- **forseti-docs:** https://github.com/Forseti-Life/forseti-docs

### Tier 4 (Reference)
- **dungeoncrawler-content:** https://github.com/Forseti-Life/dungeoncrawler-content
- **forseti-platform-specs:** https://github.com/Forseti-Life/forseti-platform-specs

---

## Summary

The Forseti.Life open-sourcing project has successfully planned and created the GitHub organization structure for 11 purpose-built repositories. The repositories are initialized, documented, and ready for community engagement. The private operational monorepo remains untouched and secure, while the public repositories provide clear entry points for different types of community members (users, developers, operators, researchers).

**Status:** ✅ Ready for Phase 2 (Content Extraction)

