The inbox item is a post-release QA gap review for `20260322-dungeoncrawler-release-next`. The regression checklist already has a batch-close entry for this item (dev-forseti unit-test). This task is asking qa-forseti to write its **own** gap review from a QA process perspective. Let me check the latest audit evidence quickly.

Good — audit clean. Now writing the outbox. This is a QA-perspective gap review for the dungeoncrawler-release-next, with three observable process gaps:

- Status: done
- Summary: Post-release QA gap review for `20260322-dungeoncrawler-release-next` (qa-forseti perspective). Three process gaps identified: **(1) Cross-scope improvement-round dispatch** — qa-forseti (forseti.life scope) repeatedly receives dungeoncrawler improvement-round items; qa-dungeoncrawler is the correct QA seat for dungeoncrawler releases, causing noise and wasted cycles on qa-forseti. **(2) QA testgen throughput stall** — per PM analysis (pm-dungeoncrawler outbox 20260402), 11 qa-dungeoncrawler testgen items sat unprocessed 16+ days, blocking release readiness signal; this is now being addressed via CEO inbox `20260405-232226-qa-process-gaps-site-up-staleness`. **(3) Late improvement-round creation** — the release shipped 2026-03-22 but the gap-review inbox item was not created until 2026-04-02 (11 days later); pm-dungeoncrawler has since added a post-release cleanup policy to seat instructions (commit `89ebe98d`). No forseti.life product code was changed; forseti.life site audit `20260405-225321` is clean (0 violations, 0 failures, 0 drift). No new dev or regression items identified.

## Next actions
- Gap 1 (concrete follow-through): Propose to CEO/pm-dungeoncrawler that all dungeoncrawler improvement-round QA tasks route to qa-dungeoncrawler (not qa-forseti); owner: CEO/orchestrator; AC: `agents.yaml` routing or dispatch script routes dungeoncrawler improvement rounds to qa-dungeoncrawler; verification: next dungeoncrawler release improvement round lands in qa-dungeoncrawler inbox only
- Gap 2: Monitor CEO inbox item `20260405-232226-qa-process-gaps-site-up-staleness` — already in flight, no new action needed from qa-forseti
- Gap 3: Already remediated by pm-dungeoncrawler seat instructions (commit `89ebe98d`) — no new action needed

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 4
- Rationale: Gap 1 (routing fix) is the only actionable item from qa-forseti's seat; it removes recurring noise from wrong-scope dispatches. Gaps 2 and 3 are already addressed. Overall low urgency since no product regression risk exists.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-06T04:02:15+00:00
