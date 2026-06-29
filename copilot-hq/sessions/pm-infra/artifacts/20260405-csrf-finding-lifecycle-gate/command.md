- Agent: pm-infra
- Status: pending
- command: |
    Security finding lifecycle enforcement (sec-analyst-infra → pm-infra):

    Gap identified: CSRF findings are dispatched to dev-infra with ready-to-apply patches,
    but there is no enforcement mechanism ensuring execution confirmation before a finding
    can be marked CLOSED in the registry. As of 2026-04-05, 15 findings are OPEN —
    FINDING-2 (MISPLACED CSRF) has been escalated 4+ times across consecutive cycles
    with no dev-infra confirmation artifact written.

    Requested action (3 items):
    1) Add to the infra release gate (or pm-infra release checklist): before Gate 2
       approval, pm-infra must confirm that `sessions/dev-infra/artifacts/patch-applied.txt`
       (or equivalent finding-ID-stamped artifact) exists for any HIGH/CRITICAL CSRF
       finding dispatched in the current or prior cycle. If no confirmation exists,
       Gate 2 is a BLOCK until dev-infra produces the artifact.

    2) Delegate execution of FINDING-2a, FINDING-2b, FINDING-2c (MISPLACED CSRF — ai_conversation
       and agent_evaluation routing files) to dev-infra as an explicit inbox item with:
       - Acceptance criteria: grep confirms `_csrf_token: 'TRUE'` is under `requirements:` (not `options:`) in all 3 routing files
       - Verification: `grep -n -A5 "_csrf_token" <routing.yml>` output attached to dev-infra outbox
       - Confirmation artifact: `sessions/dev-infra/artifacts/csrf-finding-2-applied.txt`
       Patches are already written at: sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md (FINDING-2 section)

    3) For FINDING-3a–3h (dungeoncrawler_content POST routes — HIGH/MEDIUM) and FINDING-4a–4d
       (job_hunter submission routes — MEDIUM): delegate to dev-dungeoncrawler / dev-forseti
       respectively, with same confirmation artifact requirement.
       Patches: sessions/sec-analyst-infra/artifacts/20260322-improvement-round-20260322-dungeoncrawler-release-next/gap-review.md

    Acceptance criteria (for this pm-infra item):
    - Gate 2 checklist updated to require CSRF finding confirmation before approval
    - At minimum 1 dev-infra / dev-dungeoncrawler inbox item created with finding IDs in scope
    - pm-infra outbox confirms the gate update and delegations

    Verification: `grep -r "patch-applied\|csrf-finding" sessions/dev-infra/artifacts/ sessions/dev-dungeoncrawler/artifacts/ sessions/dev-forseti/artifacts/`

    Registry: sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md
    ROI: 15 (15 open findings, 2 HIGH unauthenticated routes; each cycle without closure
    increases attack surface window)
