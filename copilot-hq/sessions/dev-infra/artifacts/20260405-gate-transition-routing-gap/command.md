- command: |
    Gate-transition routing gap fix (dev-infra):

    CONTEXT: The agent-exec-loop.sh has no post-gate routing logic. Every gate transition
    (QA BLOCK → Dev fix, Gate 2 APPROVE → PM signoff, PM signoff → coordinated signoff)
    requires manual CEO intervention. 5 consecutive misses documented in
    knowledgebase/lessons/20260330-qa-block-dev-routing-gap.md. Each miss adds 24h+ stall.

    TASK: Implement a post-execution outbox-inspection hook in scripts/agent-exec-loop.sh
    (or a new helper scripts/route-gate-transitions.sh sourced by the loop) that:

    1. After each agent-exec-next.sh call, reads the agent's latest outbox file.
    2. Detects QA BLOCK signal → creates dev-seat fix inbox item (same release scope).
    3. Detects QA Gate 2 APPROVE → creates PM signoff inbox item (if not already exists).
    4. Detects pm-dungeoncrawler signoff artifact → creates pm-forseti coordinated signoff
       inbox item (if not already exists).
    5. All routing is idempotent (skips if target item already exists).
    6. Routing failures are non-blocking (log warning, continue loop).

    See README.md in this inbox item for full acceptance criteria and verification commands.

    ROI: 18 — 5 consecutive missed transitions; each adds 24h+ release stall; fix eliminates
    the #1 structural cause of release pipeline stagnation.
