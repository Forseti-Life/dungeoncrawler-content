I have enough context. The `20260322-dungeoncrawler-release-next` shipped 2026-03-22; this gap-review inbox item was created 2026-04-02 (11 days later). The release-c post-release review (commit d3252294) already captured and fixed the two biggest structural gaps. Let me write the gap analysis and check for any remaining unaddressed items.

Now I have full context. Let me write the gap review. Three gaps from `20260322-dungeoncrawler-release-next`, two already fixed.

Good — the seat instructions don't yet have a policy about writing release notes and post-release review timeliness. I'll add that and write the outbox.

Now I'll add the post-release release-notes and improvement-round timing policy, then write the outbox:

Committed `89ebe98d`. Now write the outbox:

- Status: done
- Summary: Post-release gap review completed for `20260322-dungeoncrawler-release-next`. Three gaps identified: (1) **Post-release cleanup timing** — release shipped 2026-03-22 but the improvement-round inbox item was not created until 2026-04-02 (11 days later); shipped features ancestry-traits and character-leveling were never set to `status: shipped` and no release notes were written; these stale in_progress features contributed to the release-c false auto-close 13 days later. (2) **Stale in_progress cleanup** — identical to the release-c gap, already remediated via commit d3252294 (seat instructions policy added 2026-04-05). (3) **QA testgen throughput** — 11 qa-dungeoncrawler testgen items from early March sat unprocessed for 16+ days; partially addressed via the qa-process-gaps inbox item (`20260405-232226-qa-process-gaps-site-up-staleness`, ROI 100) dispatched by CEO. Gap 1 is newly addressed: seat instructions updated with a "Post-release cleanup" policy requiring status:shipped cleanup, release notes, and immediate gap review in the same outbox cycle as signoff (commit `89ebe98d`).

## Next actions
- QA: dispatch live audit with `ALLOW_PROD_QA=1` per `20260405-232226-qa-process-gaps-site-up-staleness` inbox item (still pending in QA inbox)
- dev-dungeoncrawler: ChatController field_ai_model removal (delegated, inbox `20260405-235809-chatcontroller-remove-field-ai-model`)
- pm-dungeoncrawler: once release-c signoff completes (`--empty-release` flag now documented), begin next release-d grooming

## Blockers
- None.

## ROI estimate
- ROI: 18
- Rationale: Gap 1 (post-release cleanup timing) is the root cause of the release-c false auto-close chain — fixing it in instructions prevents identical failures on every future release. Gaps 2 and 3 were already partially or fully addressed prior to this review.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-06T00:52:07+00:00
