<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Central architecture hub page for process flow and requirements visibility.
 */
class ArchitectureController extends ControllerBase {

  /**
   * HQ repo features directory used for architecture indexing.
   * This is configurable via settings and falls back to module directory.
   */
  private function getHQFeaturesDir(): string {
    return \Drupal::config('dungeoncrawler_content.settings')
      ->get('hq_features_path') ?: \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content') . '/hq_features';
  }

  /**
   * Render the architecture overview hub.
   */
  public function index(): array {
    $flow_cards = [
      [
        'title' => $this->t('1) Planning & requirements'),
        'summary' => $this->t('Feature intent, acceptance criteria, ownership, and sequencing are defined before implementation starts.'),
        'items' => [
          $this->t('PM defines feature brief + acceptance criteria.'),
          $this->t('QA defines test plan and release gate checks.'),
          $this->t('Dev performs impact analysis before major changes.'),
        ],
      ],
      [
        'title' => $this->t('2) Build & validate'),
        'summary' => $this->t('Implementation is delivered in Drupal module code with deterministic routes, forms, and APIs.'),
        'items' => [
          $this->t('Server-driven form flow is canonical for character creation.'),
          $this->t('Step data persists as draft and progresses through defined stage gates.'),
          $this->t('Cache rebuild and environment health checks are required after changes.'),
        ],
      ],
      [
        'title' => $this->t('3) QA audit & release'),
        'summary' => $this->t('Automated and manual QA verify URL integrity, permission behavior, and functional outcomes before release status changes.'),
        'items' => [
          $this->t('Route/permission checks and functional coverage are published per release.'),
          $this->t('Regressions are tracked as targeted findings with explicit retest scope.'),
          $this->t('Release notes capture state, evidence, and unresolved risks.'),
        ],
      ],
      [
        'title' => $this->t('4) Operate & improve'),
        'summary' => $this->t('Production behavior is monitored and fed back into architecture, requirements, and implementation notes.'),
        'items' => [
          $this->t('Document lessons learned and architecture decisions.'),
          $this->t('Retire duplicated flows and legacy pathways.'),
          $this->t('Keep process docs and system behavior in sync.'),
        ],
      ],
    ];

    $requirement_domains = [
      [
        'name' => $this->t('Functional requirements'),
        'detail' => $this->t('User-visible behavior by workflow step, including expected transitions and outcomes.'),
      ],
      [
        'name' => $this->t('Data requirements'),
        'detail' => $this->t('Draft vs active lifecycle, schema expectations, derived stat behavior, and persistence constraints.'),
      ],
      [
        'name' => $this->t('Access requirements'),
        'detail' => $this->t('Anonymous, authenticated, and admin access boundaries for routes and entities.'),
      ],
      [
        'name' => $this->t('Operational requirements'),
        'detail' => $this->t('Release checks, audit evidence, cache invalidation, and rollback considerations.'),
      ],
    ];

    $release_gates = [
      [
        'name' => $this->t('Environment gate'),
        'criteria' => $this->t('Site bootstrap healthy, required modules enabled, expected schema present.'),
      ],
      [
        'name' => $this->t('Flow gate'),
        'criteria' => $this->t('End-to-end process flows complete without blockers or conflicting submit paths.'),
      ],
      [
        'name' => $this->t('Security gate'),
        'criteria' => $this->t('Permissions and route access enforce owner/admin boundaries correctly.'),
      ],
      [
        'name' => $this->t('QA gate'),
        'criteria' => $this->t('URL checks, functional checks, and release evidence published and reviewable.'),
      ],
    ];

    $system_domains = [
      [
        'domain' => $this->t('Character lifecycle'),
        'owner' => $this->t('dungeoncrawler_content module'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.character_setup')->toString(),
        'source_of_truth' => $this->t('CharacterSetupController + CharacterCreationStepForm + dc_campaign_characters'),
      ],
      [
        'domain' => $this->t('Campaign orchestration'),
        'owner' => $this->t('dungeoncrawler_content module'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
        'source_of_truth' => $this->t('CampaignController + campaign entities/tables'),
      ],
      [
        'domain' => $this->t('Combat runtime'),
        'owner' => $this->t('Hexmap + combat API controllers'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.hexmap_demo')->toString(),
        'source_of_truth' => $this->t('CombatEncounterApiController server state'),
      ],
      [
        'domain' => $this->t('Architecture documentation'),
        'owner' => $this->t('Engineering process (PM/QA/Dev)'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.architecture')->toString(),
        'source_of_truth' => $this->t('Architecture pages + feature implementation notes'),
      ],
    ];

    $critical_flows = [
      [
        'name' => $this->t('Character Creation'),
        'steps' => $this->t('/charactersetup → embedded /characters/create/step/{step} → draft save/finalize → /characters/{character_id}'),
        'controllers_apis' => $this->t('CharacterSetupController (tab shell), CharacterCreationStepController (embedded step flow/saveStep), CharacterCreationStepForm (server form flow), CharacterApiController (/api/character/* autosave/load/delete), CharacterViewController (final sheet render).'),
        'guardrails' => $this->t('Character Setup is the canonical user entry point; embedded step flow stays server-driven; draft persistence survives tab navigation; CSRF protection on save routes; owner/admin character access checks.'),
      ],
      [
        'name' => $this->t('Campaign Creation and Management'),
        'steps' => $this->t('/campaigns → /campaigns/create → /campaigns/{campaign_id} lifecycle actions (archive/unarchive) → character selection → tavern entrance/dungeon launch'),
        'controllers_apis' => $this->t('CampaignController (list/create/select character/tavern/dungeon selection), CampaignArchiveForm and CampaignUnarchiveForm (state transitions), CharacterListController (campaign character selection surface).'),
        'guardrails' => $this->t('Campaign access boundary enforcement via route requirements and campaign access checks; selected character ownership must validate before launch.'),
      ],
      [
        'name' => $this->t('Dungeon Creation and Management'),
        'steps' => $this->t('Dungeon generation/load request → dungeon payload retrieval/update → hexmap runtime exploration/combat actions'),
        'controllers_apis' => $this->t('DungeonController (generate/get level/update state endpoints), HexMapController (hexmap payload/render orchestration), CombatEncounterApiController (/api/combat/start, /api/combat/action, /api/combat/end-turn, /api/combat/end).'),
        'guardrails' => $this->t('Server-authoritative dungeon/encounter state; deterministic fallback behavior when AI provider responses are rejected/unavailable; route-level access and CSRF protections on mutation actions.'),
      ],
    ];

    $feature_index = $this->buildFeatureIndex();

    $governance_links = [
      [
        'label' => $this->t('Controller architecture map'),
        'url' => Url::fromRoute('dungeoncrawler_content.controller_architecture')->toString(),
        'description' => $this->t('Current backend controller responsibilities and boundaries (includes Encounter AI integration architecture: phase status, safeguards, and AI orchestration integration).'),
      ],
      [
        'label' => $this->t('Campaign operations (includes character roster)'),
        'url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
        'description' => $this->t('Entry point for campaigns and their character rosters. Characters are now scoped under /campaigns/{id}/characters.'),
      ],
      [
        'label' => $this->t('Campaign operations'),
        'url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
        'description' => $this->t('Campaign-level process orchestration and launch path.'),
      ],
    ];

    $architecture_map = [
      [
        'domain' => $this->t('Character lifecycle'),
        'owner' => $this->t('dungeoncrawler_content module'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.character_setup')->toString(),
        'source_of_truth' => $this->t('CharacterSetupController + CharacterCreationStepForm + dc_campaign_characters'),
        'flow' => $this->t('Character Creation'),
        'sequence' => $this->t('/charactersetup → embedded /characters/create/step/{step} → draft save/finalize → /characters/{character_id}'),
        'controllers_apis' => $this->t('CharacterSetupController (tab shell), CharacterCreationStepController (embedded step flow/saveStep), CharacterCreationStepForm (server form flow), CharacterApiController (/api/character/* autosave/load/delete), CharacterViewController (final sheet render).'),
        'guardrails' => $this->t('Character Setup is the canonical user entry point; embedded step flow stays server-driven; draft persistence survives tab navigation; CSRF protection on save routes; owner/admin character access checks.'),
        'drill_label' => $this->t('Campaigns (character roster)'),
        'drill_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
        'drill_description' => $this->t('Character rosters are now scoped per campaign at /campaigns/{id}/characters.'),
      ],
      [
        'domain' => $this->t('Campaign orchestration'),
        'owner' => $this->t('dungeoncrawler_content module'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
        'source_of_truth' => $this->t('CampaignController + campaign entities/tables'),
        'flow' => $this->t('Campaign Creation and Management'),
        'sequence' => $this->t('/campaigns → /campaigns/create → /campaigns/{campaign_id} lifecycle actions (archive/unarchive) → character selection → tavern entrance/dungeon launch'),
        'controllers_apis' => $this->t('CampaignController (list/create/select character/tavern/dungeon selection), CampaignArchiveForm and CampaignUnarchiveForm (state transitions), CharacterListController (campaign character selection surface).'),
        'guardrails' => $this->t('Campaign access boundary enforcement via route requirements and campaign access checks; selected character ownership must validate before launch.'),
        'drill_label' => $this->t('Campaign operations'),
        'drill_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
        'drill_description' => $this->t('Campaign-level process orchestration and launch path.'),
      ],
      [
        'domain' => $this->t('Combat runtime'),
        'owner' => $this->t('Hexmap + combat API controllers'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.hexmap_demo')->toString(),
        'source_of_truth' => $this->t('CombatEncounterApiController server state'),
        'flow' => $this->t('Dungeon Creation and Management'),
        'sequence' => $this->t('Dungeon generation/load request → dungeon payload retrieval/update → hexmap runtime exploration/combat actions'),
        'controllers_apis' => $this->t('DungeonController (generate/get level/update state endpoints), HexMapController (hexmap payload/render orchestration), CombatEncounterApiController (/api/combat/start, /api/combat/action, /api/combat/end-turn, /api/combat/end).'),
        'guardrails' => $this->t('Server-authoritative dungeon/encounter state; deterministic fallback behavior when AI provider responses are rejected/unavailable; route-level access and CSRF protections on mutation actions.'),
        'drill_label' => $this->t('Controller architecture map'),
        'drill_url' => Url::fromRoute('dungeoncrawler_content.controller_architecture')->toString(),
        'drill_description' => $this->t('Current backend controller responsibilities and boundaries (includes Encounter AI integration architecture: phase status, safeguards, and AI orchestration integration).'),
      ],
      [
        'domain' => $this->t('Architecture documentation'),
        'owner' => $this->t('Engineering process (PM/QA/Dev)'),
        'entry_route' => Url::fromRoute('dungeoncrawler_content.architecture')->toString(),
        'source_of_truth' => $this->t('Architecture pages + feature implementation notes'),
        'flow' => $this->t('Architecture governance, lineage, and review'),
        'sequence' => $this->t('/architecture → /architecture/data-lineage → /architecture/controllers → /architecture/encounter-ai-integration → feature index sync'),
        'controllers_apis' => $this->t('ArchitectureController (hub + HQ feature index), DataLineageArchitectureController (page/API/controller/table hierarchy), ControllerArchitectureController (backend mapping), EncounterAiIntegrationController (phase status/safeguards/AI orchestration metrics).'),
        'guardrails' => $this->t('Architecture page remains source-of-truth for high-level flows; updates required when workflow behavior changes.'),
        'drill_label' => $this->t('Data lineage architecture'),
        'drill_url' => Url::fromRoute('dungeoncrawler_content.data_lineage_architecture')->toString(),
        'drill_description' => $this->t('Hierarchical page/API/controller/table lineage map for runtime architecture.'),
      ],
    ];

    return [
      '#theme' => 'architecture_overview',
      '#intro' => $this->t('This page is the single architecture hub for process flows, requirements organization, and quality gates. Update this first when workflow behavior changes.'),
      '#flow_cards' => $flow_cards,
      '#requirement_domains' => $requirement_domains,
      '#release_gates' => $release_gates,
      '#system_domains' => $system_domains,
      '#critical_flows' => $critical_flows,
      '#architecture_map' => $architecture_map,
      '#feature_index' => $feature_index,
      '#governance_links' => $governance_links,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/architecture',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Build a live feature index from HQ feature artifacts.
   */
  private function buildFeatureIndex(): array {
    $root = $this->getHQFeaturesDir();
    if (!is_dir($root)) {
      return [[
        'feature_id' => (string) $this->t('Unavailable'),
        'status' => (string) $this->t('unknown'),
        'priority' => '-',
        'release' => '-',
        'acceptance' => (string) $this->t('No features directory found at @path', ['@path' => $root]),
        'implementation' => (string) $this->t('missing'),
      ]];
    }

    $feature_dirs = glob($root . '/dc-*', GLOB_ONLYDIR) ?: [];
    sort($feature_dirs);

    $rows = [];
    foreach ($feature_dirs as $dir) {
      $feature_id = basename($dir);
      $feature_md_path = $dir . '/feature.md';
      $ac_path = $dir . '/01-acceptance-criteria.md';
      $impl_path = $dir . '/02-implementation-notes.md';

      $feature_md = is_file($feature_md_path) ? (string) file_get_contents($feature_md_path) : '';
      $ac_md = is_file($ac_path) ? (string) file_get_contents($ac_path) : '';
      $has_impl = is_file($impl_path);

      $rows[] = [
        'feature_id' => $feature_id,
        'status' => $this->extractFieldValue($feature_md, 'Status', 'unknown'),
        'priority' => $this->extractFieldValue($feature_md, 'Priority', '-'),
        'release' => $this->extractFieldValue($feature_md, 'Release', '-'),
        'acceptance' => $this->summarizeAcceptanceCriteria($ac_md),
        'implementation' => $has_impl ? 'present' : 'missing',
      ];
    }

    return $rows;
  }

  /**
   * Extract markdown field values from "- Label: value" patterns.
   */
  private function extractFieldValue(string $markdown, string $label, string $fallback): string {
    $pattern = '/^-\\s*' . preg_quote($label, '/') . ':\\s*(.+)$/mi';
    if (preg_match($pattern, $markdown, $matches)) {
      return trim((string) ($matches[1] ?? $fallback));
    }
    return $fallback;
  }

  /**
   * Summarize acceptance criteria by counting open checklist items.
   */
  private function summarizeAcceptanceCriteria(string $markdown): string {
    if ($markdown === '') {
      return 'missing';
    }

    $open = preg_match_all('/^-\s*\[\s\]\s+/m', $markdown);
    $done = preg_match_all('/^-\s*\[x\]\s+/mi', $markdown);
    $open_count = (int) ($open ?: 0);
    $done_count = (int) ($done ?: 0);
    $total = $open_count + $done_count;

    if ($total === 0) {
      return 'no checklist items';
    }

    return sprintf('%d total (%d open / %d done)', $total, $open_count, $done_count);
  }

}
