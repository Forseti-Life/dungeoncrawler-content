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
