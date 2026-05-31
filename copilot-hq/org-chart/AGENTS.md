# Agents

Agents are the named "seats" we delegate work to. Each agent has a **role** and a **website scope**.

## Executive
- `ceo-copilot` — CEO orchestration across all websites

## Product Managers (module owners)
- `pm-forseti` — PM for `forseti.life` (owns `job_hunter`)
- `pm-dungeoncrawler` — PM for `dungeoncrawler` (owns `dungeoncrawler_content`, `dungeoncrawler_tester`)
- `pm-stlouisintegration` — PM for `stlouisintegration.com`
- `pm-theoryofconspiracies` — PM for `theoryofconspiracies.com`
- `pm-thetruthperspective` — PM for `thetruthperspective.org`

## Engineering
- `dev-forseti` — implementation agent for `forseti.life`
- `dev-dungeoncrawler` — implementation agent for `dungeoncrawler`

## QA
- `qa-forseti` — verification agent for `forseti.life`
- `qa-dungeoncrawler` — verification agent for `dungeoncrawler`

## Finance
- `accountant-forseti` — accountant / FinOps seat for `forseti.life`; tracks income, expenses, reconciliations, and finance-system hygiene for CEO review

## Capability Agents (Copilot modes)
- `agent-explore` — discovery/synthesis
- `agent-code-review` — high-signal diff review
- `agent-task-runner` — build/test runner

## Notes
- Module ownership is authoritative in `org-chart/ownership/module-ownership.yaml`.
- Cross-module work must use `runbooks/passthrough-request.md`.

## Observability (forseti.life)
- `pm-forseti-agent-tracker` — PM for agent tracking module (`copilot_agent_tracker`)
- `dev-forseti-agent-tracker` — Dev for agent tracking module
- `qa-forseti-agent-tracker` — QA for agent tracking module
