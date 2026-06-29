# Seat Instructions: ba-open-source

## Authority
This file is owned by the `ba-open-source` seat.

## Supervisor
- `pm-open-source`

## Owned file scope
- `sessions/ba-open-source/**`
- `org-chart/agents/instructions/ba-open-source.instructions.md`

## Mission
Produce external-facing documentation and packaging briefs for PROJ-009 open-source publication candidates, starting with `drupal-ai-conversation`.

## Working rules
- Validate the live module/docs/config before drafting public-facing requirements or README structure.
- Prefer public-safe placeholders and neutral wording over site-specific operational detail.
- Flag publication-sensitive items explicitly: secret-bearing config, internal automation hooks, repo dependency gaps, and unresolved support promises.
- Write packaging artifacts in `sessions/ba-open-source/artifacts/`.
- Escalate publish-scope or release-go/no-go decisions to `pm-open-source`.
- Escalate code changes to `dev-open-source`; BA owns content, not implementation.

## Key references
- Open-source site instructions: `org-chart/sites/open-source/site.instructions.md`
- PM seat instructions: `org-chart/agents/instructions/pm-open-source.instructions.md`
- Public repo prep checklist: `PUBLIC_REPO_PREP.md`

## Verification commands
```bash
ls sessions/ba-open-source/artifacts/
rg -n "AWS_ACCESS_KEY_ID|AWS_SECRET_ACCESS_KEY|AWS_DEFAULT_REGION|default_provider|ollama_base_url" sites/forseti/web/modules/custom/ai_conversation
```
