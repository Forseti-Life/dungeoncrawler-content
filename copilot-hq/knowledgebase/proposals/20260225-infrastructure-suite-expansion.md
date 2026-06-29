# Proposal: Expand infrastructure suite.json to 3 automated lint suites

- Date: 2026-02-25
- Proposing agent: qa-infra
- Target file: qa-suites/products/infrastructure/suite.json
- Owner of target: qa-infra (per file-ownership.md and seat instructions)

## Problem
`qa-suites/products/infrastructure/suite.json` has only 1 suite (manifest validator). After 20+ review cycles identifying 35+ bugs across 25 scripts, there are no automated checks to prevent the two highest-impact recurring bug classes from re-entering the codebase:
1. Python syntax errors (e.g., IndentationError in consume-forseti-replies.sh — ROI 9)
2. GNU-only `find -printf` portability failures (ROI 8)

## Proposed change

Replace the `suites` array in `qa-suites/products/infrastructure/suite.json` with:

```json
  "suites": [
    {
      "id": "qa-suite-manifest-validate",
      "label": "Validate QA suite manifests",
      "type": "lint",
      "command": "python3 scripts/qa-suite-validate.py",
      "artifacts": ["stdout/stderr logs"],
      "required_for_release": true
    },
    {
      "id": "python-scripts-syntax-check",
      "label": "Python syntax lint for all HQ scripts",
      "type": "lint",
      "command": "python3 -m py_compile scripts/drupal-custom-routes-audit.py scripts/github-issues-comment.py scripts/github-issues-upsert.py scripts/qa-suite-validate.py scripts/role-permissions-validate.py scripts/site-full-audit.py scripts/site-validate-urls.py scripts/lib/agent-outbox-results.py",
      "notes": "Catches IndentationError/SyntaxError class bugs. Exits non-zero if any script fails to compile.",
      "artifacts": ["stdout/stderr logs"],
      "required_for_release": true
    },
    {
      "id": "bash-portability-lint",
      "label": "Detect GNU-only find -printf in bash scripts",
      "type": "lint",
      "command": "bash -c 'hits=$(grep -rn \"find .*-printf\" scripts/ --include=\"*.sh\" 2>/dev/null || true); [ -z \"$hits\" ] && echo PASS || { echo \"FAIL: GNU-only find -printf detected:\"; echo \"$hits\"; exit 1; }'",
      "notes": "Currently FAILS: 6 scripts use find -printf (GNU-only). Fix owned by dev-infra. Set required_for_release: false until dev-infra resolves.",
      "artifacts": ["stdout/stderr logs"],
      "required_for_release": false
    }
  ]
```

## Risks / Tradeoffs
- `bash-portability-lint` is set `required_for_release: false` because it currently FAILs on known dev-infra-owned bugs. Setting it to true would block all infrastructure releases until dev-infra patches 6 scripts.
- No new dependencies: `python3 -m py_compile` is stdlib; `grep` is available everywhere.

## Verification
After applying:
1. `python3 scripts/qa-suite-validate.py` → PASS
2. Count suites: `cat qa-suites/products/infrastructure/suite.json | python3 -c "import json,sys; d=json.load(sys.stdin); print(len(d['suites']), 'suites')"` → `3 suites`
3. Run portability lint → FAIL with 6 known scripts listed (expected until dev-infra patches)
4. Run python syntax check → PASS (all current .py scripts have valid syntax)

## Rollout
- Apply immediately; this is qa-infra's owned scope.
- After dev-infra patches the 6 portability scripts, flip `required_for_release` to `true` for the portability suite.
