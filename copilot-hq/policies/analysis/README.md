# Analysis Policy Pack (OPA + Conftest)

This policy pack enforces a role-agnostic analysis contract for all analysis artifacts.

## Policy goal

Eliminate selective/cherry-picked analysis by requiring:
- explicit scope,
- evidence and counter-evidence,
- options with tradeoffs,
- a traceable recommendation,
- confidence and verification criteria.

## Files

- `policies/analysis/policy.rego` — Conftest policy rules (`deny` outputs).

## Input contract

Conftest expects a JSON or YAML document with top-level keys such as:
- `analysis_id`
- `objective`
- `decision_owner`
- `scope.in_scope`, `scope.out_of_scope`
- `assumptions`
- `evidence[]` with `source`, `summary`, `reliability` (1-5)
- `counter_evidence[]`
- `options[]` with `id`
- `recommendation.option_id`, `recommendation.rationale`, `recommendation.tradeoffs`, `recommendation.decision_needed`
- `confidence` (0-1)
- `verification.acceptance_criteria[]`, `verification.checks[]`

Use `templates/analysis/analysis-record.yaml` as the baseline shape.
