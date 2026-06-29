# Infrastructure (HQ Automation)

## What this is
The `infrastructure` product scope covers all HQ automation tooling:
- Shell scripts (`scripts/`)
- QA suite manifests (`qa-suites/products/infrastructure/`)
- Agent orchestration config (`org-chart/agents/`)
- Runbooks and templates

This is NOT a public-facing website. There is no BASE_URL, no HTTP surface, and no web UI.

## Owners
- PM: `pm-infra`
- Dev: `dev-infra`
- QA: `qa-infra`
- BA: `ba-infra`

## How to verify (operator audit)
```bash
# Syntax check all scripts
for f in scripts/*.sh; do bash -n "$f" && echo "OK: $f" || echo "FAIL: $f"; done

# QA suite manifest validation
python3 scripts/qa-suite-validate.py

# Lint (when available)
bash scripts/lint-scripts.sh
```

## Release process
- No web deploy. Changes ship via git commit + push by executor/CEO.
- QA gate: structural checks must pass (see above).

## Key files
- `org-chart/sites/infrastructure/site.instructions.md` — authoritative environment facts
- `org-chart/sites/infrastructure/qa-regression-checklist.md` — running regression checklist
- `qa-suites/products/infrastructure/suite.json` — automated QA suite manifest
