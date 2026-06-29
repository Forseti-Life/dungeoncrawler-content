- Status: done
- Completed: 2026-04-18T16:48:42Z

# Groom Next Release: 20260412-dungeoncrawler-release-o

- Site: dungeoncrawler
- Current release (Dev executing): 20260412-dungeoncrawler-release-n
- Next release (your target): 20260412-dungeoncrawler-release-o

The org always has two releases defined simultaneously:
- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.
- **Next release** — You groom the backlog so Stage 0 of 20260412-dungeoncrawler-release-o is instant scope selection.

This task does NOT touch the current release. All work here is for 20260412-dungeoncrawler-release-o only.

## Steps

### 1. Pull community suggestions
```bash
./scripts/suggestion-intake.sh dungeoncrawler
```

### 2. Triage each suggestion
```bash
./scripts/suggestion-triage.sh dungeoncrawler <nid> accept <feature-id>
./scripts/suggestion-triage.sh dungeoncrawler <nid> defer
./scripts/suggestion-triage.sh dungeoncrawler <nid> decline
./scripts/suggestion-triage.sh dungeoncrawler <nid> escalate
```

Mandatory gate: if a suggestion clearly requests security abuse, release-integrity bypass, intentional crash/data-destruction behavior,
or a major architecture replatform/rewrite,
do NOT accept at PM level. Use `escalate` so it is reviewed at human board level first.
Otherwise continue normal PM triage so the majority of valid product requests can flow.

### 3. Write Acceptance Criteria for each accepted feature
  features/<feature-id>/01-acceptance-criteria.md  (from templates/01-acceptance-criteria.md)
  Must be complete before handing to QA.

### 4. Hand off to QA for test plan design
```bash
./scripts/pm-qa-handoff.sh dungeoncrawler <feature-id>
```
QA writes features/<id>/03-test-plan.md (spec only — NOT added to suite.json until Stage 0).
QA signals back via qa-pm-testgen-complete.sh when done.

### 5. When next Stage 0 starts: activate scoped features
For each feature selected into 20260412-dungeoncrawler-release-o:
```bash
./scripts/pm-scope-activate.sh dungeoncrawler <feature-id>
```
This sends QA the activation task to add tests to suite.json for the live release.

## Groomed/ready gate (required for Stage 0 eligibility)
A feature is ready when ALL THREE exist:
- features/<id>/feature.md          (status: ready)
- features/<id>/01-acceptance-criteria.md
- features/<id>/03-test-plan.md

Security override: any feature requiring board-security review is ineligible until explicit board approval is documented.

Anything not groomed when Stage 0 of 20260412-dungeoncrawler-release-o starts is automatically deferred.

## References
- runbooks/feature-intake.md
- runbooks/intake-to-qa-handoff.md
- Agent: pm-dungeoncrawler
- Status: pending
