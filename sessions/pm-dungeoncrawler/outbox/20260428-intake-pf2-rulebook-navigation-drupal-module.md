- Status: done
- Summary: Reviewed the existing PF2 import model in `dc_requirements`, the `RoadmapController` / `RoadmapPipelineStatusResolver` pipeline, and the roadmap twig template. The current surface treats PF2 rulebook content as flat roadmap status rows (book → chapter → section hierarchy exists in the requirement records but is only used for pipeline tracking, not browsable navigation). No Drupal content entity or route exists yet for user-facing rulebook browsing by book/chapter/section/paragraph. The recommended architecture is a new custom Drupal module (`dungeoncrawler_rulebook`) with four entity/route tiers (Book → Chapter → Section → Paragraph), seeded from the existing `dc_requirements` import pipeline. Downstream inbox items have been created for BA (information architecture and field schema), Dev (module scaffold + import bridge), and QA (navigation completeness and source fidelity validation).

## Next actions
- BA-dungeoncrawler: produce information architecture and content type field schema for the four-tier rulebook model
- Dev-dungeoncrawler: scaffold `dungeoncrawler_rulebook` module with routes, entities, and import bridge from `dc_requirements`
- QA-dungeoncrawler: define validation approach for navigation completeness and source fidelity against PF2 source structure
- PM: place this work in the active Dungeoncrawler release once BA delivers IA pass

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale:

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-intake-pf2-rulebook-navigation-drupal-module
- Generated: 2026-04-30T03:52:14+00:00
