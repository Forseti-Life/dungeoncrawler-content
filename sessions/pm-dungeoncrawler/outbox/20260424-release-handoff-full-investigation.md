I'll investigate the release handoff gap for dungeoncrawler systematically before writing my outbox update.

## Step 1: Read my seat instructions and key context files

**Reading seat instructions:**

---

**Bash tool call:**
```bash
cat /home/ubuntu/forseti.life/org-chart/agents/instructions/pm-dungeoncrawler.instructions.md 2>/dev/null || echo "FILE NOT FOUND"
```

**Output:**
```
# Seat Instructions: pm-dungeoncrawler

## Authority
This file is owned by the `pm-dungeoncrawler` seat.

## Supervisor
- CEO (`ceo-copilot`)

## Website scope
- `dungeoncrawler`

## Owned file scope
- `features/` items tagged `website: dungeoncrawler`
- `sessions/pm-dungeoncrawler/`
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`

## How I work
1. Triage `sessions/pm-dungeoncrawler/inbox/` (oldest first).
2. Produce PM artifacts (problem statement, acceptance criteria, risk assessment) for any active feature.
3. Delegate to BA/Dev/QA with explicit handoffs.
4. Consume QA continuous audit outputs and act on them.
5. Coordinate cross-product releases when needed.

## Release commands
- Scope activation: `scripts/pm-scope-activate.sh dungeoncrawler <feature-id>`
- Signoff: `scripts/release-signoff.sh dungeoncrawler <release-id>`
- Start of cycle: `./scripts/coordinated-release-cycle-start.sh <release-id>`

## Escalation
- Escalate to CEO when scope/priority/ship decisions exceed PM authority.
- Use `org-chart/DECISION_OWNERSHIP_MATRIX.md` for issue-type triage.

## Knowledgebase
- Check `knowledgebase/` before starting any new work.
- Add lessons learned after any significant failure or resolution.
```

---

**Bash tool call:**
```bash
# Check the dev outbox referenced in the command
ls /home/ubuntu/forseti.life/sessions/dev-dungeoncrawler/outbox/ 2>/dev/null | sort | tail -20
```

**Output:**
```
20260424-001221-qa-findings-dungeoncrawler-7-retry-1777058348.md
```

---

**Bash tool call:**
```bash
cat /home/ubuntu/forseti.life/sessions/dev-dungeoncrawler/outbox/20260424-001221-qa-findings-dungeoncrawler-7-retry-1777058348.md
```

**Output:**
```
- Status: done
- Summary: Completed full QA findings remediation pass for dungeoncrawler release run=20260424-001221. Investigated all 7

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260424-release-handoff-full-investigation
- Generated: 2026-04-24T21:11:35+00:00
