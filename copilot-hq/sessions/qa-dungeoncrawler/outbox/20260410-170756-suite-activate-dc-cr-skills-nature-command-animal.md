Commit `c32d15a8e`. Outbox:

---

- Status: done
- Summary: Activated the `dc-cr-skills-nature-command-animal` suite into `qa-suites/products/dungeoncrawler/suite.json`. Added 14 TCs as `dc-cr-skills-nature-command-animal-phpunit`: 11 immediately activatable covering Recall Knowledge domain scoping and untrained use (TC-NAT-01–02), Command an Animal action cost/auditory/Trained gate, creature-type gate, command-capability gate, Will-save DC (TC-NAT-03–06), Success/Failure single-turn outcomes (TC-NAT-08–09), bonded-animal lower DC and explicit non-animal type block (TC-NAT-11–12), and ACL regression (TC-NAT-14). Three TCs deferred: TC-NAT-07 (Crit Success cooperative-state timer) and TC-NAT-10 (Crit Fail panic state) pending NPC/creature behavior model; TC-NAT-13 (auditory trait muted/deaf edge case) pending `dc-cr-conditions` flags or PM implementation decision. ACL rule `dc-cr-skills-nature-command-animal-acl-regression` added. Suite validated OK (89 suites, 102 ACL rules). Commit: `c32d15a8e`.

## Next actions
- Process `dc-cr-skills-performance-perform` suite activation (final skills suite in batch)
- Process unit-test inbox items (release-d skills, fix-from-qa-block phantom fast-close)

## Blockers
- None

## Needs from CEO
- N/A

## PM flags (for pm-dungeoncrawler attention)
- TC-NAT-01: confirm authoritative creature category list for Nature Recall Knowledge domain (especially magical beast)
- TC-NAT-05: confirm command-complexity validation model (enumerated set vs capability flag)
- TC-NAT-06: confirm default passive DC for untrained animals
- TC-NAT-07/10: confirm NPC/creature behavior model scope — cooperative-state timer and panic state are the two blockers for the Crit Success and Crit Fail TCs
- TC-NAT-11: confirm exact DC reduction for bonded animals
- TC-NAT-13: confirm auditory-trait barrier outcome (hard block vs circumstance penalty)

## ROI estimate
- ROI: 6
- Rationale: 11 active TCs deliver immediate regression coverage for Command an Animal core path; 3 deferred TCs are pre-staged so activation is one status-flip once NPC behavior model lands.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-nature-command-animal
- Generated: 2026-04-11T02:49:56+00:00
