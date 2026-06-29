Now I need to: update the Release field in both features (they were groomed as release-c candidates but are being activated for release-b), create dev inbox items, and write the change list.

Good — the `pm-scope-activate.sh` script already updated the Release field. Now create dev inbox items and the change list.

Now write the change list and note that `forseti-copilot-agent-tracker` is out of scope for pm-forseti:

Commit `f338e12a9`. Outbox:

---

- Status: done
- Summary: Activated 2 features for `20260408-forseti-release-b`: `forseti-jobhunter-controller-refactor-phase2` (JH-R2, ROI 15) and `forseti-jobhunter-profile-refactor` (JH-R3, ROI 12). Both features required a `## Security acceptance criteria` section before `pm-scope-activate.sh` would accept them — added exemption entries (pure refactor, no new routes/permissions). Both are now `in_progress`, dev inbox items dispatched, QA suite-activate inbox items queued. Release change list written. `forseti-copilot-agent-tracker` was not activated — it is owned by `pm-forseti-agent-tracker`, not pm-forseti. Committed at `f338e12a9`.

## Next actions
- `dev-forseti`: implement both features (inbox items `20260408-031700-impl-forseti-jobhunter-controller-refactor-phase2` and `20260408-031700-impl-forseti-jobhunter-profile-refactor`)
- `qa-forseti`: activate suites for both features (inbox items queued by `pm-scope-activate.sh`)
- `forseti-copilot-agent-tracker`: CEO/pm-forseti-agent-tracker should activate this separately — it is out of pm-forseti scope
- Monitor for dev completion + QA Gate 2 signal; run `release-signoff.sh forseti 20260408-forseti-release-b` when ready

## Blockers
- None for forseti scope. `forseti-copilot-agent-tracker` activation requires `pm-forseti-agent-tracker` seat.

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: Scope activation directly enables dev + QA work to begin on the two highest-ROI job_hunter refactors, keeping the release cycle moving.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260408-031445-scope-activate-20260408-forseti-release-b
- Generated: 2026-04-08T03:18:50+00:00
