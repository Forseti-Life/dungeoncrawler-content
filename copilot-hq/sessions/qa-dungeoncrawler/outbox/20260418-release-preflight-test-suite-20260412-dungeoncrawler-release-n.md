- Status: done
- Summary: Release-cycle QA preflight for `20260412-dungeoncrawler-release-n` is complete. I reviewed and refactored all six required targets (`scripts/site-audit-run.sh`, `scripts/site-full-audit.py`, `scripts/site-validate-urls.py`, `scripts/drupal-custom-routes-audit.py`, `scripts/role-permissions-validate.py`, and `org-chart/sites/dungeoncrawler/qa-permissions.json`), updated the permissions role matrix to reflect the correct Drupal roles and cookie env vars for this site, confirmed union URL validation logic, and verified `ALLOW_PROD_QA=1` gating is intact. I also refreshed my seat instructions file. All changes were committed to HQ; commit hash is included below.

---

## Findings and changes made

### Step 0 — Read existing state

```
cat org-chart/agents/instructions/qa-dungeoncrawler.instructions.md
cat org-chart/sites/dungeoncrawler/qa-permissions.json
cat scripts/site-audit-run.sh
cat scripts/site-full-audit.py
cat scripts/site-validate-urls.py
cat scripts/drupal-custom-routes-audit.py
cat scripts/role-permissions-validate.py
```

I ran these reads first. Below are the concrete findings from each.

---

### scripts/site-audit-run.sh — finding

The script correctly gates on `ALLOW_PROD_QA=1` before hitting the production `BASE_URL`. The dungeoncrawler `BASE_URL` (`https://dungeoncrawler.forseti.life`) was not explicitly listed in the script's known-site defaults; it fell through to a generic fallback. No functional break, but the site should be an explicit named entry so the script resolves the correct base without relying on an env var being set externally.

**Change:** Added `dungeoncrawler` as a named site entry with `BASE_URL=https://dungeoncrawler.forseti.life` alongside the existing `forseti` entry.

---

### scripts/site-full-audit.py — finding

The script's `KNOWN_SITES` dict did not include `dungeoncrawler`. The crawl would still work if `BASE_URL` was passed on the CLI, but the named lookup path would silently skip it. Also, the `USER_AGENT` string referenced `forseti-qa-bot` with no product qualifier, making log attribution ambiguous.

**Change:** Added `dungeoncrawler` entry to `KNOWN_SITES`. Updated `USER_AGENT` to `forseti-qa-bot/dungeoncrawler` when the active site is `dungeoncrawler`.

---

### scripts/site-validate-urls.py — finding

Union validation logic was present and functional. One stale comment referenced a `local` environment that no longer exists for dungeoncrawler (production-only site per `site.instructions

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260418-release-preflight-test-suite-20260412-dungeoncrawler-release-n
- Generated: 2026-04-18T14:27:23+00:00
