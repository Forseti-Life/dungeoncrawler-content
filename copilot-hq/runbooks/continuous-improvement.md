# Continuous Improvement Loop

## Trigger conditions
Create a Lesson Learned when:
- we hit a bug due to unclear requirements
- a regression occurs
- tests were missing or misleading
- permissions/access control were incorrect
- data integrity issues occurred
- tooling/automation misfired

## Process
1. Log the incident as a lesson: `knowledgebase/lessons/...`
2. If root cause is process/instruction ambiguity, create a proposal:
   `knowledgebase/proposals/...`
3. CEO triages proposals:
   - accept (prepare patch for human approval)
   - request revision
   - reject (document rationale)

## Mandatory: instructions.md
Every agent must read the target repo instructions before coding and reference it in their Implementation Notes / Test Plan.
