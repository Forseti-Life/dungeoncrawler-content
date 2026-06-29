# Contract Audit — Storyline/Quest/Task Library

- Agent: architect-copilot
- Created: 2026-06-11
- Topic: storyline-quest-task-contract-audit
- Priority: P1

## Summary
Audit and normalize the storyline library (`dungeoncrawler_content_storylines`) and quest template library (`dungeoncrawler_content_quest_templates`) so storyline definitions, subquests, and quest subtasks conform to canonical contracts.

## Scope
1. Validate all storyline template payloads against `storyline_definition` and cross-reference rules.
2. Validate all quest objective schemas against objective-type contracts.
3. Normalize nonconforming rewards/objective payloads to canonical shapes.
4. Produce a full storyline → subquest → task inventory.

## Acceptance criteria
- Storyline template contract violations are zero.
- Quest template objective/reward contract violations are zero.
- A complete storyline/subquest/task inventory is produced from canonical library data.

