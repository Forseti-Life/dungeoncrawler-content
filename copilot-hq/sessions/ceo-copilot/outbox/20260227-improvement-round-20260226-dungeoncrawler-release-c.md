- Status: done
- Summary: Improvement round complete. Top process improvement identified: add a BASE_URL pre-flight check to `scripts/site-audit-run.sh` that validates the resolved URL is actually serving the correct site before running the full crawl. Top current blocker: the systemd service fix (`c813fa7`) that set `DUNGEONCRAWLER_BASE_URL=http://localhost:8080` was committed but the service was never reloaded — automated runs 070052 through 075108 continued probing the wrong host. The `latest` symlink was restored to the last clean PASS run (`20260227-065132`). No new code was written this cycle — only the symlink was corrected and the improvement was documented.

## Top process improvement: QA BASE_URL pre-flight validation

### Problem
When `DUNGEONCRAWLER_BASE_URL` resolves to the wrong host (port 80 vs 8080), the crawl silently produces 10 failures with no clear signal that the wrong site was targeted. This consumed 3 escalation cycles before root cause was found.

### SMART improvement
- **Specific**: Add a pre-flight check in `scripts/site-audit-run.sh` before `run_site()` is called. Fetch the resolved base URL (1-second timeout). If the response body contains a site fingerprint for the WRONG site (e.g., dungeoncrawler audit hits a page with "forseti" in title/header), emit `[PRE-FLIGHT FAIL] BASE_URL $base_url appears to serve wrong site` and abort the run with exit code 3.
- **Measurable**: Zero false-positive failure runs due to wrong-host probing. Any wrong-URL run aborts in <5 seconds with a clear error instead of producing a full set of 4xx findings.
- **Achievable**: ~20-line bash addition at the top of the `run_site` function; fingerprint is a simple `curl | grep` against a known string per site (e.g., dungeoncrawler checks for `dungeoncrawler` in the page title).
- **Relevant**: Directly prevents the 3-cycle repeat blocker seen this release.
- **Time-bound**: One dev-infra task, estimated 1 cycle.

### Delegation target
`dev-infra` (owns `scripts/`). CEO will delegate via inbox item if Board/human approves prioritization.

## Top current blocker

The automated QA timer (systemd) is still restarting runs with the wrong URL. Commit `c813fa7` patched the service file, but `systemctl daemon-reload && systemctl restart copilot-sessions-hq-site-audit` was never run. Runs 070052–075108 all show `Base URL: http://localhost`. The `latest` symlink has been manually restored to `20260227-065132` (clean PASS, 0 violations).

**Action needed**: whoever manages the systemd service must reload and restart it. This is a one-command fix but requires the executor with service access.

## Next actions
- Service operator (dev-infra or human): `sudo systemctl daemon-reload && sudo systemctl restart copilot-sessions-hq-site-audit.timer` (or equivalent) to activate the port-8080 fix.
- After service restart: verify next automated run shows `Base URL: http://localhost:8080` and 0 violations.
- Create `dev-infra` inbox item for the BASE_URL pre-flight check improvement (ROI 6) — pending Board/human prioritization signal.

## Blockers
- None preventing outbox delivery.
- Soft blocker: systemd service not reloaded — automated QA still probes wrong host for dungeoncrawler.

## Needs from CEO
- None for this outbox. The service reload requires human/executor with systemd access.

## ROI estimate
- ROI: 6
- Rationale: The pre-flight check prevents a repeat of the 3-cycle waste pattern (wrong BASE_URL → 10 false failures → 3 escalations → CEO intervention). Low implementation cost, high recurrence prevention value. Service reload is a one-command fix that unblocks all future automated dungeoncrawler QA runs.
