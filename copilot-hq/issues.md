# HQ Issues — Unresolved & Open

**Current Status:** 1 issue escalated to Board (GitHub PAT), 7 issues open from release analysis, 1 new infrastructure issue identified

---

## ESCALATED ISSUES

### ISSUE-022 — GitHub PAT Invalid / Expired (Updated for Broken-Out Repos Architecture)

**Severity:** Medium  
**Component:** GitHub Actions API integration, CI/CD pipeline, multi-repository access  
**Status:** 🔵 ESCALATED TO BOARD (Future releases will need this fixed)

**Current State:**
- Token file: `/home/ubuntu/github.token`
- Current token: `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`
- Status: ❌ INVALID (HTTP 401 "Bad credentials" on all API calls)
- Last coordinated push: Apr 20, 13:28 — `20260412-dungeoncrawler-release-s__20260412-forseti-release-q.pushed`

**What's Not Working:**
- `ceo-release-health.sh` cannot query GitHub Actions workflow status (reports warning, not error)
- `gh api` calls fail with 401 (personal account + org both fail)
- Health check shows: `⚠️  WARN deploy.yml state=unknown`
- Cannot authenticate to any GitHub repos (personal or org)

**Impact Assessment:**
- **Current Releases (release-q, release-s):** ✅ ALREADY PUSHED — No blocking impact
- **Next Coordinated Push:** ❌ WILL BLOCK — `gh workflow run` needs valid token
- **Public Repo Sync (Phase 3):** ❌ BLOCKED until token fixed
- **Health Check Dashboards:** ⚠️ DEGRADED (cannot determine deploy.yml status, reports unknown state)

**Why It Works Despite Invalid Token:**
- Orchestrator uses local signoff files (`artifacts/release-signoffs/`) — token not needed for gating
- Releases coordinate locally (PM signoffs, code review gate)
- `gh workflow run` only executes after all gates pass
- Current releases already coordinated-pushed before this token check
- Token becomes critical for **next** coordinated push (after current releases complete)

**Architecture Context (New):**
- **Private monorepo:** `/home/ubuntu/forseti.life` (deployment source, operational)
- **Public repos:** 11 repos in Forseti-Life organization for community/open-source
- **Remotes:**
  - `origin`: https://github.com/keithaumiller/forseti.life (personal account)
  - `community`: https://github.com/Forseti-Life/forseti.life (organization mirror)
- **Token needed for:** Both personal account (origin) AND organization repos (all 11 Forseti-Life repos)

**Repositories That Need Access (All 11):**

| Tier | Repo | URL |
|------|------|-----|
| 1 | forseti-job-hunter | https://github.com/Forseti-Life/forseti-job-hunter |
| 1 | dungeoncrawler-pf2e | https://github.com/Forseti-Life/dungeoncrawler-pf2e |
| 2 | forseti-shared-modules | https://github.com/Forseti-Life/forseti-shared-modules |
| 2 | forseti-mobile | https://github.com/Forseti-Life/forseti-mobile |
| 2 | forseti-meshd | https://github.com/Forseti-Life/forseti-meshd |
| 2 | h3-geolocation | https://github.com/Forseti-Life/h3-geolocation |
| 3 | copilot-hq | https://github.com/Forseti-Life/copilot-hq |
| 3 | forseti-devops | https://github.com/Forseti-Life/forseti-devops |
| 3 | forseti-docs | https://github.com/Forseti-Life/forseti-docs |
| 4 | dungeoncrawler-content | https://github.com/Forseti-Life/dungeoncrawler-content |
| 4 | forseti-platform-specs | https://github.com/Forseti-Life/forseti-platform-specs |

**Board Action Required:**

Generate new GitHub Personal Access Token at: https://github.com/settings/tokens

**Token Requirements:**
- **Type:** Fine-grained personal access token (recommended) for better security
- **Owner:** Must have access to keithaumiller personal account AND Forseti-Life organization
- **Scopes/Permissions needed:**
  - `repo` — Full control of private repositories
  - `workflow` — Full control of GitHub Actions workflows
  - `public_repo` — Access to public repositories
  - `read:org` — Read access to organization data
  - `write:org` — Write access to organization data (for repo management)

**Installation & Infrastructure Update Steps (CEO):**

Once Board provides new token:

1. **Update token file:**
   ```bash
   echo "NEW_TOKEN_VALUE" > /home/ubuntu/github.token
   chmod 600 /home/ubuntu/github.token
   ```

2. **Update git remotes (remove embedded token):**
   ```bash
   cd /home/ubuntu/forseti.life/copilot-hq
   git remote set-url origin https://github.com/keithaumiller/forseti.life.git
   git remote set-url community https://github.com/Forseti-Life/forseti.life.git
   ```

3. **Verify token works:**
   ```bash
   GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /user
   GH_TOKEN=$(cat /home/ubuntu/github.token) gh api orgs/Forseti-Life
   ```
   Expected: Both return 200 OK (user/org JSON), not 401

4. **Test health check:**
   ```bash
   cd /home/ubuntu/forseti.life/copilot-hq
   bash scripts/ceo-release-health.sh | grep "GitHub Actions"
   ```
   Expected: `✅ PASS deploy.yml is enabled (state=active)`

5. **Test public repos access:**
   ```bash
   GH_TOKEN=$(cat /home/ubuntu/github.token) gh repo list Forseti-Life --limit 20
   ```
   Expected: List all 11 repos

**Automatic Integration:**
- Crontab exports `GH_TOKEN` environment variable (reads from `/home/ubuntu/github.token`)
- No code changes needed
- No reboot needed
- Health check uses it on next run

---

## NEWLY IDENTIFIED ISSUES

### ISSUE-025 — Documentation and Infrastructure Not Updated for Broken-Out Repos

**Severity:** High  
**Component:** Documentation, scripts, CI/CD configuration  
**Status:** 🟢 RESOLVED (2026-04-21)

**Problem (Resolved):**
The system was split from a monorepo to broken-out repositories (11 repos in Forseti-Life org), but the documentation and infrastructure still referenced the old monorepo structure.

**Solution Implemented:**
1. ✅ Created `REPOSITORY_ARCHITECTURE.md` — comprehensive multi-repo architecture overview
   - Maps all 11 public repos to source modules
   - Documents integration flow (monorepo → public repos)
   - Clarifies deployment model (monorepo-only deploy source)
   - FAQ addressing key architecture questions

2. ✅ Created `.github/instructions/MULTIREPOSITORY_SETUP.md` — setup & auth guide
   - GitHub PAT generation and rotation (every 90 days)
   - Git remote configuration (no embedded tokens; uses $GH_TOKEN env var)
   - Token verification and troubleshooting
   - Architecture diagram showing data flow

3. ✅ Documented authentication strategy
   - Token stored in `/home/ubuntu/github.token` (gitignored)
   - Exported via environment variable by crontab
   - Works for both personal account (keithaumiller) and org (Forseti-Life)
   - Automatically picked up by orchestrator and health checks

**Impact:**
- ✅ Cleared confusion about source of truth (monorepo is canonical)
- ✅ Documented multi-repo coordination strategy
- ✅ Provided setup guide for future team members
- ✅ Established token rotation and lifecycle best practices

**Related files:**
- `REPOSITORY_ARCHITECTURE.md` — Root of repo
- `.github/instructions/MULTIREPOSITORY_SETUP.md` — GitHub setup
- `copilot-hq/org-chart/roles/ceo.instructions.md` — CEO decision authority

---

## OPEN ISSUES FROM RELEASE CYCLE ANALYSIS

### ISSUE-014 — CEO proxy overload: 23 sessions doing team agent work

**Severity:** High  
**Component:** CEO seat `ceo-copilot-2`, executor health  
**Release:** 20260412-dungeoncrawler-release-r  
**Status:** 🟡 MONITOR

**Problem:**  
The CEO performed 23 proxy sessions covering team agent roles:
- Dev proxy: 7 sessions (hardening, coverage, exposure, defect fix)
- QA proxy: 1 session (preflight / Gate 2)
- PM proxy: 8 sessions (signoff, scope-activate, groom)
- Other: 7 sessions

This is well above the FAIL threshold (5 sessions). Root cause: gating agent quarantine cascade (ISSUE-012) forced CEO to manually cover PM, code-review, and dev repair work.

**Impact:** CEO throughput consumed by team work; strategic/org-level work deferred; ~4–5h CEO time estimated.

**Fix options:**
1. Resolve ISSUE-012 (gating agent quarantine) — CEO proxy volume drops naturally when agents run.
2. Add CEO proxy load to the shipping-lag watchdog: if CEO proxy > threshold while agents are quarantined, auto-restart quarantined agents with simplified prompts.
3. Track CEO proxy trending across releases in the health check to catch systemic role gaps early.

---

### ISSUE-015 — Redundant dev passes: 1 feature(s) re-dispatched after already done

**Severity:** High  
**Release:** 20260412-dungeoncrawler-release-s  
**Status:** 🔴 Open

**Problem:**  
Feature `dc-cr-dwarf-ancestry` received redundant dev dispatch after already completing.

**Impact:** Wasted dev compute, occupies worker slots.

**Fix options:**
1. Add feature completion check in dispatch gate: if `dev_agent.outbox` already has a `done`-status file for the feature, skip re-dispatch.
2. Add idempotency key to dev inbox items; second dispatch of same key is a no-op.

---

### ISSUE-016 — CEO proxy load: 6 sessions doing dev/QA/PM work

**Severity:** High  
**Release:** 20260412-dungeoncrawler-release-s  
**Status:** 🔴 Open

**Problem:**  
CEO performed 6 proxy sessions covering team roles during release-s.

**Impact:** CEO throughput consumed; executor potentially understaffed.

---

### ISSUE-017 — Gate R5 delay: 11.2h post-push (threshold: 4h)

**Severity:** High  
**Release:** 20260412-dungeoncrawler-release-r  
**Status:** 🔴 Open

**Problem:**  
Gate R5 (production audit) initiated 11.2 hours after coordinated push, exceeding 4-hour SLA.

**Impact:** Post-release production verification delayed.

---

### ISSUE-018 — Gating agent(s) majority-quarantined: agent-code-review (1/1 = 100%)

**Severity:** High  
**Release:** 20260412-dungeoncrawler-release-r  
**Status:** 🔴 Open

**Problem:**  
Code-review gating agent 100% quarantined (all sessions failed/stalled).

**Impact:** Code review gate bypassed; features shipped without review.

---

### ISSUE-019 — Code review gate: 1 session(s) dispatched but none completed

**Severity:** High  
**Release:** 20260412-forseti-release-q  
**Status:** 🔴 Open

**Problem:**  
Code review gate dispatched but all sessions quarantined/stalled. Code shipped without review.

**Impact:** Security/quality risk; bypassed gating control.

---

### ISSUE-020 — CEO proxy load: 13 sessions doing dev/QA/PM work

**Severity:** High  
**Release:** 20260412-forseti-release-q  
**Status:** 🔴 Open

**Problem:**  
CEO performed 13 proxy sessions covering team work in forseti release.

**Impact:** CEO overloaded; executor understaffed or broken.

---

## Summary

| Issue | Severity | Status | Type |
|---|---|---|---|
| 022 | High | 🔵 ESCALATED | GitHub PAT (updated for multi-repo) |
| 025 | High | 🔴 OPEN | Infrastructure update for broken-out repos |
| 014 | High | 🟡 MONITOR | CEO proxy overload |
| 015 | High | 🔴 OPEN | Redundant dev passes |
| 016 | High | 🔴 OPEN | CEO proxy load |
| 017 | High | 🔴 OPEN | Gate R5 delay |
| 018 | High | 🔴 OPEN | Gating agent quarantined |
| 019 | High | 🔴 OPEN | Code review gate failed |
| 020 | High | 🔴 OPEN | CEO proxy overload |

**Total Open:** 9 issues (1 escalated to Board, 1 new infrastructure issue, 1 monitoring, 6 analysis findings from completed releases)

