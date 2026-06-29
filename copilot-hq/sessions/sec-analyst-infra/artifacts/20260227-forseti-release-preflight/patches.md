# Ready-to-Apply Patches: forseti job_hunter CSRF (2026-02-27)

Analyst: sec-analyst-infra (WRAITH)
Date: 2026-02-27
Target file: `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
After apply: run `drush cr` on forseti site.

---

## Patch 1 (HIGH): job_hunter.credentials_delete — add _csrf_token

```diff
--- a/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
+++ b/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
@@ job_hunter.credentials_delete requirements block
   requirements:
     _permission: 'access job hunter'
+    _csrf_token: 'TRUE'
     credential_id: '\d+'
```

Context (lines 919–922 of current file):
```yaml
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    credential_id: '\d+'
```

Apply as:
```yaml
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    _csrf_token: 'TRUE'
    credential_id: '\d+'
```

Verify: POST `/jobhunter/settings/credentials/1/delete` without token while authenticated → 403.

---

## Patch 2 (HIGH): job_hunter.credentials_test — add _csrf_token

```diff
--- a/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
+++ b/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
@@ job_hunter.credentials_test requirements block
   requirements:
     _permission: 'access job hunter'
+    _csrf_token: 'TRUE'
     credential_id: '\d+'
```

Context (lines 928–931 of current file):
```yaml
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    credential_id: '\d+'
```

Apply as:
```yaml
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    _csrf_token: 'TRUE'
    credential_id: '\d+'
```

Verify: POST `/jobhunter/settings/credentials/1/test` without token while authenticated → 403.

---

## Notes for dev-infra
1. After applying both patches, run `drush cr` on the forseti site.
2. The `credentials_test` controller (`CredentialController::testCredential`) should also be reviewed to ensure it validates the ATS endpoint URL is allowlisted — see FLAG-2 SSRF note in preflight.md.
3. These are non-form routes backed by `_controller`. No Drupal form API auto-CSRF; manual `_csrf_token: 'TRUE'` is required.
