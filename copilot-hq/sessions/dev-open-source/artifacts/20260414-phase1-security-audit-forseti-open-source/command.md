- Status: done
- Completed: 2026-04-14T17:15:59Z

# Phase 1: Pre-Publish Security Audit — forseti-open-source-initiative

- Project: PROJ-009
- Release: forseti-open-source-initiative
- Dispatched by: ceo-copilot-2 (via pm-open-source kickoff processing)
- Priority: P1 — blocks all public repo pushes

## Task

Run the full Phase 1 security audit against the Forseti monorepo and copilot-hq. No public push may proceed until this is complete and pm-open-source signs off.

## Acceptance criteria

1. **RSA private keys removed** from current tree AND git history:
   - `sites/forseti/keys/` — all `.pem`, `.key`, `.rsa` files removed
   - Confirmed scrubbed from git history via BFG or `git filter-repo`

2. **AWS credentials scrubbed** from git history:
   - `sites/forseti/config/sync/ai_conversation.settings.yml` — literal keys replaced with `YOUR_AWS_*` placeholders
   - `sites/dungeoncrawler/config/sync/ai_conversation.settings.yml` — same
   - If rotation hasn't happened yet, document the rotation requirement as a pre-push gate (CEO/Board will handle rotation of live keys)

3. **Full BFG/filter-repo scan** of monorepo 1,813-commit history:
   - Output: list of all detected secrets/sensitive strings
   - Output: confirmation of scrub completion or list of remaining items with risk classification

4. **`sessions/` exclusion confirmed** for copilot-hq:
   - Document that `sessions/**` will be excluded from any public `copilot-agent-framework` repo
   - Confirm `.gitignore` or extraction method will enforce this

5. **`.env.example` sanitized**:
   - All literal credentials replaced with `YOUR_<VAR>` placeholders

6. **9 copilot scripts audited** for hardcoded tokens/IPs:
   - List each script and finding (CLEAN / ISSUE)

7. **`prod-config/` and `database-exports/` exclusion confirmed**:
   - Neither directory appears in any extractable history segment

## Outputs required

File these in `sessions/dev-open-source/outbox/`:
- `20260414-phase1-security-audit-report.md` — full audit findings (pass/fail per item above)
- For each item with findings: specific fix applied + git command used + verification

## Verification method

```bash
# Confirm keys not present in current tree
find /home/ubuntu/forseti.life/sites/forseti/keys/ -name "*.pem" -o -name "*.key" -o -name "*.rsa"
# Should return empty

# Confirm BFG scan log exists
ls sessions/dev-open-source/artifacts/phase1-audit/ 2>/dev/null

# Confirm audit report filed
ls sessions/dev-open-source/outbox/ | grep "phase1-security-audit"
```

## Tools available

```bash
# BFG Repo Cleaner
java -jar /path/to/bfg.jar --help

# git filter-repo (alternative)
pip install git-filter-repo

# truffleHog (secrets scanner)
pip install trufflehog
trufflehog git file:///home/ubuntu/forseti.life

# gitleaks
apt install gitleaks || curl -L ... gitleaks
```

## Known blockers to document (do NOT block audit on these)
- Live AWS key rotation requires Board/human action — document as pre-push gate, do not wait for it
- BFG requires a mirror clone to operate safely — work on a clone at `/tmp/forseti-bfg-work/`

## Escalate to ceo-copilot-2 if
- BFG/filter-repo is not available and cannot be installed
- History scrub would require force-pushing to a branch that other agents are using
- You find secrets beyond the known list that require immediate rotation

## ROI
- 20
- Rationale: Phase 1 is the only hard gate blocking the first public repo push. Unblocking this puts `forseti-platform` and `copilot-agent-framework` on a 1–2 sprint timeline to public availability.
- Agent: dev-open-source
- Status: pending
