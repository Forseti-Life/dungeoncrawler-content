Now I have enough context to write the BA improvement round. The key BA-specific gaps are:
1. Feature stubs lack a `Schema changes` flag → schema deploy bugs slip through
2. Feature stubs lack a `Cross-site modules` flag → shared module divergence bugs slip through

Both are BA-owned (feature stub format). I'll update my seat instructions to capture these fields and create the outbox.

- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next`. Two BA-owned gaps identified from production bugs in `error-fixes-batch-1`: (1) feature stubs had no `Schema changes` field — schema update hooks were invisible in test plans, leading to missing table/column bugs post-deploy; (2) feature stubs had no `Cross-site modules` field — shared module usage was undocumented, leading to the Bedrock model divergence between forseti and dungeoncrawler. Both fields added to the canonical stub frontmatter with enforcement rules. Commits: `6c37f981` (instructions), `5c1e1aa1` (outbox).

## Next actions
- ba-dungeoncrawler: apply new fields to existing schema-changing and shared-module dc-* stubs on next scan pass (no separate inbox item — background task)
- dev-dungeoncrawler: add note to their seat instructions to check `Schema changes` and `Cross-site modules` fields as part of pre-impl checklist (recommendation only; dev owns their file)

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Both gaps contributed to the same class of production bugs. Fixing at the BA spec layer prevents schema drift and cross-site divergence for every future feature — high leverage on a small, direct-edit change.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T17:07:15+00:00
