# Test Plan Design: dc-cr-session-structure

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-session-structure/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-session-structure "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: Session and Campaign Structure
# Feature: dc-cr-session-structure

## AC-001: Session Data Model
- Given a new game session is created, when it is saved, then it records session_id, campaign_id (optional), date, GM user, player users, and starting narrative state
- Given a session ends, when session data is committed, then character XP, HP, conditions, and inventory reflect end-of-session state
- Given a session is resumed, when the character state is loaded, then all persistent state from the last session is restored

## AC-002: Campaign Model
- Given a campaign is created, when saved, then it stores campaign_id, title, GM user, a list of enrolled player characters, and narrative notes
- Given a session is linked to a campaign, when the campaign is viewed, then the session history is visible in chronological order
- Given a character advances in a campaign, when sessions are replayed, then XP totals accumulate correctly across sessions

## AC-003: One-Shot vs. Campaign Mode
- Given a player starts a one-shot session, when the session completes, then no persistent campaign state is required (session is self-contained)
- Given a player starts a campaign session, when the session begins, then it loads the character state from the last campaign session

## AC-004: Character State Persistence
- Given a character enters a session with inventory, HP, and conditions, when the session begins, then all carry-over state is present
- Given the AI GM tracks narrative continuity, when a new session in the same campaign begins, then the GM context includes a summary of prior sessions

## AC-005: AI GM Context Integration
- Given a session has a narrative summary, when the AI GM receives its context, then the summary is included in the system prompt
- Given important NPCs appeared in prior sessions, when referenced again, then their relationship status and last-known state are surfaced to the AI GM

## Security acceptance criteria

- Security AC exemption: Session/campaign data is user-scoped and protected by standard auth. No sharing of one user's campaign data with another without explicit invite. Session invites should validate the invited user is a registered account.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Campaign infrastructure
- Agent: qa-dungeoncrawler
- Status: pending
