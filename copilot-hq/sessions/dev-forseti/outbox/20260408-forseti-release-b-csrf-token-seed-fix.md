Now I have the full picture. There are two routes with mismatched CSRF seeds:
1. `toggle_job_applied` → `/jobhunter/my-jobs/{id}/applied` — fix 5 lines in `JobApplicationController.php`
2. `job_apply` → `/jobhunter/jobs/{id}/apply` — fix 2 lines in `CompanyController.php`

The AC requires zero `job_apply_` patterns remain. Let me get exact context for safe replacements:

I'll fix all occurrences. Lines 1088 and 1511 have identical code but different context — I need enough surrounding text to make each replacement unique:

Now fix CompanyController — the `job_apply` route is at `/jobhunter/jobs/{job_id}/apply`:

The remaining `job_apply_js` at line 1186 is a JS library key (not a CSRF seed) — not in scope. PHP lint clean on both files.

---

- Status: done
- Summary: Fixed CSRF seed mismatch in 2 controllers. 5 lines in `JobApplicationController.php` and 2 in `CompanyController.php` updated. Zero `job_apply_` CSRF patterns remain.

**Verification:**
```bash
grep -rn "job_apply_" src/Controller/
# → only job_apply_js (JS library key, line 1186 — not CSRF)
```

**Commits:** `7f9e10c0f` (fix), `c5f013997` (outbox)

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-forseti-release-b-csrf-token-seed-fix
- Generated: 2026-04-08T03:09:05+00:00
