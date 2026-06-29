# Acceptance Criteria: Downtime Mode
# Feature: dc-cr-downtime-mode

## AC-001: Downtime Time Scale
- Given a session transitions to downtime, when downtime begins, then time is tracked in days (not rounds or minutes)
- Given a downtime period begins, when the character chooses an activity, then the system records how many days are allocated to that activity

## AC-002: Earn Income
- Given a character uses the Earn Income downtime action, when the check is resolved, then the character earns gold per day based on the task level and skill check result (using the Earn Income table)
- Given a failed Earn Income check, when the result is below DC, then the character earns no income for that day
- Given a critical failure on Earn Income, when applied, then the character is fired and cannot attempt the same task again for a week

## AC-003: Retraining
- Given a character wants to retrain a feat, when retraining begins, then it requires 1 week of downtime per feat level
- Given a character wants to retrain a skill increase, when retraining is applied, then it requires 1 week of downtime
- Given retraining completes, when downtime ends, then the old selection is replaced by the new selection

## AC-004: Crafting (Basic)
- Given Crafting downtime begins for an item, when the process starts, then the character must have the formula, appropriate tools, and meet the skill rank requirement
- Given 4 days of Crafting downtime pass, when progress is calculated, then the character pays half the item price and the item is available (additional days reduce the remaining cost)
- Given the character has the Alchemist's Infused Reagents, when the daily Crafting resolution runs, then alchemical items are prepared at no gold cost (only time)

## AC-005: Other Downtime Activities
- Given a character uses the Subsist downtime action, when resolved, then they make a Survival or Society check against the local environment DC to cover living expenses
- Given a character uses the Treat Disease downtime action, when the check is resolved, then the disease stage is reduced on success
- Given a character runs a business or crafts for sale, when downtime is tracked, then the income calculation uses the appropriate table and skill

## Security acceptance criteria

- Security AC exemption: Downtime activities modify character economic state (gold) and character build (retraining). All mutations are server-validated and audit-logged to prevent gold duplication exploits.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 9: Playing the Game (Downtime)
