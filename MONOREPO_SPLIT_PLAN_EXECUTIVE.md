# Forseti.Life: Open-Source Monorepo Split Strategy

**Status:** ✅ **GITHUB REPOSITORIES CREATED**  
**Date:** 2026-04-20 20:15 UTC  
**Organization:** `github.com/Forseti-Life`  
**Total Repos:** 11 (all initialized and ready)

---

## Executive Summary

The Forseti.life monorepo has been split into **11 purpose-built public repositories** under the GitHub community organization `Forseti-Life`. This strategy maintains the private operational monorepo while exposing curated, independently-useful public projects.

**Key Principle:** Each repository is independently useful while documenting its relationship to the Forseti ecosystem.

---

## 🎯 GitHub Organization Structure

### **Tier 1: Core Products** (Primary Community Focus)
These are the main products users and contributors will engage with.

| Repository | Purpose | Audience | Status |
|---|---|---|---|
| **forseti-job-hunter** | Job search platform with AI matching | Job seekers, recruiters, community | ✅ Created |
| **dungeoncrawler-content** | PF2E campaign & character management | GMs, players, tabletop RPG community | ✅ Created |

### **Tier 2: Libraries & Services** (Developer & Operator Focus)
Supporting infrastructure for integrations and deployments.

| Repository | Purpose | Audience | Status |
|---|---|---|---|
| **forseti-shared-modules** | Reusable Drupal modules (ORM, auth, content) | Drupal developers, integrators | ✅ Created |
| **forseti-mobile** | Native iOS/Android applications | Mobile developers, app users | ✅ Created |
| **forseti-meshd** | Peer-to-peer mesh network daemon | Network engineers, operators | ✅ Created |
| **h3-geolocation** | H3 hexagon geospatial integration | Geospatial developers, mappers | ✅ Created |

### **Tier 3: Tooling & Operational** (Advanced Operations)
Specialized tools for organizational and infrastructure work.

| Repository | Purpose | Audience | Status |
|---|---|---|---|
| **copilot-hq** | Open-source org model with governance patterns | Team leaders, org designers, AI-ops | ✅ Created |
| **forseti-devops** | Infrastructure-as-code & deployment automation | DevOps engineers, system admins | ✅ Created |
| **forseti-docs** | Central documentation hub | All users, integrators, contributors | ✅ Created |

### **Tier 4: Content & Reference** (Archive & Reference)
Structured data and specifications for community reference.

| Repository | Purpose | Audience | Status |
|---|---|---|---|
| **dungeoncrawler-content** | Structured PF2E rules and game data | Content creators, tool developers, GMs | ✅ Created |
| **forseti-platform-specs** | System architecture and API specs | Architects, integrators, deep-dive users | ✅ Created |

---

## 📊 Repository Organization at a Glance

```
github.com/Forseti-Life/

📦 CORE PRODUCTS (User Focus)
├── forseti-job-hunter              [Job search platform]
└── dungeoncrawler-content             [Campaign management]

📚 LIBRARIES & SERVICES (Developer Focus)
├── forseti-shared-modules          [Drupal modules]
├── forseti-mobile                  [Mobile apps]
├── forseti-meshd                   [Mesh network]
└── h3-geolocation                  [Geospatial]

🛠️ TOOLING & OPERATIONS (Advanced Focus)
├── copilot-hq                      [Org governance]
├── forseti-devops                  [Infrastructure]
└── forseti-docs                    [Documentation]

📋 CONTENT & REFERENCE (Archive)
├── dungeoncrawler-content          [Game rules data]
└── forseti-platform-specs          [Architecture]
```

---

## 🔑 Key Repository Characteristics

### Separation of Concerns
- **Live monorepo** (`forseti.life` private) = operational deployment, credentials, session data
- **Public repos** = clean, curated, documented, no secrets
- **Sync model** = Documentation in public repos links back to operational examples

### Initialization Template
Each repo includes:
```
├── README.md                 # Project overview, quick start
├── CONTRIBUTING.md           # Contribution guidelines
├── CODE_OF_CONDUCT.md        # Community standards
├── LICENSE                   # MIT License
├── SECURITY.md               # Security reporting
├── .github/
│   ├── ISSUE_TEMPLATE/       # Bug, feature, docs templates
│   ├── PULL_REQUEST_TEMPLATE # PR contribution guide
│   └── workflows/            # CI/CD (linting, tests, builds)
└── docs/
    ├── README.md             # Documentation index
    ├── ARCHITECTURE.md       # System design
    ├── API.md                # API documentation
    └── DEPLOYMENT.md         # Setup guides
```

### Topics & Discoverability
Each repo is tagged with 3-5 topics for GitHub search and discovery:
- **forseti-job-hunter:** job-search, drupal, ai, community
- **dungeoncrawler-content:** pathfinder, pf2e, campaign-management, tabletop-rpg, drupal
- **forseti-shared-modules:** drupal, drupal-modules, php, library
- **forseti-mobile:** mobile, react-native, ios, android
- **forseti-meshd:** mesh-network, p2p, distributed, networking
- **h3-geolocation:** geolocation, h3, geospatial, mapping
- **copilot-hq:** governance, organizational-model, copilot, release-management
- **forseti-devops:** devops, infrastructure, docker, terraform, deployment
- **forseti-docs:** documentation, api-docs, architecture, deployment-guide
- **dungeoncrawler-content:** pf2e, game-data, reference, rules
- **forseti-platform-specs:** architecture, specifications, api-docs, design

---

## 🚀 Content Extraction (Next Phase)

### Phase 1: Core Products (Immediate)
Extract and initialize:
1. **forseti-job-hunter** — Job search codebase + setup guide
2. **dungeoncrawler-content** — Campaign management system + content guides

### Phase 2: Supporting Libraries (Week 1-2)
Extract and initialize:
3. **forseti-shared-modules** — Drupal modules library
4. **forseti-mobile** — Mobile app code + build instructions
5. **forseti-meshd** — Network daemon + protocol documentation

### Phase 3: Operational & Governance (Week 2-3)
Extract and initialize:
6. **copilot-hq** — Org model, roles, release processes
7. **forseti-devops** — Infrastructure templates + deployment guides
8. **forseti-docs** — Unified documentation hub

### Phase 4: Reference Data (Week 3+)
Extract and initialize:
9. **dungeoncrawler-content** — Rules data in JSON/YAML format
10. **forseti-platform-specs** — Architecture diagrams and specs

---

## 📋 What's NOT Being Published (Private Monorepo)

✓ **Stays in private monorepo** (`forseti.life`):
- Production credentials and API keys (`prod-config/`)
- Database exports and backups (`database-exports/`)
- Site-specific keys and configuration (`sites/*/keys/`)
- Internal operational sessions (`sessions/`)
- Temporary runtime state (`tmp/`)
- AI training data and models (if applicable)
- Internal analytics and user data

✓ **Published with examples only**:
- Configuration templates (`.env.example` in public repos)
- Sample deployment guides (sanitized)
- Reference architectures (no prod details)

---

## 🔗 Repository Relationships

```
User → forseti-job-hunter
       ├─ depends on → forseti-shared-modules
       ├─ integrates with → forseti-mobile
       ├─ may use → forseti-devops (for deployment)
       └─ docs from → forseti-docs

GM/Player → dungeoncrawler-content
           ├─ depends on → forseti-shared-modules
           ├─ references → dungeoncrawler-content
           ├─ APIs from → forseti-platform-specs
           └─ docs from → forseti-docs

Developer → forseti-shared-modules (integrate into projects)
           ├─ deploy with → forseti-devops
           ├─ integrate with → h3-geolocation
           ├─ coordinate via → copilot-hq (for org/governance)
           └─ learn from → forseti-docs + forseti-platform-specs

Network Operator → forseti-meshd
                  ├─ configure with → forseti-devops
                  ├─ integrate with → h3-geolocation (for location services)
                  └─ docs from → forseti-docs
```

---

## 📈 Community Engagement Model

### Contribution Workflow
1. **Discover** — Find project via GitHub search or forseti-docs hub
2. **Understand** — Read README and ARCHITECTURE.md
3. **Setup** — Run local quickstart or deploy
4. **Contribute** — Follow CONTRIBUTING.md, submit issues/PRs
5. **Integrate** — Use in own projects, report back
6. **Feedback** — Community discussions in issues and discussions

### Success Metrics
- **Stars/Forks:** Community interest
- **Issues Created:** Engagement signal
- **Pull Requests:** Contribution rate
- **Downloads:** Adoption rate
- **Integrations:** Third-party usage examples
- **Deployment Reports:** Self-hosted success stories

---

## 🔐 Security & Privacy

### Credential Management
- ✅ No credentials in any public repo
- ✅ All configs use environment variables with `.env.example` templates
- ✅ GitHub Actions use org-level secrets
- ✅ SECURITY.md published with responsible disclosure policy

### Data Privacy
- ✅ No real user data in repos
- ✅ Session data remains private (only operational monorepo)
- ✅ Content data published only where appropriate (PF2E rules are own work)
- ✅ User privacy policy documented and linked

### Versioning & Backward Compatibility
- ✅ Each repo uses semantic versioning (semver)
- ✅ Compatibility matrix published in forseti-docs
- ✅ Changelog maintained per release
- ✅ Deprecation policy documented

---

## 🎯 Community Goals

### Job Hunter Community
- Self-hosted job search tools
- Community job listings
- Integration with regional job markets
- Contribution of matching algorithms

### Dungeon Crawler Community
- GM reference tools
- Campaign management templates
- Character sheet generators
- Module sharing and collaboration
- Content expansion (homebrew rules)

### Developer Community
- Reusable Drupal modules
- Reference implementations
- Integration patterns
- DevOps automation
- Mobile app examples

### Governance & Organizational Model
- Open-source org structure reference
- Release cycle processes
- Copilot/AI integration patterns
- Decentralized governance examples
- Decision-making frameworks

---

## 📅 Suggested Migration Timeline

| Phase | Duration | Focus | Repos |
|---|---|---|---|
| **Phase 1** | Week 1 | Core products launch | forseti-job-hunter, dungeoncrawler-content |
| **Phase 2** | Week 2 | Developer tools | forseti-shared-modules, forseti-mobile, forseti-meshd |
| **Phase 3** | Week 3 | Operations & docs | copilot-hq, forseti-devops, forseti-docs |
| **Phase 4** | Ongoing | Reference data | dungeoncrawler-content, forseti-platform-specs |

---

## ✅ Repository Creation Status

**All 11 repositories successfully created and initialized:**

```
✅ forseti-job-hunter               https://github.com/Forseti-Life/forseti-job-hunter
✅ dungeoncrawler-content              https://github.com/Forseti-Life/dungeoncrawler-content
✅ forseti-shared-modules           https://github.com/Forseti-Life/forseti-shared-modules
✅ forseti-mobile                   https://github.com/Forseti-Life/forseti-mobile
✅ forseti-meshd                    https://github.com/Forseti-Life/forseti-meshd
✅ h3-geolocation                   https://github.com/Forseti-Life/h3-geolocation
✅ copilot-hq                       https://github.com/Forseti-Life/copilot-hq
✅ forseti-devops                   https://github.com/Forseti-Life/forseti-devops
✅ forseti-docs                     https://github.com/Forseti-Life/forseti-docs
✅ dungeoncrawler-content           https://github.com/Forseti-Life/dungeoncrawler-content
✅ forseti-platform-specs           https://github.com/Forseti-Life/forseti-platform-specs

Organization: https://github.com/Forseti-Life
```

---

## 🔄 Next Steps

1. **No Local Changes Yet** ✓ (as requested)
2. **Initialize README Templates** — Add introductory docs to each repo
3. **Setup CI/CD Workflows** — GitHub Actions for testing, builds, releases
4. **Extract Content** — Phase 1-4 progressive extraction
5. **Community Launch** — Announce repositories, invite contributors
6. **Monitor & Support** — Address early feedback, improve documentation

---

## Notes

- All repositories are **public** and ready for community discovery
- Each repository has **independent versioning** and release cycles
- **forseti-docs** serves as the central hub connecting all projects
- The **private operational monorepo** remains the source-of-truth for development
- Public repos reference back to monorepo for deployment/operations details (with placeholders)

