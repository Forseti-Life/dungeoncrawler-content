# Website Team: forseti.life

Authoritative operating rules: `org-chart/sites/forseti.life/site.instructions.md`

## Scope
All work items for **forseti.life** should remain logically separated from other websites.

## Environments
- Production `BASE_URL`: `https://forseti.life`

## Code roots (on this host)
- Live Drupal docroot: `/var/www/html/forseti`
- Live Drupal web root: `/var/www/html/forseti/web`
- Canonical workspace root: `/home/ubuntu/forseti.life`
- Canonical standalone theme repo: `/home/ubuntu/forseti.life/forseti-theme`
- Canonical module/product repositories live as sibling repos under `/home/ubuntu/forseti.life/*` and are the source of truth for development

## Module Ownership
See:
- `org-chart/ownership/module-ownership.yaml`

## Active Work Queue
- Track via master session + delegated sessions.

## Escalation
Cross-module needs and conflicts go through:
- `runbooks/passthrough-request.md`
- `runbooks/conflict-resolution.md`
