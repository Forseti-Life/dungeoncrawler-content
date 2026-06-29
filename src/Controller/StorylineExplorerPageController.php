<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Storyline explorer page for canonical storyline analysis.
 *
 * Source-of-truth rule: validator checks are DB-authoritative via services;
 * JSON template files are analysis/reference material for fixing DB drift.
 */
class StorylineExplorerPageController extends ControllerBase {

  protected ?StorylineManagerService $storylineManager;

  public function __construct(?StorylineManagerService $storyline_manager = NULL) {
    $this->storylineManager = $storyline_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $storyline_manager = $container->has('dungeoncrawler_content.storyline_manager')
      ? $container->get('dungeoncrawler_content.storyline_manager')
      : NULL;

    return new static($storyline_manager);
  }

  /**
   * Render the storyline explorer.
   */
  public function index(Request $request): array {
    $templates = $this->loadStorylineTemplates();
    $selected_template_id = trim((string) $request->query->get('template_id', ''));
    if ($selected_template_id === '' && isset($templates[0]['template_id'])) {
      $selected_template_id = (string) $templates[0]['template_id'];
    }

    $selected_template = $this->findTemplate($templates, $selected_template_id);
    $graph = $this->buildTemplateGraph($selected_template['template_data'] ?? []);
    $diagram = $this->buildMermaidDiagram($graph['nodes'], $graph['edges']);
    $player_party_projection = $this->buildPlayerPartyHappyPath(
      $selected_template_id,
      $selected_template['template_data'] ?? [],
      $graph
    );

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container', 'py-4', 'py-lg-5', 'world-game-flow', 'storyline-explorer-page'],
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/world-game-flow',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['user', 'url.query_args'],
      ],
    ];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#attributes' => ['class' => ['h3', 'mb-2']],
          '#value' => (string) $this->t('Storyline Explorer'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0']],
          '#value' => (string) $this->t('Refine storyline validator contracts and validate canonical process flow, graph edges, and Mermaid projections for template-library quests.'),
        ],
      ],
    ];

    $build['filters'] = $this->buildFilterSection($templates, $selected_template_id);

    $build['canonical_storylines'] = $this->buildCanonicalStorylinesTable(
      $templates,
      $selected_template_id
    );
    $build['validator'] = $this->buildValidationSummaryCard($selected_template_id, $selected_template['template_data'] ?? [], $graph);
    $build['flow'] = $this->buildCanonicalProcessFlowTable($selected_template_id, $selected_template['template_data'] ?? []);
    $build['trace'] = $this->buildStorylineTraceAccordion($selected_template_id, $selected_template['template_data'] ?? []);

    $build['graph'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Storyline Graph (@template)', ['@template' => $selected_template_id ?: 'none']),
        ],
        'diagram' => [
          '#markup' => '<div class="world-game-flow__mermaid-shell"><div class="world-game-flow__mermaid" data-mermaid-diagram>'
            . Html::escape($diagram)
            . '</div></div>',
        ],
      ],
    ];
    $build['player_party_graph'] = $this->buildPlayerPartyPerspectiveCard(
      $selected_template_id,
      $player_party_projection
    );
    $build['happy_path'] = $this->buildHappyPathStoryCard(
      $selected_template_id,
      $player_party_projection
    );

    $build['nodes'] = $this->buildNodesTable($graph['nodes'], (string) $this->t('Canonical Nodes'));
    $build['edges'] = $this->buildEdgesTable($graph['edges'], (string) $this->t('Canonical Edges'));

    return $build;
  }

  /**
   * Load storyline templates safely.
   *
   * @return array<int, array<string, mixed>>
   *   Template rows.
   */
  protected function loadStorylineTemplates(): array {
    $templates_dir = dirname(__DIR__, 2) . '/config/examples/templates/dungeoncrawler_content_storylines';
    if (!is_dir($templates_dir)) {
      return [];
    }

    $templates = [];
    foreach (glob($templates_dir . '/*.json') ?: [] as $path) {
      $raw = file_get_contents($path);
      if (!is_string($raw) || trim($raw) === '') {
        continue;
      }
      $decoded = json_decode($raw, TRUE);
      if (!is_array($decoded) || !is_array($decoded['rows'] ?? NULL)) {
        continue;
      }
      foreach ($decoded['rows'] as $row) {
        if (!is_array($row)) {
          continue;
        }
        $template_id = trim((string) ($row['template_id'] ?? ''));
        if ($template_id === '') {
          continue;
        }
        $templates[] = [
          'template_id' => $template_id,
          'name' => (string) ($row['name'] ?? $template_id),
          'template_data' => is_array($row['template_data'] ?? NULL) ? $row['template_data'] : [],
        ];
      }
    }

    usort($templates, static fn(array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    return $templates;
  }

  /**
   * Locate the selected template row.
   */
  protected function findTemplate(array $templates, string $template_id): array {
    foreach ($templates as $template) {
      if ((string) ($template['template_id'] ?? '') === $template_id) {
        return is_array($template) ? $template : [];
      }
    }
    return [];
  }

  /**
   * Build query-link filters for canonical template selection.
   */
  protected function buildFilterSection(array $templates, string $selected_template_id): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row', 'g-3', 'mb-4']],
    ];

    $template_items = [];
    foreach ($templates as $template) {
      $template_id = (string) ($template['template_id'] ?? '');
      if ($template_id === '') {
        continue;
      }
      $template_items[] = [
        '#markup' => Markup::create(sprintf(
          '<a class="btn btn-sm %s me-2 mb-2" href="%s">%s</a>',
          $template_id === $selected_template_id ? 'btn-primary' : 'btn-outline-primary',
          Html::escape($this->buildExplorerUrl($template_id)),
          Html::escape((string) ($template['name'] ?? $template_id))
        )),
      ];
    }

    $build['templates'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-lg-12']],
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'h-100']],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#attributes' => ['class' => ['h6', 'mb-3']],
            '#value' => (string) $this->t('Template selector'),
          ],
          'items' => $template_items,
        ],
      ],
    ];

    return $build;
  }

  /**
   * Build a URL to this explorer route with optional template selector.
   */
  protected function buildExplorerUrl(string $template_id): string {
    $query = [];
    if ($template_id !== '') {
      $query['template_id'] = $template_id;
    }
    return Url::fromRoute('dungeoncrawler_content.storyline_explorer', [], ['query' => $query])->toString();
  }

  /**
   * Build graph nodes/edges from template_data.
   *
   * @return array{
   *   nodes: array<string, array{type: string, label: string}>,
   *   edges: array<int, array{from: string, to: string, relation: string}>
   * }
   *   Graph payload.
   */
  protected function buildTemplateGraph(array $template_data): array {
    $nodes = [];
    $edges = [];
    $quest_context_map = [];

    $storyline_id = 'storyline:' . trim((string) ($template_data['template_id'] ?? 'unknown'));
    $nodes[$storyline_id] = [
      'type' => 'storyline',
      'label' => (string) ($template_data['name'] ?? $storyline_id),
    ];

    foreach (($template_data['chapters'] ?? []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = 'chapter:' . trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id === 'chapter:') {
        continue;
      }
      $nodes[$chapter_id] = ['type' => 'chapter', 'label' => (string) ($chapter['name'] ?? $chapter_id)];
      $edges[] = ['from' => $storyline_id, 'to' => $chapter_id, 'relation' => 'has_chapter'];

      foreach (($chapter['scenes'] ?? []) as $scene) {
        if (!is_array($scene)) {
          continue;
        }
        $scene_id = 'scene:' . trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id === 'scene:') {
          continue;
        }
        $nodes[$scene_id] = ['type' => 'scene', 'label' => (string) ($scene['name'] ?? $scene_id)];
        $edges[] = ['from' => $chapter_id, 'to' => $scene_id, 'relation' => 'has_scene'];

        foreach (($scene['quest_ids'] ?? []) as $quest_id_value) {
          $quest_template_id = trim((string) $quest_id_value);
          $quest_id = 'quest:' . $quest_template_id;
          if ($quest_id === 'quest:') {
            continue;
          }
          $nodes[$quest_id] = ['type' => 'quest', 'label' => str_replace('quest:', '', $quest_id)];
          $edges[] = ['from' => $scene_id, 'to' => $quest_id, 'relation' => 'has_quest'];
          $quest_context_map[$quest_template_id] = [
            'chapter_id' => trim((string) ($chapter['chapter_id'] ?? '')),
            'scene_id' => trim((string) ($scene['scene_id'] ?? '')),
          ];
        }
      }
    }

    foreach (($template_data['contacts'] ?? []) as $contact) {
      if (!is_array($contact)) {
        continue;
      }
      $contact_id = 'contact:' . trim((string) ($contact['contact_id'] ?? ''));
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($contact_id === 'contact:') {
        continue;
      }
      $nodes[$contact_id] = ['type' => 'contact', 'label' => (string) ($contact['display_name'] ?? $contact_id)];
      $edges[] = ['from' => $storyline_id, 'to' => $contact_id, 'relation' => 'has_contact'];
      if ($entity_id !== '') {
        $entity_node = 'entity:' . $entity_id;
        $nodes[$entity_node] = ['type' => (string) ($contact['entity_type'] ?? 'entity'), 'label' => $entity_id];
        $edges[] = ['from' => $contact_id, 'to' => $entity_node, 'relation' => 'entity_ref'];
      }
      foreach (($contact['introduces_to'] ?? []) as $introduced) {
        if (!is_array($introduced)) {
          continue;
        }
        $introduced_id = trim((string) ($introduced['entity_id'] ?? ''));
        if ($introduced_id === '') {
          continue;
        }
        $introduced_node = 'entity:' . $introduced_id;
        $nodes[$introduced_node] = [
          'type' => (string) ($introduced['entity_type'] ?? 'entity'),
          'label' => (string) ($introduced['display_name'] ?? $introduced_id),
        ];
        $edges[] = [
          'from' => $contact_id,
          'to' => $introduced_node,
          'relation' => (string) ($introduced['relationship_type'] ?? 'introduces_to'),
        ];
      }
    }

    foreach (($template_data['asset_references'] ?? []) as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_id = trim((string) ($reference['asset_id'] ?? ''));
      $asset_type = trim((string) ($reference['asset_type'] ?? 'asset'));
      if ($asset_id === '') {
        continue;
      }
      $asset_node = $asset_type . ':' . $asset_id;
      $nodes[$asset_node] = ['type' => $asset_type, 'label' => $asset_id];
      $edges[] = [
        'from' => $storyline_id,
        'to' => $asset_node,
        'relation' => 'asset_' . trim((string) ($reference['asset_role'] ?? 'linked')),
      ];
    }

    $this->enrichGraphWithQuestObjectiveDetails($nodes, $edges, $quest_context_map);

    return [
      'nodes' => $nodes,
      'edges' => $edges,
    ];
  }

  /**
   * Enrich graph with DB-authoritative objective/action/location/trigger nodes.
   */
  protected function enrichGraphWithQuestObjectiveDetails(array &$nodes, array &$edges, array $quest_context_map): void {
    if (!($this->storylineManager instanceof StorylineManagerService)) {
      return;
    }

    foreach ($quest_context_map as $quest_id => $quest_meta) {
      $quest_id = trim((string) $quest_id);
      if ($quest_id === '') {
        continue;
      }
      $quest_node_id = 'quest:' . $quest_id;
      if (!isset($nodes[$quest_node_id])) {
        continue;
      }

      try {
        $objective_phases = $this->storylineManager->getCanonicalQuestTemplateObjectivePhases($quest_id);
      }
      catch (\Throwable) {
        continue;
      }

      if (!is_array($objective_phases) || $objective_phases === []) {
        continue;
      }

      foreach (array_values($objective_phases) as $phase_index => $phase) {
        if (!is_array($phase)) {
          continue;
        }
        $objectives = array_values(array_filter(is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [], 'is_array'));
        foreach ($objectives as $objective_index => $objective) {
          $objective_label = trim((string) ($objective['objective_id'] ?? ''));
          if ($objective_label === '') {
            $objective_label = 'phase-' . ($phase_index + 1) . '-objective-' . ($objective_index + 1);
          }

          $objective_node_id = 'objective:' . $quest_id . ':' . $objective_label;
          if (isset($nodes[$objective_node_id])) {
            $objective_node_id .= '-' . ($phase_index + 1) . '-' . ($objective_index + 1);
          }
          $nodes[$objective_node_id] = [
            'type' => 'objective',
            'label' => $objective_label,
          ];
          $edges[] = [
            'from' => $quest_node_id,
            'to' => $objective_node_id,
            'relation' => 'has_objective',
          ];

          $action = $this->resolveObjectiveAction($objective);
          $location = $this->resolveObjectiveLocation($objective, is_array($quest_meta) ? $quest_meta : []);
          $completion_trigger = $this->resolveObjectiveCompletionTrigger($objective);

          $action_node_id = $objective_node_id . ':action';
          $nodes[$action_node_id] = ['type' => 'objective_action', 'label' => $action];
          $edges[] = ['from' => $objective_node_id, 'to' => $action_node_id, 'relation' => 'objective_action'];

          $location_node_id = $objective_node_id . ':location';
          $nodes[$location_node_id] = ['type' => 'objective_location', 'label' => $location];
          $edges[] = ['from' => $objective_node_id, 'to' => $location_node_id, 'relation' => 'objective_location'];

          $trigger_node_id = $objective_node_id . ':completion-trigger';
          $nodes[$trigger_node_id] = ['type' => 'objective_completion_trigger', 'label' => $completion_trigger];
          $edges[] = ['from' => $objective_node_id, 'to' => $trigger_node_id, 'relation' => 'objective_completion_trigger'];
        }
      }
    }
  }

  /**
   * Build a player-party path from canonical storyline + objective data only.
   *
   * @param array{
   *   nodes?: array<string, array{type: string, label: string}>,
   *   edges?: array<int, array{from: string, to: string, relation: string}>
   * } $graph
   *   Canonical graph payload.
   *
   * @return array{
   *   diagram: string,
   *   steps: array<int, array{
   *     title: string,
   *     detail: string,
   *     touches: array<int, string>,
   *     progression_gate: string,
   *     how_to_trigger: string,
   *     location: string,
   *     actors: string
   *   }>,
   *   coverage: array{touched: int, total: int, missing: array<int, string>},
   *   node_map: array<string, array{type: string, label: string}>
   * }
   *   Player-party projection payload.
   */
  protected function buildPlayerPartyHappyPath(string $selected_template_id, array $template_data, array $graph): array {
    $nodes = is_array($graph['nodes'] ?? NULL) ? $graph['nodes'] : [];
    $steps = [];
    $touched = [];

    $add_step = function (
      string $title,
      string $detail,
      array $touches = [],
      string $progression_gate = '',
      string $how_to_trigger = '',
      string $location = '',
      string $actors = ''
    ) use (&$steps, &$touched, $nodes): void {
      $normalized_touches = [];
      foreach ($touches as $node_id) {
        $node_id = trim((string) $node_id);
        if ($node_id === '' || !isset($nodes[$node_id])) {
          continue;
        }
        $normalized_touches[] = $node_id;
        $touched[$node_id] = TRUE;
      }

      $steps[] = [
        'title' => $title,
        'detail' => $detail,
        'touches' => array_values(array_unique($normalized_touches)),
        'progression_gate' => $progression_gate,
        'how_to_trigger' => $how_to_trigger,
        'location' => $location,
        'actors' => $actors,
      ];
    };

    $template_id = trim((string) ($template_data['template_id'] ?? $selected_template_id));
    $storyline_node = 'storyline:' . ($template_id !== '' ? $template_id : 'unknown');
    $contacts = array_values(array_filter(is_array($template_data['contacts'] ?? NULL) ? $template_data['contacts'] : [], 'is_array'));
    $asset_references = array_values(array_filter(is_array($template_data['asset_references'] ?? NULL) ? $template_data['asset_references'] : [], 'is_array'));

    $contact_id_by_entity = [];
    foreach ($contacts as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      $contact_id = trim((string) ($contact['contact_id'] ?? ''));
      if ($entity_id !== '' && $contact_id !== '') {
        $contact_id_by_entity[$entity_id] = $contact_id;
      }
    }

    $entry_point = is_array($template_data['metadata']['generated_outline']['entry_point'] ?? NULL)
      ? $template_data['metadata']['generated_outline']['entry_point']
      : [];

    if ($entry_point !== []) {
      $primary_quest_giver_id = trim((string) ($entry_point['primary_quest_giver_id'] ?? ''));
      $broker_id = trim((string) ($entry_point['broker_id'] ?? ''));
      $primary_dungeon_id = trim((string) ($entry_point['primary_dungeon_id'] ?? ''));
      $primary_location_id = trim((string) ($entry_point['primary_location_id'] ?? ''));
      $primary_chapter_id = trim((string) ($entry_point['primary_chapter_id'] ?? ''));
      $primary_scene_id = trim((string) ($entry_point['primary_scene_id'] ?? ''));
      $entry_how_to_trigger = trim((string) ($entry_point['how_to_trigger'] ?? $entry_point['introduction_path'] ?? ''));
      $entry_progression_gate = trim((string) ($entry_point['progression_gate'] ?? ''));

      $entry_touches = [];
      if (isset($nodes[$storyline_node])) {
        $entry_touches[] = $storyline_node;
      }
      foreach ([
        $primary_dungeon_id !== '' ? 'dungeon:' . $primary_dungeon_id : '',
        $primary_location_id !== '' ? 'room:' . $primary_location_id : '',
        $primary_location_id !== '' ? 'location:' . $primary_location_id : '',
        $primary_chapter_id !== '' ? 'chapter:' . $primary_chapter_id : '',
        $primary_scene_id !== '' ? 'scene:' . $primary_scene_id : '',
      ] as $entry_node_id) {
        if ($entry_node_id !== '' && isset($nodes[$entry_node_id])) {
          $entry_touches[] = $entry_node_id;
        }
      }
      foreach ([$primary_quest_giver_id, $broker_id] as $entity_id) {
        if ($entity_id === '') {
          continue;
        }
        $contact_id = $contact_id_by_entity[$entity_id] ?? '';
        if ($contact_id !== '' && isset($nodes['contact:' . $contact_id])) {
          $entry_touches[] = 'contact:' . $contact_id;
        }
        if (isset($nodes['entity:' . $entity_id])) {
          $entry_touches[] = 'entity:' . $entity_id;
        }
      }

      $entry_location = array_values(array_filter([
        $primary_dungeon_id,
        $primary_location_id,
        $primary_chapter_id,
        $primary_scene_id,
      ], static fn(string $value): bool => trim($value) !== ''));
      $entry_actors = array_values(array_filter([
        $primary_quest_giver_id,
        $broker_id,
      ], static fn(string $value): bool => trim($value) !== ''));

      $add_step(
        'Entry point',
        trim((string) ($entry_point['detail_summary'] ?? '')),
        $entry_touches,
        $entry_progression_gate,
        $entry_how_to_trigger,
        $entry_location !== [] ? implode(' / ', array_values(array_unique($entry_location))) : '',
        $entry_actors !== [] ? implode(', ', array_values(array_unique($entry_actors))) : ''
      );
    }
    elseif (isset($nodes[$storyline_node])) {
      $add_step(
        'Storyline',
        trim((string) ($template_data['name'] ?? $template_id)),
        [$storyline_node]
      );
    }

    $chapters = array_values(array_filter(is_array($template_data['chapters'] ?? NULL) ? $template_data['chapters'] : [], 'is_array'));
    foreach ($chapters as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      $scenes = array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array'));
      foreach ($scenes as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        $scene_summary = trim((string) ($scene['summary'] ?? ''));
        $quest_ids = array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []), static fn(string $id): bool => trim($id) !== ''));
        $scene_how_to_trigger = trim((string) ($scene['how_to_trigger'] ?? $scene['player_trigger'] ?? ''));
        $scene_progression_gate = trim((string) ($scene['progression_gate'] ?? ''));
        if ($scene_progression_gate === '' && $quest_ids !== []) {
          $scene_progression_gate = implode(', ', $quest_ids);
        }

        $scene_touches = [];
        foreach ([
          $chapter_id !== '' ? 'chapter:' . $chapter_id : '',
          $scene_id !== '' ? 'scene:' . $scene_id : '',
          $scene_id !== '' ? 'location:' . $scene_id : '',
          $scene_id !== '' ? 'room:' . $scene_id : '',
        ] as $scene_node_id) {
          if ($scene_node_id !== '' && isset($nodes[$scene_node_id])) {
            $scene_touches[] = $scene_node_id;
          }
        }
        foreach ($quest_ids as $quest_id) {
          if (isset($nodes['quest:' . $quest_id])) {
            $scene_touches[] = 'quest:' . $quest_id;
          }
        }

        $scene_actor_ids = [];
        foreach ($contacts as $contact) {
          $entity_id = trim((string) ($contact['entity_id'] ?? ''));
          $relationship_state = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
          $contact_chapter_id = trim((string) ($relationship_state['chapter_id'] ?? ''));
          $contact_scene_id = trim((string) ($relationship_state['scene_id'] ?? ''));
          if (
            $entity_id !== ''
            && (($contact_chapter_id !== '' && $contact_chapter_id === $chapter_id) || ($contact_scene_id !== '' && $contact_scene_id === $scene_id))
          ) {
            $scene_actor_ids[$entity_id] = TRUE;
          }
        }
        foreach ($asset_references as $asset_reference) {
          $asset_type = trim((string) ($asset_reference['asset_type'] ?? ''));
          $asset_id = trim((string) ($asset_reference['asset_id'] ?? ''));
          $asset_chapter_id = trim((string) ($asset_reference['chapter_id'] ?? ''));
          $asset_scene_id = trim((string) ($asset_reference['scene_id'] ?? ''));
          if (
            $asset_id !== ''
            && in_array($asset_type, ['npc', 'hazard', 'creature', 'character', 'character_group', 'campaign_npc', 'npc_template'], TRUE)
            && (($asset_chapter_id !== '' && $asset_chapter_id === $chapter_id) || ($asset_scene_id !== '' && $asset_scene_id === $scene_id))
          ) {
            $scene_actor_ids[$asset_id] = TRUE;
          }
        }

        $scene_location = implode(' / ', array_values(array_filter([$chapter_id, $scene_id], static fn(string $value): bool => trim($value) !== '')));
        $add_step(
          'Scene: ' . $scene_id,
          $scene_summary,
          $scene_touches,
          $scene_progression_gate,
          $scene_how_to_trigger,
          $scene_location,
          implode(', ', array_keys($scene_actor_ids))
        );

        foreach ($quest_ids as $quest_id) {
          $quest_meta = [
            'chapter_id' => $chapter_id,
            'scene_id' => $scene_id,
          ];
          $objective_phases = NULL;
          if ($this->storylineManager instanceof StorylineManagerService) {
            try {
              $objective_phases = $this->storylineManager->getCanonicalQuestTemplateObjectivePhases($quest_id);
            }
            catch (\Throwable) {
              $objective_phases = NULL;
            }
          }

          $quest_touches = [];
          if (isset($nodes['quest:' . $quest_id])) {
            $quest_touches[] = 'quest:' . $quest_id;
          }
          $quest_objective_ids = [];
          $quest_action_details = [];
          $quest_how_triggers = [];
          $quest_completion_gates = [];
          $quest_actor_ids = [];

          if (is_array($objective_phases) && $objective_phases !== []) {
            foreach (array_values($objective_phases) as $phase_index => $phase) {
              if (!is_array($phase)) {
                continue;
              }
              $objectives = array_values(array_filter(is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [], 'is_array'));
              foreach ($objectives as $objective_index => $objective) {
                $objective_id = trim((string) ($objective['objective_id'] ?? ''));
                if ($objective_id === '') {
                  $objective_id = 'phase-' . ($phase_index + 1) . '-objective-' . ($objective_index + 1);
                }
                $quest_objective_ids[$objective_id] = TRUE;

                $objective_node_id = 'objective:' . $quest_id . ':' . $objective_id;
                if (!isset($nodes[$objective_node_id])) {
                  $candidate = $objective_node_id . '-' . ($phase_index + 1) . '-' . ($objective_index + 1);
                  if (isset($nodes[$candidate])) {
                    $objective_node_id = $candidate;
                  }
                }
                if (isset($nodes[$objective_node_id])) {
                  $quest_touches[] = $objective_node_id;
                }

                $objective_action = $this->resolveObjectiveAction($objective);
                $objective_completion_gate = $this->resolveObjectiveCompletionTrigger($objective);
                $objective_how_to_trigger = $this->resolveObjectiveHowToTrigger($objective, $quest_meta);

                $objective_location_values = [];
                foreach (['location_id', 'destination_id', 'location', 'destination'] as $location_field) {
                  $location_value = trim((string) ($objective[$location_field] ?? ''));
                  if ($location_value !== '') {
                    $objective_location_values[$location_value] = TRUE;
                  }
                }
                if ($objective_location_values === []) {
                  foreach (array_values(array_filter([$chapter_id, $scene_id], static fn(string $value): bool => trim($value) !== '')) as $fallback_location) {
                    $objective_location_values[$fallback_location] = TRUE;
                  }
                }

                $objective_actor_ids = [];
                $objective_actor_touches = [];
                foreach (['target', 'npc_id', 'npc_ref'] as $actor_field) {
                  $actor_value = trim((string) ($objective[$actor_field] ?? ''));
                  if ($actor_value === '') {
                    continue;
                  }
                  $resolved_as_actor = FALSE;
                  foreach (['contact', 'entity', 'campaign_npc', 'npc_template', 'npc', 'hazard', 'creature', 'character', 'character_group'] as $actor_prefix) {
                    $actor_node_id = $actor_prefix . ':' . $actor_value;
                    if (!isset($nodes[$actor_node_id])) {
                      continue;
                    }
                    $objective_actor_touches[] = $actor_node_id;
                    $resolved_as_actor = TRUE;
                  }
                  if ($resolved_as_actor || $actor_field !== 'target') {
                    $objective_actor_ids[$actor_value] = TRUE;
                    $quest_actor_ids[$actor_value] = TRUE;
                  }
                }

                if ($objective_action !== '') {
                  $quest_action_details[] = $objective_action;
                }
                if ($objective_how_to_trigger !== '') {
                  $quest_how_triggers[] = $objective_id . ': ' . $objective_how_to_trigger;
                }
                if ($objective_completion_gate !== '') {
                  $quest_completion_gates[] = $objective_id . ': ' . $objective_completion_gate;
                }

                $add_step(
                  'Objective: ' . $objective_id,
                  $objective_action,
                  [
                    $objective_node_id,
                    $objective_node_id . ':action',
                    $objective_node_id . ':location',
                    $objective_node_id . ':completion-trigger',
                    ...$objective_actor_touches,
                  ],
                  $objective_completion_gate,
                  $objective_how_to_trigger,
                  implode(', ', array_keys($objective_location_values)),
                  implode(', ', array_keys($objective_actor_ids))
                );
              }
            }
          }

          $quest_location = implode(' / ', array_values(array_filter([$chapter_id, $scene_id], static fn(string $value): bool => trim($value) !== '')));
          $quest_detail = implode(' | ', array_values(array_unique(array_filter(array_map('strval', $quest_action_details), static fn(string $detail): bool => trim($detail) !== ''))));
          $quest_how_to_trigger = implode(' ; ', array_values(array_unique(array_filter(array_map('strval', $quest_how_triggers), static fn(string $detail): bool => trim($detail) !== ''))));
          $quest_progression_gate = implode(' ; ', array_values(array_unique(array_filter(array_map('strval', $quest_completion_gates), static fn(string $detail): bool => trim($detail) !== ''))));
          if ($quest_progression_gate === '' && $quest_objective_ids !== []) {
            $quest_progression_gate = 'Complete objectives: ' . implode(', ', array_keys($quest_objective_ids));
          }
          $add_step(
            'Quest: ' . $quest_id,
            $quest_detail,
            $quest_touches,
            $quest_progression_gate,
            $quest_how_to_trigger,
            $quest_location,
            implode(', ', array_keys($quest_actor_ids))
          );
        }
      }
    }

    foreach ($contacts as $contact) {
      $contact_id = trim((string) ($contact['contact_id'] ?? ''));
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($contact_id === '') {
        continue;
      }

      $contact_touches = [];
      if (isset($nodes['contact:' . $contact_id])) {
        $contact_touches[] = 'contact:' . $contact_id;
      }
      if ($entity_id !== '' && isset($nodes['entity:' . $entity_id])) {
        $contact_touches[] = 'entity:' . $entity_id;
      }

      $relationship_state = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
      $contact_location = implode(' / ', array_values(array_filter([
        trim((string) ($relationship_state['chapter_id'] ?? '')),
        trim((string) ($relationship_state['scene_id'] ?? '')),
      ], static fn(string $value): bool => trim($value) !== '')));
      $contact_how_to_trigger = trim((string) ($contact['how_to_trigger'] ?? ''));
      $contact_progression_gate = trim((string) ($contact['progression_gate'] ?? ''));

      $add_step(
        'Contact: ' . $contact_id,
        trim((string) ($contact['notes'] ?? '')),
        $contact_touches,
        $contact_progression_gate,
        $contact_how_to_trigger,
        $contact_location,
        $entity_id
      );
    }

    $diagram = $this->buildPlayerPartyMermaidDiagram($steps);
    $missing = array_values(array_filter(array_keys($nodes), static fn(string $node_id): bool => !isset($touched[$node_id])));

    return [
      'diagram' => $diagram,
      'steps' => $steps,
      'coverage' => [
        'touched' => count($touched),
        'total' => count($nodes),
        'missing' => $missing,
      ],
      'node_map' => $nodes,
    ];
  }

  /**
   * Build a player-party perspective Mermaid card.
   */
  protected function buildPlayerPartyPerspectiveCard(string $selected_template_id, array $projection): array {
    $diagram = (string) ($projection['diagram'] ?? 'graph LR' . PHP_EOL . '  PARTY["party: Player Party"]');
    $coverage = is_array($projection['coverage'] ?? NULL) ? $projection['coverage'] : [];
    $covered = (int) ($coverage['touched'] ?? 0);
    $total = (int) ($coverage['total'] ?? 0);
    $coverage_text = $total > 0
      ? "{$covered}/{$total} canonical nodes touched by happy path."
      : 'No canonical nodes available.';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-2']],
          '#value' => (string) $this->t('Player Party Journey Diagram (@template)', ['@template' => $selected_template_id !== '' ? $selected_template_id : 'none']),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted', 'mb-3']],
          '#value' => (string) $this->t('Linear happy-path projection from the player party perspective. @coverage', ['@coverage' => $coverage_text]),
        ],
        'diagram' => [
          '#markup' => '<div class="world-game-flow__mermaid-shell"><div class="world-game-flow__mermaid" data-mermaid-diagram>'
            . Html::escape($diagram)
            . '</div></div>',
        ],
      ],
    ];
  }

  /**
   * Build a happy-path story table with touched-node mapping.
   */
  protected function buildHappyPathStoryCard(string $selected_template_id, array $projection): array {
    $steps = array_values(array_filter(is_array($projection['steps'] ?? NULL) ? $projection['steps'] : [], 'is_array'));
    $node_map = is_array($projection['node_map'] ?? NULL) ? $projection['node_map'] : [];
    $rows = [];
    foreach ($steps as $index => $step) {
      $touches = array_values(array_filter(array_map('strval', is_array($step['touches'] ?? NULL) ? $step['touches'] : []), static fn(string $id): bool => trim($id) !== ''));
      $location = trim((string) ($step['location'] ?? ''));
      if ($location === '') {
        $location = $this->resolveHappyPathStepLocations($touches, $node_map);
      }
      $actors = trim((string) ($step['actors'] ?? ''));
      if ($actors === '') {
        $actors = $this->resolveHappyPathStepActors($touches, $node_map);
      }
      $how_to_trigger = trim((string) ($step['how_to_trigger'] ?? ''));
      $progression_gate = trim((string) ($step['progression_gate'] ?? ''));
      $rows[] = [
        (string) ($index + 1),
        (string) ($step['title'] ?? ''),
        (string) ($step['detail'] ?? ''),
        $how_to_trigger !== '' ? $how_to_trigger : '[not specified in data]',
        $progression_gate !== '' ? $progression_gate : '[not specified in data]',
        $location,
        $actors,
        $touches !== [] ? implode(', ', $touches) : '-',
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Happy Path Story (@template)', ['@template' => $selected_template_id !== '' ? $selected_template_id : 'none']),
        ],
        'table' => [
          '#type' => 'table',
          '#header' => ['Step', 'Player-party narrative', 'What happens', 'How (player trigger)', 'Progression gate', 'Location', 'Actors', 'Touched canonical nodes'],
          '#rows' => $rows,
          '#empty' => $this->t('No happy-path steps available.'),
        ],
      ],
    ];
  }

  /**
   * Resolve location display text for one happy-path step.
   *
   * @param array<int, string> $touches
   *   Touched node ids for the step.
   * @param array<string, array{type: string, label: string}> $node_map
   *   Canonical node map keyed by node id.
   */
  protected function resolveHappyPathStepLocations(array $touches, array $node_map): string {
    $locations = [];
    foreach ($touches as $node_id) {
      $node_id = trim((string) $node_id);
      if ($node_id === '' || !isset($node_map[$node_id])) {
        continue;
      }
      $type = trim((string) ($node_map[$node_id]['type'] ?? ''));
      if (!in_array($type, ['objective_location', 'room', 'location', 'scene', 'chapter', 'dungeon'], TRUE)) {
        continue;
      }
      $label = trim((string) ($node_map[$node_id]['label'] ?? $node_id));
      if ($label === '') {
        continue;
      }
      $locations[$label] = TRUE;
    }

    return $locations !== [] ? implode(', ', array_keys($locations)) : '-';
  }

  /**
   * Resolve actor display text for one happy-path step.
   *
   * @param array<int, string> $touches
   *   Touched node ids for the step.
   * @param array<string, array{type: string, label: string}> $node_map
   *   Canonical node map keyed by node id.
   */
  protected function resolveHappyPathStepActors(array $touches, array $node_map): string {
    $actors = [];
    foreach ($touches as $node_id) {
      $node_id = trim((string) $node_id);
      if ($node_id === '' || !isset($node_map[$node_id])) {
        continue;
      }
      $type = trim((string) ($node_map[$node_id]['type'] ?? ''));
      if (!in_array($type, ['contact', 'campaign_npc', 'npc_template', 'npc', 'entity', 'hazard', 'creature', 'character', 'character_group'], TRUE)) {
        continue;
      }
      $label = trim((string) ($node_map[$node_id]['label'] ?? $node_id));
      if ($label === '') {
        continue;
      }
      $actors[$label] = TRUE;
    }

    if ($actors === []) {
      return '-';
    }

    return implode(', ', array_keys($actors));
  }

  /**
   * Build a linear Mermaid journey flow from happy-path steps.
   *
   * @param array<int, array{title: string, detail: string, touches: array<int, string>}> $steps
   *   Happy-path steps.
   */
  protected function buildPlayerPartyMermaidDiagram(array $steps): string {
    if ($steps === []) {
      return 'graph LR' . PHP_EOL . '  PARTY["party: Player Party"]';
    }

    $lines = ['graph LR', '  PARTY["party: Player Party"]'];
    $previous = 'PARTY';
    foreach ($steps as $index => $step) {
      $alias = 'P' . ($index + 1);
      $label = trim((string) ($step['title'] ?? ('Step ' . ($index + 1))));
      $label = str_replace(['"', "\n", "\r"], ['\"', ' ', ''], ($index + 1) . '. ' . $label);
      $lines[] = sprintf('  %s["%s"]', $alias, $label);
      $lines[] = sprintf('  %s --> %s', $previous, $alias);
      $previous = $alias;
    }

    return implode(PHP_EOL, $lines);
  }

  /**
   * Build canonical storyline template summary table.
   */
  protected function buildCanonicalStorylinesTable(array $templates, string $selected_template_id): array {
    $rows = [];
    foreach ($templates as $template) {
      if (!is_array($template)) {
        continue;
      }
      $template_id = (string) ($template['template_id'] ?? '');
      if ($template_id === '') {
        continue;
      }
      $template_data = is_array($template['template_data'] ?? NULL) ? $template['template_data'] : [];
      $chapters = array_values(array_filter(is_array($template_data['chapters'] ?? NULL) ? $template_data['chapters'] : [], 'is_array'));
      $scene_count = 0;
      foreach ($chapters as $chapter) {
        $scene_count += count(array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array')));
      }
      $quest_count = count(array_values(array_filter(is_array($template_data['linked_quests'] ?? NULL) ? $template_data['linked_quests'] : [])));
      $rows[] = [
        $template_id,
        (string) ($template['name'] ?? $template_id),
        (string) count($chapters),
        (string) $scene_count,
        (string) $quest_count,
        $template_id === $selected_template_id ? (string) $this->t('Yes') : (string) $this->t('No'),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Storylines'),
        ],
        'table' => [
          '#type' => 'table',
          '#header' => ['Template ID', 'Name', 'Chapters', 'Scenes', 'Quest Links', 'Selected'],
          '#rows' => $rows,
          '#empty' => $this->t('No canonical storyline templates found.'),
        ],
      ],
    ];
  }

  /**
   * Build validation diagnostics for canonical storyline templates.
   */
  protected function buildValidationSummaryCard(string $selected_template_id, array $template_data, array $graph): array {
    $diagnostics = $this->collectValidationDiagnostics($template_data, $graph);
    $validator_rows = [];
    foreach ($diagnostics['validator_errors'] as $error) {
      $validator_rows[] = [(string) $error];
    }
    $graph_rows = [];
    foreach ($diagnostics['graph_errors'] as $error) {
      $graph_rows[] = [(string) $error];
    }

    $validator_status = $diagnostics['validator_status'];
    $status_badge = $validator_status === 'pass'
      ? 'text-bg-success'
      : ($validator_status === 'fail' ? 'text-bg-danger' : 'text-bg-secondary');
    $validator_label = strtoupper($validator_status);

    // Per-stage summary rows.
    $summary_rows = [
      ['Template', $selected_template_id !== '' ? $selected_template_id : 'none'],
      ['Validator (all stages)', Markup::create('<span class="badge ' . $status_badge . '">' . Html::escape($validator_label) . '</span>')],
      ['Graph contracts', $diagnostics['graph_errors'] === [] ? 'PASS' : 'FAIL'],
      ['Node count', (string) count($graph['nodes'] ?? [])],
      ['Edge count', (string) count($graph['edges'] ?? [])],
    ];
    foreach (($diagnostics['stages'] ?? []) as $stage_name => $stage) {
      $stage_pass = $stage['valid'] ?? FALSE;
      $badge_class = $stage_pass ? 'text-bg-success' : 'text-bg-danger';
      $stage_label = $stage_pass ? 'PASS' : 'FAIL (' . count($stage['errors'] ?? []) . ')';
      $summary_rows[] = [
        Html::escape($stage_name),
        Markup::create('<span class="badge ' . $badge_class . '">' . Html::escape($stage_label) . '</span>'),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Storyline Validator Diagnostics'),
        ],
        'summary' => [
          '#type' => 'table',
          '#header' => ['Check', 'Result'],
          '#rows' => $summary_rows,
        ],
        'validator_errors_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
          '#value' => (string) $this->t('Validator errors'),
        ],
        'validator_errors' => [
          '#type' => 'table',
          '#header' => ['Error'],
          '#rows' => $validator_rows,
          '#empty' => $this->t('No validator errors.'),
        ],
        'graph_errors_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['class' => ['h6', 'mt-3', 'mb-2']],
          '#value' => (string) $this->t('Graph and Mermaid contract errors'),
        ],
        'graph_errors' => [
          '#type' => 'table',
          '#header' => ['Error'],
          '#rows' => $graph_rows,
          '#empty' => $this->t('No graph contract errors.'),
        ],
      ],
    ];
  }

  /**
   * Collect validator and structural graph diagnostics.
   *
   * Returns stage-by-stage results for all 7 validator stages plus graph checks.
   *
   * @return array{
   *   validator_status: string,
   *   validator_errors: array<int, string>,
   *   stages: array<string, array{valid: bool, errors: array<int, string>}>,
   *   graph_errors: array<int, string>
   * }
   */
  protected function collectValidationDiagnostics(array $template_data, array $graph): array {
    $validator_status = 'unavailable';
    $validator_errors = [];
    $stages = [];

    if ($this->storylineManager instanceof StorylineManagerService) {
      $validator_status = 'pass';
      try {
        $normalized = $this->storylineManager->normalizeStorylineDefinition($template_data);
        $result = $this->storylineManager->validateNormalizedStorylineDefinition($normalized);
        $stages = $result['stages'] ?? [];
        if (!($result['valid'] ?? FALSE)) {
          $validator_status = 'fail';
          foreach (($result['errors'] ?? []) as $error) {
            $validator_errors[] = (string) $error;
          }
        }

        // Stage 6 — task contract (via StorylineGenerationService if available).
        $task_errors = $this->collectTaskContractDiagnostics($normalized);
        $stages['task_contract'] = [
          'valid' => $task_errors === [],
          'errors' => $task_errors,
        ];
        if ($task_errors !== []) {
          $validator_status = 'fail';
          foreach ($task_errors as $error) {
            $validator_errors[] = '[task_contract] ' . $error;
          }
        }

        // Stage 7 — entity linkage (via StorylineGenerationService if available).
        $entity_errors = $this->collectEntityLinkageDiagnostics($normalized);
        $stages['entity_linkage'] = [
          'valid' => $entity_errors === [],
          'errors' => $entity_errors,
        ];
        if ($entity_errors !== []) {
          $validator_status = 'fail';
          foreach ($entity_errors as $error) {
            $validator_errors[] = '[entity_linkage] ' . $error;
          }
        }
      }
      catch (\Throwable $e) {
        $validator_status = 'fail';
        $validator_errors[] = $e->getMessage();
      }
    }

    $graph_errors = [];
    $template_id = trim((string) ($template_data['template_id'] ?? ''));
    if ($template_id === '') {
      $graph_errors[] = 'template_id is required.';
    }
    $chapters = array_values(array_filter(is_array($template_data['chapters'] ?? NULL) ? $template_data['chapters'] : [], 'is_array'));
    if ($chapters === []) {
      $graph_errors[] = 'At least one chapter is required.';
    }

    foreach ($chapters as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id === '') {
        $graph_errors[] = 'A chapter is missing chapter_id.';
      }
      $scenes = array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array'));
      if ($scenes === []) {
        $graph_errors[] = sprintf('Chapter "%s" has no scenes.', $chapter_id !== '' ? $chapter_id : 'unknown');
      }
      foreach ($scenes as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id === '') {
          $graph_errors[] = sprintf('Chapter "%s" contains a scene without scene_id.', $chapter_id !== '' ? $chapter_id : 'unknown');
        }
      }
    }

    $nodes = is_array($graph['nodes'] ?? NULL) ? $graph['nodes'] : [];
    $edges = is_array($graph['edges'] ?? NULL) ? $graph['edges'] : [];
    foreach ($edges as $edge) {
      $from = (string) ($edge['from'] ?? '');
      $to = (string) ($edge['to'] ?? '');
      if ($from === '' || !isset($nodes[$from])) {
        $graph_errors[] = sprintf('Edge source node missing or unknown: "%s".', $from);
      }
      if ($to === '' || !isset($nodes[$to])) {
        $graph_errors[] = sprintf('Edge target node missing or unknown: "%s".', $to);
      }
    }

    return [
      'validator_status' => $validator_status,
      'validator_errors' => array_values(array_unique($validator_errors)),
      'stages' => $stages,
      'graph_errors' => array_values(array_unique($graph_errors)),
    ];
  }

  /**
   * Stage 6 — collect task contract errors for the Explorer diagnostic view.
   *
   * Loads DB quest template objective phases for each quest referenced by the
   * storyline and validates task (child) contracts. Returns errors only; does
   * not throw.
   *
   * @return array<int, string>
   */
  protected function collectTaskContractDiagnostics(array $template_data): array {
    if (!($this->storylineManager instanceof StorylineManagerService)) {
      return [];
    }
    $errors = [];
    $player_interaction_types = ['interact', 'collect', 'explore', 'escort', 'investigate', 'kill'];
    $supports_children_types = ['composite', 'escort'];

    foreach ((array) ($template_data['chapters'] ?? []) as $chapter) {
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        foreach ((array) ($scene['quest_ids'] ?? []) as $quest_id) {
          $quest_id = trim((string) $quest_id);
          if ($quest_id === '') {
            continue;
          }
          try {
            $phases = $this->storylineManager->getCanonicalQuestTemplateObjectivePhases($quest_id);
          }
          catch (\Throwable $e) {
            $errors[] = "quest.{$quest_id}: failed to load objective phases — " . $e->getMessage();
            continue;
          }
          if ($phases === NULL || $phases === []) {
            continue;
          }

          foreach ($phases as $phase_index => $phase) {
            if (!is_array($phase)) {
              continue;
            }
            foreach ((array) ($phase['objectives'] ?? []) as $obj_index => $objective) {
              if (!is_array($objective)) {
                continue;
              }
              $children = is_array($objective['children'] ?? NULL) ? $objective['children'] : [];
              if ($children === []) {
                continue;
              }
              $obj_type = strtolower(trim((string) ($objective['type'] ?? '')));
              $obj_path = "quest.{$quest_id}.phase[{$phase_index}].objective[{$obj_index}]";
              if (!in_array($obj_type, $supports_children_types, TRUE)) {
                $errors[] = "{$obj_path}: type '{$obj_type}' does not support children";
                continue;
              }
              $criteria_kind = strtolower(trim((string) ($objective['completion_criteria']['kind'] ?? '')));
              if ($criteria_kind !== 'all_children') {
                $errors[] = "{$obj_path}: objective with children must use completion_criteria.kind=all_children";
              }
              foreach ($children as $task_index => $task) {
                if (!is_array($task)) {
                  continue;
                }
                $task_path = "{$obj_path}.children[{$task_index}]";
                if (trim((string) ($task['objective_id'] ?? '')) === '') {
                  $errors[] = "{$task_path}: objective_id (task_id) is required";
                }
                if (trim((string) ($task['description'] ?? '')) === '') {
                  $errors[] = "{$task_path}: description is required";
                }
                if (!is_array($task['completion_criteria'] ?? NULL)) {
                  $errors[] = "{$task_path}: completion_criteria is required";
                }
                $task_type = strtolower(trim((string) ($task['type'] ?? '')));
                if (in_array($task_type, $player_interaction_types, TRUE) && trim((string) ($task['next_step'] ?? '')) === '') {
                  $errors[] = "{$task_path}: next_step is required for player-interaction task type '{$task_type}'";
                }
              }
            }
          }
        }
      }
    }
    return array_values(array_unique($errors));
  }

  /**
   * Stage 7 — collect entity linkage errors for the Explorer diagnostic view.
   *
   * Builds entity registry from the template definition and validates refs in
   * DB quest template objectives. Returns errors only; does not throw.
   *
   * @return array<int, string>
   */
  protected function collectEntityLinkageDiagnostics(array $template_data): array {
    if (!($this->storylineManager instanceof StorylineManagerService)) {
      return [];
    }

    // Build entity registry from storyline definition.
    $actors = [];
    $locations = [];
    $items = [];

    foreach ((array) ($template_data['contacts'] ?? []) as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $actors[$entity_id] = TRUE;
      }
    }
    foreach ((array) ($template_data['asset_references'] ?? []) as $ref) {
      $asset_id = trim((string) ($ref['asset_id'] ?? ''));
      if ($asset_id === '') {
        continue;
      }
      $asset_type = strtolower(trim((string) ($ref['asset_type'] ?? '')));
      if ($asset_type === 'npc') {
        $actors[$asset_id] = TRUE;
      }
      elseif (in_array($asset_type, ['room', 'location'], TRUE)) {
        $locations[$asset_id] = TRUE;
      }
      elseif ($asset_type === 'item') {
        $items[$asset_id] = TRUE;
      }
    }
    $outline = is_array($template_data['metadata']['generated_outline'] ?? NULL) ? $template_data['metadata']['generated_outline'] : [];
    $big_boss_id = trim((string) ($outline['big_boss']['boss_id'] ?? ''));
    if ($big_boss_id !== '') {
      $actors[$big_boss_id] = TRUE;
    }
    foreach ((array) ($outline['sub_bosses'] ?? []) as $boss) {
      $boss_id = trim((string) ($boss['boss_id'] ?? ''));
      if ($boss_id !== '') {
        $actors[$boss_id] = TRUE;
      }
    }
    foreach ((array) ($template_data['chapters'] ?? []) as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      if ($chapter_id !== '') {
        $locations[$chapter_id] = TRUE;
      }
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id !== '') {
          $locations[$scene_id] = TRUE;
        }
      }
    }

    $actor_target_types = ['kill', 'interact', 'investigate', 'escort'];
    $errors = [];

    foreach ((array) ($template_data['chapters'] ?? []) as $chapter) {
      foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
        foreach ((array) ($scene['quest_ids'] ?? []) as $quest_id) {
          $quest_id = trim((string) $quest_id);
          if ($quest_id === '') {
            continue;
          }
          try {
            $phases = $this->storylineManager->getCanonicalQuestTemplateObjectivePhases($quest_id);
          }
          catch (\Throwable $e) {
            continue;
          }
          if ($phases === NULL || $phases === []) {
            continue;
          }
          foreach ($phases as $phase_index => $phase) {
            if (!is_array($phase)) {
              continue;
            }
            foreach ((array) ($phase['objectives'] ?? []) as $obj_index => $objective) {
              if (!is_array($objective)) {
                continue;
              }
              $obj_path = "quest.{$quest_id}.phase[{$phase_index}].objective[{$obj_index}]";
              $obj_type = strtolower(trim((string) ($objective['type'] ?? '')));
              $target = trim((string) ($objective['target'] ?? ''));
              if ($target !== '' && in_array($obj_type, $actor_target_types, TRUE) && !isset($actors[$target])) {
                $errors[] = "{$obj_path}: target '{$target}' not in entity registry";
              }
              foreach (['location', 'location_id', 'destination', 'destination_id'] as $field) {
                $ref = trim((string) ($objective[$field] ?? ''));
                if ($ref !== '' && !isset($locations[$ref])) {
                  $errors[] = "{$obj_path}: {$field} '{$ref}' not in entity registry";
                }
              }
              $item_ref = trim((string) ($objective['item'] ?? ''));
              if ($item_ref !== '' && !isset($items[$item_ref])) {
                $errors[] = "{$obj_path}: item '{$item_ref}' not in entity registry";
              }
              foreach ((array) ($objective['children'] ?? []) as $task_index => $task) {
                if (!is_array($task)) {
                  continue;
                }
                $task_path = "{$obj_path}.children[{$task_index}]";
                $task_type = strtolower(trim((string) ($task['type'] ?? '')));
                $task_target = trim((string) ($task['target'] ?? ''));
                if ($task_target !== '' && in_array($task_type, $actor_target_types, TRUE) && !isset($actors[$task_target])) {
                  $errors[] = "{$task_path}: target '{$task_target}' not in entity registry";
                }
                foreach (['location', 'location_id', 'destination', 'destination_id'] as $field) {
                  $ref = trim((string) ($task[$field] ?? ''));
                  if ($ref !== '' && !isset($locations[$ref])) {
                    $errors[] = "{$task_path}: {$field} '{$ref}' not in entity registry";
                  }
                }
                $task_item = trim((string) ($task['item'] ?? ''));
                if ($task_item !== '' && !isset($items[$task_item])) {
                  $errors[] = "{$task_path}: item '{$task_item}' not in entity registry";
                }
              }
            }
          }
        }
      }
    }
    return array_values(array_unique($errors));
  }

  /**
   * Build canonical process-flow table for chapter/scene/quest progression.
   */
  protected function buildCanonicalProcessFlowTable(string $selected_template_id, array $template_data): array {
    $rows = [];
    $step = 1;
    $chapters = array_values(array_filter(is_array($template_data['chapters'] ?? NULL) ? $template_data['chapters'] : [], 'is_array'));
    foreach ($chapters as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      $scenes = array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array'));
      foreach ($scenes as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        $quest_ids = array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : [])));
        $rows[] = [
          (string) $step++,
          $chapter_id,
          $scene_id,
          $quest_ids !== [] ? implode(', ', $quest_ids) : '-',
        ];
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Canonical Process Flow (@template)', ['@template' => $selected_template_id !== '' ? $selected_template_id : 'none']),
        ],
        'table' => [
          '#type' => 'table',
          '#header' => ['Step', 'Chapter', 'Scene', 'Scene quest_ids'],
          '#rows' => $rows,
          '#empty' => $this->t('No chapter/scene flow is defined for this template.'),
        ],
      ],
    ];
  }

  /**
   * Build a collapsible parent/child trace of the full storyline flow.
   */
  protected function buildStorylineTraceAccordion(string $selected_template_id, array $template_data): array {
    $normalized = $template_data;
    $normalization_error = '';
    if ($this->storylineManager instanceof StorylineManagerService) {
      try {
        $normalized = $this->storylineManager->normalizeStorylineDefinition($template_data);
      }
      catch (\Throwable $e) {
        $normalization_error = $e->getMessage();
      }
    }

    $chapters = array_values(array_filter(is_array($normalized['chapters'] ?? NULL) ? $normalized['chapters'] : [], 'is_array'));
    $linked_quests = $this->extractLinkedQuestMap($normalized);
    $scene_quest_ids = $this->collectSceneQuestIds($normalized);
    $all_quest_ids = array_values(array_unique(array_merge(array_keys($linked_quests), $scene_quest_ids)));
    sort($all_quest_ids);

    $quest_phase_map = [];
    $quest_phase_errors = [];
    foreach ($all_quest_ids as $quest_id) {
      if (!($this->storylineManager instanceof StorylineManagerService)) {
        $quest_phase_errors[$quest_id] = 'Storyline manager service unavailable for objective lookup.';
        continue;
      }
      try {
        $quest_phase_map[$quest_id] = $this->storylineManager->getCanonicalQuestTemplateObjectivePhases($quest_id);
      }
      catch (\Throwable $e) {
        $quest_phase_errors[$quest_id] = $e->getMessage();
      }
    }

    $scene_count = 0;
    foreach ($chapters as $chapter) {
      $scene_count += count(array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array')));
    }

    $chapter_nodes = [];
    foreach ($chapters as $chapter) {
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      $chapter_name = trim((string) ($chapter['name'] ?? ''));
      $chapter_label = $chapter_name !== '' ? $chapter_name : $chapter_id;
      if ($chapter_label === '') {
        $chapter_label = 'Unnamed chapter';
      }

      $scene_nodes = [];
      $scenes = array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array'));
      foreach ($scenes as $scene) {
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        $scene_name = trim((string) ($scene['name'] ?? ''));
        $scene_label = $scene_name !== '' ? $scene_name : $scene_id;
        if ($scene_label === '') {
          $scene_label = 'Unnamed scene';
        }

        $scene_quests = array_values(array_filter(array_map('strval', is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []), static fn(string $id): bool => trim($id) !== ''));
        $quest_nodes = [];
        foreach ($scene_quests as $quest_id) {
          $quest_id = trim($quest_id);
          if ($quest_id === '') {
            continue;
          }
          $quest_nodes[] = $this->buildQuestTraceNode(
            $quest_id,
            $linked_quests[$quest_id] ?? [],
            $quest_phase_map[$quest_id] ?? NULL,
            $quest_phase_errors[$quest_id] ?? ''
          );
        }
        if ($quest_nodes === []) {
          $quest_nodes[] = '<div class="text-muted small mb-2">No quests linked to this scene.</div>';
        }

        $scene_nodes[] = $this->renderTraceDetails(
          "Scene: {$scene_label}",
          [
            $this->buildTraceKeyValueTable([
              'scene_id' => $scene_id,
              'name' => $scene_name,
              'quest_count' => count($scene_quests),
            ]),
            $this->renderTraceDetails('Quest flow', $quest_nodes, FALSE),
          ],
          FALSE
        );
      }
      if ($scene_nodes === []) {
        $scene_nodes[] = '<div class="text-muted small mb-2">No scenes configured.</div>';
      }

      $chapter_nodes[] = $this->renderTraceDetails(
        "Chapter: {$chapter_label}",
        [
          $this->buildTraceKeyValueTable([
            'chapter_id' => $chapter_id,
            'name' => $chapter_name,
            'scene_count' => count($scenes),
          ]),
          $this->renderTraceDetails('Scenes', $scene_nodes, FALSE),
        ],
        FALSE
      );
    }
    if ($chapter_nodes === []) {
      $chapter_nodes[] = '<div class="text-muted small mb-2">No chapters configured.</div>';
    }

    $linked_quest_nodes = [];
    foreach ($all_quest_ids as $quest_id) {
      $linked_quest_nodes[] = $this->buildQuestTraceNode(
        $quest_id,
        $linked_quests[$quest_id] ?? [],
        $quest_phase_map[$quest_id] ?? NULL,
        $quest_phase_errors[$quest_id] ?? ''
      );
    }
    if ($linked_quest_nodes === []) {
      $linked_quest_nodes[] = '<div class="text-muted small mb-2">No linked quests configured.</div>';
    }

    $contact_nodes = [];
    $contacts = array_values(array_filter(is_array($normalized['contacts'] ?? NULL) ? $normalized['contacts'] : [], 'is_array'));
    foreach ($contacts as $contact) {
      $contact_label = trim((string) ($contact['display_name'] ?? ''));
      if ($contact_label === '') {
        $contact_label = trim((string) ($contact['contact_id'] ?? ''));
      }
      if ($contact_label === '') {
        $contact_label = 'Unnamed contact';
      }
      $contact_nodes[] = $this->renderTraceDetails(
        "Contact: {$contact_label}",
        [
          $this->buildTraceKeyValueTable([
            'contact_id' => (string) ($contact['contact_id'] ?? ''),
            'entity_id' => (string) ($contact['entity_id'] ?? ''),
            'entity_type' => (string) ($contact['entity_type'] ?? ''),
            'role' => (string) ($contact['role'] ?? ''),
            'chapter_id' => (string) ($contact['chapter_id'] ?? ''),
            'scene_id' => (string) ($contact['scene_id'] ?? ''),
            'availability' => $contact['availability'] ?? '',
            'relationship_state' => $contact['relationship_state'] ?? '',
            'introduces_to' => $contact['introduces_to'] ?? [],
          ]),
        ],
        FALSE
      );
    }
    if ($contact_nodes === []) {
      $contact_nodes[] = '<div class="text-muted small mb-2">No contacts configured.</div>';
    }

    $asset_nodes = [];
    $assets = array_values(array_filter(is_array($normalized['asset_references'] ?? NULL) ? $normalized['asset_references'] : [], 'is_array'));
    foreach ($assets as $asset) {
      $asset_id = trim((string) ($asset['asset_id'] ?? ''));
      $asset_type = trim((string) ($asset['asset_type'] ?? 'asset'));
      $asset_label = $asset_id !== '' ? "{$asset_type}: {$asset_id}" : 'Unnamed asset';
      $asset_nodes[] = $this->renderTraceDetails(
        $asset_label,
        [
          $this->buildTraceKeyValueTable([
            'asset_id' => $asset_id,
            'asset_type' => $asset_type,
            'asset_role' => (string) ($asset['asset_role'] ?? ''),
            'chapter_id' => (string) ($asset['chapter_id'] ?? ''),
            'scene_id' => (string) ($asset['scene_id'] ?? ''),
            'source_scope' => $asset['source_scope'] ?? '',
            'notes' => $asset['notes'] ?? '',
            'link_data' => $asset['link_data'] ?? [],
          ]),
        ],
        FALSE
      );
    }
    if ($asset_nodes === []) {
      $asset_nodes[] = '<div class="text-muted small mb-2">No asset references configured.</div>';
    }

    $root_children = [];
    if ($normalization_error !== '') {
      $root_children[] = '<div class="alert alert-warning py-2 px-3 mb-2">'
        . Html::escape('Normalization warning: ' . $normalization_error)
        . '</div>';
    }
    $root_children[] = $this->buildTraceKeyValueTable([
      'template_id' => (string) ($normalized['template_id'] ?? $selected_template_id),
      'name' => (string) ($normalized['name'] ?? ''),
      'schema_version' => (string) ($normalized['schema_version'] ?? ''),
      'storyline_type' => (string) ($normalized['storyline_type'] ?? ''),
      'level_range' => (string) ($normalized['level_range'] ?? ''),
      'chapter_count' => count($chapters),
      'scene_count' => $scene_count,
      'quest_count' => count($all_quest_ids),
      'tags' => $normalized['tags'] ?? [],
    ]);
    $root_children[] = $this->renderTraceDetails('Chapter → Scene → Quest process flow', $chapter_nodes, TRUE);
    $root_children[] = $this->renderTraceDetails('Linked quest registry', $linked_quest_nodes, FALSE);
    $root_children[] = $this->renderTraceDetails('Contacts', $contact_nodes, FALSE);
    $root_children[] = $this->renderTraceDetails('Asset references', $asset_nodes, FALSE);

    $summary = $selected_template_id !== '' ? "Storyline Trace: {$selected_template_id}" : 'Storyline Trace';
    $trace_markup = $this->renderTraceDetails($summary, $root_children, TRUE);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => (string) $this->t('Storyline Accordion Trace'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted', 'mb-3']],
          '#value' => (string) $this->t('Trace the complete parent/child storyline flow from chapters and scenes to DB-authoritative quest objective phases.'),
        ],
        'trace' => [
          '#markup' => Markup::create($trace_markup),
        ],
      ],
    ];
  }

  /**
   * Extract linked quest metadata indexed by quest id.
   *
   * @return array<string, array<string, mixed>>
   *   Linked quest map keyed by quest id.
   */
  protected function extractLinkedQuestMap(array $template_data): array {
    $linked_quests = [];
    $raw = is_array($template_data['linked_quests'] ?? NULL) ? $template_data['linked_quests'] : [];
    foreach ($raw as $entry_key => $entry) {
      if (is_string($entry) && trim($entry) !== '') {
        $linked_quests[trim($entry)] = ['quest_id' => trim($entry)];
        continue;
      }
      if (!is_array($entry)) {
        continue;
      }
      $quest_id = trim((string) ($entry['quest_id'] ?? (is_string($entry_key) ? $entry_key : '')));
      if ($quest_id === '') {
        continue;
      }
      $linked_quests[$quest_id] = $entry + ['quest_id' => $quest_id];
    }
    return $linked_quests;
  }

  /**
   * Collect quest ids attached to chapter scenes.
   *
   * @return array<int, string>
   *   Unique quest ids.
   */
  protected function collectSceneQuestIds(array $template_data): array {
    $quest_ids = [];
    $chapters = array_values(array_filter(is_array($template_data['chapters'] ?? NULL) ? $template_data['chapters'] : [], 'is_array'));
    foreach ($chapters as $chapter) {
      $scenes = array_values(array_filter(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [], 'is_array'));
      foreach ($scenes as $scene) {
        foreach ((is_array($scene['quest_ids'] ?? NULL) ? $scene['quest_ids'] : []) as $quest_id) {
          $quest_id = trim((string) $quest_id);
          if ($quest_id === '') {
            continue;
          }
          $quest_ids[$quest_id] = $quest_id;
        }
      }
    }
    return array_values($quest_ids);
  }

  /**
   * Build one quest trace node including DB-backed objective-phase detail.
   */
  protected function buildQuestTraceNode(string $quest_id, array $quest_meta, ?array $objective_phases, string $error_message = ''): string {
    $children = [
      $this->buildTraceKeyValueTable([
        'quest_id' => $quest_id,
        'status' => (string) ($quest_meta['status'] ?? ''),
        'chapter_id' => (string) ($quest_meta['chapter_id'] ?? ''),
        'scene_id' => (string) ($quest_meta['scene_id'] ?? ''),
      ]),
    ];

    if ($error_message !== '') {
      $children[] = '<div class="alert alert-danger py-2 px-3 mb-2">'
        . Html::escape($error_message)
        . '</div>';
    }
    elseif ($objective_phases === NULL) {
      $children[] = '<div class="text-warning small mb-2">'
        . Html::escape("No canonical quest-template row found for '{$quest_id}' in dungeoncrawler_content_quest_templates.")
        . '</div>';
    }
    elseif ($objective_phases === []) {
      $children[] = '<div class="text-warning small mb-2">'
        . Html::escape("Quest '{$quest_id}' has an empty objectives_schema payload.")
        . '</div>';
    }
    else {
      $phase_nodes = [];
      foreach (array_values($objective_phases) as $phase_index => $phase) {
        if (!is_array($phase)) {
          continue;
        }
        $phase_label = trim((string) ($phase['phase'] ?? ''));
        if ($phase_label === '') {
          $phase_label = 'phase-' . ($phase_index + 1);
        }
        $objective_nodes = [];
        $objectives = array_values(array_filter(is_array($phase['objectives'] ?? NULL) ? $phase['objectives'] : [], 'is_array'));
        foreach ($objectives as $objective) {
          $objective_id = trim((string) ($objective['objective_id'] ?? ''));
          if ($objective_id === '') {
            $objective_id = 'unnamed-objective';
          }
          $action = $this->resolveObjectiveAction($objective);
          $location = $this->resolveObjectiveLocation($objective, $quest_meta);
          $completion_trigger = $this->resolveObjectiveCompletionTrigger($objective);
          $how_to_trigger = $this->resolveObjectiveHowToTrigger($objective, $quest_meta);
          $objective_nodes[] = $this->renderTraceDetails(
            "Objective: {$objective_id}",
            [
              $this->buildTraceKeyValueTable([
                'objective_id' => $objective_id,
                'action' => $action,
                'location' => $location,
                'completion_trigger' => $completion_trigger,
                'how_to_trigger' => $how_to_trigger,
                'type' => (string) ($objective['type'] ?? ''),
                'description' => (string) ($objective['description'] ?? ''),
                'target' => $objective['target'] ?? '',
                'item' => $objective['item'] ?? '',
                'target_count' => $objective['target_count'] ?? '',
                'location_field' => $objective['location'] ?? '',
                'location_id' => $objective['location_id'] ?? '',
                'destination' => $objective['destination'] ?? '',
                'destination_id' => $objective['destination_id'] ?? '',
                'item_id' => $objective['item_id'] ?? '',
                'depends_on' => $objective['depends_on'] ?? [],
                'unlocks_to' => $objective['unlocks_to'] ?? [],
                'next_step' => $objective['next_step'] ?? '',
                'completion_criteria' => $objective['completion_criteria'] ?? [],
              ]),
            ],
            FALSE
          );
        }
        if ($objective_nodes === []) {
          $objective_nodes[] = '<div class="text-muted small mb-2">No objectives listed in this phase.</div>';
        }

        $phase_nodes[] = $this->renderTraceDetails(
          'Phase: ' . $phase_label,
          [
            $this->buildTraceKeyValueTable([
              'phase' => $phase_label,
              'objective_count' => count($objectives),
            ]),
            $this->renderTraceDetails('Objectives', $objective_nodes, FALSE),
          ],
          FALSE
        );
      }

      if ($phase_nodes === []) {
        $phase_nodes[] = '<div class="text-muted small mb-2">No objective phases available.</div>';
      }
      $children[] = $this->renderTraceDetails('Objective phases (DB source of truth)', $phase_nodes, FALSE);
    }

    return $this->renderTraceDetails("Quest: {$quest_id}", $children, FALSE);
  }

  /**
   * Resolve a user-facing objective action summary.
   */
  protected function resolveObjectiveAction(array $objective): string {
    $type = strtolower(trim((string) ($objective['type'] ?? '')));
    $description = trim((string) ($objective['description'] ?? ''));
    $target = trim((string) (
      $objective['target']
      ?? $objective['item']
      ?? $objective['item_id']
      ?? $objective['location_id']
      ?? $objective['destination_id']
      ?? $objective['location']
      ?? $objective['destination']
      ?? ''
    ));

    $verb = match ($type) {
      'investigate' => 'Investigate',
      'explore' => 'Explore',
      'interact' => 'Interact',
      'collect' => 'Collect',
      'kill' => 'Defeat',
      'escort' => 'Escort',
      'composite' => 'Complete child objectives for',
      default => $type !== '' ? ucfirst($type) : 'Complete',
    };

    if ($target !== '' && $description !== '') {
      return "{$verb} {$target} — {$description}";
    }
    if ($target !== '') {
      return "{$verb} {$target}";
    }
    if ($description !== '') {
      return "{$verb} — {$description}";
    }

    return $verb;
  }

  /**
   * Resolve a user-facing location summary for an objective.
   */
  protected function resolveObjectiveLocation(array $objective, array $quest_meta): string {
    foreach (['location_id', 'destination_id', 'location', 'destination'] as $field) {
      $value = trim((string) ($objective[$field] ?? ''));
      if ($value !== '') {
        return $value;
      }
    }

    $chapter_id = trim((string) ($quest_meta['chapter_id'] ?? ''));
    $scene_id = trim((string) ($quest_meta['scene_id'] ?? ''));
    if ($chapter_id !== '' && $scene_id !== '') {
      return "{$chapter_id} / {$scene_id}";
    }
    if ($scene_id !== '') {
      return $scene_id;
    }
    if ($chapter_id !== '') {
      return $chapter_id;
    }

    return 'unspecified';
  }

  /**
   * Resolve a readable completion trigger for an objective.
   */
  protected function resolveObjectiveCompletionTrigger(array $objective): string {
    $criteria = is_array($objective['completion_criteria'] ?? NULL) ? $objective['completion_criteria'] : [];
    $criteria_description = trim((string) ($criteria['description'] ?? ''));
    $kind = strtolower(trim((string) ($criteria['kind'] ?? '')));
    $metric = trim((string) ($criteria['metric'] ?? ''));

    $rule = '';
    switch ($kind) {
      case 'count':
        $target_count = max(1, (int) ($criteria['target_count'] ?? $objective['target_count'] ?? 1));
        $metric_label = $metric !== '' ? $metric : 'progress';
        $rule = "{$metric_label} >= {$target_count}";
        break;

      case 'all_children':
        $rule = 'all child objectives complete';
        break;

      case 'flag':
      default:
        $metric_label = $metric !== '' ? $metric : 'completed';
        $required_value = array_key_exists('required_value', $criteria)
          ? ((bool) $criteria['required_value'] ? 'true' : 'false')
          : 'true';
        $rule = "{$metric_label} == {$required_value}";
        break;
    }

    if ($criteria_description !== '') {
      return "{$criteria_description} ({$rule})";
    }

    return $rule !== '' ? $rule : 'completed == true';
  }

  /**
   * Resolve how the player triggers one objective action.
   */
  protected function resolveObjectiveHowToTrigger(array $objective, array $quest_meta = []): string {
    $next_step = trim((string) ($objective['next_step'] ?? ''));
    if ($next_step !== '') {
      return $next_step;
    }

    $type = strtolower(trim((string) ($objective['type'] ?? '')));
    $target = trim((string) (
      $objective['target']
      ?? $objective['item']
      ?? $objective['item_id']
      ?? $objective['destination']
      ?? $objective['destination_id']
      ?? ''
    ));
    $location = $this->resolveObjectiveLocation($objective, $quest_meta);

    return match ($type) {
      'investigate' => $target !== ''
        ? "Use investigation/search interactions on {$target} at {$location}."
        : "Use investigation/search interactions at {$location}.",
      'explore' => "Use movement/navigation to reach {$location}.",
      'interact' => $target !== ''
        ? "Use Interact on {$target} at {$location}."
        : "Use Interact at {$location}.",
      'collect' => $target !== ''
        ? "Use Search/Loot and collect {$target} at {$location}."
        : "Use Search/Loot actions at {$location}.",
      'kill' => $target !== ''
        ? "Initiate combat and defeat {$target}."
        : 'Initiate combat and defeat the encounter target.',
      'escort' => $target !== ''
        ? "Start escort flow for {$target} and travel to {$location}."
        : "Start escort flow and travel to {$location}.",
      default => "Perform the objective action at {$location}.",
    };
  }

  /**
   * Render one trace details node.
   */
  protected function renderTraceDetails(string $summary, array $children, bool $open = FALSE): string {
    $content = implode('', array_values(array_filter($children, static fn($value): bool => is_string($value) && $value !== '')));
    if ($content === '') {
      $content = '<div class="text-muted small mb-2">No data.</div>';
    }
    return '<details class="border rounded p-2 mb-2"' . ($open ? ' open' : '') . '>'
      . '<summary class="fw-semibold">' . Html::escape($summary) . '</summary>'
      . '<div class="mt-2 ms-2">' . $content . '</div>'
      . '</details>';
  }

  /**
   * Render a compact key/value table for trace nodes.
   */
  protected function buildTraceKeyValueTable(array $rows): string {
    $pairs = [];
    foreach ($rows as $label => $value) {
      if ($value === NULL || $value === '' || $value === []) {
        continue;
      }
      $pairs[] = '<tr><th class="pe-3 text-nowrap align-top">'
        . Html::escape((string) $label)
        . '</th><td>'
        . $this->renderTraceValue($value)
        . '</td></tr>';
    }
    if ($pairs === []) {
      return '';
    }
    return '<table class="table table-sm table-borderless mb-2"><tbody>'
      . implode('', $pairs)
      . '</tbody></table>';
  }

  /**
   * Render trace table values with safe string/JSON formatting.
   */
  protected function renderTraceValue(mixed $value): string {
    if (is_bool($value)) {
      return Html::escape($value ? 'true' : 'false');
    }
    if (is_scalar($value)) {
      return Html::escape((string) $value);
    }
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json) || $json === '') {
      return Html::escape((string) $this->t('[unrenderable]'));
    }
    return '<pre class="small mb-0">' . Html::escape($json) . '</pre>';
  }

  /**
   * Convert nodes/edges into Mermaid graph syntax.
   */
  protected function buildMermaidDiagram(array $nodes, array $edges): string {
    if ($nodes === []) {
      return 'graph TD' . PHP_EOL . '  A["No storyline template selected"]';
    }

    $aliases = [];
    $index = 1;
    foreach (array_keys($nodes) as $node_id) {
      $aliases[$node_id] = 'N' . $index++;
    }

    $lines = ['graph TD'];
    foreach ($nodes as $node_id => $node) {
      $label = trim((string) ($node['label'] ?? $node_id));
      $type = trim((string) ($node['type'] ?? 'node'));
      $escaped = str_replace(['"', "\n", "\r"], ['\"', ' ', ''], "{$type}: {$label}");
      $lines[] = sprintf('  %s["%s"]', $aliases[$node_id], $escaped);
    }
    foreach ($edges as $edge) {
      $from = (string) ($edge['from'] ?? '');
      $to = (string) ($edge['to'] ?? '');
      if (!isset($aliases[$from], $aliases[$to])) {
        continue;
      }
      $relation = str_replace(['"', "\n", "\r"], ['\"', ' ', ''], (string) ($edge['relation'] ?? 'linked_to'));
      $lines[] = sprintf('  %s -->|%s| %s', $aliases[$from], $relation, $aliases[$to]);
    }

    return implode(PHP_EOL, $lines);
  }

  /**
   * Build the graph nodes table.
   */
  protected function buildNodesTable(array $nodes, string $title = 'Nodes'): array {
    $rows = [];
    foreach ($nodes as $node_id => $node) {
      $rows[] = [
        $node_id,
        (string) ($node['type'] ?? ''),
        (string) ($node['label'] ?? ''),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => $title,
        ],
        'table' => [
          '#type' => 'table',
          '#header' => ['Node ID', 'Type', 'Label'],
          '#rows' => $rows,
          '#empty' => $this->t('No nodes available.'),
        ],
      ],
    ];
  }

  /**
   * Build the graph edges table.
   */
  protected function buildEdgesTable(array $edges, string $title = 'Edges'): array {
    $rows = [];
    foreach ($edges as $edge) {
      $rows[] = [
        (string) ($edge['from'] ?? ''),
        (string) ($edge['relation'] ?? ''),
        (string) ($edge['to'] ?? ''),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card', 'mb-4']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['h5', 'mb-3']],
          '#value' => $title,
        ],
        'table' => [
          '#type' => 'table',
          '#header' => ['From', 'Relation', 'To'],
          '#rows' => $rows,
          '#empty' => $this->t('No edges available.'),
        ],
      ],
    ];
  }

}
