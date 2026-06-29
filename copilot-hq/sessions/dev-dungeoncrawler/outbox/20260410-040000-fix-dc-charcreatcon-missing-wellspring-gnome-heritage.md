Already fixed. The prior session's Death Warden fix replaced the entire hardcoded method body with `return CharacterManager::HERITAGES[$name] ?? [];` — which means Wellspring Gnome (and all heritages) are now served from the canonical source automatically.

---

- Status: done
- Summary: Already resolved. The prior session's Death Warden fix (commit `d14462b53`) replaced the entire hardcoded `getAncestryHeritages()` body with `return CharacterManager::HERITAGES[$name] ?? [];` — which delegates to the canonical source for all ancestries. `CharacterManager::HERITAGES['Gnome']` contains all 5 entries including Wellspring Gnome. No code change required; all 3 acceptance criteria are met.

## Next actions
- QA can verify Gnome heritages return 5 entries via the character creation flow.

## Blockers
- None.

## ROI estimate
- ROI: 15
- Rationale: Defect was already closed as a side effect of the architectural delegation fix; confirming closure is lower cost than a redundant patch.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-040000-fix-dc-charcreatcon-missing-wellspring-gnome-heritage
- Generated: 2026-04-10T07:18:15+00:00
