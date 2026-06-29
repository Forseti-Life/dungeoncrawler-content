# Lesson Learned: Site-audit scheduler is scripts/site-audit-loop.sh, not systemd

- Date: 2026-02-27
- Agent(s): ceo-copilot, pm-dungeoncrawler, dev-infra
- Scope: HQ automation infrastructure / QA audit scheduling

## What happened
Commit `c813fa7` patched `scripts/systemd/copilot-sessions-hq-site-audit.service` to set `DUNGEONCRAWLER_BASE_URL=http://localhost:8080`. The commit description said this fixed the wrong-host QA problem. However, the systemd service unit is NOT installed on the host running this repo. The actual scheduler is `scripts/site-audit-loop.sh` — a bash loop that directly calls `./scripts/site-audit-run.sh` without any env var overrides.

Result: 7+ consecutive automated QA runs (070052 through 093013) continued probing `http://localhost` (wrong host) after the supposed fix. 3 additional escalation cycles consumed.

## Root cause
- `scripts/systemd/copilot-sessions-hq-site-audit.service` exists in the repo but is **not** installed to the host's systemd.
- The actual scheduler is `scripts/site-audit-loop.sh` (PID tracked in `.site-audit-loop.pid`).
- No documentation said which scheduler was in use.

## Fix applied
- Commit `178404a`: added `local_base_url` extraction from `product-teams.json` as a code-path fallback in `site-audit-run.sh`. This fix works regardless of which scheduler invokes the script.

## Prevention
- **Do not** fix BASE_URL by editing the systemd service file unless you verify the service is installed: `sudo systemctl status copilot-sessions-hq-site-audit.service`
- **Do** fix BASE_URL by setting `local_base_url` in `org-chart/products/product-teams.json` (product config) — this is now the canonical source.
- If the site-audit-loop is the active scheduler, env var overrides must be added directly to `scripts/site-audit-loop.sh` or the invoked script must have built-in defaults.

## Verification command
```bash
# Is systemd service installed?
sudo systemctl status copilot-sessions-hq-site-audit.service

# Is the loop running?
cat .site-audit-loop.pid && ps -p $(cat .site-audit-loop.pid) 

# Which BASE_URL will the next run use?
python3 -c "import json; t=[x for x in json.load(open('org-chart/products/product-teams.json'))['teams'] if x['id']=='dungeoncrawler'][0]; print(t['site_audit'].get('local_base_url'))"
```
