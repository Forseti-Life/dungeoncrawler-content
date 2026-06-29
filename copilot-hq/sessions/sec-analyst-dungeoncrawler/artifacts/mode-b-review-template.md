# Mode B Script Security Review Template

- Reviewer: sec-analyst-dungeoncrawler (WATCHDOG)
- Template version: 1.0 (2026-02-27)
- Use for: HQ script/config security reviews (Mode B)

## How to use
1. Copy this template to a new section in your outbox entry.
2. Fill in each field for the script under review.
3. Skip sections that genuinely don't apply; do not omit silently.

---

## Review target
- File: `scripts/<name>.sh` (or path)
- Date reviewed: YYYY-MM-DD
- Finding IDs used: `F-<ABBREV>-<N>` (e.g. F-RC-1 for release-cycle script finding 1)

## Trust boundary map
- Inputs (who/what controls each input?):
  - Arg $1: …
  - Env vars: …
  - Files read: …
- Outputs (where do outputs go?):
  - Files written: …
  - Commands executed: …
  - Repos modified: …

## Checklist

### Access control
- [ ] Does the script require elevated privileges? Are they justified?
- [ ] Does it operate on files/repos beyond its logical scope?
- [ ] Are there hardcoded agent IDs, paths, or credentials?

### Input validation
- [ ] Are shell arguments validated before use in commands or heredocs?
- [ ] Are file paths sanitized? (path traversal possible?)
- [ ] Are variable expansions safe inside heredocs / eval / exec?

### Injection surface
- [ ] Do any shell variables get interpolated into: heredoc content, eval, git commit messages, or LLM prompts?
- [ ] Could a malicious value in any input reach an LLM prompt unencoded?
- [ ] Are there command substitution patterns (`$(...)`, backticks) using external data?

### Automation safety
- [ ] Is the script idempotent? (safe to run twice?)
- [ ] Does it have a lock/guard against concurrent execution?
- [ ] Does it suppress errors that should be surfaced? (`-q`, `2>/dev/null`, `|| true` patterns)

### Output/exfiltration
- [ ] Does the script write sensitive data to files that are auto-committed/pushed?
- [ ] Does it publish internal data to external systems (Drupal, git remote, HTTP)?

### Error handling
- [ ] Does the script fail open (continue on error) when it should fail closed?
- [ ] Are all critical commands checked for exit code?

## Findings

### F-<ID>-1: <Title>
- Severity: Critical | High | Medium | Low
- Surface: `<file>:<line>` — `<code snippet>`
- Condition: <when does this trigger?>
- Impact: <what can go wrong?>
- Likelihood: <how easy/likely to exploit?>
- Mitigation: <concrete fix>
- Verification: <how to confirm fix reduced risk>
- Owner: <dev-infra | ceo-copilot | pm-dungeoncrawler>

## Summary verdict
- Total findings: 0 Critical, 0 High, 0 Medium, 0 Low
- Recommendation: PASS | REVIEW RECOMMENDED | BLOCK PENDING FIX
