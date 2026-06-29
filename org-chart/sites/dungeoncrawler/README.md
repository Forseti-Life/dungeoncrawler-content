# Website Team: dungeoncrawler

Authoritative operating rules: `org-chart/sites/dungeoncrawler/site.instructions.md`

## Scope
All work items for **dungeoncrawler** should remain logically separated from other websites.

## Environments
- Production `BASE_URL`: `https://dungeoncrawler.forseti.life`

## Code roots (on this host)
- Live Drupal docroot: `/var/www/html/dungeoncrawler`
- Live Drupal web root: `/var/www/html/dungeoncrawler/web`
- Canonical workspace root: `/home/ubuntu/forseti.life`
- Canonical standalone theme repo: `/home/ubuntu/forseti.life/dungeoncrawler-theme`
- Canonical product/module repositories live as sibling repos under `/home/ubuntu/forseti.life/*` and are the source of truth for development

## Module Ownership
See:
- `org-chart/ownership/module-ownership.yaml`

## Active Work Queue
- Track via master session + delegated sessions.

## Escalation
Cross-module needs and conflicts go through:
- `runbooks/passthrough-request.md`
- `runbooks/conflict-resolution.md`
