# Site Instructions: forseti.life

## Authority
- Primary owner: `pm-forseti`
- Methodology owner: `ceo-copilot`

## Mission Statement (non-negotiable)
> **Democratize and decentralize internet services by building community-managed versions of core systems for scientific, technology-focused, and tolerant people.**

This is the core product mission. All features, priorities, and trade-offs must be evaluated against it.

**What this means in practice:**
- Features that concentrate control, add tracking/surveillance, or restrict community access **contradict the mission** — escalate to Board before proceeding.
- Features that lower barriers, increase transparency, improve access, or add community ownership **align with the mission** — CEO can approve and execute without Board consult.
- When in doubt about mission alignment: ask "does this give power to the community or take it away?"

**Current product/project authority** (as of 2026): `https://forseti.life/roadmap` is the authoritative list for active portfolio items on Forseti. Its backing source is `dashboards/PROJECTS.md`, and every active item should be represented there as a numbered `PROJ-*` entry. Do not maintain a separate competing list in seat memory.

## Applies to
All seats with `website_scope: ["forseti.life"]`.

## Environments
- Production `BASE_URL`: `https://forseti.life`
- There is no local/dev environment on this host. This server IS production (Apache 2.4 on ports 80/443, Let's Encrypt SSL).
- **Code is always live**: `/var/www/html/forseti/web/modules/custom` is a live directory of symlinks that points module-by-module at canonical repositories under `/home/ubuntu/forseti.life/*`, and `/var/www/html/forseti/web/themes/custom/forseti` points directly at `/home/ubuntu/forseti.life/forseti-theme`. Any committed change in those canonical targets is immediately present in production — no rsync/deploy step is needed for module or theme changes.
- **GitHub Actions deploy.yml** is therefore redundant for module/theme changes on this server. It still handles: config/sync updates via rsync, composer installs, and `drush cr`. If deploy.yml stops triggering (GitHub Actions), do NOT escalate as a code-deploy blocker — verify with `drush config:status` and a checksum diff instead. (Lesson: 2026-04-08 false alarm where pm-forseti halted release-b post-push steps because deploy.yml hadn't triggered since 2026-04-02; production was already current via symlinks.)

Rule:
- QA seats must set `ALLOW_PROD_QA=1` to run live audits via `scripts/site-audit-run.sh` (default target is now production; the script still requires explicit opt-in).
- Do NOT run recursive crawls or destructive probes against production unless explicitly authorized for a specific purpose.

## Code roots (on this host)
- Canonical repository workspace root: `/home/ubuntu/forseti.life`
- Canonical standalone theme repo: `/home/ubuntu/forseti.life/forseti-theme`
- Canonical module development repos are the owning sibling repositories under `/home/ubuntu/forseti.life/*` (for example `ai-conversation`, `forseti-amisafe`, `forseti-games`, `forseti-job-hunter`, `forseti-jobhunter-tester`, `forseti-cluster`, `drupal-langgraph`)
- Live production web root: `/var/www/html/forseti/web`

## Working convention
- Make Forseti module/product changes in the owning canonical repository under `/home/ubuntu/forseti.life/*`.
- For `job_hunter` specifically, `/home/ubuntu/forseti.life/forseti-job-hunter` is the only source of truth, and the live site consumes it through `/var/www/html/forseti/web/modules/custom/job_hunter`.
- Use `/home/ubuntu/forseti.life/forseti-theme` for the standalone Forseti Drupal theme.
- Theme and module changes should land in the owning sibling repository that backs the live symlink target.

## Product-wide rules
- Keep work items logically separated from other websites.
- Module ownership is authoritative in `org-chart/ownership/module-ownership.yaml`.
- Cross-module work uses `runbooks/passthrough-request.md`.

## Security requirements (forseti-copilot-agent-tracker)
- All feature.md files under `features/forseti-copilot-agent-tracker/` MUST include a populated `## Security acceptance criteria` section before the feature moves to `in_progress`.
- Minimum required subsections: Authentication/permission surface, CSRF expectations, Input validation requirements, PII/logging constraints.
- Verification: `grep -l "Security acceptance criteria" features/forseti-copilot-agent-tracker/*/feature.md` must return all in-progress feature files.
- Added: 2026-04-05 (gap fix from sec-analyst outbox 20260227-improvement-round-20260226-forseti-release-d).

## QA posture
- Anonymous crawling is expected to work on public pages.
- Auth-required surfaces may return 403/redirect; report unexpected changes.
- Continuous audit evidence (if configured): `sessions/qa-forseti/artifacts/auto-site-audit/latest/`.
- Role matrix + expected permissions source of truth: `org-chart/sites/forseti.life/qa-permissions.json`.

## Escalation
- Cross-module needs/conflicts: `runbooks/passthrough-request.md`, `runbooks/conflict-resolution.md`.
