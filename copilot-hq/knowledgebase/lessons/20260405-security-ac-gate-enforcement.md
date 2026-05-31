# Lesson: Security Acceptance Criteria Gate Enforcement

**Date:** 2026-04-05 (implemented 2026-04-06)  
**Source:** Post-release gap review `20260405-improvement-round-fake-no-signoff-release`  
**Reported by:** sec-analyst  
**Implemented by:** dev-infra  

## Problem

`features/forseti-copilot-agent-tracker/feature.md` reached `in_progress` status across multiple
release cycles before a `## Security acceptance criteria` section was added. The gap was flagged
by sec-analyst in Feb 2026 but not enforced until April 2026 because `pm-scope-activate.sh`
validated grooming artifacts (feature.md, AC file, test plan) but not the security surface
documentation inside feature.md.

## Root cause

No gate at scope-activation time required security AC documentation. Enforcement relied on
sec-analyst review timing, which could lag behind scope selection.

## Fix applied

`scripts/pm-scope-activate.sh` now enforces a security AC gate before scope activation:

1. Checks for `## Security acceptance criteria` section (case-insensitive) in `feature.md`
2. Verifies the section is non-empty (at least one non-blank line)
3. Accepts `- Security AC exemption: <reason>` to bypass for features with no security surface

## Gate behavior

```bash
# Missing section → exit 1
bash scripts/pm-scope-activate.sh forseti.life my-feature
# ERROR: feature.md is missing a '## Security acceptance criteria' section.

# Section present and non-empty → gate passes
# Exemption field present → gate passes (bypass)
```

## Required section format

```markdown
## Security acceptance criteria
- Authentication/permission surface: <who can access>
- CSRF expectations: <which routes need CSRF token>
- Input validation: <what is validated and where>
- PII/logging constraints: <what must not be logged>
```

## Exemption format (for pure content/display features)

```markdown
- Security AC exemption: static content only, no routes or user input
```

## Note for existing features

As of 2026-04-06, only `forseti-copilot-agent-tracker` had a security AC section.
All other features will require either the section or an exemption field before
their next scope activation. PM agents should add the section during grooming.

## Commit

`c4b12201` — feat(infra): add security AC gate to pm-scope-activate.sh
