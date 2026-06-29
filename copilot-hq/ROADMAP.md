# Forseti Org — Release Roadmap

- Last updated: 2026-04-19
- Maintained by: CEO (`ceo-copilot-2`)
- Products: forseti.life (Job Hunter) · DungeonCrawler (PF2E) · LangGraph UI · Open-Source Initiative

---

## Status at a Glance

| Product | Features Shipped | Features Done | In-Flight | Planned | Backlog |
|---|---|---|---|---|---|
| forseti.life | 70 | — | release-p | release-q | local-llm, financial-health |
| DungeonCrawler | — | 149 | release-q | release-r | 3 features + B3 content |
| LangGraph UI | 6 consoles shipped | — | (within forseti-p) | release-f, -g, -h | — |
| Open-Source | — | — | PROJ-009 | Phase 2 | — |

> **Status vocabulary:** `✅ Shipped` = production-deployed · `🟢 Done` = implemented, included in a release · `🚀 In-Flight` = active release cycle · `🗓️ Planned` = scoped, not yet started · `📥 Backlog` = defined, awaiting activation · `⏸️ Deferred` = explicitly deferred

---

## forseti.life — Job Hunter + AI Platform

### Release History (Milestones)

| Release | Status | Theme | Key Features |
|---|---|---|---|
| forseti-release-a/b | ✅ Shipped | Foundation | Core Drupal scaffold, CSRF hardening, schema fixes |
| forseti-release-c | ✅ Shipped | LangGraph Telemetry | Telemetry schema fix, dashboard path helper, feature-progress auto-refresh |
| forseti-release-d | ✅ Shipped | Agent Tracker Core | copilot_agent_tracker DB + telemetry API + admin dashboard |
| forseti-release-e | ✅ Shipped | Console Wiring: Run/Session | Run/Session panels wired to live orchestrator tick data |
| forseti-release-f/g/h/i/j | ✅ Shipped | Job Hunter Core | Application analytics, interview tracking, offer tracker, browser automation, Google Jobs UX |
| forseti-release-k/l/m | ✅ Shipped | Job Hunter Polish | Resume tailoring, follow-up reminders, profile completeness, resume version tracker |
| forseti-release-n | ✅ Shipped | AI Conversation Suite | Conversation export, history browser, job suggestions, user chat, debug gate |
| forseti-release-o | ✅ Shipped | AI Service Refactor + QA | AI service db-refactor, local LLM provider selection, e2e auth pipeline |

### 🚀 In-Flight

#### forseti-release-p (active · started 2026-04-12)
| Theme | Status |
|---|---|
| Job Hunter completions + QA hardening | In progress |

Scope includes: remaining Job Hunter feature completions (application-deadline-tracker, bulk-status-update, company-research-tracker, contact-referral-tracker, job-board-preferences, resume-version-labeling/tracker) + QA suite fill for agent-tracker and controller-extraction.

#### forseti-release-q (next · seeded)
Scope TBD — PM grooming in next cycle.

### 🗓️ Planned

#### forseti-release-r: LangGraph Console — Observe + Feature Progress
- Wire Observe section (node traces, runtime metrics, drift/anomaly detection, alerts)
- Integrate Feature Progress panel into console frame
- Feature: `forseti-langgraph-console-run-session` follow-on (phase 2)

#### forseti-release-s: LangGraph Console — Build + Test Sections Wired
- State schema visualization from `engine.py` LangGraphDeps TypedDict
- Node/routing topology parser
- Test path scenarios + eval scorecard framework

#### forseti-release-t: LangGraph Console — Release Control Panel (mutations) ⚠️ Board gate
- Graph version management, promotion flow UI, canary controls
- **Requires Board approval before scope commit** — grants UI access to alter org-wide orchestration

### 📥 Backlog

| Feature | Priority | Blocker |
|---|---|---|
| `local-llm-integration` | P2 | Awaiting storage capacity on target machine — infrastructure scaffolded, models not downloaded |
| `forseti-financial-health-home` | P2 | MVP implemented; activate when Board wants dedicated finance module or when AWS billing data is confirmed |
| `forseti-open-source-initiative` | High | In-progress (see §4) |

---

## DungeonCrawler — PF2E Assistant

### Release History (Milestones)

| Release | Status | Theme | Key Features |
|---|---|---|---|
| releases a–e | ✅ Shipped | Core Rules Foundation | Character creation, ancestry system, background system, class system (all 12 classes), action economy, conditions |
| releases f–j | ✅ Shipped | Skills + Combat | Full skill suite (18 skills), encounter rules, tactical grid, XP system, dice system, DC/rarity system |
| releases k–n | ✅ Shipped | Equipment + Magic | Equipment system, rune system, magic items, spellcasting, focus spells, rituals |
| releases o–p | ✅ Shipped | Sourcebooks | APG classes (investigator, oracle, swashbuckler, witch), APG ancestries, equipment, feats, spells; Bestiary 1, Bestiary 2; Guns & Gears; Secrets of Magic; Gods & Magic |
| release-p | ✅ Shipped | Bestiary 2 + SoM + GnG | dc-b2-bestiary2, dc-som-secrets-of-magic, dc-gng-guns-gears |

> **Note:** DungeonCrawler features use status `done` (game-mechanic content is implemented in DB/PHP, shipped as part of batch content releases). The 149 `done` features represent substantially complete PF2E Core Rulebook + major sourcebook coverage.

### 🚀 In-Flight

#### dungeoncrawler-release-q (active · started 2026-04-12)

| Feature | Status | Notes |
|---|---|---|
| `dc-b3-bestiary3` | 🚀 Plumbing shipped | Controller filter `?source=b3` live; content JSON intentionally absent pending Board content-source decision |

Preflight test suite re-dispatched for this release. Signoff reminder re-dispatched to pm-dungeoncrawler.

#### dungeoncrawler-release-r (next · seeded)

| Feature | Status | Notes |
|---|---|---|
| `dc-cr-gnome-heritage-chameleon` | 🟢 Done | Implementation complete; activates in release-r |

### 🗓️ Planned

#### dungeoncrawler-release-s+: Halfling Feat Tier
Remaining halfling content rules not yet activated:

| Feature | Priority | Description |
|---|---|---|
| `dc-cr-halfling-resolve` | P3 | Halfling Resolve (Feat 9): emotion save upgrade, Gutsy crit-fail protection |
| `dc-cr-halfling-weapon-expertise` | P3 | Halfling Weapon Expertise (Feat 13): cascades class weapon proficiency to halfling weapons |
| `dc-cr-ceaseless-shadows` | P3 | Ceaseless Shadows (Halfling Feat 13): hide/sneak without cover; crowd cover upgrade |

### 📥 Backlog

| Feature | Priority | Blocker |
|---|---|---|
| `dc-b3-bestiary3` content data | High | **Board decision required**: content source not authorized. Options: OGL/SRD via Archives of Nethys, licensed dataset, or curated originals. Plumbing complete. |
| APG Ancestries tier 2 | P3 | Not yet scoped |
| GMG Subsystems deeper wiring | P3 | dc-gmg-subsystems done; deeper encounter/hazard tools unscoped |

---

## LangGraph UI — Agent Tracker Console

> Detailed roadmap: `features/forseti-langgraph-ui/roadmap.md`

### Release History

| Release | Status | Theme |
|---|---|---|
| forseti-release-c (Phase 1b) | ✅ Shipped | Console stub scaffold (7-section route structure) |
| forseti-release-d (Phase 1c) | ✅ Shipped | Workflow Registry, context banners, key-terms glossaries on 15 pages |
| forseti-release-d | ✅ Shipped | Agent Tracker Core — DB + telemetry API + admin views |
| 20260411-coordinated-release | ✅ Shipped | Console Build + Test sections |
| forseti-release-b (2026-04-11) | ✅ Shipped | Release Control Panel (read-only) |
| forseti-release-e | ✅ Shipped | Console Wiring: Run + Session panels wired to live tick stream |

### 🚀 In-Flight

Ongoing improvements to Run/Session wiring within forseti-release-p.

### 🗓️ Planned

| Release | Theme | Key Scope |
|---|---|---|
| forseti-release-r | Observe + Feature Progress | Node traces, runtime metrics, drift detection, alerts wired to tick stream; Feature Progress in console frame |
| forseti-release-s | Build + Test Sections wired | State schema from `engine.py`, node topology parser, eval scorecard framework |
| forseti-release-t ⚠️ | Release Control Panel (mutations) | Graph versions, promotion flow UI, canary controls — **Board gate required** |

---

## Open-Source Initiative — PROJ-009

### 🚀 In-Flight

**Goal:** Publish the Forseti Autonomous Drupal Development Platform under `github.com/Forseti-Life/`

**Current phase:** PROJ-009 — Publication Candidate (`forseti-open-source-initiative`)
- Platform architecture complete (Drupal 10/11 + Bedrock AI + LangGraph orchestration)
- dev-open-source has active inbox: `20260419-133506-remediate-drupal-ai-conversation-public-candidate`
- pm-open-source seat has had 3 quarantine failures — seat investigation pending

### 🗓️ Planned

- Phase 2: Public README and contributor guide
- Phase 3: Forseti-Life GitHub org provisioning and initial public repo push
- Phase 4: Community onboarding and issue tracker launch

### ⏸️ Deferred (Board decision required)

| Item | Reason |
|---|---|
| Multi-org / multi-tenant orchestrator support | Single-org scope for launch; revisit post-community feedback |
| Public-facing graph status page | Admin-only for now |
| Real-time WebSocket streaming in console | AJAX polling sufficient at current scale |

---

## Infrastructure

### ✅ Shipped
- Orchestrator parallelism (8 concurrent agents, Bedrock cap fix, CEO additive slot, fire-and-forget publish, kpi_monitor concurrency) — `issues.md` all 8 fixes shipped 2026-04-19
- GH_TOKEN authentication for `gh workflow run` — restored 2026-04-19 (was broken ~3 weeks)

### 📥 Backlog

| Item | Priority | Notes |
|---|---|---|
| pm-open-source seat investigation | High | 3+ quarantine failures — needs backend/session/prompt diagnosis |
| `local-llm-integration` model installation | P2 | Storage gating; infrastructure scaffolded |
| Groom dispatch off-by-one fix | P2 | Dispatched to dev-infra; KB lesson written |

---

## Board Decisions Pending

| Decision | Owner | Urgency |
|---|---|---|
| Bestiary 3 content source (OGL/SRD vs licensed vs curated) | Board | High — blocks dc-b3 content release |
| Release Control Panel (mutations) scope approval | Board | Medium — blocks forseti-release-t |
| pm-open-source seat investigation prioritization | CEO | Medium |
