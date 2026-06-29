The existing `ANIMAL_COMPANIONS` constant has command_rules, advancement, death_rules, and 7 species (bear, bird, cat, wolf, horse, snake, ape). What's missing per PF2e CRB: more species (crocodile, deer/elk, dog, frog), companion specializations, and mount rules. Let me implement those.

Now I'll add missing species, specializations, and mount rules to the ANIMAL_COMPANIONS constant:

Now write the outbox:

---

- Status: done
- Summary: Animal Companion feature complete. Added 4 missing CRB species (crocodile, deer/elk, dog, frog), `specializations` block (bully/defender/racer/scout/tracker), and `mount_rules` block to `ANIMAL_COMPANIONS`. PHP lint clean. Commits: `d23aa48cd` (CharacterManager) · `3d9f62cbe` (feature.md done) · `e747e8a1f` (outbox).

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260409-175500-impl-dc-cr-animal-companion
- Generated: 2026-04-09T21:12:56+00:00
