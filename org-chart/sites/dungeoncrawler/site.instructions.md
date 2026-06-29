# Site Instructions: dungeoncrawler

## Authority
- Primary owner: `pm-dungeoncrawler`
- Methodology owner: `ceo-copilot`

## Applies to
All seats with `website_scope: ["dungeoncrawler"]`.

## Environments
- Production `BASE_URL`: `https://dungeoncrawler.forseti.life`
- There is no local/dev environment on this host. This server IS production (Apache 2.4 on ports 80/443, Let's Encrypt SSL).

Rule:
- QA seats must set `ALLOW_PROD_QA=1` to run live audits via `scripts/site-audit-run.sh` (default target is now production; the script still requires explicit opt-in).
- Do NOT run recursive crawls or destructive probes against production unless explicitly authorized for a specific purpose.

## Code roots (on this host)
- Live Drupal docroot: `/var/www/html/dungeoncrawler`
- Live Drupal web root: `/var/www/html/dungeoncrawler/web`
- Canonical workspace root: `/home/ubuntu/forseti.life`
- Canonical editable product repo: `/home/ubuntu/forseti.life/dungeoncrawler-pf2e`
- Canonical standalone theme repo: `/home/ubuntu/forseti.life/dungeoncrawler-theme`
- Canonical standalone module repos: `/home/ubuntu/forseti.life/dungeoncrawler-content`, `/home/ubuntu/forseti.life/dungeoncrawler-tester`, and shared `/home/ubuntu/forseti.life/ai-conversation`
- `dungeoncrawler-pf2e` remains the standalone product repo, and Dungeoncrawler module development should land in the owning canonical sibling repo under `/home/ubuntu/forseti.life/*` without requiring a matching `copilot-hq` nested-repo pointer commit.
- Apache serves the live site from `/var/www/html/dungeoncrawler/web`.
- Inside the live `web/modules/custom` directory, the current module links resolve to canonical repositories:
  - `ai_conversation` -> `/home/ubuntu/forseti.life/ai-conversation`
  - `dungeoncrawler_content` -> `/home/ubuntu/forseti.life/dungeoncrawler-content`
  - `dungeoncrawler_tester` -> `/home/ubuntu/forseti.life/dungeoncrawler-tester`
- The live theme entry resolves directly to the standalone theme repo:
  - `/var/www/html/dungeoncrawler/web/themes/custom/dungeoncrawler` -> `/home/ubuntu/forseti.life/dungeoncrawler-theme`
- Site config sync resolves to the product repo:
  - `/var/www/html/dungeoncrawler/config/sync` -> `/home/ubuntu/forseti.life/dungeoncrawler-pf2e/config/sync`

## Architecture source of truth (required)
- Site-level architecture source of truth for Dungeoncrawler is this file: `org-chart/sites/dungeoncrawler/site.instructions.md`.
- Custom-module architecture source of truth is module-local `module.instructions.md` in each canonical module repository.
- Each `module.instructions.md` must define the module's subsystem map, entry points, and key paths so implementation routing is deterministic.
- When a request is module-specific, start at that module's `module.instructions.md` before broad code exploration.

### Custom module instruction index

| Module | Canonical repository | Module instructions |
|---|---|---|
| `dungeoncrawler_content` | `/home/ubuntu/forseti.life/dungeoncrawler-content` | `/home/ubuntu/forseti.life/dungeoncrawler-content/module.instructions.md` |
| `dungeoncrawler_tester` | `/home/ubuntu/forseti.life/dungeoncrawler-tester` | `/home/ubuntu/forseti.life/dungeoncrawler-tester/module.instructions.md` |
| `ai_conversation` (shared) | `/home/ubuntu/forseti.life/ai-conversation` | `/home/ubuntu/forseti.life/ai-conversation/module.instructions.md` |

## Working convention
- Make Dungeoncrawler module/product-code changes in the owning canonical repository under `/home/ubuntu/forseti.life/*`.
- Use `/home/ubuntu/forseti.life/dungeoncrawler-pf2e` for full-product code and product-owned assets.
- Use `/home/ubuntu/forseti.life/dungeoncrawler-theme` for the standalone Drupal theme.
- Use `/home/ubuntu/forseti.life/dungeoncrawler-content` and `/home/ubuntu/forseti.life/dungeoncrawler-tester` for those standalone module repositories.
- Use `/var/www/html/dungeoncrawler` when commands must run against the full live Drupal install (for example `drush cr`).
- Route Dungeoncrawler-owned code into the correct canonical sibling repository, not `copilot-hq`.

## Product-wide rules
- Keep work items logically separated from other websites.
- Module ownership is authoritative in `org-chart/ownership/module-ownership.yaml`.
- Cross-module work uses `runbooks/passthrough-request.md`.

## QA posture
- Anonymous crawling is expected to work on public pages.
- Auth-required surfaces may return 403/redirect; report unexpected changes.
- Continuous audit evidence (if configured): `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/`.
- Role matrix + expected permissions source of truth: `org-chart/sites/dungeoncrawler/qa-permissions.json`.

## Escalation
- Cross-module needs/conflicts: `runbooks/passthrough-request.md`, `runbooks/conflict-resolution.md`.
