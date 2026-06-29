# Product Team Onboarding Checklist

- Product ID: `<product-id>`
- Site key: `<site-key>`
- Team owner (PM): `<pm-agent-id>`
- Target release cycle: `<release-id>`

## 1) Seat setup

- [ ] Added `pm-<product>` in `org-chart/agents/agents.yaml`
- [ ] Added `dev-<product>` in `org-chart/agents/agents.yaml`
- [ ] Added `qa-<product>` in `org-chart/agents/agents.yaml`

## 2) QA suite source of truth

- [ ] Created `qa-suites/products/<product>/suite.json`
- [ ] Replaced all placeholder suite commands with executable commands
- [ ] Confirmed required release suites are marked `required_for_release=true`

## 3) Team registry

- [ ] Added team in `org-chart/products/product-teams.json`
- [ ] Set aliases that match expected operator inputs
- [ ] Linked `qa_agent`, `dev_agent`, `pm_agent`
- [ ] Linked `qa_suite_manifest`
- [ ] Set release flags (`release_preflight_enabled`, `coordinated_release_default`)

## 4) Site audit (if enabled)

- [ ] Added `site_audit.*` config values
- [ ] Added permissions file at `org-chart/sites/<site>/qa-permissions.json`
- [ ] Verified base URL env var strategy for local/dev vs production gating

## 5) Validation

- [ ] `python3 scripts/product-team-standard-validate.py`
- [ ] `python3 scripts/qa-suite-validate.py`
- [ ] `scripts/release-cycle-start.sh <alias> <release-id>` queues expected QA preflight item
- [ ] `scripts/coordinated-release-cycle-start.sh <release-id>` includes/excludes team as configured