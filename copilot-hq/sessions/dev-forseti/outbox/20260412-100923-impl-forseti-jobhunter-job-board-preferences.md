Now I'll implement all components in parallel:

Now I'll add all four pieces: update hook, routes, controller methods, and JobDiscoveryService method:

Now add routes:

Now add controller methods to CompanyController:

Now add `getSourcePreferences()` to JobDiscoveryService for AC-3:

Duplicate key name — `unique keys` already creates an index, can't also have `indexes` with the same name:

Schema is perfect — all columns present, unique key on `uid`. Now clear cache and test the route:

403 for unauthenticated (SEC-1 ✅). Verify CSRF is on POST route only:

SEC-2 ✅ — GET route has no CSRF, POST save route has `_csrf_token: 'TRUE'`. Now commit:

---

- Status: done
- Summary: Implemented `forseti-jobhunter-job-board-preferences` in one commit (`c68e2789c`). `job_hunter_update_9051` creates `jobhunter_source_preferences` (uid unique, sources_enabled JSON, min_salary, remote_preference, location_radius_km). Form at `/jobhunter/preferences/sources` (GET, no CSRF) has source checkboxes, salary, remote, and radius fields. POST save at `/jobhunter/preferences/sources/save` enforces CSRF split-route (SEC-2), uid from session (SEC-3), source key allowlist (SEC-4), and salary/radius bounds (SEC-5). Idempotent upsert satisfies AC-5. `JobDiscoveryService::getSourcePreferences(uid)` added for AC-3 adapter filtering. Update hook ran cleanly; 403 on unauthenticated access verified.

## Commits
- `c68e2789c` — feat(job-hunter): add job-board source preferences (update 9051)

## Next actions
- qa-forseti: Gate 2 for `forseti-jobhunter-job-board-preferences`

## ROI estimate
- ROI: 20 — Completes release-d feature #4, unblocking Gate 2 for this item.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260412-100923-impl-forseti-jobhunter-job-board-preferences
- Generated: 2026-04-12T13:18:38+00:00
