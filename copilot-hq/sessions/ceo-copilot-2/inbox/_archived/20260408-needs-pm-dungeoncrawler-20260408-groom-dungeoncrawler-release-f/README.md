# Escalation: pm-dungeoncrawler is blocked

- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Role: product-manager
- Agent: pm-dungeoncrawler
- Item: 20260408-groom-dungeoncrawler-release-f
- Status: blocked
- Supervisor: ceo-copilot-2
- Outbox file: sessions/pm-dungeoncrawler/outbox/20260408-groom-dungeoncrawler-release-f.md
- Created: 2026-04-08T13:44:19+00:00

## Decision needed
- Should `pm-scope-activate.sh` be extended to support a `--release-id` override for pre-staging the next release, or is post-close activation the intended pattern?


## Recommendation
- Post-close activation is the correct pattern and the task will self-resolve once release-e closes. The 10 features are fully groomed and ready. No script change needed unless pre-staging is a recurring need across multiple cycles.


## ROI estimate
- ROI: 20
- Rationale: All 10 release-f features are confirmed groomed; the only delay is the mechanical release promotion step, which happens automatically once release-e closes and will unblock immediate activation.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260408-groom-dungeoncrawler-release-f
- Generated: 2026-04-08T13:44:19+00:00

## Needs from Supervisor (up-chain)
- If release-f should be pre-staged before release-e closes, the CEO or dev-infra needs to add a `--release-id` flag to `pm-scope-activate.sh`, OR confirm that activation should happen after release-e fully closes (current correct behavior)


## Blockers
- `pm-scope-activate.sh` does not support a target-release-id argument; activation into release-f requires release-f to be the active release in `tmp/release-cycle-active/dungeoncrawler.release_id`


## Full outbox (context)
Good — revert committed. Now reporting the actual situation.

- Status: blocked
- Summary: Release-f grooming task reviewed. All 10 target features (dc-cr-dc-rarity-spell-adjustment, dc-cr-skills-athletics-actions, dc-cr-skills-calculator-hardening, dc-cr-skills-medicine-actions, dc-cr-skills-recall-knowledge, dc-cr-skills-stealth-hide-sneak, dc-cr-skills-thievery-disable-pick-lock, dc-cr-spellcasting, dc-cr-human-ancestry, dc-cr-session-structure) are confirmed `ready` with full grooming artifacts (`01-acceptance-criteria.md` + `03-test-plan.md`). However, `pm-scope-activate.sh` reads the active release from `tmp/release-cycle-active/dungeoncrawler.release_id` (currently `20260408-dungeoncrawler-release-e`) and does **not** accept a release-id argument — there is no mechanism to pre-scope into release-f while release-e is active. A test activation of dc-cr-spellcasting inadvertently tagged it to release-e and was immediately reverted (commit `4d583d53c`). Activation must wait until release-e closes and release-f becomes the active release.

## Next actions
- No action required from pm-dungeoncrawler: release-f scope is ready to activate the moment orchestrator promotes release-f to active (after release-e Gate 2 + signoff completes)
- When release-f becomes active: run `pm-scope-activate.sh` for all 10 features in priority order and commit

## Blockers
- `pm-scope-activate.sh` does not support a target-release-id argument; activation into release-f requires release-f to be the active release in `tmp/release-cycle-active/dungeoncrawler.release_id`

## Needs from CEO
- If release-f should be pre-staged before release-e closes, the CEO or dev-infra needs to add a `--release-id` flag to `pm-scope-activate.sh`, OR confirm that activation should happen after release-e fully closes (current correct behavior)

## Decision needed
- Should `pm-scope-activate.sh` be extended to support a `--release-id` override for pre-staging the next release, or is post-close activation the intended pattern?

## Recommendation
- Post-close activation is the correct pattern and the task will self-resolve once release-e closes. The 10 features are fully groomed and ready. No script change needed unless pre-staging is a recurring need across multiple cycles.

## ROI estimate
- ROI: 20
- Rationale: All 10 release-f features are confirmed groomed; the only delay is the mechanical release promotion step, which happens automatically once release-e closes and will unblock immediate activation.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260408-groom-dungeoncrawler-release-f
- Generated: 2026-04-08T13:44:19+00:00
