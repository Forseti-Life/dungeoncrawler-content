# Intake → QA Handoff: Full Process Flow

**Owner:** `pm-forseti` (process driver) + `qa-forseti` (test generation)  
**Runbooks referenced:** `runbooks/feature-intake.md`, `runbooks/release-cycle-process-flow.md`  
**Scripts:** `scripts/suggestion-intake.sh`, `scripts/suggestion-triage.sh`, `scripts/pm-qa-handoff.sh`

---

## The full picture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  UPSTREAM: User suggestion capture (continuous, always on)                  │
│                                                                             │
│  User talks to Forseti  ──▶  AI detects suggestion                         │
│                              ──▶  community_suggestion node (status: new)  │
│                                   (Drupal, accumulates between cycles)      │
└─────────────────────────────────────────────────────────────────────────────┘
         │
         │  ◀── STAGE 3 OF CURRENT RELEASE (parallel grooming track)
         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  GROOMING (runs during Stage 3, parallel to Dev execution)                  │
│                                                                             │
│  1. PM runs:  ./scripts/suggestion-intake.sh forseti                        │
│  2. PM triages each suggestion (accept/defer/decline)                       │
│  3. PM writes Acceptance Criteria:  features/<id>/01-acceptance-criteria.md │
│  4. PM hands off to QA:  ./scripts/pm-qa-handoff.sh forseti <feature-id>    │
│  5. QA generates test cases → feature overlay + features/<id>/03-test-plan.md │
│                                                                             │
│  ✓ Feature is GROOMED when all three exist:                                 │
│    feature.md  +  01-acceptance-criteria.md  +  03-test-plan.md            │
│  Groomed features enter the "ready pool" for the NEXT Stage 0              │
└─────────────────────────────────────────────────────────────────────────────┘
         │
         │  ◀── NEXT CYCLE BOUNDARY: new release cycle starts (Stage 0)
         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  STAGE 0 — Scope selection (fast — PM picks from ready pool only)           │
│                                                                             │
│  PM reviews features/*/  — only items with all 3 artifacts are eligible    │
│  PM selects scope → 01-change-list.md  ◀── SCOPE FREEZE                   │
│  Ungroomed items are automatically deferred (not negotiated, just skipped)  │
│  QA preflight queued → release-cycle-start.sh                               │
└─────────────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  STAGE 3 — Dev execution  (tests already written, Dev builds to spec)       │
│  + PM begins grooming NEXT release in parallel (see above)                  │
└─────────────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  STAGE 4 — QA runs full regression suite                                    │
│  New feature tests + existing regression → PASS/FAIL → Dev repair loop     │
│  QA notifies PM with APPROVE/BLOCK + 04-verification-report.md             │
└─────────────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  STAGE 5–7 — Release candidate assembly → PM signoff → Ship                │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## PM Grooming Process (runs during Stage 3 of the current release)

Grooming is PM's job during Dev execution of the current release — it runs entirely in parallel
and does not interact with the current release at all.

### Step 1 — Audit the existing next-release backlog

Before pulling new suggestions, scan the already-tracked backlog for features that are partially groomed.
If a feature for the next release is already `planned`, `ready`, or `in_progress` but is missing either
`01-acceptance-criteria.md` or `03-test-plan.md`, finish that backlog item first.

```bash
python3 - <<'PY'
import pathlib, re
site = "forseti"  # swap for the product you are grooming
for fm in sorted(pathlib.Path("features").glob("*/feature.md")):
    text = fm.read_text(encoding="utf-8")
    if f"- Website: {site}" not in text:
        continue
    m = re.search(r"^- Status:\s*(.+)$", text, re.MULTILINE)
    if not m:
        continue
    status = m.group(1).strip()
    if status not in {"planned", "ready", "in_progress"}:
        continue
    ac = fm.with_name("01-acceptance-criteria.md").exists()
    tp = fm.with_name("03-test-plan.md").exists()
    if not (ac and tp):
        print(f"{fm.parent.name}: status={status} ac={ac} testplan={tp}")
PY
```

### Step 2 — Pull community suggestions (once at Stage 3 start)

Open `README.md` in the batch folder. Review the summary table — gives you category + title at a glance.

### Step 3 — Triage each suggestion

For each `triage/NID-<n>-triage.md`:

**Ask these questions in order:**

1. **Mission alignment** — Does this advance "democratize and decentralize internet services for scientific, technology-focused, and tolerant people"?
   - Yes → continue
   - No → `decline`

2. **This cycle or next?** — Is this feasible given current scope? Does it conflict with in-flight work?
   - Feasible + high ROI → `accept`
   - Good idea, wrong timing → `defer`
   - Not worth doing → `decline`

2.5. **Security/integrity gate (mandatory)** — Could this introduce security abuse, release-gate bypass,
or crash/data-loss risk?
   - No clear risk → continue
   - Any risk or uncertainty → `escalate` (human board review required)

3. **Module ownership** — Which module does this touch? Is the owning Dev available this cycle?
   - If cross-module: flag in the feature brief for passthrough coordination

Record your decision and rationale in the triage file.

### Step 4 — Run the decision

```bash
# Accept — creates features/<feature-id>/feature.md
./scripts/suggestion-triage.sh forseti <nid> accept <feature-id>

# Defer — marks Drupal node deferred
./scripts/suggestion-triage.sh forseti <nid> defer

# Decline — marks Drupal node declined
./scripts/suggestion-triage.sh forseti <nid> decline

# Escalate — route to board-security review queue
./scripts/suggestion-triage.sh forseti <nid> escalate
```

### Step 5 — Gap analysis (required before writing AC)

**This step is mandatory.** Do not write `01-acceptance-criteria.md` until it is complete.

For every requirement in the feature brief, audit the existing codebase and record findings in the `## Gap Analysis` section of `feature.md`.

**What to look for (dungeoncrawler):**

| Layer | Path to audit |
|---|---|
| Service logic | `web/modules/custom/dungeoncrawler_content/src/Service/` |
| Controllers / API entry points | `web/modules/custom/dungeoncrawler_content/src/Controller/` |
| Existing tests (unit) | `tests/src/Unit/Service/` |
| Existing tests (functional) | `tests/src/Functional/` |
| Data schemas | `web/modules/custom/dungeoncrawler_content/schema/` |
| Content/reference data | `web/modules/custom/dungeoncrawler_content/content/` |
| Test fixtures | `tests/fixtures/` |

**For each requirement, determine one of three coverage states:**

| Status | Meaning | Feature type impact |
|---|---|---|
| **Full** | Implementation exists and is correct | → `needs-testing` (write tests only) |
| **Partial** | Implementation exists but is incomplete, incorrect, or missing fields | → `enhancement` (extend existing code) |
| **None** | No implementation found | → `new-feature` (build from scratch) |

Set `Feature type:` in `feature.md` header based on the majority finding.

**Output for QA:** For each requirement, record the exact test file path QA should create or extend in the "Test path guidance" table. QA must not guess at file locations — the PM provides them.

A gap analysis is **blocked** if:
- The codebase is inaccessible (file access error) — escalate to CEO.
- The feature has no identifiable source requirements — return to intake and request clarification.

### Step 6 — Complete the feature brief

For each accepted feature, open `features/<feature-id>/feature.md` and fill in:
- `Module:` — which Drupal module owns this
- `Priority:` — P0/P1/P2
- `Feature type:` — set from gap analysis result (`new-feature` / `enhancement` / `needs-testing`)
- Create `features/<feature-id>/01-acceptance-criteria.md` from `templates/01-acceptance-criteria.md`
- Tag each AC criterion with `[NEW]`, `[EXTEND]`, or `[TEST-ONLY]` per the gap analysis

**The Acceptance Criteria doc is the handoff contract to QA.** It must exist before calling `pm-qa-handoff.sh`. QA will read it to generate test cases — it must be complete, not a stub. **AC criteria without gap analysis tags will be rejected by QA and returned for clarification.**

### Step 7 — Select release scope

Review all `features/forseti-*/feature.md` entries with `Status: planned`.  
Add accepted + prioritized items to the release change list:
```
sessions/pm-forseti/artifacts/release-candidates/<release-id>/01-change-list.md
```
**This is the scope freeze point.** After this, no net-new features enter the current cycle.

---

## Stage 0 — Scope selection (PM picks from groomed-only pool)

Stage 0 is fast because all grooming was done during the prior Stage 3.

### Step 1 — Identify groomed features

A feature is **ready for release** when all four exist:
```
features/<id>/feature.md                  ✓  (includes completed Gap Analysis section)
features/<id>/01-acceptance-criteria.md   ✓  (all criteria tagged [NEW]/[EXTEND]/[TEST-ONLY])
features/<id>/03-test-plan.md             ✓
```
Anything missing any of these is **not eligible**. Do not negotiate — defer it. The next grooming cycle will finish it.

### Step 2 — Select release scope

Review the groomed pool, prioritize by ROI + mission alignment, and write:
```
sessions/pm-forseti/artifacts/release-candidates/<release-id>/01-change-list.md
```
This is the **scope freeze**. No net-new items after this point.

### Step 3 — Queue QA preflight

```bash
scripts/release-cycle-start.sh forseti <release-id>
# or for coordinated:
scripts/coordinated-release-cycle-start.sh <release-id>
```

Dev starts immediately. Tests already exist (written during grooming). Dev builds to make them PASS.

---

## PM → QA Handoff (runs during Stage 3 grooming, not Stage 0)

```bash
./scripts/pm-qa-handoff.sh forseti <feature-id>
```

This writes a QA inbox item containing:
- The feature brief (`feature.md`)
- The acceptance criteria (`01-acceptance-criteria.md`)
- An explicit instruction to generate the QA test plan and feature overlay metadata for the feature

**QA output contract for this handoff:**
- Input: `feature.md` + `01-acceptance-criteria.md`
- Outputs:
  - `features/<feature-id>/03-test-plan.md`
  - `qa-suites/products/forseti/features/<feature-id>.json`
  - QA outbox completion note
- QA must validate the overlay manifest before signaling completion

**PM must not send the handoff until `01-acceptance-criteria.md` is complete.** Incomplete AC → QA has nothing to write tests against.
Likewise, a quiet suggestion-intake run does **not** mean grooming is done if the backlog audit still shows partially groomed features.

---

## QA Test Generation Process (Stage 0 → 3 bridge, QA side)

When QA receives a `testgen-<feature-id>` inbox item:

### Step 1 — Read the AC

Open the attached `01-acceptance-criteria.md`. Every acceptance criterion becomes one or more test cases.

### Step 2 — Categorize test cases

For each AC item, determine the test type:

| AC type | Test approach |
|---------|--------------|
| Route accessible to role X | Add to `role-url-audit` suite — expected HTTP 200 for role X |
| Route blocked for role Y | Add to `role-url-audit` suite — expected HTTP 403 for role Y |
| Form submission / E2E flow | Add to `jobhunter-e2e` or new feature-specific Playwright suite |
| Content visible / not visible | Add to crawl + role audit suite |
| Permission check | Add to `qa-permissions.json` rule + role audit |

### Step 3 — Add runnable suite metadata

Create or update the feature overlay manifest: `qa-suites/products/forseti/features/<feature-id>.json`  
Do **not** edit the live `qa-suites/products/forseti/suite.json` during grooming.

Each overlay suite entry must include:
- `owner_seat`
- `source_path`
- `env_requirements`
- `release_checkpoint`

After editing:
```bash
python3 scripts/qa-suite-validate.py --product forseti --feature-id <feature-id>
```

### Step 4 — Write the test plan stub

Create `features/<feature-id>/03-test-plan.md` from `templates/03-test-plan.md`.  
List each test case added, the suite it lives in, the overlay path, and the expected PASS/FAIL signal.

### Step 5 — Report back to PM

Write outbox update: `sessions/qa-forseti/outbox/<item-id>.md`  
Include:
- How many test cases added
- Which suites were updated
- Any AC items that couldn't be expressed as automation (flag to PM)
- Confirmation: suite validated

### Step 5 — Signal completion back to PM (required)

When test cases are written and suite is validated, close the loop:

```bash
./scripts/qa-pm-testgen-complete.sh forseti <feature-id> \
  "Added 3 test cases to role-url-audit suite; 1 Playwright flow added; suite validated"
```

This:
- Marks `features/<id>/feature.md` status → `ready`
- Writes a PM inbox item confirming the feature is groomed and in the ready pool
- Verifies the feature overlay manifest exists and validates cleanly
- PM doesn't need to do anything — the feature just becomes available at next Stage 0

**After this step, Dev can start implementing.** The test cases exist. Dev's job is to make them PASS.

---

## Timing summary — two parallel tracks

Grooming for the **next release** runs during **Stage 3 of the current release**.
Stage 0 of any release only selects from already-groomed items. This keeps every release moving.

```
CURRENT RELEASE                          NEXT RELEASE (grooming, parallel)
───────────────────────────────────      ─────────────────────────────────────────
Stage 0  (scope freeze)
  PM selects ONLY groomed items
  (feature.md + AC + test-plan exist)
  → 01-change-list.md                    ← items not groomed yet: deferred here
  QA preflight queued
     │
Stage 3  Dev executes                    PM: suggestion-intake.sh
         QA monitors                     PM: triage (accept/defer/decline)
         (current release)               PM: write 01-acceptance-criteria.md
                                         PM→QA: pm-qa-handoff.sh
                                         QA: generate test cases → feature overlay
                                         QA: write 03-test-plan.md
                                         ✓ feature is now GROOMED (ready pool)
     │
Stage 4  QA full regression run
         Dev↔QA repair loop
     │
Stage 5  Release candidate assembly
Stage 6  PM signoff
Stage 7  Ship
     │
     └─▶ Next Stage 0:
         PM selects from groomed pool
         (all grooming done during prior Stage 3 — instant scope selection)
```

**The result:** Stage 0 is fast — PM picks from a ready list, freezes scope, and Dev starts immediately.
No grooming work ever blocks or delays an in-flight release.

---

## Artifacts produced at each step

| Step | Artifact | Location |
|------|----------|----------|
| Suggestion intake | Batch inbox item | `sessions/pm-forseti/inbox/<date>-suggestion-intake/` |
| Triage decision | Triage file | `sessions/pm-forseti/inbox/<date>-suggestion-intake/triage/NID-<n>-triage.md` |
| Feature brief | `feature.md` | `features/<feature-id>/feature.md` |
| Acceptance criteria | `01-acceptance-criteria.md` | `features/<feature-id>/01-acceptance-criteria.md` |
| Release scope | `01-change-list.md` | `sessions/pm-forseti/artifacts/release-candidates/<release-id>/01-change-list.md` |
| QA handoff | QA inbox item | `sessions/qa-forseti/inbox/<date>-testgen-<feature-id>/` |
| Runnable suite metadata | Feature overlay manifest | `qa-suites/products/forseti/features/<feature-id>.json` |
| Live release suite | Product suite manifest | `qa-suites/products/forseti/suite.json` |
| Permission rules | QA permissions | `org-chart/sites/forseti.life/qa-permissions.json` |
| Test plan | `03-test-plan.md` | `features/<feature-id>/03-test-plan.md` |
| Verification report | `04-verification-report.md` | `features/<feature-id>/04-verification-report.md` |

---

## Related files

| File | Purpose |
|------|---------|
| `runbooks/feature-intake.md` | Community suggestion intake detail |
| `runbooks/release-cycle-process-flow.md` | Full 9-stage release cycle |
| `runbooks/shipping-gates.md` | Release gate requirements |
| `scripts/suggestion-intake.sh` | Pull community suggestions → PM inbox |
| `scripts/suggestion-triage.sh` | Record PM triage decision |
| `scripts/pm-qa-handoff.sh` | Formal PM→QA feature handoff |
| `templates/suggestion-triage.md` | Triage decision form |
| `templates/01-acceptance-criteria.md` | AC template (QA contract) |
| `templates/03-test-plan.md` | QA test plan template |
| `qa-suites/products/forseti/suite.json` | Canonical test suite manifest |
| `org-chart/sites/forseti.life/qa-permissions.json` | Permission truth table |
