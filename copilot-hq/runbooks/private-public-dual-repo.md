# Private + Public Dual-Repo Workflow

This workflow keeps operational data safe while publishing a clean, public mirror.

## Why dual-repo
- Private repo keeps full operational history (`sessions/`, `tmp/`, runtime artifacts).
- Public repo contains curated code/docs only.
- Crash recovery remains strong because private operational data is preserved and backed up.

## Repositories
- Private source (this repo): `copilot-sessions-hq`
- Public mirror (recommended separate repo): e.g. `copilot-sessions-hq-public`
- Program rule: use curated mirrors / extracted repos for public release; do **not** flip the private operational repo public.

## Included vs excluded in public mirror
Public export policy is controlled by:
- `.public-mirror-ignore`

By default, public export excludes:
- `sessions/**`
- `tmp/**`
- `inbox/responses/**`
- virtualenvs (`**/.venv/`)
- runtime pid/state files and local heavy model artifacts

Export behavior:
- `scripts/export-public-mirror.sh` removes previously exported excluded paths from an existing mirror (`--delete-excluded`), so stale private directories do not linger after policy changes.
- The mirror currently recreates only `tmp/.gitkeep` as a public-safe scaffold placeholder.

Forseti platform publication candidates should also keep these private unless intentionally sanitized for a specific public repo:
- `prod-config/**`
- `database-exports/**`
- `sites/*/keys/**`
- credential-bearing runtime config files

## One-time setup
1) Create mirror repo location (local path).
2) Run setup script:

```bash
./scripts/setup-public-mirror.sh /path/to/copilot-sessions-hq-public <optional-remote-url>
```

This initializes the mirror git repo (if needed), applies an initial export, and optionally configures `origin`.

## Ongoing sync (private -> public)
After private changes are ready for public release:

```bash
./scripts/export-public-mirror.sh /path/to/copilot-sessions-hq-public
cd /path/to/copilot-sessions-hq-public
git status --short
```

Then commit/push from the mirror repo only.

## Recommended release cadence
- Keep private commits frequent (operational continuity).
- Export to public on deliberate release points (daily/weekly milestones).
- Treat public exports as curated releases, not raw operational snapshots.

## Crash recovery guidance (private repo)
Use layered backups:
1) Frequent local commit/checkpoint cadence.
2) Periodic `git bundle` snapshots:

```bash
git bundle create backups/hq-$(date +%Y%m%d-%H%M%S).bundle --all
```

3) Off-machine encrypted backup of:
- private repo working tree
- bundle snapshots

## Safety checks before public push
- Review mirror diff in full.
- Search for obvious sensitive strings.
- Verify no runtime/session artifacts were exported.
- Verify previously exported private directories were removed from the mirror working tree after sync.
- Verify no credential-bearing config sync or private key material was exported.
- Verify docs do not expose private infrastructure details unintentionally.

## Notes
- This model does not replace history rewrite/truncation for previously leaked data.
- If sensitive data was ever committed in history, perform a history rewrite before making the public repo visible.
