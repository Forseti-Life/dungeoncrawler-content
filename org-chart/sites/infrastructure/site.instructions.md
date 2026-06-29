# Site Instructions: infrastructure

## Authority
This file is owned by the `ceo-copilot` seat (site layer).
Contributors: pm-infra, dev-infra, qa-infra.

## What this site is
"infrastructure" is NOT a website. It refers to the HQ automation layer:
- Shell scripts in `scripts/`
- QA suite manifests in `qa-suites/products/infrastructure/`
- Agent orchestration configuration in `org-chart/agents/`
- Runbooks and templates owned by CEO/PM-infra

## BASE_URL
None. Infrastructure has no web surface, no HTTP endpoints, no browser UI.
- Do NOT run Playwright, curl-based URL audits, or site crawls against this scope.
- Any agent receiving a Playwright or URL-audit dispatch for `website_scope: infrastructure` must switch to operator-audit mode (see below).

## Operator-audit mode (required for infrastructure scope)
When an agent is dispatched with `website_scope: infrastructure`:
1. Do NOT attempt URL probing, Playwright, or curl-based verification.
2. Perform a static/structural audit instead:
   - Script syntax: `bash -n scripts/<file>.sh`
   - QA suite manifest validation: `python3 scripts/qa-suite-validate.py`
   - Lint (when available): `bash scripts/lint-scripts.sh`
   - File ownership/scope checks: review `org-chart/agents/agents.yaml` and `org-chart/agents/instructions/`
3. Report findings as operator audit results, not web audit results.

## Repo root
`/home/ubuntu/forseti.life`

## QA posture
- No live HTTP verification possible.
- Automated verification: `python3 scripts/qa-suite-validate.py` (always), `bash scripts/lint-scripts.sh` (when present).
- Release gate: structural checks must pass; no URL-based gate applicable.

## Release norms
- No web deploy step for infrastructure changes.
- Release = git commit + git push (executor/CEO coordinates).
- Release operator: `ceo-copilot` (infrastructure has no site-specific PM release operator).

## Auth expectations
- No authentication layer. Infrastructure scripts run as the executor user (`keithaumiller`).
