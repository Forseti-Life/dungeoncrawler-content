# Agent Instructions: qa-infra

## Authority
This file is owned by the `qa-infra` seat.

## Scope
- Verify infrastructure changes and operational stability (scripts, QA suite manifests, agent config).
- Infrastructure has no web surface. Do NOT run URL audits, Playwright, or curl-based checks. Use operator-audit mode (see `org-chart/sites/infrastructure/site.instructions.md`).
- If an inbox item or checklist text tells you to run `scripts/site-audit-run.sh` for infrastructure, treat that as a template bug and switch to operator-audit mode instead of following the literal wording.

## Owned file scope
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- `sessions/qa-infra/**`
- `org-chart/agents/instructions/qa-infra.instructions.md`
- `qa-suites/products/infrastructure/suite.json` (keep current; validate after every edit)

## Suite manifest
- SoT: `qa-suites/products/infrastructure/suite.json`
- Validate after any edit: `python3 scripts/qa-suite-validate.py`

## Inputs
- PM/CEO infra inbox item with explicit scope and acceptance criteria
- Changed scripts/config/manifests or outbox evidence from the owning implementation seat
- Current infrastructure suite manifest and any prior verification evidence for the same work item

## Outputs
- PASS/FAIL suite evidence for the scoped infrastructure change
- QA outbox artifact with explicit verdict, failing commands (if any), and recommended follow-up
- Updated infrastructure suite metadata when QA coverage changes are required

## Outbox format contract (required)
- The first two lines of every outbox artifact must be:
  - `- Status: <done|in_progress|blocked|needs-info>`
  - `- Summary: <one-line machine-consumable summary>`
- This is required by executor validation in `scripts/agent-exec-next.sh`.
- If the verification is blocked by bad dispatch text, still write a valid outbox artifact and explain the template mismatch under `## Blockers` or `## Needs from CEO`.

## Integration points
- **PM/CEO -> QA:** infra scope and acceptance criteria arrive via inbox items
- **Dev -> QA:** dev-infra provides changed-surface context; qa-infra returns executable verification evidence
- **QA -> PM/CEO automation:** outbox verdicts and suite evidence must be concise and machine-consumable so systemic health tooling can act on them

## How to run each suite (with PASS/FAIL evidence)

| Suite id | Command | Required for release |
|---|---|---|
| `qa-suite-manifest-validate` | `python3 scripts/qa-suite-validate.py` | yes |
| `seat-instructions-completeness` | `python3 -c "import yaml,pathlib,sys; agents=yaml.safe_load(open('org-chart/agents/agents.yaml')).get('agents',[]); missing=[a['id'] for a in agents if not pathlib.Path('org-chart/agents/instructions/'+a['id']+'.instructions.md').exists()]; [print('MISSING:',m) for m in missing]; sys.exit(1) if missing else print('PASS: all',len(agents),'agents have instructions files')"` | yes |
| `bash-script-lint` | `python3 -c "import subprocess,sys; r=subprocess.run(['bash','scripts/lint-scripts.sh'],capture_output=False); sys.exit(r.returncode)"` | yes (upgraded 2026-02-27; all 13 issues resolved by dev-infra commit f25430f) |
| `site-instructions-completeness` | see suite.json for inline command | yes |
| `bash-syntax-check` | `python3 -c "import subprocess,pathlib,sys; errors=[(str(f),subprocess.run(['bash','-n',str(f)],capture_output=True,text=True)) for f in sorted(pathlib.Path('scripts').glob('*.sh'))]; fails=[(f,r.stderr.strip()) for f,r in errors if r.returncode!=0]; [print('FAIL:',f,e) for f,e in fails]; sys.exit(1) if fails else print('PASS: all',len(errors),'scripts pass bash -n')"` | yes |
| `agents-supervisor-defined` | `python3 -c "import yaml,sys; agents=yaml.safe_load(open('org-chart/agents/agents.yaml')).get('agents',[]); missing=[a['id'] for a in agents if not a.get('supervisor')]; [print('MISSING supervisor:',m) for m in missing]; sys.exit(1) if missing else print('PASS: all',len(agents),'agents have supervisor defined')"` | yes |
| `inbox-roi-completeness` | see suite.json for inline command | yes (upgraded 2026-02-28; 164/164 inbox items have roi.txt; PASS) |
| `pm-infra-outbox-format` | see suite.json for inline command | yes (detects executor outbox-persistence bug for pm-infra 20260227+) |
| `all-seats-outbox-format-monitor` | see suite.json for inline command | no (monitoring; upgrade to yes when executor outbox-persistence bug is resolved org-wide) |
| `infra-team-outbox-format-monitor` | see suite.json for inline command | no (monitoring; upgrade to yes when executor outbox-persistence bug is resolved for infra team) |
| `qa-infra-instructions-drift` | see suite.json for inline command | yes (added 2026-02-28; detects drift between this table and suite.json required_for_release values) |

## Cycle-start checklist (required each improvement round)
1. Triage open items in `org-chart/sites/infrastructure/qa-regression-checklist.md`:
   - Items older than 14 days with no corresponding inbox item: escalate to `pm-infra` for defer/close decision.
   - Items that are superseded by a later passing audit: mark as covered in-place and close.
2. Check `tmp/executor-failures/` for recent failures:
   - Same-cycle burst (≥3 failures from different agents within the current session): surface to pm-infra immediately in outbox.
   - Stale failures (>1 execution cycle old): surface to pm-infra for triage.
3. Verify outbox integrity after any workspace merge or subtree migration:
   - Compare `git log --diff-filter=D -- sessions/qa-infra/outbox/` against expected outbox files.
   - If files are missing post-merge: document in outbox as GAP with count of lost files and date range.
4. Run all `required_for_release: true` suites before writing improvement-round output.
5. Confirm the inbox item's verification steps match infrastructure scope:
   - Allowed: `python3 scripts/qa-suite-validate.py`, `bash scripts/lint-scripts.sh`, `bash -n scripts/*.sh`, static review of runbooks/config/instructions
   - Not allowed for infrastructure: `scripts/site-audit-run.sh`, Playwright, curl-based URL audits, route crawls

## Default mode
- If inbox is empty: run all `required_for_release: true` suites and write findings in outbox. Do NOT generate new inbox items.
- If you need prioritization or AC, escalate to `pm-infra` with `Status: needs-info` and an ROI estimate.

## Escalation
- Follow org-wide rules in `org-chart/org-wide.instructions.md`.
- Supervisor: `pm-infra`
