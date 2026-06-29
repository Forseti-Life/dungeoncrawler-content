# Feature Brief

- Work item id:
- Website:
- Module:
- Status: planned | in_progress | blocked | shipped
- Priority: P0 | P1 | P2
- Feature type: new-feature | enhancement | needs-testing
- PM owner:
- Dev owner:
- QA owner:

## Goal

## Non-goals

## Gap Analysis

> **Required before writing Acceptance Criteria.**
> PM must audit existing code for each requirement in this feature before the AC doc is written.
> QA must not receive a handoff until this section is complete.

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| (list each REQ or AC item) | (file path or "none") | Full / Partial / None |

### Coverage determination

- **Feature type: new-feature** — No existing implementation found for the majority of requirements.
- **Feature type: enhancement** — Partial implementation exists; new code extends or corrects it.
- **Feature type: needs-testing** — Full implementation exists; tests are absent or incomplete.

Set `Feature type:` in the header above based on this determination.

### Test path guidance for QA

For each requirement above, specify the test file QA should create or extend:

| Requirement | Test file | Test type |
|---|---|---|
| (REQ or AC item) | (path relative to module root) | Unit / Functional / Integration |

Unit tests: `tests/src/Unit/Service/<ServiceName>Test.php`
Functional tests: `tests/src/Functional/Controller/<ControllerName>Test.php`
Character creation tests: `tests/src/Functional/CharacterCreation/<WorkflowName>Test.php`

## Acceptance Criteria (link)

## Risks

## Latest updates
- 
