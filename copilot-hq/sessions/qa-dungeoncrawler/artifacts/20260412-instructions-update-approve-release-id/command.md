- Status: done
- Completed: 2026-04-12T15:35:59Z

- command: |
    Update your seat instructions (`org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`) to add the following rule under Gate 2 APPROVE guidance:

    **Rule: always reference the active release ID in Gate 2 APPROVE outbox**
    When filing a Gate 2 APPROVE (or BLOCK) outbox, the first line of the artifact must include the active release ID from `tmp/release-cycle-active/<site>.release_id`, NOT the feature's original development release ID.

    Rationale: `release-signoff.sh` performs a string-match check requiring the QA APPROVE outbox to contain the active release ID. Filing APPROVE against the wrong release ID blocks `release-signoff.sh` and requires a re-dispatch (confirmed occurrence: 20260412-dungeoncrawler, coordinated-release-next).

    Acceptance criteria: rule is present in qa-dungeoncrawler seat instructions under Gate 2 APPROVE guidance; change committed.
- Agent: qa-dungeoncrawler
- Status: pending
