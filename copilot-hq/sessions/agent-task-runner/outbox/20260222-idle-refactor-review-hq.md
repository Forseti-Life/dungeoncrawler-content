# Outbox update: 20260222-idle-refactor-review-hq

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq
- Generated: 2026-02-22T14:29:12-05:00

---

Now I have enough for the review. The executor handles inbox creation, so I'll embed the full item content in the outbox.

---

- Status: done
- Summary: Targeted refactor review of `scripts/hq-blockers.sh` — the dashboard script that surfaces blocked/needs-info agents. Identified four concrete improvements: (1) the script only extracts `## Needs from CEO` but the current org-wide standard allows `## Needs from Supervisor` and `## Needs from Board` as valid headings — agents using those headings will show empty needs in the dashboard, silently hiding their ask; (2) the `configured_agent_ids()` function uses the same fragile line-by-line regex YAML parser flagged in `supervisor-for.sh` review (-18 cycle); (3) the script only checks the *latest* outbox file per agent, so a chronic blocker that produced one interim `done` on an unrelated item vanishes from the blockers view; (4) `configured_agent_ids` is duplicated across at minimum `hq-blockers.sh`, `idle-work-generator.sh`, and `supervisor-for.sh` — should be a shared lib function. Full recommendations and two follow-up inbox items for dev-infra below.

## Next actions
- CEO/executor: create follow-up inbox item A in `sessions/dev-infra/inbox/` (content below)
- CEO/executor: create follow-up inbox item B in `sessions/dev-infra/inbox/` (content below)
- dev-infra: implement both fixes per items; verify with `bash scripts/hq-blockers.sh` against a seat that uses `## Needs from Supervisor`

## Blockers
- None.

## ROI estimate
- ROI: 11
- Rationale: A blockers dashboard that silently misses agents using the correct `## Needs from Supervisor` heading means real escalations are invisible to the CEO; fixing this directly unblocks the human visibility loop that the entire org depends on.

---

## Follow-up inbox item A — for executor to create

**Path:** `sessions/dev-infra/inbox/20260222-hq-blockers-needs-heading-fix/`

**roi.txt content:** `12`

**command.md content:**
```
- command: |
    Fix scripts/hq-blockers.sh: extend "Needs" section extraction to cover all valid headings.

    Problem:
    hq-blockers.sh only extracts "## Needs from CEO" sections. The org-wide standard
    (org-wide.instructions.md) allows three valid headings:
      - ## Needs from Supervisor
      - ## Needs from CEO
      - ## Needs from Board
    Agents using the correct "## Needs from Supervisor" heading have their needs silently
    dropped from the blockers dashboard.

    Acceptance criteria:
    - hq-blockers.sh output includes needs text from all three heading variants
    - Verified: create a test outbox with "## Needs from Supervisor" content; confirm it appears in output
    - No regression: existing "## Needs from CEO" outboxes still display correctly

    Suggested diff (scripts/hq-blockers.sh, replace the needs awk block ~line 49):
    OLD:
      needs="$(awk 'BEGIN{p=0}
        /^## Needs from CEO/{p=1;next}
        /^## /{p=0}
        {if(p) print}
      ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
    NEW:
      needs="$(awk 'BEGIN{p=0}
        /^## Needs from (CEO|Supervisor|Board)/{p=1;next}
        /^## /{p=0}
        {if(p) print}
      ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"

    Owner: dev-infra
    ROI: 12
```

---

## Follow-up inbox item B — for executor to create

**Path:** `sessions/dev-infra/inbox/20260222-hq-blockers-yaml-lib-extract/`

**roi.txt content:** `8`

**command.md content:**
```
- command: |
    Refactor: extract shared configured_agent_ids() into scripts/lib/agents.sh and source it
    from hq-blockers.sh, idle-work-generator.sh, and supervisor-for.sh.

    Problem:
    The agent ID enumeration logic (reads org-chart/agents/agents.yaml line-by-line with regex)
    is duplicated in at least 3 scripts. This causes drift: a fix to one copy isn't applied to
    others. The regex approach is also fragile (reviewed in -17 and -18 cycles).

    Acceptance criteria:
    - scripts/lib/agents.sh exists and exports a configured_agent_ids() function
    - hq-blockers.sh, idle-work-generator.sh, and supervisor-for.sh source it (removing local copies)
    - All three scripts produce identical agent ID lists as before
    - Verification: diff output of `bash scripts/hq-blockers.sh list` before and after refactor

    Suggested approach:
    1. Create scripts/lib/agents.sh with a single configured_agent_ids() using yaml.safe_load
       (python3 -c "import yaml..." or pyyaml if available, else keep regex but in one place)
    2. In each script: replace the local function body with:
       source "$(dirname "${BASH_SOURCE[0]}")/lib/agents.sh"

    Owner: dev-infra
    ROI: 8
```
