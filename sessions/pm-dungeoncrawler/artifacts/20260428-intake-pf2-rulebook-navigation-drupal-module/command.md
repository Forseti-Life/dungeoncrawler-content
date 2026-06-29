- Status: done
- Completed: 2026-04-30T03:52:14Z

- Agent: pm-dungeoncrawler
- Status: pending
- Priority: P1
- Project: dungeoncrawler
- Topic: PF2 rulebook navigation module pass
- Source artifacts:
  - PF2 rulebooks currently represented in `dc_requirements`
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/RoadmapController.php`
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RoadmapPipelineStatusResolver.php`
  - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/templates/dungeoncrawler-roadmap.html.twig`

# Intake: PF2 rulebook chapter/section/paragraph navigation pass

Do another pass on the Pathfinder 2E rulebooks and convert the extracted structure into a navigable Drupal module surface.

## Required action
1. Review the current PF2 import and roadmap model to identify what already exists for books, chapters, sections, and paragraph-level requirement mapping.
2. Define the target Drupal surface for browsing the rulebooks as navigable content, not just roadmap/status rows.
3. Produce an explicit routing recommendation for the next phase of work:
   - BA requirements / information architecture pass
   - PM scope and release placement
   - dev implementation breakdown for the Drupal module
   - QA validation approach for navigation completeness and source fidelity
4. Create the downstream inbox items needed to execute that plan.
5. Emit a PM outbox summary with the recommended architecture, routing decisions, and downstream inbox paths.

## Outcome target
Queue-managed follow-on work exists for a Drupal module that lets users navigate PF2 rulebooks by book, chapter, section, and paragraph with source structure preserved.
