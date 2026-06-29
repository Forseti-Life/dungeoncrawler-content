Route path mismatch: suite and permissions have `/jobhunter/application-notes` but Dev implemented `/jobhunter/jobs/{job_id}/notes`. Fixing both files.

Site audit: all PASS — 0 permission violations, 0 missing assets. Now updating the regression checklist and committing.

---

- Status: done
- Summary: APPROVE — forseti-jobhunter-application-notes verified. All security controls confirmed in code (ownership guard, strip_tags, email validation, 2000-char limit, UPSERT, PII-safe logging). Live ACL: anon GET/POST → 403, non-integer job_id → 404, auth POST no-CSRF → 403 all PASS. DB schema (9 cols) PASS. Site audit 20260411-003040: 0 violations, 0 drift. **QA artifact bug fixed**: suite entries and qa-permissions.json had wrong paths (`/jobhunter/application-notes`) — corrected to actual routes (`/jobhunter/jobs/{job_id}/notes`) in commit `ccb539277`. Outbox committed `04e0f51df`.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260410-unit-test-20260410-220901-implement-forseti-jobhunter-application-note
- Generated: 2026-04-11T00:35:40+00:00
