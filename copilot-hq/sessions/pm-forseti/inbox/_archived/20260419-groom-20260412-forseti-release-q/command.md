# Groom Next Release: 20260412-forseti-release-q

- Site: forseti.life
- Current release (Dev executing): 20260412-forseti-release-p
- Next release (your target): 20260412-forseti-release-q

The org always has two releases defined simultaneously:
- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.
- **Next release** — You groom the backlog so Stage 0 of 20260412-forseti-release-q is instant scope selection.

This task does NOT touch the current release. All work here is for 20260412-forseti-release-q only.

## Steps

### 1. Audit the existing next-release backlog first
```bash
python3 - <<'PY'
import pathlib, re
site = "forseti.life"
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
If this prints any features, finish those backlog items before treating suggestion intake as done.

### 2. Pull community suggestions
```bash
./scripts/suggestion-intake.sh forseti
```

### 3. Triage each suggestion
```bash
./scripts/suggestion-triage.sh forseti <nid> accept <feature-id>
./scripts/suggestion-triage.sh forseti <nid> defer
./scripts/suggestion-triage.sh forseti <nid> decline
./scripts/suggestion-triage.sh forseti <nid> escalate
```

Mandatory gate: if a suggestion clearly requests security abuse, release-integrity bypass, intentional crash/data-destruction behavior,
or a major architecture replatform/rewrite,
do NOT accept at PM level. Use `escalate` so it is reviewed at human board level first.
Otherwise continue normal PM triage so the majority of valid product requests can flow.

### 4. Write or complete Acceptance Criteria
  features/<feature-id>/01-acceptance-criteria.md  (from templates/01-acceptance-criteria.md)
  Any accepted or already-tracked next-release feature missing AC must be completed before handing to QA.

### 5. Hand off to QA for test plan design
```bash
./scripts/pm-qa-handoff.sh forseti <feature-id>
```
Any next-release feature that has AC but is missing `03-test-plan.md` must be handed off.
QA writes features/<id>/03-test-plan.md (spec only — NOT added to suite.json until Stage 0).
QA signals back via qa-pm-testgen-complete.sh when done.

### 6. When next Stage 0 starts: activate scoped features
For each feature selected into 20260412-forseti-release-q:
```bash
./scripts/pm-scope-activate.sh forseti <feature-id>
```
This sends QA the activation task to add tests to suite.json for the live release.

## Groomed/ready gate (required for Stage 0 eligibility)
A feature is ready when ALL THREE exist:
- features/<id>/feature.md          (status: ready)
- features/<id>/01-acceptance-criteria.md
- features/<id>/03-test-plan.md

If suggestion intake returns nothing, the grooming task is still not done until the backlog audit above is clean.

Security override: any feature requiring board-security review is ineligible until explicit board approval is documented.

Anything not groomed when Stage 0 of 20260412-forseti-release-q starts is automatically deferred.

## References
- runbooks/feature-intake.md
- runbooks/intake-to-qa-handoff.md
- Agent: pm-forseti
- Status: pending
