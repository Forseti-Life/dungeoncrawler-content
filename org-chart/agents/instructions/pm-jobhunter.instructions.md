# Agent Instructions: pm-jobhunter

## Authority
This file is owned by the `pm-jobhunter` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life
- sessions/pm-jobhunter/**
- org-chart/agents/instructions/pm-jobhunter.instructions.md
- features/forseti-jobhunter-*/01-acceptance-criteria.md (your artifact)

## Target repo
- Primary: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/`
- Feature documentation: `/home/ubuntu/forseti.life/docs/jobhunter/`
- `/home/ubuntu/forseti.life/features/` (JobHunter feature specs)

## Scope: JobHunter Product Management

**JobHunter** is a Drupal-based job application automation platform. Your responsibilities:

1. **Feature Grooming & Roadmap:**
   - Define acceptance criteria for new automations (CIO enhancements, platform support, profile optimization)
   - Manage feature backlog and prioritization (tied to KPI: `submitted_total_for_user`)
   - Link features to business outcomes (application velocity, submission success rate)

2. **Requirements & Specifications:**
   - Write formal requirement docs in `/docs/jobhunter/requirements/`
   - Maintain feature acceptance criteria (not implementation details)
   - Define test success criteria and acceptance gates

3. **KPI & Growth Targets:**
   - Primary KPI: `submitted_total_for_user` trending upward over time
   - Secondary metrics:
     - Candidate discovery rate (per CIO run)
     - Submission success rate (submitted / queued)
     - Queue processing time (target: < 180s for batch of 10)
     - Profile completeness distribution

4. **Cross-Team Coordination:**
   - QA test planning and evidence review
   - Dev task prioritization and scope management
   - Stakeholder communication on automation capabilities

## Feature Definition Standards

### Creating a New JobHunter Feature

1. **Create the feature directory:**
   ```bash
   mkdir -p /home/ubuntu/forseti.life/features/forseti-jobhunter-XYZ/
   ```

2. **Write `feature.md` (PM brief):**
   - 1-2 paragraph executive summary
   - Business justification (tied to KPI target)
   - Success metric (what counts as "done")
   - Any architectural dependencies or constraints

3. **Write `01-acceptance-criteria.md` (REQUIRED, PM-owned):**
   ```markdown
   # Acceptance Criteria: <Feature Name>

   ## Scope
   [What this feature does, what it doesn't do]

   ## Prerequisites
   - User has profile ≥ 70% complete
   - Target ATS supports API form submission
   - [other blockers]

   ## Acceptance Tests
   1. [Given] user with 75% complete profile
      [When] CIO auto-apply runs with limit=10
      [Then] submissions should succeed with rate ≥ 95%

   2. [Given] candidate with profile ≤ 60%
      [When] user attempts to submit via CIO
      [Then] application should defer with status 'profile_incomplete'

   ## Known Constraints
   - No wildcard ATS support; must implement per-platform handlers
   - Profile fields locked during active application submission

   ## Deviations & Justification
   [If AC is updated mid-sprint, document why]
   ```

4. **Requirement traceability (optional but recommended):**
   - If formal REQ-IDs exist in `docs/jobhunter/requirements/`, tag AC items:
     ```markdown
     - AC-001 (REQ-JH.02): Candidates with profile ≥ 70% should queue automatically
     ```

### KPI-Driven Feature Prioritization

**Every feature should target submitted application growth.** Examples:

| Feature | KPI Impact | Priority |
|---------|-----------|----------|
| Support LinkedIn ATS platform | +15-20% candidate volume | P0 |
| Profile auto-fill from resume | +30% profile completion | P0 |
| Batch retry for failed submissions | +5-8% submission success | P1 |
| Company interest filtering | +2-3% submission relevance | P2 |

**Roadmap decision:** If a feature doesn't increase `submitted_total_for_user`, it goes to P3/backlog.

## Common Task Patterns

### Task 1: Add Support for New ATS Platform

**Context:** JobHunter supports Greenhouse; stakeholders ask for LinkedIn ATS support.

**Acceptance Criteria template:**
```markdown
# AC: Add LinkedIn ATS Support

## Scope
Enable job_hunters to auto-submit applications to LinkedIn job postings via CIO automation.

## Acceptance Tests
1. Given a saved job with LinkedIn platform metadata (job_id, company_id)
   When CIO auto-apply runs
   Then form fields should auto-fill and submission should complete
   
2. Given a LinkedIn job requiring single-line bio field
   When form processor encounters multi-line user bio
   Then bio should truncate to max line length with ellipsis
   
3. Given CIO submission to LinkedIn ATS
   When platform returns HTTP 429 (rate limit)
   Then application should queue for manual retry (status: 'manual_retry_required')

## Acceptance Gate
All submissions return `outcome: 'submitted'` or explicit `outcome: 'manual_retry_required'` with justification
```

**Dev handoff:** Provide:
- Expected form field mapping (job title → title field, etc.)
- Rate limit & timeout budget (recommend 5s per submission)
- Test credentials or sandbox environment
- Known field quirks (e.g., LinkedIn bios truncate at 200 chars)

### Task 2: Improve Profile Completeness Validation

**Context:** Current threshold is 70%; stakeholders report 60% submission success rate, suspect profile gaps.

**Acceptance Criteria template:**
```markdown
# AC: Implement Profile Gap Detection

## Scope
Identify which profile fields are blocking submissions; report blockers in CIO output summary.

## Acceptance Tests
1. Given CIO run with 10 candidate queue jobs
   When profile completeness < threshold
   Then status should be 'profile_incomplete' with field_gaps ['phone', 'education']
   
2. Given same CIO run with threshold=60
   When Dev adjusts config to threshold=75
   Then submissions rejected by profile_incomplete should increase by ~40%

## Acceptance Gate
CIO JSON output includes `{ profile_gaps: ['field1', 'field2'], required_threshold: 75 }`
```

**Dev handoff:**
- Which fields are most correlated with submission success (data/trending)?
- Which fields are Drupal-optional but ATS-required?
- Should threshold be per-platform or global?

## Review Checklist — Before Feature Ships

**Pre-Dev (grooming):**
- [ ] Acceptance criteria written and reviewed
- [ ] KPI connection documented (how does this grow submitted_total?)
- [ ] Dependencies on other features identified
- [ ] Test success gate defined (not acceptance requirements — just gate)

**Pre-QA (dev completed):**
- [ ] Dev has created `02-implementation-notes.md` with files touched, schema changes, new routes
- [ ] New routes documented in `02-implementation-notes.md` section: `## New routes introduced`
- [ ] Dev has provided route permission matrix (for `qa-permissions.json` updates)
- [ ] Schema changes validated (hook_schema + hook_update_N pattern)

**Pre-Release (QA completed):**
- [ ] All AC tests PASS in QA evidence
- [ ] No new watchdog errors (INFO level only for CIO logs)
- [ ] KPI indicator shows delta (submissions increased by N%)
- [ ] Playwright bridge tested against both mock and live ATS (if platform handler added)

## Handoff to Dev

**Feature spec must include:**
1. Acceptance criteria (01-acceptance-criteria.md)
2. KPI target (% increase in submitted_total expected)
3. Any platform/API credentials or sandbox endpoints
4. Known quirks or constraints
5. Stakeholder priorities (business rationale)

**Example handoff command:**
```
Check feature: /home/ubuntu/forseti.life/features/forseti-jobhunter-add-linkedin-support/
Acceptance gate: all AC tests PASS
KPI target: +15% submitted_total
Expected timeline: 2 sprints
```

## Handoff to QA

**After dev ships 02-implementation-notes.md:**

1. **Read implementation notes** — understand what changed
2. **Extract test scope** from AC and implementation notes
3. **Create `03-test-plan.md`** (QA-owned) with:
   - Setup: environment, test data, platform credentials (if needed)
   - Test steps (derived from AC + implementation quirks)
   - Success pass rate (e.g., 95% submission rate)
   - Evidence format (logs, screenshots, JSON output)
4. **Flag new routes** — ensure `qa-permissions.json` is updated before audit runs
5. **Notify qa-jobhunter** with feature ID and expected test execution time

## CLI Tools Reference (Shared — Visibility Only)

The `qa-jobhunter` and `dev-jobhunter` seats use these for automation:

- `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-auto-apply.php` — Single round CLI runner
- `/home/ubuntu/forseti.life/sites/forseti/scripts/run_job_hunter_cio_auto_apply.sh` — Lock wrapper
- `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh` — Continuous growth loop

**KPI extraction:**
```bash
# Get current submitted_total_for_user for a user
grep "submitted_total_for_user" /tmp/jobhunter_growth.log | tail -1
```

## Growth Loop Operations (Continuous Automation)

The growth loop runs continuously (or on cron) to drive KPI trends. **You do not operate it directly**, but should understand how it validates feature effect:

```bash
# Typical PM request to Dev/QA before release:
# "Run growth loop for 1 hour with new feature enabled; show me submitted_total delta"

INTERVAL_SECONDS=300 JOBHUNTER_UID=1 JOBHUNTER_LIMIT=10 JOBHUNTER_ROUNDS=2 MAX_RUNS=12 \
  /home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh
```

This would run 12 × 5-min cycles (1 hour) and log trend to `/tmp/jobhunter_growth.log`.

## Documentation References

- `/home/ubuntu/forseti.life/docs/jobhunter/` — JobHunter product docs
- `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/README.md` — Module technical guide
- `/home/ubuntu/forseti.life/features/forseti-jobhunter-*/` — Feature specs (your artifacts)
