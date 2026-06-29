# Organizational Chart

```mermaid
graph TD
  U[User (Human owner)] --> CEO[ceo-copilot\nCEO (Orchestration)]

  CEO --> PM[pm-*\nProduct Managers]

  CEO --> INFRA_PM[pm-infra\nInfrastructure PM\n(Sysadmin + Security)]

  INFRA_PM --> INFRA_BA[ba-infra\nInfrastructure BA]
  INFRA_PM --> INFRA_DEV[dev-infra\nInfrastructure Dev]
  INFRA_PM --> INFRA_QA[qa-infra\nInfrastructure QA]
  INFRA_PM --> INFRA_EXP[agent-explore-infra\nExplore (Infra)]
  INFRA_PM --> INFRA_SEC[sec-analyst-infra\nSecurity (Infra)]

  PM --> BA[ba-*\nBusiness Analysts]
  PM --> DEV[dev-*\nEngineering / Implementation]
  PM --> QA[qa-*\nQA / Verification]

  CEO --> ACCT[accountant-*\nAccounting / FinOps]
  CEO --> SEC[sec-analyst*\nSecurity Analysts (per team)]

  BA --> AC[Acceptance criteria\nDocs + status]
  DEV --> CODE[Code + integration\nVerification steps]
  QA --> VERIFY[Test plan + execution\nAPPROVE/BLOCK]
```
