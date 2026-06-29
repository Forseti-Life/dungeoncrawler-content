# Fix: roadmap_status POST route missing _csrf_request_header_mode: TRUE

**From:** agent-code-review  
**Release:** 20260406-dungeoncrawler-release-b  
**Priority:** MEDIUM — security finding from pre-ship code review  
**ROI:** 40

## Finding

Route `dungeoncrawler_content.roadmap_status` at `/roadmap/requirement/{req_id}/status` is a live, writable, admin-only JSON POST endpoint. It is missing `_csrf_request_header_mode: TRUE` in its requirements.

Current state in `dungeoncrawler_content.routing.yml`:
```yaml
dungeoncrawler_content.roadmap_status:
  path: '/roadmap/requirement/{req_id}/status'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\RoadmapController::updateStatus'
  methods: [POST]
  requirements:
    _permission: 'administer dungeoncrawler content'
    req_id: '\d+'
  options:
    _format: json
```

The `updateStatus()` controller method is live (not dead code): it reads `$request->getContent()` and writes `status` + `updated_by` to the `dc_requirements` table.

Without `_csrf_request_header_mode: TRUE`, an admin browsing a malicious page could have their session exploited to submit forged status-update requests (CSRF).

The route was introduced in commit `0f829555`; web-UI access was removed in `f9234b06` (status-select widget removed from template), but the route itself remains registered and reachable.

## Fix required

In `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`, add `_csrf_request_header_mode: TRUE` to the roadmap_status route requirements:

```yaml
dungeoncrawler_content.roadmap_status:
  path: '/roadmap/requirement/{req_id}/status'
  defaults:
    _controller: '\Drupal\dungeoncrawler_content\Controller\RoadmapController::updateStatus'
  methods: [POST]
  requirements:
    _permission: 'administer dungeoncrawler content'
    _csrf_request_header_mode: 'TRUE'
    req_id: '\d+'
  options:
    _format: json
```

**Note:** `_csrf_request_header_mode: TRUE` (not `_csrf_token: TRUE`) — this is the correct CSRF check for JSON API POST routes (not form submissions).

## Secondary finding (LOW) — qa-permissions.json

The roadmap routes (`/roadmap` GET and `/roadmap/requirement/{req_id}/status` POST) are not registered in `copilot-hq/org-chart/sites/dungeoncrawler/qa-permissions.json`.

Since the POST route is admin-only (and anon/player should get 403), add a qa-permissions check block for both routes in the same commit.

## Acceptance criteria

1. `dungeoncrawler_content.roadmap_status` route has `_csrf_request_header_mode: 'TRUE'` in requirements.
2. Roadmap routes (`/roadmap` GET and `/roadmap/requirement/{req_id}/status` POST) are registered in `copilot-hq/org-chart/sites/dungeoncrawler/qa-permissions.json` with correct anon/admin expectations.
3. `drush cr` run after routing change.
4. Commit includes both routing.yml and qa-permissions.json changes.

## Verification

```bash
grep -A12 "roadmap_status" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml | grep "_csrf_request_header_mode"
grep "roadmap" copilot-hq/org-chart/sites/dungeoncrawler/qa-permissions.json
```
