# GitHub Organization Reference: Forseti-Life

**Organization URL:** https://github.com/Forseti-Life  
**Created:** 2026-04-20  
**Total Repositories:** 11  
**Status:** ✅ Ready for Community  

---

## Quick Reference: All Repositories

### Core Products (2 repos)
| Repo | Purpose | URL |
|------|---------|-----|
| forseti-job-hunter | Job search platform | https://github.com/Forseti-Life/forseti-job-hunter |
| dungeoncrawler-pf2e | PF2E campaign management | https://github.com/Forseti-Life/dungeoncrawler-pf2e |

### Libraries & Services (4 repos)
| Repo | Purpose | URL |
|------|---------|-----|
| forseti-shared-modules | Reusable Drupal modules | https://github.com/Forseti-Life/forseti-shared-modules |
| forseti-mobile | iOS/Android apps | https://github.com/Forseti-Life/forseti-mobile |
| forseti-meshd | P2P mesh network | https://github.com/Forseti-Life/forseti-meshd |
| h3-geolocation | Geospatial integration | https://github.com/Forseti-Life/h3-geolocation |

### Tooling & Operations (3 repos)
| Repo | Purpose | URL |
|------|---------|-----|
| copilot-hq | Organizational model | https://github.com/Forseti-Life/copilot-hq |
| forseti-devops | DevOps automation | https://github.com/Forseti-Life/forseti-devops |
| forseti-docs | Documentation hub | https://github.com/Forseti-Life/forseti-docs |

### Content & Reference (2 repos)
| Repo | Purpose | URL |
|------|---------|-----|
| dungeoncrawler-content | Game rules data | https://github.com/Forseti-Life/dungeoncrawler-content |
| forseti-platform-specs | Architecture specs | https://github.com/Forseti-Life/forseti-platform-specs |

---

## Repository Topics (Discoverability)

### By Technology
- **drupal**: forseti-job-hunter, dungeoncrawler-pf2e, forseti-shared-modules
- **mobile**: forseti-mobile (react-native, ios, android)
- **mesh-network**: forseti-meshd (p2p, distributed, networking)
- **geospatial**: h3-geolocation (h3, geolocation, mapping)
- **devops**: forseti-devops (docker, terraform, infrastructure, deployment)
- **documentation**: forseti-docs, forseti-platform-specs (api-docs, architecture)

### By Community
- **job-search**: forseti-job-hunter (ai, community)
- **pathfinder**: dungeoncrawler-pf2e, dungeoncrawler-content (pf2e, campaign-management, tabletop-rpg)
- **governance**: copilot-hq (organizational-model, copilot, release-management)

---

## Content Extraction Checklist

### Phase 1: Core Products
- [ ] Extract forseti/ (job-hunter) → forseti-job-hunter
- [ ] Extract dungeoncrawler/ → dungeoncrawler-pf2e
- [ ] Create comprehensive README for each
- [ ] Setup issue templates
- [ ] Setup GitHub Actions CI

### Phase 2: Libraries & Services
- [ ] Extract shared/modules/ → forseti-shared-modules
- [ ] Extract forseti-mobile/ → forseti-mobile
- [ ] Extract forseti-meshd/ → forseti-meshd
- [ ] Extract h3-geolocation/ → h3-geolocation
- [ ] Add API documentation
- [ ] Setup dependency linking

### Phase 3: Tooling & Operations
- [ ] Extract copilot-hq/ → copilot-hq
- [ ] Extract script/ + deployment/ → forseti-devops
- [ ] Extract docs/ → forseti-docs
- [ ] Create documentation index
- [ ] Setup central landing page

### Phase 4: Reference Data
- [ ] Extract game data → dungeoncrawler-content
- [ ] Extract specs → forseti-platform-specs
- [ ] Create data schema documentation
- [ ] Setup API specifications

---

## Standard Repository Template

Each repository includes:

```
README.md                           # Project overview, quick start
CONTRIBUTING.md                     # How to contribute
CODE_OF_CONDUCT.md                  # Community standards
LICENSE                             # MIT License
SECURITY.md                         # Security reporting
.github/
  ├── ISSUE_TEMPLATE/               # Bug, feature, docs templates
  ├── PULL_REQUEST_TEMPLATE.md      # PR guidelines
  └── workflows/                    # GitHub Actions (CI/CD)
docs/
  ├── README.md                     # Documentation index
  ├── ARCHITECTURE.md               # System design
  ├── API.md                        # API documentation
  └── DEPLOYMENT.md                 # Setup & deployment
.gitignore                          # Exclude secrets, builds, etc.
```

---

## Topics & Tags

### Discoverability Tags
Each repo is tagged with 3-5 topics from GitHub's common tags:

**All Repos:**
- `open-source` (when actively seeking contributors)
- `documentation` (if significant docs included)

**Technology-specific:**
- `drupal`, `drupal-modules` — for Drupal projects
- `mobile`, `react-native`, `ios`, `android` — for mobile
- `mesh-network`, `p2p`, `distributed` — for networking
- `geospatial`, `h3`, `mapping` — for location services
- `devops`, `docker`, `terraform`, `infrastructure` — for ops

**Community-specific:**
- `job-search`, `ai` — for job-hunter
- `pathfinder`, `pf2e`, `campaign-management`, `tabletop-rpg` — for dungeoncrawler
- `governance`, `organizational-model`, `copilot` — for org/governance
- `game-data`, `reference`, `rules` — for content

---

## Community Roles & Responsibilities

### Organization Owners
- GitHub organization settings and member management
- High-level governance and policy decisions
- Conflict resolution between repos

### Repository Maintainers (per repo)
- Code review and merge authority
- Release management
- Issue triage and prioritization
- Community communication

### Contributors
- Submit issues and feature requests
- Create pull requests
- Participate in discussions
- Provide feedback on roadmap

---

## Contribution Workflow

1. **Find a repo** — Browse by topic or project interest
2. **Understand** — Read README, ARCHITECTURE, and CONTRIBUTING
3. **Find an issue** — Look for "good-first-issue" or "help-wanted" labels
4. **Setup locally** — Follow the DEPLOYMENT/QUICKSTART guide
5. **Make changes** — Follow code style from existing commits
6. **Test** — Run automated tests before submitting PR
7. **Submit PR** — Follow pull request template
8. **Code review** — Maintainers will review and provide feedback
9. **Merge** — Once approved, your changes are merged!

---

## Communication Channels

### GitHub-based
- **Issues** — Bug reports, feature requests, questions
- **Discussions** — General questions, ideas, announcements
- **Pull Requests** — Code contributions and changes
- **Wiki** (optional) — Community-maintained guides

### Expected Response Times (SLAs)
- **Critical bug** — 24 hours
- **Feature request** — 48 hours
- **Question/discussion** — 1 week
- **Pull request review** — 2-3 days

---

## Private Monorepo vs. Public Repos

### What's in the Private Monorepo
- Production credentials and configuration
- Operational session data and logs
- Database backups and internal tooling
- Site-specific keys and private documentation
- Internal analytics and performance data

### What's in Public Repos
- Source code (clean, production-ready)
- Documentation (setup, API, architecture)
- Configuration templates (.env.example)
- Community examples and use cases
- Contributing guidelines and roadmaps

### How They Relate
- Public repos are extracted from monorepo
- Monorepo is source-of-truth for active development
- Public repos focus on community reuse and contribution
- Changes propagate: public repo PR → monorepo integration

---

## Success Metrics

### Engagement
- ⭐ Stars/forks — Community interest
- 👥 Contributors — Community participation
- 💬 Issues/discussions — Community engagement
- 🔗 Pull requests — Contribution rate

### Adoption
- 📦 Downloads/installs — Usage rate
- 🔄 Integrations — Third-party use cases
- 📖 Documentation views — Learning rate
- 🚀 Deployments — Production usage

### Quality
- ✅ Test coverage — Code reliability
- 🐛 Bug resolution time — Responsiveness
- 📝 Documentation completeness — Usability
- 🔒 Security updates — Maintenance

---

## GitHub Actions Setup (CI/CD)

### Recommended Workflows per Repo

**All Repos:**
```
- lint.yml          → Code style checking
- test.yml          → Automated testing
- security.yml      → Dependency scanning
```

**Language-specific:**
- **JavaScript/Node**: npm test, npm run build
- **Python**: pytest, pylint, coverage
- **PHP/Drupal**: phpunit, phpcs
- **Go**: go test, go vet
- **Java**: gradle test, checkstyle

**Deployment (when applicable):**
- Build Docker images
- Release to package registries (npm, PyPI, etc.)
- Generate API documentation
- Publish to GitHub releases

---

## Repository Settings (Recommended)

### Branch Protection
- ✅ Require pull request reviews (1-2 reviewers)
- ✅ Require status checks to pass
- ✅ Require branches to be up to date
- ✅ Include administrators in restrictions (until v1.0)

### Merge Strategy
- Allow squash merging (for cleaner history)
- Require commit message from PR title
- Allow auto-delete of head branches

### Issue & PR Templates
- ✅ Bug report template
- ✅ Feature request template
- ✅ Documentation update template
- ✅ Pull request template with checklist

---

## Maintenance Cadence

### Weekly
- Review and triage new issues
- Monitor failing CI builds
- Respond to discussions

### Monthly
- Process pull requests backlog
- Update dependencies
- Review roadmap progress
- Publish progress updates

### Quarterly
- Release new versions (if stable)
- Review and update documentation
- Assess community feedback
- Plan next quarter roadmap

---

## Growth Milestones

### Phase 1: Launch (Week 1-2)
- ✅ Repositories created
- ✅ Basic documentation
- ✅ CI/CD setup
- ⏳ Initial content extraction
- 🎯 Goal: Public availability

### Phase 2: Growth (Week 3-8)
- All Tier 1-2 repos active
- Growing issue/discussion activity
- First community contributions
- 🎯 Goal: 100+ stars on core repos

### Phase 3: Community (Month 3-6)
- Active contributor community
- Third-party integrations
- Self-hosted deployments
- 🎯 Goal: Sustainable contributor base

### Phase 4: Ecosystem (Month 6+)
- Integration examples and plugins
- Community-maintained tools
- Public showcase of deployments
- 🎯 Goal: De-facto standard for use cases

---

## Documentation Standards

### Each Repository Must Have:
- ✅ Clear README with purpose and use cases
- ✅ Quick start guide (5-10 minutes)
- ✅ Installation instructions
- ✅ API documentation (if applicable)
- ✅ Architecture overview
- ✅ Contribution guidelines
- ✅ Security policy

### Bonus (Recommended):
- 📊 Architecture diagrams
- 🎓 Tutorial guides
- 🔗 Integration examples
- 📋 Troubleshooting guide
- 🗺️ Roadmap (public version)

---

## Links & Resources

### Organization
- **Organization URL:** https://github.com/Forseti-Life
- **Organization Wiki:** https://github.com/Forseti-Life/community/wiki (optional)

### Key Repositories
- **forseti-docs:** Central documentation hub
- **copilot-hq:** Organizational governance model
- **forseti-platform-specs:** Architecture and design specs

### External Links
- **Project Website:** https://forseti.life
- **Main Monorepo:** https://github.com/keithaumiller/forseti.life (private)

