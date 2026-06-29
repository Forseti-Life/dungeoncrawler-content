# Feature Brief: Investigator Class Mechanics (APG)

- Work item id: dc-apg-class-investigator
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Investigator)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Investigator
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-skill-system

## Goal

Implement Investigator class mechanics — Devise a Stratagem (replace attack roll with Recall Knowledge result), Strategic Strike (scaling +d6), Methodology (4 options), and Pursue a Lead — so players can execute an intelligence-based tactical combat style.

## Source reference

> "Devise a Stratagem: You assess a foe's weaknesses before attacking; attempt a Recall Knowledge check. On a success, you can use the result of that check as your attack roll against that foe until the end of your turn."

## Implementation hint

`DeviseAStratagemAction` is a free action before a Strike: trigger `RecallKnowledgeService` for the target, store the check result as `stratagem_attack_value` on the character's turn state, then use this value as the attack roll in `StrikeResolver` instead of a d20 roll. Clear `stratagem_attack_value` at turn end. `StrategicStrike` adds +1d6 per 5 levels to the attack gated by an active stratagem on that turn. `PursueALeadAction` sets a `studied_target_id` with benefits applying to subsequent checks against that target. Methodology (Empiricism/Forensic Medicine/Interrogation/Scalpel) modifies which Recall Knowledge skills benefit from bonus and which bonus the stratagem confers.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; stratagem value computed server-side from a real Recall Knowledge roll; not client-submittable.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Stratagem target must be a valid encounter creature; attack roll substitution gated to same target on same turn; Methodology enum restricted to [Empiricism, ForensicMedicine, Interrogation, Scalpel].
- PII/logging constraints: no PII logged; log character_id, target_id, stratagem_roll, attack_used, outcome; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
