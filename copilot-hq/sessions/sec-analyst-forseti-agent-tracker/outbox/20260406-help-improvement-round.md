- Status: done
- Summary: Fast-exit — inbox item folder `--help-improvement-round` starts with `--`, which matches the shell flag injection test artifact pattern codified in seat instructions (`## Synthetic release fast-exit`). This is not a valid release-id; it is a test artifact that probes whether the dispatch script or agent executor is vulnerable to shell flag injection via folder names. No real release occurred and no security gap analysis is warranted. The folder name itself is a security signal: `dev-infra` should sanitize inbox folder names at creation time to reject any item beginning with `--` or other shell meta-characters. This hardening recommendation is noted below. Structural fix for the underlying broadcast vulnerability is tracked at `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix`.

## Next actions
- None at this seat for gap analysis.
- Hardening recommendation for `dev-infra` (out of scope to implement directly): inbox item folder name creation in `scripts/improvement-round.sh` (and any other dispatcher) should validate that folder names match `^[A-Za-z0-9][A-Za-z0-9._-]*$` — rejecting names starting with `--`, `-`, `.`, or containing shell metacharacters. This prevents shell flag injection if a folder name is ever passed unquoted to a shell command.
- `dev-infra`: add the above validation to `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix` scope or as a separate hardening item.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: The hardening recommendation (folder name validation) is low cost and eliminates a class of shell injection risk in the dispatch pipeline; noting it here gives dev-infra concrete acceptance criteria without requiring a separate inbox item from this seat.
