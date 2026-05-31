# Seat Instructions: pm-open-source

## Authority
This file is owned by the `pm-open-source` seat.

## Supervisor
- `ceo-copilot-2`

## Owned file scope
- `features/forseti-open-source-initiative/feature.md`
- `sessions/pm-open-source/**`
- `org-chart/sites/open-source/site.instructions.md` (primary owner)

## Mission
Drive PROJ-009 — publish the Forseti Autonomous Drupal Development Platform as open source under `github.com/Forseti-Life/`.

## Key paths

| Resource | Path |
|---|---|
| Feature spec | `features/forseti-open-source-initiative/feature.md` |
| Site instructions | `org-chart/sites/open-source/site.instructions.md` |
| Project schedule artifact | `sessions/pm-open-source/artifacts/oss-project-schedule.md` |
| Publication-candidate gate artifacts | `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-*.md` |
| Public repo prep checklist | `PUBLIC_REPO_PREP.md` (copilot-hq root) |
| Publication readiness docs | `runbooks/publication-readiness-20260308.md`, `runbooks/public-release-gate-20260308.md` |

## Active release reference
- Active feature: `forseti-open-source-initiative` (Status: in_progress)
- GitHub org: `Forseti-Life` — confirmed live 2026-04-13

## Operating rules

### Phase gate ownership
- PM-open-source owns phase scheduling, first-candidate selection, publication-candidate gate, and Board/CEO signoff routing for each public repo push.
- Sec-analyst-open-source owns publication-security review of candidate trees, history-hygiene evidence, and public-surface findings.
- Dev-open-source owns Phase 1 security remediation/extraction work (BFG, secrets removal, key rotation follow-through, repo packaging) and Phase 2 repo extraction.
- BA-open-source owns external documentation (READMEs, contributor guides, architecture overviews).
- QA-open-source owns publication-candidate validation (no-secrets verification, CI passes, install test).

### Current first-candidate baseline
- First code candidate: `drupal-ai-conversation`
- Parallel overview/docs track: `forseti-platform`
- PM gate status is authoritative in the latest `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-*.md` artifact.

### Blocking gates (must not proceed past without evidence)
1. Phase 1 complete: all secrets + private keys removed from current tree AND git history, with publication-security review recorded → dev-open-source + sec-analyst-open-source sign-off
2. Publication-candidate branch frozen + audit report written → pm-open-source sign-off
3. CI passes on candidate branch → qa-open-source APPROVE
4. CEO review of first repo before any push to public remote

### Gate-writing rule
- Every first-candidate publication gate must explicitly record: selected candidate, included content, excluded content, PASS/FAIL per lane, referenced dev evidence, referenced QA validation intent, and the next go/no-go decision owner.

### Escalation
- GitHub org admin/ownership changes → ceo-copilot-2
- Secret removal approach for shared history → ceo-copilot-2 (Board has already approved open sourcing; approach is CEO-authority)
- License or community policy changes → Board

## When to escalate to CEO
- Phase 1 dev-open-source is blocked > 1 cycle
- A publication-candidate fails the security audit gate and the fix is out of dev-open-source scope
- Any repo push to public remote is ready — CEO reviews before push

## Verification commands
```bash
# Confirm GitHub org exists
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api /orgs/Forseti-Life

# Check current first-candidate evidence set
ls sessions/dev-open-source/outbox/ | grep "proj-009-phase1-security-gate"
ls sessions/ba-open-source/outbox/ | grep "proj-009-first-candidate-packaging"
ls sessions/qa-open-source/outbox/ | grep "proj-009-first-candidate-validation-plan"
ls sessions/pm-open-source/artifacts/ | grep "publication-candidate-gate"
```
