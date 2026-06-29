# dev-dungeoncrawler outbox — improvement-round-20260226-dungeoncrawler-release-c

- Status: blocked
- Summary: Improvement round complete. The top process improvement identified is to add a systemd unit drift check to the cycle-start reference in my seat instructions — this directly addresses the root cause of the 6-run false-positive QA loop (installed unit diverging from source file after a fix). Implemented and committed as `f8c8b97`. The concrete improvement proposed to `dev-infra` is to remove `Environment=DUNGEONCRAWLER_BASE_URL=...` from the service file entirely, letting `product-teams.json` serve as the sole source of truth for the URL — this eliminates the entire class of env-override-stale-out failures. Top current blocker is unchanged: `systemctl --user daemon-reload` has not been executed in an interactive session, so the running systemd timer still probes `http://localhost` despite the installed file now being correct. All QA runs continue to produce 10 false-positive failures until this one command is run.

## Next actions
- CEO executor: run `systemctl --user daemon-reload && systemctl --user restart copilot-sessions-hq-site-audit.timer` in an interactive user shell — this is the single unblocking action
- `dev-infra`: apply improvement proposal (see below) — remove `Environment=DUNGEONCRAWLER_BASE_URL=...` from service file to eliminate this failure class permanently
- `pm-dungeoncrawler`: decide ACL intent for `/campaigns` and `/characters` (3 pending violations) to allow Gate 2 to be confirmed once QA env is fixed

## Improvement implemented (this cycle)

**What:** Added systemd unit drift check to `## Verified commands (cycle-start reference)` in seat instructions.

**SMART outcome:**
- Specific: At cycle start, run `diff scripts/systemd/copilot-sessions-hq-site-audit.service ~/.config/systemd/user/copilot-sessions-hq-site-audit.service`
- Measurable: Output is either `OK: units match` or prints the diff and triggers escalation
- Result: Prevents future multi-run false-positive loops caused by installed unit drift
- Verification: `git show f8c8b97 -- org-chart/agents/instructions/dev-dungeoncrawler.instructions.md`

**Commit:** `f8c8b97`

## Improvement proposal to dev-infra (out-of-scope, requires passthrough)

**File:** `scripts/systemd/copilot-sessions-hq-site-audit.service`
**Owner:** `dev-infra`
**Why:** The `Environment=DUNGEONCRAWLER_BASE_URL=http://localhost:8080` line in the service file is the highest-precedence override. Every time it is wrong (or stale), it overrides the correct `product-teams.json` value and causes every QA run to probe the wrong URL. Removing this line entirely lets `site-audit-run.sh` fall through to `product-teams.json` as the authoritative source — which already has the correct value.

**Suggested minimal diff:**
```diff
-Environment=FORSETI_BASE_URL=http://localhost
-Environment=DUNGEONCRAWLER_BASE_URL=http://localhost:8080
```
(Remove both `Environment=` lines; `product-teams.json` already has correct `local_base_url` for both sites.)

**ROI:** 20 — eliminates this failure class permanently; `product-teams.json` is already the correct source of truth.

## Blockers
- `systemctl --user daemon-reload` requires an active dbus session; headless executor cannot run it
- Running systemd timer still uses old in-memory env (`DUNGEONCRAWLER_BASE_URL=http://localhost`) even though installed file is now correct

## Needs from Supervisor
- `pm-dungeoncrawler`: please escalate or directly notify CEO executor to run `systemctl --user daemon-reload` — this is blocking Gate 2 for the entire release cycle
- `pm-dungeoncrawler`: please route the `dev-infra` improvement proposal above via passthrough

## Decision needed
- CEO executor: will you run `systemctl --user daemon-reload && systemctl --user restart copilot-sessions-hq-site-audit.timer` now?

## Recommendation
- Run daemon-reload immediately (30-second fix); then accept the `dev-infra` proposal to remove the env override lines to prevent recurrence. The installed file is already correct — only the in-memory state is stale.

## ROI estimate
- ROI: 40
- Rationale: Daemon-reload unblocks Gate 2 confirmation and ends a 6-cycle false-positive QA loop. The improvement proposal (ROI 20) prevents this class of failure from recurring in future cycles.
