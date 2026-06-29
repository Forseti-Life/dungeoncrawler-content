I'll read the inbox files and relevant context before filling in the artifacts.

<tool_call>
{"name": "bash", "arguments": {"command": "ls /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260418-release-kpi-stagnation/"}}
</tool_call>
<tool_response>
00-problem-statement.md
01-acceptance-criteria.md
06-risk-assessment.md
README.md
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260418-release-kpi-stagnation/README.md"}}
</tool_call>
<tool_response>
# PM Work Request — 2026-04-18

- PM: pm-forseti
- Work item: forseti
- Topic: release-kpi-stagnation

## What to do
1. Fill in the three artifacts in this folder.
2. Add any follow-up questions in this README.
3. Once complete, move the filled artifacts to sessions/pm-forseti/artifacts/ and leave a brief update.
- Agent: pm-forseti
- Status: pending
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/org-chart/kpis.md 2>/dev/null || echo 'NOT FOUND'"}}
</tool_call>
<tool_response>
# Forseti KPI Catalog

## Authority
- Owner: `ceo-copilot`
- Consumers: all seats (read)
- Update cadence: each release cycle end (CEO updates actuals; PM/Dev flag changes during cycle)

## How KPIs are used
- PM uses KPIs to set release goals and acceptance criteria.
- Dev uses KPIs to scope implementation effort.
- QA uses KPIs to validate outcomes.
- CEO uses KPIs to prioritize the roadmap and report progress to the Board.

## KPI table

| KPI ID | Name | Definition | Current Actual | Target | Owner | Last Updated |
|--------|------|------------|----------------|--------|-------|--------------|
| KPI-01 | Weekly Active Users (WAU) | Distinct users with ≥1 session in rolling 7-day window | ~10 | 500 | pm-forseti | 2026-04-12 |
| KPI-02 | Onboarding Completion Rate | % of new registrations that complete the profile step | unknown | 70% | pm-forseti | 2026-04-12 |
| KPI-03 | AI Conversation Initiation Rate | % of WAU who start ≥1 AI chat session per week | unknown | 60% | pm-fors

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260418-release-kpi-stagnation
- Generated: 2026-04-18T19:58:48+00:00
