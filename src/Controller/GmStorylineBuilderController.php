<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consolidated Game Master storyline builder and process map page.
 */
class GmStorylineBuilderController extends ControllerBase {

  public function __construct(
    protected StorylineManagerService $storylineManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.storyline_manager'),
    );
  }

  /**
   * Render the GM storyline builder process map.
   */
  public function page(): array {
    $initial_fields = $this->loadInitialWizardFields();
    $build = [
      '#type' => 'container',
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/character-setup-tabs',
          'dungeoncrawler_content/gm-storyline-builder',
        ],
        'drupalSettings' => [
          'dungeoncrawlerGmStorylineBuilder' => [
            'initialFields' => $initial_fields,
            'assistant' => [
              'describeUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.storyline_builder_gm_describe', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
              'executeUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.storyline_builder_gm_execute', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
            ],
            'drafts' => [
              'createUrl' => Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_create')->toString(),
              'listUrl' => Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_list')->toString(),
              'loadUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_get', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
              'revisionsUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_revision_list', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
              'revisionUrlTemplate' => str_replace(
                ['00000000-0000-4000-8000-000000000000', '/999999'],
                ['{draft_id}', '/{revision}'],
                Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_revision_get', [
                  'draft_id' => '00000000-0000-4000-8000-000000000000',
                  'revision' => 999999,
                ])->toString()
              ),
              'saveUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_save', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
              'copyUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_copy', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
              'deleteUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_delete', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
            ],
            'publish' => [
              'publishUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_publish', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
              'validateUrlTemplate' => str_replace('00000000-0000-4000-8000-000000000000', '{draft_id}', Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_validate', ['draft_id' => '00000000-0000-4000-8000-000000000000'])->toString()),
            ],
            'objectEditors' => [
              'actor' => $this->editorUrlTemplate(
                'dungeoncrawler_content.actor_view',
                ['actor_id' => 'storyline-object-placeholder'],
                'storyline-object-placeholder'
              ),
              'item' => $this->editorUrlTemplate(
                'dungeoncrawler_content.definition_edit',
                ['family' => 'item', 'definition_id' => 'storyline-object-placeholder'],
                'storyline-object-placeholder'
              ),
              'room' => $this->editorUrlTemplate(
                'dungeoncrawler_content.room_editor_edit',
                ['room_id' => 'storyline-object-placeholder'],
                'storyline-object-placeholder'
              ),
              'dungeon' => $this->editorUrlTemplate(
                'dungeoncrawler_content.dungeon_editor_edit',
                ['dungeon_id' => 'storyline-object-placeholder'],
                'storyline-object-placeholder'
              ),
            ],
          ],
        ],
      ],
      '#attributes' => [
        'class' => ['character-setup-page'],
      ],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__header']],
    ];
    $build['header']['copy'] = [
      '#markup' => '<p class="character-setup-page__eyebrow">' . $this->t('Game master workspace') . '</p>'
        . '<h1 class="character-setup-page__title">' . $this->t('GM Storyline Builder') . '</h1>'
        . '<p class="character-setup-page__intro">' . $this->t('Build storylines with one consolidated process flow showing available generation and validation tools at each stage.') . '</p>',
    ];
    $build['header']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Storyline Manager'),
      '#url' => Url::fromRoute('dungeoncrawler_content.storyline_management'),
      '#attributes' => ['class' => ['btn', 'btn-secondary', 'character-setup-page__back']],
    ];

    $build['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__layout']],
    ];

    $build['layout']['sidebar'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__sidebar']],
    ];
    $build['layout']['sidebar']['stack'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__sidebar-stack']],
    ];
    $build['layout']['sidebar']['stack']['flow'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__sidebar-card']],
    ];
    $build['layout']['sidebar']['stack']['flow']['title'] = [
      '#markup' => '<h2 class="character-setup-page__sidebar-title">' . $this->t('Storyline process stages') . '</h2>',
    ];
    $build['layout']['sidebar']['stack']['flow']['steps'] = [
      '#type' => 'inline_template',
      '#template' => '
        <div class="character-setup-page__tabs" data-gm-storyline-step-tabs>
          <button type="button" class="character-setup-page__tab is-active" data-step-tab="1" title="Define the storyline identity, premise, player hook, entry point, level range, and intended ending."><span class="character-setup-page__tab-number">1</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Title, Description, Premise</span><span class="character-setup-page__tab-description">Core identity and narrative premise.</span></span></button>
          <button type="button" class="character-setup-page__tab" data-step-tab="2" title="Build chapters, scenes, tasks, objectives, conflict arcs, relationship changes, and progression dependencies."><span class="character-setup-page__tab-number">2</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Journal/Task Objective Flow</span><span class="character-setup-page__tab-description">Completion criteria and progression links.</span></span></button>
          <button type="button" class="character-setup-page__tab" data-step-tab="3" title="Connect canonical actors to scenes, roles, motivations, dialogue, knowledge, attitudes, and locations."><span class="character-setup-page__tab-number">3</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Actors Framework</span><span class="character-setup-page__tab-description">Generate/validate actors and role bindings.</span></span></button>
          <button type="button" class="character-setup-page__tab" data-step-tab="4" title="Associate independent dungeon and room objects with chapters and scenes without conflating their identities."><span class="character-setup-page__tab-number">4</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Dungeons & Rooms Framework</span><span class="character-setup-page__tab-description">Generate/validate traversal spaces.</span></span></button>
          <button type="button" class="character-setup-page__tab" data-step-tab="5" title="Connect canonical items to scenes, quests, actors, acquisition requirements, prerequisites, and rewards."><span class="character-setup-page__tab-number">5</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Items Framework</span><span class="character-setup-page__tab-description">Generate/validate rewards and dependencies.</span></span></button>
          <button type="button" class="character-setup-page__tab" data-step-tab="6" title="Generate the canonical definition, run structural and narrative validation, and inspect conflicts or continuity findings."><span class="character-setup-page__tab-number">6</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Composition & Validation</span><span class="character-setup-page__tab-description">Assemble canonical storyline contract.</span></span></button>
          <button type="button" class="character-setup-page__tab" data-step-tab="7" title="Review the complete generated storyline object before canonical creation or publication."><span class="character-setup-page__tab-number">7</span><span class="character-setup-page__tab-copy"><span class="character-setup-page__tab-name">Review Blueprint</span><span class="character-setup-page__tab-description">Generated storyline blueprint draft.</span></span></button>
        </div>',
    ];
    $build['layout']['sidebar']['stack']['status'] = [
      '#type' => 'inline_template',
      '#template' => '
        <section class="character-setup-page__sidebar-card gm-storyline-status" data-storyline-status aria-labelledby="storyline-status-title">
          <h2 id="storyline-status-title" class="character-setup-page__sidebar-title">Current state</h2>
          <div class="gm-storyline-status__score"><strong data-storyline-status-percent>0%</strong><span>complete</span></div>
          <progress data-storyline-status-progress max="100" value="0">0%</progress>
          <dl>
            <div><dt>Validity</dt><dd data-storyline-status-validity>No draft</dd></div>
            <div><dt>Current focus</dt><dd data-storyline-status-focus>Stage 1</dd></div>
            <div><dt>Objects</dt><dd data-storyline-status-counts>0 scenes · 0 actors · 0 rooms</dd></div>
          </dl>
          <ol class="gm-storyline-status__stages" data-storyline-status-stages></ol>
          <p class="gm-storyline-status__label"><strong>Minimum content still needed</strong></p>
          <ul data-storyline-status-missing><li>Create or load a draft.</li></ul>
        </section>',
    ];
    $build['layout']['sidebar']['stack']['quick_links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__sidebar-card']],
    ];
    $build['layout']['sidebar']['stack']['quick_links']['title'] = [
      '#markup' => '<h2 class="character-setup-page__sidebar-title">' . $this->t('Quick launch') . '</h2>',
    ];
    $quick_links = array_values(array_filter([
      $this->routeLinkIfAccessible('Storyline Explorer', 'dungeoncrawler_content.storyline_explorer'),
      $this->routeLinkIfAccessible('Analysis Explorer Hub', 'dungeoncrawler_content.analysis_explorer_home'),
      $this->routeLinkIfAccessible('Dungeon Editor', 'dungeoncrawler_content.dungeon_editor'),
      $this->routeLinkIfAccessible('Room Editor', 'dungeoncrawler_content.room_editor'),
      $this->routeLinkIfAccessible('Actor Editor', 'dungeoncrawler_content.definition_family', ['family' => 'actor']),
      $this->routeLinkIfAccessible('Item Editor', 'dungeoncrawler_content.definition_family', ['family' => 'item']),
      $this->routeLinkIfAccessible('Creature Editor', 'dungeoncrawler_content.definition_family', ['family' => 'creature']),
      $this->routeLinkIfAccessible('Obstacle Editor', 'dungeoncrawler_content.definition_family', ['family' => 'obstacle']),
      $this->routeLinkIfAccessible('Trap Editor', 'dungeoncrawler_content.definition_family', ['family' => 'trap']),
      $this->routeLinkIfAccessible('Hazard Editor', 'dungeoncrawler_content.definition_family', ['family' => 'hazard']),
      $this->routeLinkIfAccessible('My Campaigns', 'dungeoncrawler_content.campaigns'),
      $this->routeLinkIfAccessible('Create Campaign', 'dungeoncrawler_content.campaign_create'),
      $this->routeLinkIfAccessible('Archived Characters', 'dungeoncrawler_content.characters_archived'),
    ]));
    $build['layout']['sidebar']['stack']['quick_links']['items'] = [
      '#theme' => 'item_list',
      '#items' => $quick_links,
      '#empty' => $this->t('No additional quick-launch links are available for your role.'),
      '#attributes' => ['class' => ['character-setup-page__quick-links']],
    ];
    $build['layout']['sidebar']['stack']['quick_links']['object_title'] = [
      '#markup' => '<h3 class="character-setup-page__sidebar-title">' . $this->t('Storyline objects') . '</h3>',
    ];
    $build['layout']['sidebar']['stack']['quick_links']['object_items'] = [
      '#markup' => '<ul class="character-setup-page__quick-links" data-storyline-object-editor-links><li>Add or load storyline objects to open their exact editors.</li></ul>',
    ];

    $build['layout']['workspace'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__workspace']],
    ];
    $build['layout']['workspace']['panel_header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__panel-header']],
    ];
    $build['layout']['workspace']['panel_header']['copy'] = [
      '#markup' => '<p class="character-setup-page__panel-label">' . $this->t('Primary authoring interface') . '</p>'
        . '<h2 class="character-setup-page__panel-title">' . $this->t('Game Master Storyline Agent') . '</h2>',
    ];
    $build['layout']['workspace']['panel_header']['meta'] = [
      '#markup' => '<p class="character-setup-page__panel-meta">' . $this->t('Guided creation with structured data controls below') . '</p>',
    ];
    $build['layout']['workspace']['frame'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['character-setup-page__frame-shell']],
    ];
    $build['layout']['workspace']['frame']['wizard'] = [
      '#type' => 'inline_template',
      '#template' => $this->buildWizardMarkup(),
    ];

    return $build;
  }

  /**
   * Build initial wizard field values from template_id query param.
   */
  private function loadInitialWizardFields(): array {
    $template_id = trim((string) \Drupal::request()->query->get('template_id', ''));
    if ($template_id === '') {
      return [];
    }

    $template = $this->storylineManager->getTemplate($template_id);
    if ($template === NULL) {
      return [];
    }

    $data = is_array($template['template_data'] ?? NULL) ? $template['template_data'] : [];
    $process_flow = is_array($data['process_flow'] ?? NULL) ? $data['process_flow'] : [];
    $objects = is_array($data['objects'] ?? NULL) ? $data['objects'] : [];
    $actors = is_array($objects['actors'] ?? NULL) ? $objects['actors'] : [];
    $dungeons = is_array($objects['dungeons'] ?? NULL) ? $objects['dungeons'] : [];
    $rooms = is_array($objects['rooms'] ?? NULL) ? $objects['rooms'] : [];
    $items = is_array($objects['items'] ?? NULL) ? $objects['items'] : [];
    $orchestration = is_array($data['orchestration'] ?? NULL) ? $data['orchestration'] : [];
    $chapters = is_array($data['chapters'] ?? NULL) ? $data['chapters'] : [];
    $linked_quests = is_array($data['linked_quests'] ?? NULL) ? $data['linked_quests'] : [];
    $contacts = is_array($data['contacts'] ?? NULL) ? $data['contacts'] : [];
    $asset_references = is_array($data['asset_references'] ?? NULL) ? $data['asset_references'] : [];
    $metadata = is_array($data['metadata'] ?? NULL) ? $data['metadata'] : [];
    $identity = is_array($metadata['storyline_builder']['identity'] ?? NULL) ? $metadata['storyline_builder']['identity'] : [];
    $entry_point = is_array($metadata['generated_outline']['entry_point'] ?? NULL) ? $metadata['generated_outline']['entry_point'] : [];

    $journal_flow_rows = [];
    $room_flow_rows = [];
    $dungeon_flow_rows = [];
    foreach ($chapters as $chapter_index => $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      $chapter_name = trim((string) ($chapter['name'] ?? $chapter_id));
      if ($chapter_id !== '') {
        $dungeon_flow_rows[] = $chapter_id . ' | ' . ($chapter_name !== '' ? $chapter_name : 'chapter');
      }
      $scenes = is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : [];
      foreach ($scenes as $scene_index => $scene) {
        if (!is_array($scene)) {
          continue;
        }
        $order = (string) ($scene['order'] ?? ($scene_index + 1));
        $scene_name = trim((string) ($scene['name'] ?? $scene['scene_id'] ?? 'Scene'));
        $gate = is_array($scene['gates'][0] ?? NULL) ? $scene['gates'][0] : [];
        $trigger = trim((string) ($gate['trigger'] ?? ''));
        $journal_flow_rows[] = $order . ' | ' . $scene_name . ' | ' . $trigger;
        $asset_refs = is_array($scene['asset_references'] ?? NULL) ? $scene['asset_references'] : [];
        foreach ($asset_refs as $ref) {
          if (!is_array($ref)) {
            continue;
          }
          if (strtolower(trim((string) ($ref['asset_type'] ?? ''))) !== 'room') {
            continue;
          }
          $room_id = trim((string) ($ref['asset_id'] ?? ''));
          if ($room_id === '') {
            continue;
          }
          $room_flow_rows[] = $chapter_id . ' | ' . $room_id . ' | ' . $scene_name;
        }
      }
    }

    $task_rows = [];
    foreach ($linked_quests as $quest_id => $quest) {
      if (!is_array($quest)) {
        continue;
      }
      $owner = trim((string) ($quest['owner'] ?? 'party'));
      $requirement = trim((string) ($quest['description'] ?? $quest['goal'] ?? $quest['summary'] ?? ''));
      $task_rows[] = trim((string) $quest_id) . ' | ' . $owner . ' | ' . $requirement;
    }

    $actor_rows = [];
    foreach ($contacts as $contact) {
      if (!is_array($contact)) {
        continue;
      }
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id === '') {
        continue;
      }
      $role = trim((string) ($contact['role'] ?? 'contact'));
      $notes = trim((string) ($contact['notes'] ?? ''));
      $actor_rows[] = $entity_id . ' | ' . $role . ' | ' . $notes;
    }

    $item_rows = [];
    foreach ($asset_references as $reference) {
      if (!is_array($reference)) {
        continue;
      }
      $asset_type = strtolower(trim((string) ($reference['asset_type'] ?? '')));
      if ($asset_type !== 'item') {
        continue;
      }
      $item_id = trim((string) ($reference['asset_id'] ?? ''));
      if ($item_id === '') {
        continue;
      }
      $role = trim((string) ($reference['asset_role'] ?? 'storyline-item'));
      $notes = trim((string) ($reference['notes'] ?? ''));
      $item_rows[] = $item_id . ' | ' . $role . ' | ' . $notes;
    }

    return [
      'storyline_id' => (string) ($data['template_id'] ?? $template_id),
      'storyline_title' => (string) ($data['name'] ?? $template['name'] ?? $template_id),
      'storyline_description' => (string) ($data['description'] ?? $data['synopsis'] ?? $template['synopsis'] ?? ''),
      'storyline_premise' => (string) ($data['premise'] ?? $data['goal'] ?? $metadata['goal'] ?? ''),
      'storyline_level_range' => (string) ($data['level_range'] ?? ''),
      'storyline_tags' => implode(', ', array_values(array_filter((array) ($data['tags'] ?? []), 'is_string'))),
      'storyline_player_hook' => (string) ($identity['player_hook'] ?? ''),
      'storyline_entry_actor' => (string) ($entry_point['primary_quest_giver_id'] ?? ''),
      'storyline_entry_location' => (string) ($entry_point['primary_location_id'] ?? ''),
      'storyline_ending' => (string) ($identity['ending'] ?? ''),
      'journal_flow' => $this->stringifyMultiline($process_flow['journal_entries'] ?? $journal_flow_rows),
      'task_flow' => $this->stringifyMultiline($process_flow['tasks'] ?? $task_rows),
      'objective_criteria' => $this->stringifyMultiline($process_flow['objective_completion_criteria'] ?? []),
      'actor_specs' => $this->stringifyMultiline($actors['generation_specs'] ?? $data['supporting_actors'] ?? $actor_rows),
      'actor_validation' => $this->stringifyMultiline($actors['validation_criteria'] ?? []),
      'dungeon_specs' => $this->stringifyMultiline($dungeons['generation_specs'] ?? $data['dungeons'] ?? $dungeon_flow_rows),
      'room_specs' => $this->stringifyMultiline($rooms['generation_specs'] ?? $data['rooms'] ?? $room_flow_rows),
      'room_validation' => $this->stringifyMultiline($rooms['validation_criteria'] ?? []),
      'item_specs' => $this->stringifyMultiline($items['generation_specs'] ?? $data['items'] ?? $item_rows),
      'item_validation' => $this->stringifyMultiline($items['validation_criteria'] ?? []),
      'generation_orchestration' => $this->stringifyMultiline($orchestration['generation'] ?? []),
      'validation_orchestration' => $this->stringifyMultiline($orchestration['validation'] ?? []),
    ];
  }

  /**
   * Normalize lists/objects/strings into newline-separated text.
   */
  private function stringifyMultiline(mixed $value): string {
    if (is_string($value)) {
      return trim($value);
    }
    if (!is_array($value)) {
      return '';
    }
    $lines = [];
    foreach ($value as $entry) {
      if (is_scalar($entry)) {
        $line = trim((string) $entry);
      }
      elseif (is_array($entry)) {
        $line = trim(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
      }
      else {
        $line = '';
      }
      if ($line !== '') {
        $lines[] = $line;
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Build a route link string.
   */
  private function routeLink(string $label, string $route_name): array {
    return Link::fromTextAndUrl($this->t($label), Url::fromRoute($route_name))->toRenderable();
  }

  /**
   * Build route link when route is accessible for current user.
   */
  private function routeLinkIfAccessible(string $label, string $route_name, array $route_params = []): ?array {
    $url = Url::fromRoute($route_name, $route_params);
    if (!$url->access($this->currentUser())) {
      return NULL;
    }
    return Link::fromTextAndUrl($this->t($label), $url)->toRenderable();
  }

  /**
   * Build an object editor URL template only when its route is accessible.
   */
  private function editorUrlTemplate(string $route_name, array $route_params, string $placeholder): string {
    $url = Url::fromRoute($route_name, $route_params);
    if (!$url->access($this->currentUser())) {
      return '';
    }
    return str_replace($placeholder, '{object_id}', $url->toString());
  }

  /**
   * Build wizard markup for storyline authoring flow.
   */
  private function buildWizardMarkup(): string {
    $editor_suite = '';
    $storyline_explorer = $this->buildToolLinkIfAccessible('Storyline Explorer', 'dungeoncrawler_content.storyline_explorer');
    $actor_library = $this->buildToolLinkIfAccessible('Actor Library', 'dungeoncrawler_content.definition_family', ['family' => 'actor']);
    $item_library = $this->buildToolLinkIfAccessible('Item Library', 'dungeoncrawler_content.definition_family', ['family' => 'item']);
    $room_editor = $this->buildToolLinkIfAccessible('Room Editor', 'dungeoncrawler_content.room_editor');
    $dungeon_editor = $this->buildToolLinkIfAccessible('Dungeon Editor', 'dungeoncrawler_content.dungeon_editor');

    return <<<HTML
      <div class="gm-storyline-wizard" data-gm-storyline-wizard>
        <section class="gm-storyline-wizard__assistant-panel gm-storyline-wizard__assistant-panel--primary" data-gm-assistant-panel aria-labelledby="gm-storyline-assistant-title">
          <div class="gm-storyline-wizard__assistant-header">
            <div>
              <p class="gm-storyline-wizard__assistant-eyebrow">Primary storyline interface</p>
              <h3 id="gm-storyline-assistant-title">Game Master Agent</h3>
              <p>Describe the storyline you want, ask for critique, or request structured changes. Clear requests update the draft immediately and remain editable.</p>
            </div>
          </div>
          <div class="gm-storyline-wizard__assistant-prompts" aria-label="Suggested requests">
            <button type="button" class="btn btn-secondary btn-sm" data-gm-assistant-prompt="Help me create a new storyline. Ask me for the most important missing decisions one at a time.">Create a storyline</button>
            <button type="button" class="btn btn-secondary btn-sm" data-gm-assistant-prompt="Review the current storyline object and identify all incomplete or contradictory data.">Review current work</button>
            <button type="button" class="btn btn-secondary btn-sm" data-gm-assistant-prompt="Propose a complete progression structure with chapters, scenes, quests, objectives, actors, rooms, and items for the current premise.">Build progression</button>
          </div>
          <h4 class="gm-storyline-wizard__assistant-log-title">GM conversation</h4>
          <ul class="gm-storyline-wizard__assistant-log" data-gm-assistant-log aria-live="polite" aria-relevant="additions">
            <li class="gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--assistant gm-storyline-wizard__assistant-item--welcome">
              <strong>Welcome to the Storyline Builder. Let’s build a complete, connected story together.</strong>
              <span>Building a strong storyline is hard. It requires a coherent premise, meaningful progression, consistent characters and relationships, connected locations and objects, and an ending that follows from the choices players make.</span>
              <span>I’m your Game Master Agent. I will apply clear requests directly to the draft and finish each task with concrete next steps. If an object-creation request is vague, I will present exactly three different options instead of choosing for you.</span>
              <span><strong>Explore the interface:</strong> hover over the numbered sections in the left sidebar to learn what each stage controls. The structured fields below this conversation let you inspect and refine the exact storyline data.</span>
            </li>
            <li class="gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--status" data-gm-assistant-status role="status" aria-live="polite">Connecting to the Game Master Agent…</li>
          </ul>
          <div class="gm-storyline-wizard__assistant-composer">
            <label for="gm-storyline-assistant-input">Ask the Game Master Agent</label>
            <textarea id="gm-storyline-assistant-input" class="form-textarea" rows="4" data-gm-assistant-input placeholder="Describe the storyline, ask for an analysis, or request a structured change."></textarea>
            <div class="gm-storyline-wizard__assistant-composer-actions">
              <span>Enter to send · Shift + Enter for a new line</span>
              <button type="button" class="btn btn-primary" data-gm-assistant-send>Send to GM</button>
            </div>
          </div>
        </section>

        <div class="gm-storyline-wizard__top-actions">
          <label class="visually-hidden" for="gm-storyline-shared-draft">Load storyline</label>
          <select id="gm-storyline-shared-draft" class="form-select" data-gm-draft-picker><option value="">Load a storyline…</option></select>
          <span class="gm-storyline-wizard__draft-count" data-gm-draft-count>0 drafts</span>
          <label class="visually-hidden" for="gm-storyline-draft-revision">View draft revision</label>
          <select id="gm-storyline-draft-revision" class="form-select" data-gm-draft-revision disabled><option value="">Current revision</option></select>
          <button type="button" class="btn btn-secondary" data-gm-draft-create>Create draft</button>
          <button type="button" class="btn btn-primary" data-gm-draft-save>Save draft</button>
          <button type="button" class="btn btn-danger" data-gm-draft-delete disabled>Delete draft</button>
          <span class="gm-storyline-wizard__draft-status" data-gm-draft-status role="status" aria-live="polite">No draft loaded</span>
        </div>
        <div class="gm-storyline-wizard__structured-header">
          <div>
            <p class="gm-storyline-wizard__structured-eyebrow">Structured authoring, validation, and review</p>
            <h3 data-gm-storyline-active-step-title>Step 1 · Title, Description, Premise</h3>
          </div>
          <p data-gm-storyline-active-step-meta>Step 1 of 7</p>
        </div>
        <section class="gm-storyline-wizard__step" data-step-panel="1">
          <h3>Step 1 · Title, Description, Premise</h3>
          <p>Define the canonical storyline identity and narrative premise.</p>
          <div class="gm-storyline-wizard__tools"><strong>Canonical output:</strong> identity fields become the public storyline definition and are validated before creation.</div>
          <div class="gm-storyline-wizard__link-chips">{$storyline_explorer} {$editor_suite}</div>
          <label>Storyline ID <input type="text" class="form-text" data-field="storyline_id" placeholder="island-ward-crisis-v1"></label>
          <label>Title <input type="text" class="form-text" data-field="storyline_title" placeholder="The Wardfall Crisis"></label>
          <label>Description <textarea class="form-textarea" rows="3" data-field="storyline_description" placeholder="Public-facing summary for this storyline."></textarea></label>
          <label>Premise <textarea class="form-textarea" rows="4" data-field="storyline_premise" placeholder="Inciting situation, core conflict, and expected player role."></textarea></label>
          <label>Level range <input type="text" class="form-text" data-field="storyline_level_range" placeholder="1-4"></label>
          <label>Tags <input type="text" class="form-text" data-field="storyline_tags" placeholder="mystery, urban, political"></label>
          <label>Player hook <textarea class="form-textarea" rows="2" data-field="storyline_player_hook" placeholder="What players know and why they engage."></textarea></label>
          <label>Entry actor <input type="text" class="form-text" data-field="storyline_entry_actor" placeholder="Canonical actor ID"></label>
          <label>Entry location <input type="text" class="form-text" data-field="storyline_entry_location" placeholder="Canonical location ID"></label>
          <label>Ending <textarea class="form-textarea" rows="2" data-field="storyline_ending" placeholder="Public-facing completion state and outcome."></textarea></label>
        </section>

        <section class="gm-storyline-wizard__step" data-step-panel="2" hidden>
          <h3>Step 2 · Journal, Tasks, Objectives, Completion Criteria</h3>
          <p>Define the adventure narrative architecture, then map it into runtime scenes, conflicts, tasks, objectives, and relationship changes.</p>
          <div class="gm-storyline-wizard__tools"><strong>Canonical output:</strong> narrative beats, adventure requirements, chapters, scenes, quest dependencies, completion criteria, and conflicts.</div>
          <div class="gm-storyline-wizard__link-chips">{$editor_suite}</div>
          <details class="gm-storyline-wizard__collection gm-storyline-wizard__narrative-section" open>
            <summary>Narrative architecture · six required beats</summary>
            <p>All six beats are required. Describe both parts of each beat; the scene and object records below remain the canonical implementation of those beats.</p>
            <div class="gm-storyline-wizard__fields">
              <label>Inciting incident: disruption <textarea class="form-textarea" rows="3" data-field="narrative_inciting_disruption" placeholder="What shatters the characters' status quo?"></textarea></label>
              <label>Inciting incident: stakes <textarea class="form-textarea" rows="3" data-field="narrative_inciting_stakes" placeholder="Why must the characters answer the call?"></textarea></label>
              <label>Threshold crossing <textarea class="form-textarea" rows="3" data-field="narrative_threshold_crossing" placeholder="How do the characters enter the special world?"></textarea></label>
              <label>Point of no return <textarea class="form-textarea" rows="3" data-field="narrative_point_of_no_return" placeholder="What makes retreat impossible or unacceptable?"></textarea></label>
              <label>Road of trials <textarea class="form-textarea" rows="3" data-field="narrative_road_of_trials" placeholder="How do obstacles escalate and test the party?"></textarea></label>
              <label>Midpoint shift <textarea class="form-textarea" rows="3" data-field="narrative_midpoint_shift" placeholder="What revelation, reversal, or false victory changes the quest?"></textarea></label>
              <label>All hope is lost <textarea class="form-textarea" rows="3" data-field="narrative_dark_night" placeholder="How does the plan fail at the story's lowest point?"></textarea></label>
              <label>Psychological pivot <textarea class="form-textarea" rows="3" data-field="narrative_psychological_pivot" placeholder="What must the characters understand or change to continue?"></textarea></label>
              <label>Central confrontation <textarea class="form-textarea" rows="3" data-field="narrative_central_confrontation" placeholder="What is the final emotional and physical ordeal?"></textarea></label>
              <label>Resolution of the premise <textarea class="form-textarea" rows="3" data-field="narrative_premise_resolution" placeholder="How is the central dramatic question answered?"></textarea></label>
              <label>New equilibrium <textarea class="form-textarea" rows="3" data-field="narrative_new_equilibrium" placeholder="What world exists after the climax?"></textarea></label>
              <label>Internal shift <textarea class="form-textarea" rows="3" data-field="narrative_internal_shift" placeholder="How are the characters permanently changed?"></textarea></label>
            </div>
          </details>
          <details class="gm-storyline-wizard__collection gm-storyline-wizard__narrative-section">
            <summary>Adventure genre requirements</summary>
            <p>Name the concrete engines that make this an adventure rather than only a sequence of events.</p>
            <div class="gm-storyline-wizard__fields">
              <label>Defined physical quest <textarea class="form-textarea" rows="3" data-field="adventure_physical_quest" placeholder="What spatial journey drives the plot from point A to point B?"></textarea></label>
              <label>Antagonistic force <textarea class="form-textarea" rows="3" data-field="adventure_antagonistic_force" placeholder="Who or what actively opposes the journey?"></textarea></label>
              <label>Mentor or guide <textarea class="form-textarea" rows="3" data-field="adventure_mentor_guide" placeholder="Who supplies the initial knowledge, map, or tool?"></textarea></label>
              <label>Boon or MacGuffin <textarea class="form-textarea" rows="3" data-field="adventure_boon_macguffin" placeholder="What tangible object of desire drives the quest?"></textarea></label>
            </details>
            <h4 class="gm-storyline-wizard__section-title">Scenes, conflicts, and relationships</h4>
            <div class="gm-storyline-wizard__collection" data-collection="journal_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Journal entries</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="journal_entries">Add entry</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="journal_entries"></div>
            <template data-row-template="journal_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--analysis">
              <input type="text" class="form-text" placeholder="Chapter ID" aria-label="Chapter ID" data-col-field="chapter_id">
              <input type="text" class="form-text" placeholder="Scene ID" aria-label="Scene ID" data-col-field="scene_id">
              <input type="number" class="form-text" min="1" placeholder="Order" data-col-field="order">
              <input type="text" class="form-text" placeholder="Entry title" data-col-field="title">
              <input type="text" class="form-text" placeholder="Scene summary" aria-label="Scene summary" data-col-field="summary">
              <input type="text" class="form-text" placeholder="Reveal trigger" data-col-field="trigger">
              <input type="text" class="form-text" placeholder="Room IDs (comma separated)" aria-label="Scene room IDs" data-col-field="room_ids">
              <input type="text" class="form-text" placeholder="Central conflict" aria-label="Central conflict" data-col-field="central_conflict">
              <input type="text" class="form-text" placeholder="Dramatic function" aria-label="Dramatic function" data-col-field="dramatic_function">
              <input type="text" class="form-text" placeholder="Themes (comma separated)" aria-label="Themes" data-col-field="themes">
              <input type="text" class="form-text" placeholder="Actor IDs (comma separated)" aria-label="Actor IDs" data-col-field="character_ids">
              <input type="text" class="form-text" placeholder="Foreshadows scene ID" aria-label="Foreshadows scene ID" data-col-field="foreshadows_scene_id">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="conflict_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Conflict arcs</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="conflict_entries">Add conflict</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="conflict_entries"></div>
            <template data-row-template="conflict_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--analysis">
              <input type="text" class="form-text" placeholder="Conflict ID" aria-label="Conflict ID" data-col-field="conflict_id">
              <input type="text" class="form-text" placeholder="Conflict title" aria-label="Conflict title" data-col-field="title">
              <input type="text" class="form-text" placeholder="Actor IDs (comma separated)" aria-label="Conflict actor IDs" data-col-field="actor_ids">
              <input type="text" class="form-text" placeholder="Introduced scene ID" aria-label="Conflict introduced scene ID" data-col-field="introduced_scene_id">
              <input type="text" class="form-text" placeholder="Escalation scene IDs (comma separated)" aria-label="Conflict escalation scene IDs" data-col-field="escalation_scene_ids">
              <input type="text" class="form-text" placeholder="Resolved scene ID" aria-label="Conflict resolved scene ID" data-col-field="resolved_scene_id">
              <select aria-label="Conflict status" data-col-field="status"><option value="active">Active</option><option value="resolved">Resolved</option><option value="intentional_open_thread">Intentional open thread</option></select>
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="relationship_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Relationship changes</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="relationship_entries">Add relationship change</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="relationship_entries"></div>
            <template data-row-template="relationship_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--analysis">
              <input type="text" class="form-text" placeholder="Scene ID" aria-label="Relationship change scene ID" data-col-field="scene_id">
              <input type="text" class="form-text" placeholder="Source actor ID" aria-label="Relationship source actor ID" data-col-field="source_actor_id">
              <input type="text" class="form-text" placeholder="Target actor ID" aria-label="Relationship target actor ID" data-col-field="target_actor_id">
              <input type="number" class="form-number" min="-100" max="100" placeholder="Before" aria-label="Relationship score before" data-col-field="before">
              <input type="number" class="form-number" min="-100" max="100" placeholder="After" aria-label="Relationship score after" data-col-field="after">
              <input type="text" class="form-text" placeholder="Reason for change" aria-label="Relationship change reason" data-col-field="reason">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="task_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Tasks and scene placement</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="task_entries">Add task</button></div>
            <p>Task order supplies the default linear path. Authored decisions below replace outgoing edges with conditional choices.</p>
            <div class="gm-storyline-wizard__rows" data-rows="task_entries"></div>
            <template data-row-template="task_entries"><div class="gm-storyline-wizard__row">
              <input type="text" class="form-text" placeholder="Quest ID" aria-label="Quest ID" data-col-field="task_id">
              <input type="text" class="form-text" placeholder="Chapter ID" aria-label="Quest chapter ID" data-col-field="chapter_id">
              <input type="text" class="form-text" placeholder="Scene ID" aria-label="Quest scene ID" data-col-field="scene_id">
              <select class="form-select" aria-label="Quest status" data-col-field="status"><option value="available">Available</option><option value="active">Active</option><option value="completed">Completed</option><option value="locked">Locked</option></select>
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <h4 class="gm-storyline-wizard__section-title">Player decisions and consequences</h4>
          <div class="gm-storyline-wizard__section-help">
            <p>These records become executable branch contracts in the published storyline, not standalone tools.</p>
            <ol>
              <li>Author at least two choices for a decision and define the trigger that makes each choice eligible.</li>
              <li>Publication compiles the choices into runtime questline nodes and exposes their decision and choice IDs.</li>
              <li>During play, the GM receives current decisions in its prompt context and can inspect or resolve them through the standard GM tools. Other runtime callers may submit the same decision/choice pair through storyline advancement.</li>
              <li>The engine records the selection, applies actor/environment/storyline state changes, moves to the target task or scene, and completes terminal branches.</li>
            </ol>
            <p>Action, skill, state, timer, and GM-decision triggers tell the gameplay GM when a choice is eligible. They are not background polling jobs; the GM or another runtime caller resolves the matching choice after play establishes the outcome.</p>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="branch_choice_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Choices and triggers</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="branch_choice_entries">Add choice</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="branch_choice_entries"></div>
            <template data-row-template="branch_choice_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--analysis">
              <input type="text" class="form-text" placeholder="Decision ID" aria-label="Decision ID" data-col-field="decision_id">
              <input type="text" class="form-text" placeholder="Source task ID" aria-label="Source task ID" data-col-field="source_task_id">
              <input type="text" class="form-text" placeholder="Unique choice ID" aria-label="Choice ID" data-col-field="choice_id">
              <input type="text" class="form-text" placeholder="Player-facing choice" aria-label="Choice label" data-col-field="label">
              <select class="form-select" aria-label="Trigger type" data-col-field="trigger_type"><option value="player_choice">Player choice</option><option value="action">Action</option><option value="objective_result">Objective result</option><option value="skill_result">Skill result</option><option value="actor_state">Actor state</option><option value="environment_state">Environment state</option><option value="item_state">Item state</option><option value="timer">Timer</option><option value="gm_decision">GM decision</option></select>
              <select class="form-select" aria-label="Actor interaction" data-col-field="trigger_interaction"><option value="other">Other interaction</option><option value="attacked">Actor is attacked</option><option value="killed">Actor is killed</option></select>
              <input type="text" class="form-text" placeholder="Trigger condition" aria-label="Trigger condition" data-col-field="trigger_condition">
              <input type="text" class="form-text" placeholder="Affected actor/source ID" aria-label="Trigger source ID" data-col-field="trigger_source_id">
              <input type="text" class="form-text" placeholder="Effect summary" aria-label="Effect summary" data-col-field="effect_summary">
              <input type="text" class="form-text" placeholder="Target task ID" aria-label="Target task ID" data-col-field="target_task_id">
              <input type="text" class="form-text" placeholder="Target scene ID" aria-label="Target scene ID" data-col-field="target_scene_id">
              <select class="form-select" aria-label="Terminal outcome" data-col-field="terminal"><option value="false">Continues story</option><option value="true">Terminal ending</option></select>
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="branch_actor_effect_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Actor effects</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="branch_actor_effect_entries">Add actor effect</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="branch_actor_effect_entries"></div>
            <template data-row-template="branch_actor_effect_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--effect">
              <input type="text" class="form-text" placeholder="Choice ID" aria-label="Effect choice ID" data-col-field="choice_id">
              <input type="text" class="form-text" placeholder="Actor ID" aria-label="Affected actor ID" data-col-field="actor_id">
              <select class="form-select" aria-label="Actor interaction" data-col-field="interaction"><option value="other">Other interaction</option><option value="attacked">Attacked</option><option value="killed">Killed</option></select>
              <input type="text" class="form-text" placeholder="How does this actor react?" aria-label="Actor reaction" data-col-field="reaction">
              <input type="text" class="form-text" placeholder="What changes for the actor?" aria-label="Actor effect" data-col-field="effect">
              <textarea class="form-textarea" rows="2" placeholder="State changes: key | operation | value" aria-label="Actor state changes" data-col-field="state_changes"></textarea>
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="branch_environment_effect_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Environment effects</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="branch_environment_effect_entries">Add environment effect</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="branch_environment_effect_entries"></div>
            <template data-row-template="branch_environment_effect_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--effect">
              <input type="text" class="form-text" placeholder="Choice ID" aria-label="Effect choice ID" data-col-field="choice_id">
              <select class="form-select" aria-label="Environment target type" data-col-field="target_type"><option value="room">Room</option><option value="dungeon">Dungeon</option><option value="location">Location</option><option value="world">World</option></select>
              <input type="text" class="form-text" placeholder="Target ID" aria-label="Environment target ID" data-col-field="target_id">
              <input type="text" class="form-text" placeholder="What changes in the environment?" aria-label="Environment effect" data-col-field="effect">
              <textarea class="form-textarea" rows="2" placeholder="State changes: key | operation | value" aria-label="Environment state changes" data-col-field="state_changes"></textarea>
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="branch_storyline_effect_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Storyline implications</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="branch_storyline_effect_entries">Add storyline implication</button></div>
            <p>Required whenever a choice changes an actor or the environment.</p>
            <div class="gm-storyline-wizard__rows" data-rows="branch_storyline_effect_entries"></div>
            <template data-row-template="branch_storyline_effect_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--effect">
              <input type="text" class="form-text" placeholder="Choice ID" aria-label="Effect choice ID" data-col-field="choice_id">
              <select class="form-select" aria-label="Storyline target type" data-col-field="target_type"><option value="task">Task</option><option value="scene">Scene</option><option value="conflict">Conflict</option><option value="chapter">Chapter</option><option value="storyline">Storyline</option></select>
              <input type="text" class="form-text" placeholder="Target ID" aria-label="Storyline target ID" data-col-field="target_id">
              <input type="text" class="form-text" placeholder="Source actor ID (required if killed)" aria-label="Storyline source actor ID" data-col-field="source_actor_id">
              <select class="form-select" aria-label="Storyline operation" data-col-field="operation"><option value="unlock">Unlock</option><option value="lock">Lock</option><option value="reveal">Reveal</option><option value="conceal">Conceal</option><option value="redirect">Redirect</option><option value="escalate">Escalate</option><option value="resolve">Resolve</option><option value="set">Set state</option></select>
              <input type="text" class="form-text" placeholder="Storyline implication" aria-label="Storyline implication" data-col-field="effect">
              <select class="form-select" aria-label="Follow-up required" data-col-field="follow_up_required"><option value="false">No follow-up</option><option value="true">Follow-up required</option></select>
              <textarea class="form-textarea" rows="2" placeholder="State changes: key | operation | value" aria-label="Storyline state changes" data-col-field="state_changes"></textarea>
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="objective_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Objective completion criteria</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="objective_entries">Add objective</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="objective_entries"></div>
            <template data-row-template="objective_entries"><div class="gm-storyline-wizard__row gm-storyline-wizard__row--five">
              <input type="text" class="form-text" placeholder="Objective ID" data-col-field="objective_id">
              <select class="form-select" data-col-field="kind"><option value="count">count</option><option value="flag">flag</option><option value="all_children">all_children</option></select>
              <input type="text" class="form-text" placeholder="Metric" data-col-field="metric">
              <input type="text" class="form-text" placeholder="Description" data-col-field="description">
              <input type="text" class="form-text" placeholder="Required value/target count" data-col-field="required">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__actions"><button type="button" class="btn btn-secondary" data-step-back="1">Back</button></div>
        </section>

        <section class="gm-storyline-wizard__step" data-step-panel="3" hidden>
          <h3>Step 3 · Actor Object Generation + Validation</h3>
          <p>Define actor objects and validation expectations with existing actor frameworks.</p>
          <div class="gm-storyline-wizard__tools"><strong>Canonical output:</strong> actor contacts, placements, knowledge, dialogue guidance, and relationship anchors.</div>
          <div class="gm-storyline-wizard__link-chips">{$actor_library} {$editor_suite}</div>
          <div class="gm-storyline-wizard__collection" data-collection="actor_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Actors in storyline</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="actor_entries">Add actor</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="actor_entries"></div>
            <template data-row-template="actor_entries"><div class="gm-storyline-wizard__row">
              <input type="text" class="form-text" placeholder="Contact ID" aria-label="Contact ID" data-col-field="contact_id">
              <input type="text" class="form-text" placeholder="Actor ID" data-col-field="actor_id">
              <input type="text" class="form-text" placeholder="Role" data-col-field="role">
              <input type="text" class="form-text" placeholder="Display name" data-col-field="display_name">
              <input type="text" class="form-text" placeholder="Motivation" data-col-field="motivation">
              <input type="text" class="form-text" placeholder="Dialogue guidance" data-col-field="dialogue">
              <input type="text" class="form-text" placeholder="Knowledge" data-col-field="knowledge">
              <select class="form-select" aria-label="Initial attitude" data-col-field="attitude"><option value="indifferent">Indifferent</option><option value="friendly">Friendly</option><option value="helpful">Helpful</option><option value="hostile">Hostile</option></select>
              <input type="text" class="form-text" placeholder="Availability" data-col-field="availability">
              <input type="text" class="form-text" placeholder="Chapter ID" data-col-field="chapter_id">
              <input type="text" class="form-text" placeholder="Scene ID" data-col-field="scene_id">
              <input type="text" class="form-text" placeholder="Location ID" data-col-field="location_id">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__actions"><button type="button" class="btn btn-secondary" data-step-back="2">Back</button></div>
        </section>

        <section class="gm-storyline-wizard__step" data-step-panel="4" hidden>
          <h3>Step 4 · Dungeon and Room Object Generation + Validation</h3>
          <p>Define dungeon and room assets, connectors, and required validation contracts.</p>
          <div class="gm-storyline-wizard__tools"><strong>Canonical output:</strong> independent dungeon and room references bound explicitly to chapters and scenes.</div>
          <div class="gm-storyline-wizard__link-chips">{$dungeon_editor} {$room_editor} {$editor_suite}</div>
          <div class="gm-storyline-wizard__collection" data-collection="dungeon_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Dungeons</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="dungeon_entries">Add dungeon</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="dungeon_entries"></div>
            <template data-row-template="dungeon_entries"><div class="gm-storyline-wizard__row">
              <input type="text" class="form-text" placeholder="Dungeon ID" data-col-field="dungeon_id">
              <input type="text" class="form-text" placeholder="Chapter ID" aria-label="Dungeon chapter ID" data-col-field="chapter_id">
              <input type="text" class="form-text" placeholder="Purpose" data-col-field="purpose">
              <input type="text" class="form-text" placeholder="Entrance room" data-col-field="entrance_room">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__collection" data-collection="room_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Rooms</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="room_entries">Add room</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="room_entries"></div>
            <template data-row-template="room_entries"><div class="gm-storyline-wizard__row">
              <input type="text" class="form-text" placeholder="Dungeon ID" data-col-field="dungeon_id">
              <input type="text" class="form-text" placeholder="Chapter ID" aria-label="Room chapter ID" data-col-field="chapter_id">
              <input type="text" class="form-text" placeholder="Scene ID" aria-label="Room scene ID" data-col-field="scene_id">
              <input type="text" class="form-text" placeholder="Room ID" data-col-field="room_id">
              <input type="text" class="form-text" placeholder="Narrative beat" data-col-field="narrative">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__actions"><button type="button" class="btn btn-secondary" data-step-back="3">Back</button></div>
        </section>

        <section class="gm-storyline-wizard__step" data-step-panel="5" hidden>
          <h3>Step 5 · Item Object Generation + Validation</h3>
          <p>Define canonical items and validate their links to objectives/tasks/journal flow.</p>
          <div class="gm-storyline-wizard__tools"><strong>Canonical output:</strong> item acquisition, reward, prerequisite, quest, actor, and location associations.</div>
          <div class="gm-storyline-wizard__link-chips">{$item_library} {$editor_suite}</div>
          <div class="gm-storyline-wizard__collection" data-collection="item_entries">
            <div class="gm-storyline-wizard__collection-header"><h4>Items</h4><button type="button" class="btn btn-secondary btn-sm" data-add-row="item_entries">Add item</button></div>
            <div class="gm-storyline-wizard__rows" data-rows="item_entries"></div>
            <template data-row-template="item_entries"><div class="gm-storyline-wizard__row">
              <input type="text" class="form-text" placeholder="Item ID" data-col-field="item_id">
              <input type="text" class="form-text" placeholder="Purpose" data-col-field="purpose">
              <input type="text" class="form-text" placeholder="Acquisition step" data-col-field="acquisition">
              <input type="text" class="form-text" placeholder="Chapter ID" data-col-field="chapter_id">
              <input type="text" class="form-text" placeholder="Scene ID" data-col-field="scene_id">
              <input type="text" class="form-text" placeholder="Quest ID" data-col-field="quest_id">
              <input type="text" class="form-text" placeholder="Actor ID" data-col-field="actor_id">
              <input type="text" class="form-text" placeholder="Prerequisite item IDs" data-col-field="prerequisite_ids">
              <input type="text" class="form-text" placeholder="Reward use" data-col-field="reward">
              <button type="button" class="btn btn-secondary btn-sm" data-remove-row>Remove</button>
            </div></template>
          </div>
          <div class="gm-storyline-wizard__actions"><button type="button" class="btn btn-secondary" data-step-back="4">Back</button></div>
        </section>

        <section class="gm-storyline-wizard__step" data-step-panel="6" hidden>
          <h3>Step 6 · Composition and Validation Orchestration</h3>
          <p>Assemble sections into one canonical contract and define generation/validation run order.</p>
          <div class="gm-storyline-wizard__tools"><strong>Canonical composition:</strong> assemble one canonical storyline object and run every contract stage without changing campaign state.</div>
          <label>Generation orchestration steps (one per line)<textarea class="form-textarea" rows="5" data-field="generation_orchestration" placeholder="1. Save canonical template\n2. Validate contract stages\n3. Generate campaign storyline runtime"></textarea></label>
          <label>Validation orchestration steps (one per line: stage | expected pass condition)<textarea class="form-textarea" rows="6" data-field="validation_orchestration" placeholder="entity_linkage | all actors/rooms/items are linked to at least one objective path"></textarea></label>
          <div class="gm-storyline-wizard__actions"><button type="button" class="btn btn-secondary" data-step-back="5">Back</button></div>
        </section>

        <section class="gm-storyline-wizard__step" data-step-panel="7" hidden>
          <h3>Step 7 · Review Canonical Storyline Object</h3>
          <p>Validate to generate the exact canonical storyline definition that creation will persist.</p>
          <p>Existing templates retain untouched canonical fields while structured scene, task, actor, dungeon, room, item, relationship, and conflict rows can be added, edited, or removed.</p>
          <div class="gm-storyline-wizard__tools"><strong>Available tools:</strong><ul><li>/api/storylines/templates (POST)</li><li>/api/campaign/{campaign_id}/storylines/generate (POST)</li><li>/api/campaign/{campaign_id}/storylines/bootstrap (POST)</li></ul></div>
          <div class="gm-storyline-wizard__assistant-card">
            <h4>Game Master Agent assistance</h4>
            <p>Use the grounded GM assistant storyline toolset while authoring this storyline.</p>
            <div class="gm-storyline-wizard__link-chips">{$editor_suite} {$actor_library} {$item_library} {$room_editor} {$dungeon_editor}</div>
          </div>
          <pre class="gm-storyline-wizard__preview" data-gm-blueprint-preview>{}</pre>
          <p class="gm-storyline-wizard__publish-status" data-gm-template-publish-status></p>
          <p class="gm-storyline-wizard__validation-summary" data-gm-template-validation-summary></p>
          <ul class="gm-storyline-wizard__validation-list" data-gm-template-validation-list></ul>
          <section class="gm-storyline-wizard__analysis" aria-labelledby="gm-storyline-analysis-title">
            <h4 id="gm-storyline-analysis-title">Story timeline and conflict graph</h4>
            <p data-gm-template-analysis-summary>Validate the saved draft to generate the authoritative analysis.</p>
            <div class="gm-storyline-wizard__analysis-graph" data-gm-template-analysis-graph role="img" aria-label="Story timeline and conflict relationship graph"></div>
          </section>
          <div class="gm-storyline-wizard__actions"><button type="button" class="btn btn-secondary" data-step-back="6">Back</button><button type="button" class="btn btn-secondary" data-gm-blueprint-copy>Copy object JSON</button><button type="button" class="btn btn-secondary" data-gm-template-validate>Generate and validate object</button><button type="button" class="btn btn-primary" data-gm-template-publish>Create canonical storyline</button></div>
        </section>

      </div>
HTML;
  }

  /**
   * Render a styled tool chip that links when the route is accessible.
   */
  private function buildToolLinkIfAccessible(string $label, string $route_name, array $route_params = []): string {
    $url = Url::fromRoute($route_name, $route_params);
    $safe_label = Html::escape($label);
    if ($url->access($this->currentUser())) {
      $href = Html::escape($url->toString());
      return '<a class="gm-storyline-wizard__tool-chip" href="' . $href . '">' . $safe_label . '</a>';
    }
    return '<span class="gm-storyline-wizard__tool-chip gm-storyline-wizard__tool-chip--disabled">' . $safe_label . ' (unavailable)</span>';
  }

}
