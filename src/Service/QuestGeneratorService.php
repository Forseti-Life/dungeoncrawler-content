<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating quests from templates.
 *
 * Handles procedural quest generation by:
 * - Loading quest templates from library
 * - Resolving template variables with campaign context
 * - Generating objectives with target values
 * - Scaling rewards based on party level
 * - Creating campaign quest instances
 */
class QuestGeneratorService {

  public const QUEST_SUMMARY_SCHEMA_VERSION = 'quest-summary-v2';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Number generation service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGeneration;

  /**
   * State validation service.
   */
  protected ?StateValidationService $stateValidationService;

  /**
   * Cached quest-giver policy registry payload.
   */
  protected ?array $questGiverPolicyRegistry = NULL;

  /**
   * Storyline validator used by management/verification views.
   */
  protected ?StorylineManagerService $storylineManager;
  protected array $storylineContactCache = [];

  /**
   * Objective type service.
   */
  protected ObjectiveTypeService $objectiveTypeService;

  /**
   * Constructs a QuestGeneratorService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\dungeoncrawler_content\Service\NumberGenerationService $number_generation
   *   The number generation service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    NumberGenerationService $number_generation,
    ?StateValidationService $state_validation_service = NULL,
    ?ObjectiveTypeService $objective_type_service = NULL,
    ?StorylineManagerService $storyline_manager = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->numberGeneration = $number_generation;
    $this->stateValidationService = $state_validation_service;
    $this->objectiveTypeService = $objective_type_service ?? new ObjectiveTypeService();
    $this->storylineManager = $storyline_manager;
  }

  /**
   * Generate a quest from a template.
   *
   * @param string $template_id
   *   The quest template ID.
   * @param int $campaign_id
   *   The campaign ID.
   * @param array $context
   *   Generation context with keys:
   *   - party_level: Average party level
   *   - location: Location identifier
   *   - npcs: Available NPCs
   *   - difficulty: Difficulty setting
   *
   * @return array
   *   Generated quest data ready for insertion into dc_campaign_quests, or
   *   empty array if generation fails.
   */
  public function generateQuestFromTemplate(
    string $template_id,
    int $campaign_id,
    array $context
  ): array {
    try {
      // Load template
      $template = $this->loadTemplate($template_id);
      if (empty($template)) {
        $this->logger->error('Quest template not found: @template', ['@template' => $template_id]);
        return [];
      }

      if (!$this->isQuestTemplateAllowedForGiver($campaign_id, $template_id, $context)) {
        $this->logger->warning('Quest template @template is not allowed for giver @giver in campaign @campaign.', [
          '@template' => $template_id,
          '@giver' => (string) ($context['giver_npc_id'] ?? 'unknown'),
          '@campaign' => $campaign_id,
        ]);
        return [];
      }

      // Generate unique quest ID
      $quest_id = $this->generateQuestId($campaign_id, $template_id);

      // Resolve variables
      $variables = $this->buildVariables($template, $context, $campaign_id);
      $quest_name = $this->resolveVariables($template['name'], $variables);
      $quest_description = $this->resolveVariables($template['description'], $variables);

      // Generate objectives
      $generated_objectives = $this->generateObjectives(
        json_decode($template['objectives_schema'], TRUE),
        $variables,
        $context
      );

      // Scale rewards
      $generated_rewards = $this->scaleRewards(
        json_decode($template['rewards_schema'], TRUE),
        $context['party_level'] ?? 1,
        $context['difficulty'] ?? 'moderate'
      );

      // Build quest data
      $quest_data = [
        'campaign_id' => $campaign_id,
        'quest_id' => $quest_id,
        'source_template_id' => $template_id,
        'quest_name' => $quest_name,
        'quest_description' => $quest_description,
        'quest_type' => $template['quest_type'],
        'quest_data' => json_encode([
          'variables' => $variables,
          'party_level' => $context['party_level'] ?? 1,
          'difficulty' => $context['difficulty'] ?? 'moderate',
        ]),
        'generated_objectives' => json_encode($generated_objectives),
        'generated_rewards' => json_encode($generated_rewards),
        'status' => (string) ($context['initial_status'] ?? 'offered'),
        'giver_npc_id' => $context['giver_npc_id'] ?? NULL,
        'location_id' => $context['location'] ?? NULL,
        'created_at' => \Drupal::time()->getRequestTime(),
        'available_at' => \Drupal::time()->getRequestTime(),
        'expires_at' => isset($template['time_limit_hours']) ?
          \Drupal::time()->getRequestTime() + ($template['time_limit_hours'] * 3600) : NULL,
      ];

      $this->logger->info('Generated quest @quest from template @template for campaign @campaign', [
        '@quest' => $quest_id,
        '@template' => $template_id,
        '@campaign' => $campaign_id,
      ]);

      return $quest_data;
    }
    catch (\Exception $e) {
      $this->logger->error('Quest generation failed: @error', ['@error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Generate multiple quests appropriate for location and party level.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $context
   *   Generation context.
   * @param int $count
   *   Number of quests to generate.
   *
   * @return array
   *   Array of generated quests.
   */
  public function generateQuestsForLocation(
    int $campaign_id,
    array $context,
    int $count = 3
  ): array {
    $party_level = $context['party_level'] ?? 1;
    $location_tags = $context['location_tags'] ?? [];

    // Find appropriate templates
    $templates = $this->findTemplatesForLevel($party_level, $location_tags);
    $templates = array_values(array_filter($templates, function (array $template) use ($campaign_id, $context): bool {
      $template_id = trim((string) ($template['template_id'] ?? ''));
      return $template_id !== '' && $this->isQuestTemplateAllowedForGiver($campaign_id, $template_id, $context);
    }));
    $generated = [];

    // Generate up to $count quests
    for ($i = 0; $i < $count && $i < count($templates); $i++) {
      $template_id = $templates[$i]['template_id'];
      $quest = $this->generateQuestFromTemplate($template_id, $campaign_id, $context);
      if (!empty($quest)) {
        $generated[] = $quest;
      }
    }

    return $generated;
  }

  /**
   * Normalize one quest row into the canonical quest summary entry contract.
   */
  public function buildQuestSummaryEntry(array $quest_row): array {
    $generated_objectives = $this->normalizeQuestObjectivePhases(
      $this->decodeQuestField($quest_row['generated_objectives'] ?? [])
    );
    $objective_states = $this->normalizeQuestObjectivePhases(
      $this->decodeQuestField($quest_row['objective_states'] ?? $generated_objectives)
    );
    if ($objective_states === []) {
      $objective_states = $generated_objectives;
    }
    $generated_objectives = $this->hydrateObjectiveLabels($generated_objectives, $quest_row);
    $objective_states = $this->hydrateObjectiveLabels($objective_states, $quest_row);

    $generated_rewards = $this->decodeQuestObjectField($quest_row['generated_rewards'] ?? []);
    $quest_data = $this->decodeQuestObjectField($quest_row['quest_data'] ?? []);
    $quest_name = trim((string) ($quest_row['quest_name'] ?? $quest_row['name'] ?? $quest_row['title'] ?? $quest_row['quest_id'] ?? ''));
    $source_template_id = $this->normalizeNullableString($quest_row['source_template_id'] ?? NULL);

    return [
      'quest_id' => trim((string) ($quest_row['quest_id'] ?? '')),
      'campaign_id' => isset($quest_row['campaign_id']) ? (int) $quest_row['campaign_id'] : 0,
      'quest_key' => trim((string) ($quest_row['quest_key'] ?? $source_template_id ?? $quest_row['quest_id'] ?? '')),
      'source_template_id' => $source_template_id,
      'title' => trim((string) ($quest_row['title'] ?? $quest_name)),
      'quest_name' => $quest_name,
      'status' => trim((string) ($quest_row['status'] ?? 'lead')) ?: 'lead',
      'current_phase' => max(1, (int) ($quest_row['current_phase'] ?? 1)),
      'generated_objectives' => $generated_objectives,
      'objective_states' => $objective_states,
      'generated_rewards' => $generated_rewards,
      'quest_data' => $quest_data,
      'location_id' => $this->normalizeNullableString($quest_row['location_id'] ?? NULL),
      'storyline' => [
        'storyline_id' => $this->normalizeNullableString($quest_row['storyline_id'] ?? NULL),
        'chapter_id' => $this->normalizeNullableString($quest_row['storyline_chapter_id'] ?? NULL),
        'scene_id' => $this->normalizeNullableString($quest_row['storyline_scene_id'] ?? NULL),
      ],
    ];
  }

  /**
   * Attach player-facing labels to objective references.
   */
  protected function hydrateObjectiveLabels(array $phases, array $quest_row): array {
    foreach ($phases as $phase_index => $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $phases[$phase_index]['objectives'] = array_map(
        fn(array $objective): array => $this->hydrateObjectiveNodeLabels($objective, $quest_row),
        array_values(array_filter((array) ($phase['objectives'] ?? []), 'is_array'))
      );
    }

    return $phases;
  }

  /**
   * Attach labels to a single objective node recursively.
   */
  protected function hydrateObjectiveNodeLabels(array $objective, array $quest_row): array {
    foreach (['target', 'item', 'location', 'location_id', 'destination', 'destination_id'] as $field) {
      $value = trim((string) ($objective[$field] ?? ''));
      if ($value !== '') {
        $label = $this->resolveObjectiveReferenceLabel($quest_row, $value);
        if ($label !== '' && $label !== $value) {
          foreach (['description', 'next_step'] as $text_field) {
            if (!empty($objective[$text_field]) && is_string($objective[$text_field])) {
              $objective[$text_field] = str_replace($value, $label, $objective[$text_field]);
            }
          }
        }
      }
    }
    $objective = $this->ensureObjectiveGuidance($objective, $quest_row);

    if (is_array($objective['children'] ?? NULL)) {
      $objective['children'] = array_map(
        fn(array $child): array => $this->hydrateObjectiveNodeLabels($child, $quest_row),
        array_values(array_filter($objective['children'], 'is_array'))
      );
    }

    return $objective;
  }

  /**
   * Ensure every objective explains the next action and completion trigger.
   */
  protected function ensureObjectiveGuidance(array $objective, array $quest_row): array {
    if (trim((string) ($objective['next_step'] ?? '')) === '') {
      $objective['next_step'] = $this->deriveSummaryObjectiveNextStep($objective, $quest_row);
    }

    $criteria = is_array($objective['completion_criteria'] ?? NULL) ? $objective['completion_criteria'] : [];
    $criteria = $this->normalizeObjectiveCompletionCriteria($criteria, $objective);
    $description = trim((string) ($criteria['description'] ?? ''));
    $generic_descriptions = [
      'mark this objective complete.',
      'reach the required progress count.',
      'discover the required location.',
      'complete this objective.',
    ];
    if ($description === '' || in_array(strtolower($description), $generic_descriptions, TRUE)) {
      $criteria['description'] = $this->deriveSummaryObjectiveCompletionDescription($objective, $quest_row);
    }
    $objective['completion_criteria'] = $criteria;

    return $objective;
  }

  /**
   * Derive a player-facing next action from the objective contract.
   */
  protected function deriveSummaryObjectiveNextStep(array $objective, array $quest_row): string {
    $type = strtolower(trim((string) ($objective['type'] ?? 'objective')));
    $description = trim((string) ($objective['description'] ?? 'this objective'));
    $target = $this->objectiveReferenceDisplay($objective, $quest_row, 'target');
    $item = $this->objectiveReferenceDisplay($objective, $quest_row, 'item');
    $location = $this->objectiveReferenceDisplay($objective, $quest_row, 'location')
      ?: $this->objectiveReferenceDisplay($objective, $quest_row, 'location_id');
    $destination = $this->objectiveReferenceDisplay($objective, $quest_row, 'destination')
      ?: $this->objectiveReferenceDisplay($objective, $quest_row, 'destination_id');
    $count = max(1, (int) ($objective['target_count'] ?? $objective['completion_criteria']['target_count'] ?? 1));

    switch ($type) {
      case 'collect':
        $item_text = $item !== '' ? $item : 'the required item';
        $location_text = $location !== '' ? " in {$location}" : '';
        return "Search{$location_text} and collect {$count} {$item_text}.";

      case 'kill':
        $target_text = $target !== '' ? $target : 'the required foe';
        $location_text = $location !== '' ? " in {$location}" : '';
        return "Engage{$location_text} and defeat {$count} {$target_text}.";

      case 'explore':
        $location_text = $location !== '' ? $location : ($target !== '' ? $target : $description);
        return "Travel to {$location_text} and enter or reveal it on the map.";

      case 'investigate':
        $target_text = $target !== '' ? $target : ($location !== '' ? $location : $description);
        return "Investigate {$target_text} until the clue or lead is recorded.";

      case 'escort':
        $target_text = $target !== '' ? $target : 'the escorted NPC';
        $destination_text = $destination !== '' ? $destination : 'the destination';
        return "Keep {$target_text} with the party and reach {$destination_text}.";

      case 'interact':
        $target_text = $target !== '' ? $target : $description;
        $location_text = $location !== '' ? " in {$location}" : '';
        return "Speak with or interact with {$target_text}{$location_text}.";

      default:
        return "Complete this objective: {$description}.";
    }
  }

  /**
   * Derive a concrete completion condition from the objective contract.
   */
  protected function deriveSummaryObjectiveCompletionDescription(array $objective, array $quest_row): string {
    $type = strtolower(trim((string) ($objective['type'] ?? 'objective')));
    $target = $this->objectiveReferenceDisplay($objective, $quest_row, 'target');
    $item = $this->objectiveReferenceDisplay($objective, $quest_row, 'item');
    $location = $this->objectiveReferenceDisplay($objective, $quest_row, 'location')
      ?: $this->objectiveReferenceDisplay($objective, $quest_row, 'location_id');
    $destination = $this->objectiveReferenceDisplay($objective, $quest_row, 'destination')
      ?: $this->objectiveReferenceDisplay($objective, $quest_row, 'destination_id');
    $count = max(1, (int) ($objective['target_count'] ?? $objective['completion_criteria']['target_count'] ?? 1));

    switch ($type) {
      case 'collect':
        $item_text = $item !== '' ? $item : 'required item';
        $location_text = $location !== '' ? " in {$location}" : '';
        return "Complete when {$count} {$item_text} have been collected{$location_text}.";

      case 'kill':
        $target_text = $target !== '' ? $target : 'required foe';
        return "Complete when {$count} {$target_text} have been defeated.";

      case 'explore':
        $location_text = $location !== '' ? $location : ($target !== '' ? $target : 'the target location');
        return "Complete when {$location_text} is discovered.";

      case 'investigate':
        $target_text = $target !== '' ? $target : ($location !== '' ? $location : 'the investigation lead');
        return "Complete when the clue or lead for {$target_text} is recorded.";

      case 'escort':
        $target_text = $target !== '' ? $target : 'the escorted NPC';
        $destination_text = $destination !== '' ? $destination : 'the destination';
        return "Complete when {$target_text} arrives safely at {$destination_text}.";

      case 'interact':
        $target_text = $target !== '' ? $target : 'the target';
        return "Complete when the required conversation or handoff with {$target_text} is finished.";

      default:
        return 'Complete when the objective state is marked complete.';
    }
  }

  /**
   * Resolve an objective reference field into display text.
   */
  protected function objectiveReferenceDisplay(array $objective, array $quest_row, string $field): string {
    $value = trim((string) ($objective[$field] ?? ''));
    if ($value === '') {
      return '';
    }
    $label = $this->resolveObjectiveReferenceLabel($quest_row, $value);
    return $label !== '' ? $label : $value;
  }

  /**
   * Resolve a quest/objective reference into a player-facing label.
   */
  protected function resolveObjectiveReferenceLabel(array $quest_row, string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $campaign_id = isset($quest_row['campaign_id']) ? (int) $quest_row['campaign_id'] : 0;
    $storyline_id = $this->normalizeNullableString($quest_row['storyline_id'] ?? NULL);

    if ($campaign_id > 0 && $this->database->schema()->tableExists('dc_campaign_rooms')) {
      $room_name = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['name'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', [$value], 'IN')
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if (is_string($room_name) && $room_name !== '') {
        return $room_name;
      }

      $location_id = $this->normalizeNullableString($quest_row['location_id'] ?? NULL);
      if ($location_id !== NULL) {
        $room_row = $this->database->select('dc_campaign_rooms', 'r')
          ->fields('r', ['contents_data'])
          ->condition('campaign_id', $campaign_id)
          ->condition('room_id', $location_id)
          ->range(0, 1)
          ->execute()
          ->fetchAssoc();
        $contents = is_array($room_row) ? (json_decode((string) ($room_row['contents_data'] ?? '{}'), TRUE) ?: []) : [];
        foreach ((array) ($contents['npcs'] ?? []) as $npc) {
          if (!is_array($npc)) {
            continue;
          }
          $npc_refs = array_filter([
            $npc['content_id'] ?? NULL,
            $npc['id'] ?? NULL,
            $npc['npc_id'] ?? NULL,
          ], static fn($ref): bool => $ref !== NULL && trim((string) $ref) !== '');
          if (in_array($value, array_map('strval', $npc_refs), TRUE)) {
            $npc_name = trim((string) ($npc['name'] ?? ''));
            if ($npc_name !== '') {
              return $npc_name;
            }
          }
        }
      }
    }

    $storyline_contacts = $this->loadStorylineContacts($campaign_id, $storyline_id);
    if (isset($storyline_contacts[$value])) {
      return $storyline_contacts[$value];
    }

    return ucwords(str_replace(['_', '-'], ' ', $value));
  }

  /**
   * Load storyline contacts keyed by entity id.
   *
   * @return array<string, string>
   *   Entity id => display name.
   */
  protected function loadStorylineContacts(int $campaign_id, ?string $storyline_id): array {
    if ($campaign_id <= 0 || $storyline_id === NULL || $storyline_id === '') {
      return [];
    }

    $cache_key = $campaign_id . ':' . $storyline_id;
    if (isset($this->storylineContactCache[$cache_key])) {
      return $this->storylineContactCache[$cache_key];
    }

    $contacts = [];
    if ($this->database->schema()->tableExists('dc_campaign_storylines')) {
      $row = $this->database->select('dc_campaign_storylines', 's')
        ->fields('s', ['storyline_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('storyline_id', $storyline_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      $storyline_data = json_decode((string) ($row['storyline_data'] ?? '{}'), TRUE) ?? [];
      foreach ((array) ($storyline_data['chapters'] ?? []) as $chapter) {
        if (!is_array($chapter)) {
          continue;
        }
        $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
        $chapter_name = trim((string) ($chapter['name'] ?? ''));
        if ($chapter_id !== '' && $chapter_name !== '') {
          $contacts[$chapter_id] = $chapter_name;
        }
        foreach ((array) ($chapter['scenes'] ?? []) as $scene) {
          if (!is_array($scene)) {
            continue;
          }
          $scene_id = trim((string) ($scene['scene_id'] ?? ''));
          $scene_name = trim((string) ($scene['name'] ?? ''));
          if ($scene_id !== '' && $scene_name !== '') {
            $contacts[$scene_id] = $scene_name;
          }
        }
      }

      foreach ((array) ($storyline_data['contacts'] ?? []) as $contact) {
        if (!is_array($contact)) {
          continue;
        }
        $entity_id = trim((string) ($contact['entity_id'] ?? ''));
        $display_name = trim((string) ($contact['display_name'] ?? ''));
        if ($entity_id !== '' && $display_name !== '') {
          $contacts[$entity_id] = $display_name;
        }
      }
    }

    $this->storylineContactCache[$cache_key] = $contacts;
    return $contacts;
  }

  /**
   * Return the canonical objective type options for quest authoring.
   */
  public function getObjectiveTypeOptions(): array {
    return $this->objectiveTypeService->getObjectiveTypeOptions();
  }

  /**
   * Build and validate a canonical quest summary payload.
   */
  public function buildQuestSummaryPayload(?string $location_id, array $active = [], array $offers = [], array $leads = [], int $campaign_id = 0): array {
    $active_entries = array_values(array_map([$this, 'buildQuestSummaryEntry'], $active));
    $offer_entries = array_values(array_map([$this, 'buildQuestSummaryEntry'], $offers));
    $lead_entries = array_values(array_map([$this, 'buildQuestSummaryEntry'], $leads));
    $payload = [
      'schema_version' => self::QUEST_SUMMARY_SCHEMA_VERSION,
      'location_id' => $location_id !== NULL && trim($location_id) !== '' ? trim($location_id) : 'campaign',
      'active' => $active_entries,
      'offers' => $offer_entries,
      'leads' => $lead_entries,
      'management_tree' => $campaign_id > 0
        ? $this->buildQuestManagementTree($campaign_id, $active_entries, array_merge($offer_entries, $lead_entries), $location_id)
        : [],
      'counts' => [
        'active' => count($active),
        'offers' => count($offers),
        'leads' => count($leads),
      ],
    ];

    if ($this->stateValidationService !== NULL) {
      $validation = $this->stateValidationService->validateQuestSummary($payload);
      if (!($validation['valid'] ?? FALSE)) {
        throw new \InvalidArgumentException('Quest summary failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
      }
    }

    return $payload;
  }

  /**
   * Load a quest template from the database.
   *
   * @param string $template_id
   *   Template identifier.
   *
   * @return array|null
   *   Template data or NULL if not found.
   */
  protected function loadTemplate(string $template_id): ?array {
    $result = $this->database->select('dungeoncrawler_content_quest_templates', 't')
      ->fields('t')
      ->condition('template_id', $template_id)
      ->execute()
      ->fetchAssoc();

    return $result ?: NULL;
  }

  /**
   * Return the canonical NPC quest-giver policy registry.
   */
  protected function loadQuestGiverPolicyRegistry(): array {
    if ($this->questGiverPolicyRegistry !== NULL) {
      return $this->questGiverPolicyRegistry;
    }

    $path = dirname(__DIR__) . '/../config/npc_quest_giver_policies.json';
    if (!is_file($path)) {
      $this->questGiverPolicyRegistry = ['schema_version' => 'npc-quest-giver-policies-v1', 'policies' => []];
      return $this->questGiverPolicyRegistry;
    }

    $payload = json_decode((string) file_get_contents($path), TRUE);
    if (!is_array($payload)) {
      throw new \UnexpectedValueException('NPC quest-giver policy registry is not valid JSON.');
    }

    if ($this->stateValidationService !== NULL) {
      $validation = $this->stateValidationService->validateNpcQuestGiverPolicies($payload);
      if (!($validation['valid'] ?? FALSE)) {
        throw new \InvalidArgumentException('NPC quest-giver policies failed validation: ' . implode('; ', $validation['errors'] ?? []), 400);
      }
    }

    $this->questGiverPolicyRegistry = $payload;
    return $this->questGiverPolicyRegistry;
  }

  /**
   * Return the configured NPC quest-giver policies.
   *
   * @return array<int, array<string, mixed>>
   *   Policy rows.
   */
  protected function getQuestGiverPolicies(): array {
    return array_values(array_filter(is_array($this->loadQuestGiverPolicyRegistry()['policies'] ?? NULL) ? $this->loadQuestGiverPolicyRegistry()['policies'] : [], 'is_array'));
  }

  /**
   * Determine whether the supplied giver may issue the template in context.
   */
  protected function isQuestTemplateAllowedForGiver(int $campaign_id, string $template_id, array $context): bool {
    $giver_reference = $this->normalizeNullableString($context['giver_npc_id'] ?? NULL);
    if ($giver_reference === NULL) {
      return TRUE;
    }

    $storyline_template_id = $this->normalizeNullableString($context['storyline_template_id'] ?? NULL);
    $candidate_ids = $this->resolveQuestGiverPolicyIds($campaign_id, $giver_reference);
    $policy = $this->findQuestGiverPolicy($campaign_id, $giver_reference);
    if ($policy === []) {
      $this->logger->warning('Quest giver policy missing: campaign={campaign_id} giver_reference={giver_reference} candidate_ids={candidate_ids} template_id={template_id} storyline_template_id={storyline_template_id}', [
        'campaign_id' => $campaign_id,
        'giver_reference' => $giver_reference,
        'candidate_ids' => implode(',', $candidate_ids),
        'template_id' => $template_id,
        'storyline_template_id' => $storyline_template_id ?? '',
      ]);
      return FALSE;
    }

    $allowed_templates = array_values(array_filter(array_map('strval', is_array($policy['allowed_quest_template_ids'] ?? NULL) ? $policy['allowed_quest_template_ids'] : [])));
    if ($allowed_templates !== [] && !in_array($template_id, $allowed_templates, TRUE)) {
      $this->logger->warning('Quest giver policy rejected template: campaign={campaign_id} giver_reference={giver_reference} template_id={template_id} allowed_templates={allowed_templates} candidate_ids={candidate_ids}', [
        'campaign_id' => $campaign_id,
        'giver_reference' => $giver_reference,
        'template_id' => $template_id,
        'allowed_templates' => implode(',', $allowed_templates),
        'candidate_ids' => implode(',', $candidate_ids),
      ]);
      return FALSE;
    }

    $allowed_storylines = array_values(array_filter(array_map('strval', is_array($policy['allowed_storyline_template_ids'] ?? NULL) ? $policy['allowed_storyline_template_ids'] : [])));
    if ($storyline_template_id !== NULL && $allowed_storylines !== [] && !in_array($storyline_template_id, $allowed_storylines, TRUE)) {
      $this->logger->warning('Quest giver policy rejected storyline: campaign={campaign_id} giver_reference={giver_reference} template_id={template_id} storyline_template_id={storyline_template_id} allowed_storylines={allowed_storylines} candidate_ids={candidate_ids}', [
        'campaign_id' => $campaign_id,
        'giver_reference' => $giver_reference,
        'template_id' => $template_id,
        'storyline_template_id' => $storyline_template_id,
        'allowed_storylines' => implode(',', $allowed_storylines),
        'candidate_ids' => implode(',', $candidate_ids),
      ]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Locate the policy row for one giver reference.
   */
  protected function findQuestGiverPolicy(int $campaign_id, string $giver_reference): array {
    $candidate_ids = $this->resolveQuestGiverPolicyIds($campaign_id, $giver_reference);
    foreach ($this->getQuestGiverPolicies() as $policy) {
      $giver_ids = array_values(array_filter(array_map('strval', is_array($policy['giver_ids'] ?? NULL) ? $policy['giver_ids'] : [])));
      if ($giver_ids === []) {
        continue;
      }
      foreach ($candidate_ids as $candidate_id) {
        if (in_array($candidate_id, $giver_ids, TRUE)) {
          return $policy;
        }
      }
    }

    return [];
  }

  /**
   * Resolve a giver reference to the policy ids that may represent it.
   *
   * @return array<int, string>
   *   Candidate giver ids.
   */
  protected function resolveQuestGiverPolicyIds(int $campaign_id, string $giver_reference): array {
    $candidate_ids = [$giver_reference];
    if (str_starts_with($giver_reference, 'npc_')) {
      $candidate_ids[] = substr($giver_reference, 4);
    }
    if ($campaign_id > 0 && ctype_digit($giver_reference) && $this->database->schema()->tableExists('dc_campaign_characters')) {
      $row = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['instance_id', 'state_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('id', (int) $giver_reference)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (is_array($row) && !empty($row['instance_id'])) {
        $instance_id = (string) $row['instance_id'];
        $candidate_ids[] = $instance_id;
        if (str_starts_with($instance_id, 'npc_')) {
          $candidate_ids[] = substr($instance_id, 4);
        }
      }
      $state = is_array($row) ? (json_decode((string) ($row['state_data'] ?? '{}'), TRUE) ?: []) : [];
      $metadata = is_array($state['metadata'] ?? NULL) ? $state['metadata'] : [];
      foreach ([
        (string) ($state['content_id'] ?? ''),
        (string) ($metadata['content_id'] ?? ''),
        (string) ($state['runtime_entity_id'] ?? ''),
        (string) ($metadata['runtime_entity_id'] ?? ''),
      ] as $candidate_id) {
        $candidate_id = trim($candidate_id);
        if ($candidate_id === '') {
          continue;
        }
        $candidate_ids[] = $candidate_id;
        if (str_starts_with($candidate_id, 'npc_')) {
          $candidate_ids[] = substr($candidate_id, 4);
        }
      }
    }

    return array_values(array_unique(array_filter(array_map('strval', $candidate_ids))));
  }

  /**
   * Find templates appropriate for party level.
   *
   * @param int $party_level
   *   Party level.
   * @param array $tags
   *   Location tags to match.
   *
   * @return array
   *   Array of matching templates.
   */
  protected function findTemplatesForLevel(int $party_level, array $tags = []): array {
    $query = $this->database->select('dungeoncrawler_content_quest_templates', 't')
      ->fields('t')
      ->condition('level_min', $party_level, '<=')
      ->condition('level_max', $party_level, '>=');

    $templates = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($templates)) {
      return [];
    }

    $requested_tags = $this->normalizeTags($tags);

    // Without location tags, keep behavior random.
    if (empty($requested_tags)) {
      shuffle($templates);
      return array_slice($templates, 0, 10);
    }

    $matched = [];
    $fallback = [];

    foreach ($templates as $template) {
      $template_tags = $this->decodeTemplateTags($template['tags'] ?? '[]');
      $overlap = array_values(array_intersect($requested_tags, $template_tags));
      $score = count($overlap);

      if ($score > 0) {
        $template['_tag_score'] = $score;
        $matched[] = $template;
      }
      else {
        $fallback[] = $template;
      }
    }

    usort($matched, static function (array $a, array $b): int {
      $score_cmp = (int) ($b['_tag_score'] ?? 0) <=> (int) ($a['_tag_score'] ?? 0);
      if ($score_cmp !== 0) {
        return $score_cmp;
      }
      return strcmp((string) ($a['template_id'] ?? ''), (string) ($b['template_id'] ?? ''));
    });

    shuffle($fallback);
    $ordered = array_merge($matched, $fallback);

    // Remove internal scoring field before returning.
    $ordered = array_map(static function (array $row): array {
      unset($row['_tag_score']);
      return $row;
    }, $ordered);

    return array_slice($ordered, 0, 10);
  }

  /**
   * Normalize tags to lowercase tokens.
   */
  protected function normalizeTags(array $tags): array {
    $normalized = [];
    foreach ($tags as $tag) {
      if (!is_string($tag) && !is_numeric($tag)) {
        continue;
      }

      $value = strtolower(trim((string) $tag));
      if ($value === '') {
        continue;
      }

      $normalized[$value] = TRUE;
    }

    return array_keys($normalized);
  }

  /**
   * Decode template tag payload from database.
   */
  protected function decodeTemplateTags(?string $raw_tags): array {
    if ($raw_tags === NULL || $raw_tags === '') {
      return [];
    }

    $decoded = json_decode($raw_tags, TRUE);
    if (!is_array($decoded)) {
      return [];
    }

    return $this->normalizeTags($decoded);
  }

  /**
   * Generate unique quest ID for campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $template_id
   *   Template ID.
   *
   * @return string
   *   Unique quest identifier.
   */
  protected function generateQuestId(int $campaign_id, string $template_id): string {
    $suffix = '_' . $campaign_id . '_' . uniqid();
    $max_template_length = max(8, 100 - strlen($suffix));
    $template_prefix = trim(substr($template_id, 0, $max_template_length), '-_');
    if ($template_prefix === '') {
      $template_prefix = 'quest';
    }

    return $template_prefix . $suffix;
  }

  /**
   * Build variable values for template substitution.
   *
   * @param array $template
   *   Quest template.
   * @param array $context
   *   Generation context.
   *
   * @return array
   *   Variable values.
   */
  protected function buildVariables(array $template, array $context, int $campaign_id = 0): array {
    $variables = $context;
    $template_id = trim((string) ($template['template_id'] ?? ''));
    $location_id = trim((string) ($context['location'] ?? ''));
    if ($campaign_id > 0 && $template_id !== '' && $location_id !== '') {
      foreach ($this->loadQuestAssociationContextHints($campaign_id, $location_id, $template_id, $template) as $key => $value) {
        if (!array_key_exists($key, $variables) || $variables[$key] === '' || $variables[$key] === NULL) {
          $variables[$key] = $value;
        }
      }
    }

    return $variables;
  }

  /**
   * Resolve template variables in text.
   *
   * @param string $text
   *   Text with variables like {variable_name}.
   * @param array $variables
   *   Variable values.
   *
   * @return string
   *   Resolved text.
   */
  protected function resolveVariables(string $text, array $variables): string {
    foreach ($variables as $key => $value) {
      if (is_string($value) || is_numeric($value)) {
        $text = str_replace('{' . $key . '}', (string) $value, $text);
      }
    }
    return $text;
  }

  /**
   * Load variable hints from room quest associations and questgiver metadata.
   *
   * @return array<string, string>
   *   Template variable hints keyed by variable name.
   */
  protected function loadQuestAssociationContextHints(int $campaign_id, string $location_id, string $template_id, array $template = []): array {
    if (
      $campaign_id <= 0
      || $location_id === ''
      || $template_id === ''
      || !$this->database->schema()->tableExists('dc_campaign_rooms')
    ) {
      return [];
    }

    $row = $this->database->select('dc_campaign_rooms', 'r')
      ->fields('r', ['name', 'contents_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $location_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    $contents = is_array($row) ? (json_decode((string) ($row['contents_data'] ?? '{}'), TRUE) ?: []) : [];
    if (!is_array($contents)) {
      return [];
    }

    $hints = [];
    $room_name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
    if ($room_name !== '') {
      $hints['room_name'] = $room_name;
    }

    foreach ((array) ($contents['npcs'] ?? []) as $npc) {
      if (!is_array($npc)) {
        continue;
      }
      foreach ((array) ($npc['quests'] ?? []) as $quest) {
        if (!is_array($quest) || trim((string) ($quest['quest_id'] ?? '')) !== $template_id) {
          continue;
        }
        $item_name = $this->deriveItemNameFromQuestLabel((string) ($quest['title'] ?? ''), $template);
        if ($item_name !== '') {
          $hints['item_name'] = $item_name;
        }
      }
    }

    $associated_item_names = [];
    foreach ((array) ($contents['items'] ?? []) as $item) {
      if (!is_array($item) || trim((string) ($item['quest_association'] ?? '')) !== $template_id) {
        continue;
      }
      $name = trim((string) ($item['name'] ?? ''));
      if ($name !== '') {
        $associated_item_names[$name] = ($associated_item_names[$name] ?? 0) + 1;
      }
    }

    if ($associated_item_names !== []) {
      arsort($associated_item_names);
      $hints['item_name'] = $hints['item_name'] ?? (string) array_key_first($associated_item_names);
      $hints['target_count'] = (string) array_sum($associated_item_names);
    }

    return $hints;
  }

  /**
   * Derive an item placeholder from a questgiver-facing title.
   */
  protected function deriveItemNameFromQuestLabel(string $label, array $template = []): string {
    $label = trim($label);
    if ($label === '') {
      return '';
    }

    if (preg_match('/^(?:collect|gather|recover|find|bring)\s+(.+)$/i', $label, $matches) !== 1) {
      return $label;
    }

    $item_name = trim((string) ($matches[1] ?? ''));
    $template_name = strtolower(trim((string) ($template['name'] ?? '')));
    if ($item_name !== '' && str_starts_with(strtolower($item_name), 'lost ') && str_contains($template_name, 'lost {item_name}')) {
      $item_name = trim(substr($item_name, 5));
    }

    return $item_name;
  }

  /**
   * Generate objectives from schema with target values.
   *
   * @param array $objectives_schema
   *   Objectives schema from template.
   * @param array $variables
   *   Variable values.
   * @param array $context
   *   Generation context.
   *
   * @return array
   *   Generated objectives with targets.
   */
  protected function generateObjectives(
    array $objectives_schema,
    array $variables,
    array $context
  ): array {
    $this->objectiveTypeService->assertObjectivePhases($objectives_schema);
    $objectives = [];

    foreach ($objectives_schema as $phase_data) {
      $phase_objectives = [];

      foreach ((array) ($phase_data['objectives'] ?? []) as $obj) {
        if (!is_array($obj)) {
          continue;
        }
        $phase_objectives[] = $this->generateObjectiveNode($obj, $variables, $context);
      }

      $objectives[] = [
        'phase' => $phase_data['phase'],
        'objectives' => $phase_objectives,
      ];
    }

    return $objectives;
  }

  /**
   * Generate one objective node, including nested child objectives.
   */
  protected function generateObjectiveNode(array $objective_schema, array $variables, array $context): array {
    $generated_obj = [
      'objective_id' => trim((string) ($objective_schema['objective_id'] ?? $objective_schema['id'] ?? 'objective')),
      'type' => $this->objectiveTypeService->determineObjectiveType($objective_schema),
      'description' => $this->resolveVariables((string) ($objective_schema['description'] ?? $objective_schema['objective_id'] ?? 'Objective'), $variables),
      'completed' => !empty($objective_schema['completed']),
    ];
    if (isset($objective_schema['next_step'])) {
      $generated_obj['next_step'] = $this->resolveVariables((string) $objective_schema['next_step'], $variables);
    }

    switch ($generated_obj['type']) {
      case 'kill':
        $target_count = $objective_schema['target_count'] ?? $this->numberGeneration->rollRange(
          $objective_schema['target_count_range'][0] ?? 5,
          $objective_schema['target_count_range'][1] ?? 10
        );
        $generated_obj['target'] = $this->resolveVariables((string) ($objective_schema['target'] ?? ''), $variables);
        $generated_obj['current'] = 0;
        $generated_obj['target_count'] = max(1, (int) $target_count);
        $variables['target_count'] = (string) $generated_obj['target_count'];
        break;

      case 'collect':
        $range = is_array($objective_schema['target_count_range'] ?? NULL) ? array_values($objective_schema['target_count_range']) : [];
        if (isset($objective_schema['target_count'])) {
          $target_count = $objective_schema['target_count'];
        }
        elseif (isset($variables['target_count']) && is_numeric($variables['target_count'])) {
          $target_count = (int) $variables['target_count'];
        }
        elseif (count($range) === 2) {
          $target_count = $this->numberGeneration->rollRange((int) $range[0], (int) $range[1]);
        }
        else {
          $target_count = $this->numberGeneration->rollRange(3, 8);
        }
        $generated_obj['item'] = $this->resolveVariables((string) ($objective_schema['item'] ?? ''), $variables);
        $generated_obj['current'] = 0;
        $generated_obj['target_count'] = max(1, (int) $target_count);
        $variables['target_count'] = (string) $generated_obj['target_count'];
        break;

      case 'explore':
        $generated_obj['location'] = $this->resolveVariables((string) ($objective_schema['location'] ?? ''), $variables);
        $generated_obj['discovered'] = FALSE;
        break;

      case 'escort':
        $generated_obj['npc_id'] = $context['escort_npc_id'] ?? NULL;
        $generated_obj['npc_ref'] = $this->resolveVariables((string) ($objective_schema['npc_ref'] ?? ''), $variables);
        $generated_obj['target'] = $this->resolveVariables((string) ($objective_schema['target'] ?? ''), $variables);
        $generated_obj['destination'] = $this->resolveVariables((string) ($objective_schema['destination'] ?? ''), $variables);
        $generated_obj['arrived'] = FALSE;
        $path_encounters = $this->buildEscortPathEncounters($objective_schema, $variables, $generated_obj['target'], $generated_obj['destination']);
        if ($path_encounters !== []) {
          $generated_obj['path_encounters'] = $path_encounters;
          $generated_obj['children'] = $this->buildEscortRuntimeObjectives(
            $generated_obj['objective_id'],
            $path_encounters,
            $generated_obj['target'],
            $generated_obj['destination']
          );
          $generated_obj['completion_criteria'] = [
            'kind' => 'all_children',
            'metric' => 'children_completed',
            'required_value' => TRUE,
            'description' => $generated_obj['target'] !== '' && $generated_obj['destination'] !== ''
              ? 'Resolve the escort journey beats and get ' . $generated_obj['target'] . ' safely to ' . $generated_obj['destination'] . '.'
              : 'Resolve the escort journey beats and reach the destination safely.',
          ];
        }
        break;

      case 'interact':
        $generated_obj['target'] = $this->resolveVariables((string) ($objective_schema['target'] ?? ''), $variables);
        break;

      case 'investigate':
        $generated_obj['target'] = $this->resolveVariables((string) ($objective_schema['target'] ?? ''), $variables);
        if (!array_key_exists('location', $objective_schema) && !array_key_exists('location_id', $objective_schema) && $generated_obj['target'] !== '') {
          $generated_obj['location'] = $generated_obj['target'];
        }
        $generated_obj['discovered'] = FALSE;
        $generated_obj['current'] = 0;
        $generated_obj['target_count'] = max(1, (int) ($objective_schema['target_count'] ?? 1));
        break;
    }

    foreach (['location', 'location_id', 'destination', 'destination_id'] as $field) {
      if (array_key_exists($field, $objective_schema)) {
        $generated_obj[$field] = $this->resolveVariables((string) ($objective_schema[$field] ?? ''), $variables);
      }
    }

    $children = [];
    foreach ($this->extractNestedObjectiveDefinitions($objective_schema) as $child_schema) {
      $children[] = $this->generateObjectiveNode($child_schema, $variables, $context);
    }
    if ($children !== []) {
      $generated_obj['children'] = $children;
    }

    $criteria_schema = $objective_schema['completion_criteria'] ?? [];
    if (is_array($criteria_schema)) {
      foreach ($criteria_schema as $criteria_key => $criteria_value) {
        if (is_string($criteria_value)) {
          $criteria_schema[$criteria_key] = $this->resolveVariables($criteria_value, $variables);
        }
      }
    }
    $generated_obj['completion_criteria'] = $this->normalizeObjectiveCompletionCriteria($criteria_schema, $generated_obj);
    if (($generated_obj['completion_criteria']['kind'] ?? '') === 'count' && isset($generated_obj['target_count'])) {
      $generated_obj['completion_criteria']['target_count'] = max(1, (int) $generated_obj['target_count']);
    }
    return $generated_obj;
  }

  /**
   * Build generated path encounters for one escort objective.
   *
   * @return array<int, array<string, mixed>>
   *   Generated encounter descriptors.
   */
  protected function buildEscortPathEncounters(array $objective_schema, array $variables, string $escort_target, string $destination): array {
    $range = is_array($objective_schema['encounter_count_range'] ?? NULL) ? array_values($objective_schema['encounter_count_range']) : [];
    if (count($range) !== 2) {
      return [];
    }

    $minimum = max(1, (int) ($range[0] ?? 1));
    $maximum = max($minimum, (int) ($range[1] ?? $minimum));
    $encounter_count = $this->numberGeneration->rollRange($minimum, $maximum);

    $encounter_types = $this->normalizeEscortEncounterList(
      is_array($objective_schema['encounter_types'] ?? NULL) ? $objective_schema['encounter_types'] : [],
      ['social', 'problem_solving', 'combat']
    );
    $encounter_profiles = $this->normalizeEscortEncounterList(
      is_array($objective_schema['encounter_profiles'] ?? NULL) ? $objective_schema['encounter_profiles'] : [],
      ['negotiation_collapse', 'chase_transition', 'ambush']
    );

    $encounters = [];
    $target_label = $escort_target !== '' ? $escort_target : 'the escort target';
    $destination_label = $destination !== '' ? $destination : 'safety';
    $objective_id = strtolower(trim((string) ($objective_schema['objective_id'] ?? 'escort')));
    $objective_id = preg_replace('/[^a-z0-9]+/', '_', $objective_id) ?? 'escort';
    $objective_id = trim($objective_id, '_') ?: 'escort';

    for ($index = 1; $index <= $encounter_count; $index++) {
      $encounter_type = $encounter_types[($index - 1) % count($encounter_types)];
      $setup_profile = $encounter_profiles[($index - 1) % count($encounter_profiles)];
      $encounters[] = [
        'encounter_id' => $objective_id . '_path_encounter_' . $index,
        'sequence' => $index,
        'encounter_type' => $encounter_type,
        'setup_profile' => $setup_profile,
        'description' => $this->buildEscortEncounterDescription($encounter_type, $setup_profile, $target_label, $destination_label),
        'target' => $target_label,
        'destination' => $destination_label,
        'resolved' => FALSE,
      ];
    }

    return $encounters;
  }

  /**
   * Materialize escort path encounters into runtime child objectives.
   *
   * @return array<int, array<string, mixed>>
   *   Runtime child objectives.
   */
  protected function buildEscortRuntimeObjectives(string $parent_objective_id, array $path_encounters, string $escort_target, string $destination): array {
    $children = [];

    foreach ($path_encounters as $index => $encounter) {
      if (!is_array($encounter)) {
        continue;
      }

      $sequence = max(1, (int) ($encounter['sequence'] ?? ($index + 1)));
      $encounter_id = trim((string) ($encounter['encounter_id'] ?? $parent_objective_id . '_path_encounter_' . $sequence));
      if ($encounter_id === '') {
        continue;
      }

      $children[] = [
        'objective_id' => $parent_objective_id . '_runtime_' . $sequence,
        'type' => 'interact',
        'target' => $encounter_id,
        'description' => trim((string) ($encounter['description'] ?? ('Handle escort encounter ' . $sequence))),
        'completed' => !empty($encounter['resolved']),
        'hidden' => $sequence > 1,
        'encounter_id' => $encounter_id,
        'encounter_type' => trim((string) ($encounter['encounter_type'] ?? 'encounter')),
        'setup_profile' => trim((string) ($encounter['setup_profile'] ?? 'escort')),
        'completion_criteria' => [
          'kind' => 'flag',
          'metric' => 'completed',
          'required_value' => TRUE,
          'description' => trim((string) ($encounter['description'] ?? 'Resolve this escort encounter.')),
        ],
      ];
    }

    $arrival_description = $escort_target !== '' && $destination !== ''
      ? 'Get ' . $escort_target . ' safely to ' . $destination . '.'
      : ($destination !== '' ? 'Reach ' . $destination . ' safely.' : 'Reach the escort destination safely.');
    $children[] = [
      'objective_id' => $parent_objective_id . '_arrive',
      'type' => 'explore',
      'location' => $destination,
      'description' => $arrival_description,
      'completed' => FALSE,
      'hidden' => $path_encounters !== [],
      'escort_arrival' => TRUE,
      'completion_criteria' => [
        'kind' => 'flag',
        'metric' => 'discovered',
        'required_value' => TRUE,
        'description' => $arrival_description,
      ],
    ];

    return $children;
  }

  /**
   * Normalize one escort encounter metadata list.
   *
   * @param array<int|string, mixed> $values
   *   Candidate values.
   * @param array<int, string> $fallback
   *   Fallback values.
   *
   * @return array<int, string>
   *   Normalized list.
   */
  protected function normalizeEscortEncounterList(array $values, array $fallback): array {
    $normalized = array_values(array_filter(array_map(function (mixed $value): string {
      return trim((string) $value);
    }, $values)));

    return $normalized !== [] ? $normalized : $fallback;
  }

  /**
   * Build a human-readable escort encounter description.
   */
  protected function buildEscortEncounterDescription(string $encounter_type, string $setup_profile, string $escort_target, string $destination): string {
    return match (strtolower($encounter_type)) {
      'combat' => 'Overcome a ' . str_replace('_', ' ', strtolower($setup_profile)) . ' while protecting ' . $escort_target . ' on the road to ' . $destination . '.',
      'hazard' => 'Navigate a ' . str_replace('_', ' ', strtolower($setup_profile)) . ' hazard without losing ' . $escort_target . ' before reaching ' . $destination . '.',
      'stealth' => 'Slip ' . $escort_target . ' past a ' . str_replace('_', ' ', strtolower($setup_profile)) . ' obstacle on the way to ' . $destination . '.',
      'problem_solving' => 'Resolve a ' . str_replace('_', ' ', strtolower($setup_profile)) . ' complication so ' . $escort_target . ' can keep moving toward ' . $destination . '.',
      default => 'Handle a ' . str_replace('_', ' ', strtolower($setup_profile)) . ' encounter while escorting ' . $escort_target . ' toward ' . $destination . '.',
    };
  }

  /**
   * Scale rewards based on party level and difficulty.
   *
   * @param array $rewards_schema
   *   Rewards schema from template.
   * @param int $party_level
   *   Average party level.
   * @param string $difficulty
   *   Difficulty: trivial, low, moderate, severe, extreme.
   *
   * @return array
   *   Scaled rewards.
   */
  protected function scaleRewards(
    array $rewards_schema,
    int $party_level,
    string $difficulty
  ): array {
    $difficulty_multipliers = [
      'trivial' => 0.5,
      'low' => 0.75,
      'moderate' => 1.0,
      'severe' => 1.5,
      'extreme' => 2.0,
    ];

    $multiplier = $difficulty_multipliers[$difficulty] ?? 1.0;

    $rewards = [];

    // Scale XP
    if (isset($rewards_schema['xp'])) {
      $base_xp = $rewards_schema['xp']['base'] ?? 100;
      $per_level_xp = $rewards_schema['xp']['per_level'] ?? 20;
      $rewards['xp'] = (int) (($base_xp + ($per_level_xp * $party_level)) * $multiplier);
    }

    // Scale gold
    if (isset($rewards_schema['gold'])) {
      $base_gold = $rewards_schema['gold']['base'] ?? 10;
      $per_level_gold = $rewards_schema['gold']['per_level'] ?? 5;
      $gold = (int) (($base_gold + ($per_level_gold * $party_level)) * $multiplier);

      if (!empty($rewards_schema['gold']['randomize'])) {
        $variance = (int) ($gold * 0.3); // 30% variance
        $gold = $this->numberGeneration->rollRange(
          max(1, $gold - $variance),
          $gold + $variance
        );
      }

      $rewards['gold'] = $gold;
    }

    // Items (TODO: Integrate with loot tables)
    if (isset($rewards_schema['items'])) {
      $rewards['items'] = [];
      // Placeholder for loot table integration
    }

    // Reputation
    if (isset($rewards_schema['reputation'])) {
      $rewards['reputation'] = $rewards_schema['reputation'];
    }

    return $rewards;
  }

  /**
   * Decode a quest field that may be JSON or an already-normalized array.
   */
  protected function decodeQuestField(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }

    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Decode a quest object field and force an object-shaped array.
   */
  protected function decodeQuestObjectField(mixed $value): array {
    $decoded = $this->decodeQuestField($value);
    return array_is_list($decoded) ? [] : $decoded;
  }

  /**
   * Normalize objective phases into the quest summary schema shape.
   */
  protected function normalizeQuestObjectivePhases(array $phases): array {
    $normalized = [];
    foreach ($phases as $phase_index => $phase) {
      if (!is_array($phase)) {
        continue;
      }

      $objectives = [];
      foreach ((array) ($phase['objectives'] ?? []) as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        $objectives[] = $this->normalizeQuestObjective($objective);
      }

      $normalized[] = [
        'phase' => max(1, (int) ($phase['phase'] ?? ($phase_index + 1))),
        'objectives' => $objectives,
      ];
    }

    return $normalized;
  }

  /**
   * Normalize a single objective into the schema-allowed field set.
   */
  protected function normalizeQuestObjective(array $objective): array {
    $normalized = [
      'objective_id' => trim((string) ($objective['objective_id'] ?? $objective['id'] ?? 'objective')),
      'type' => $this->objectiveTypeService->determineObjectiveType($objective),
      'description' => trim((string) ($objective['description'] ?? $objective['objective_id'] ?? 'Objective')),
      'completed' => !empty($objective['completed']),
    ];

    $optional_integer_fields = ['current', 'target_count'];
    foreach ($optional_integer_fields as $field) {
      if (isset($objective[$field])) {
        $normalized[$field] = max(0, (int) $objective[$field]);
      }
    }

    $optional_string_fields = ['target', 'item', 'location', 'location_id', 'destination', 'destination_id', 'next_step'];
    foreach ($optional_string_fields as $field) {
      if (isset($objective[$field]) && $objective[$field] !== '') {
        $normalized[$field] = trim((string) $objective[$field]);
      }
    }

    if (array_key_exists('npc_id', $objective)) {
      $normalized['npc_id'] = $objective['npc_id'] === NULL ? NULL : (int) $objective['npc_id'];
    }

    $optional_boolean_fields = ['discovered', 'arrived', 'revealed', 'hidden'];
    foreach ($optional_boolean_fields as $field) {
      if (array_key_exists($field, $objective)) {
        $normalized[$field] = !empty($objective[$field]);
      }
    }

    $children = [];
    foreach ($this->extractNestedObjectiveDefinitions($objective) as $child_objective) {
      $children[] = $this->normalizeQuestObjective($child_objective);
    }
    if ($children !== []) {
      $normalized['children'] = $children;
    }

    $normalized['completion_criteria'] = $this->normalizeObjectiveCompletionCriteria($objective['completion_criteria'] ?? [], $normalized);
    if (($normalized['completion_criteria']['kind'] ?? '') === 'count' && !isset($normalized['target_count'])) {
      $normalized['target_count'] = max(1, (int) ($normalized['completion_criteria']['target_count'] ?? 1));
    }

    return $normalized;
  }

  /**
   * Return nested child-objective definitions from any supported objective shape.
   */
  protected function extractNestedObjectiveDefinitions(array $objective): array {
    return $this->objectiveTypeService->extractNestedObjectiveDefinitions($objective);
  }

  /**
   * Normalize objective completion criteria into a stable contract.
   */
  protected function normalizeObjectiveCompletionCriteria(mixed $criteria, array $objective): array {
    return $this->objectiveTypeService->normalizeCompletionCriteria($criteria, $objective);
  }

  /**
   * Build default completion rules for one objective node.
   */
  protected function buildDefaultObjectiveCompletionCriteria(array $objective): array {
    return $this->objectiveTypeService->buildDefaultCompletionCriteria($objective);
  }

  /**
   * Normalize a nullable string field.
   */
  protected function normalizeNullableString(mixed $value): ?string {
    if ($value === NULL) {
      return NULL;
    }

    $normalized = trim((string) $value);
    return $normalized === '' ? NULL : $normalized;
  }

  /**
   * Build the nested quest-management tree used by the quest journal tab.
   */
  public function buildQuestManagementTree(
    int $campaign_id,
    array $active_entries = [],
    array $available_entries = [],
    ?string $current_location_id = NULL
  ): array {
    if ($campaign_id <= 0) {
      return [];
    }

    $current_location_id = $this->normalizeNullableString($current_location_id);
    $storylines = $this->loadCampaignStorylineRows($campaign_id);
    $contact_items = $this->loadCampaignStorylineContactItems($campaign_id);
    $contact_items_by_storyline = [];
    foreach ($contact_items as $item) {
      $storyline_id = trim((string) ($item['storyline_id'] ?? ''));
      if ($storyline_id !== '') {
        $contact_items_by_storyline[$storyline_id] = $item;
      }
    }

    $quest_entries = [];
    foreach (array_merge($available_entries, $active_entries) as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $quest_id = trim((string) ($entry['quest_id'] ?? ''));
      if ($quest_id === '') {
        continue;
      }
      $quest_entries[$quest_id] = $entry;
    }

    $npc_tree = [];
    $attached_storylines = [];
    foreach ($storylines as $storyline_row) {
      $storyline_contract = $this->buildStorylineManagementContract(
        $storyline_row,
        $contact_items_by_storyline[(string) ($storyline_row['storyline_id'] ?? '')] ?? [],
        $quest_entries,
        $current_location_id
      );
      if ($storyline_contract === []) {
        continue;
      }

      foreach ($this->resolveStorylineNpcAnchors($storyline_contract) as $npc_anchor) {
        $npc_key = strtolower(trim((string) ($npc_anchor['npc_id'] ?? '')));
        if ($npc_key === '') {
          continue;
        }

        if (!isset($npc_tree[$npc_key])) {
          $npc_tree[$npc_key] = [
            'npc_id' => (string) ($npc_anchor['npc_id'] ?? ''),
            'npc_name' => (string) ($npc_anchor['npc_name'] ?? $npc_anchor['npc_id'] ?? 'Unknown Quest Giver'),
            'role' => (string) ($npc_anchor['role'] ?? 'quest_giver'),
            'location' => $this->normalizeManagementLocation($npc_anchor['location'] ?? []),
            'next_step' => trim((string) ($npc_anchor['next_step'] ?? 'Review available leads from this contact.')),
            'access' => $this->normalizeManagementAccess($npc_anchor['access'] ?? NULL),
            'storylines' => [],
          ];
        }

        $storyline_key = (string) ($storyline_contract['storyline_id'] ?? '');
        if ($storyline_key === '') {
          continue;
        }

        $attachment_key = $npc_key . ':' . $storyline_key;
        if (!isset($attached_storylines[$attachment_key])) {
          $npc_tree[$npc_key]['storylines'][] = $storyline_contract;
          $attached_storylines[$attachment_key] = TRUE;
        }
      }
    }

    foreach ($quest_entries as $quest_entry) {
      $storyline_id = trim((string) ($quest_entry['storyline']['storyline_id'] ?? ''));
      if ($storyline_id !== '') {
        continue;
      }

      $quest_contract = $this->buildStandaloneQuestManagementEntry($quest_entry, $current_location_id);
      $npc_anchor = $this->resolveStandaloneQuestNpcAnchor($campaign_id, $quest_entry, $quest_contract);
      $npc_key = strtolower(trim((string) ($npc_anchor['npc_id'] ?? '')));
      if ($npc_key === '') {
        $npc_key = 'unknown-quest-giver';
        $npc_anchor['npc_id'] = $npc_key;
        $npc_anchor['npc_name'] = 'Unknown Quest Giver';
      }

      if (!isset($npc_tree[$npc_key])) {
        $npc_tree[$npc_key] = [
          'npc_id' => (string) ($npc_anchor['npc_id'] ?? ''),
          'npc_name' => (string) ($npc_anchor['npc_name'] ?? 'Unknown Quest Giver'),
          'role' => (string) ($npc_anchor['role'] ?? 'quest_giver'),
          'location' => $this->normalizeManagementLocation($npc_anchor['location'] ?? []),
          'next_step' => trim((string) ($npc_anchor['next_step'] ?? 'Review this quest.')),
          'access' => $this->normalizeManagementAccess($npc_anchor['access'] ?? NULL),
          'storylines' => [],
        ];
      }

      $npc_tree[$npc_key]['storylines'][] = [
        'storyline_id' => 'standalone:' . (string) ($quest_contract['quest_id'] ?? ''),
        'template_id' => NULL,
        'name' => 'Standalone Quests',
        'synopsis' => 'Quest work not currently attached to a campaign storyline.',
        'status' => (string) ($quest_contract['status'] ?? 'lead'),
        'priority' => 0,
        'storyline_type' => 'questline',
        'metadata' => [
          'goal' => trim((string) ($quest_contract['next_step'] ?? 'Advance the quest.')),
          'generated_outline' => [],
        ],
        'chapters' => [],
        'linked_quests' => [],
        'questline' => [
          'primary_quest_id' => (string) ($quest_contract['quest_id'] ?? ''),
          'ordered_quest_ids' => [(string) ($quest_contract['quest_id'] ?? '')],
          'quest_nodes' => [],
        ],
        'asset_references' => [],
        'contacts' => [],
        'current_chapter_id' => NULL,
        'current_scene_id' => NULL,
        'location' => $quest_contract['location'],
        'next_step' => $quest_contract['next_step'],
        'access' => $quest_contract['access'],
        'quests' => [$quest_contract],
      ];
    }

    $tree = array_values(array_map(function (array $npc): array {
      $npc['storylines'] = $this->sortManagementStorylines($npc['storylines'] ?? []);
      if ($npc['storylines'] !== []) {
        $npc['location'] = $this->normalizeManagementLocation(($npc['storylines'][0]['location'] ?? $npc['location'] ?? []));
        $npc['next_step'] = trim((string) ($npc['storylines'][0]['next_step'] ?? $npc['next_step'] ?? 'Review available leads.'));
        $npc['access'] = $this->normalizeManagementAccess($npc['storylines'][0]['access'] ?? $npc['access'] ?? NULL);
      }
      return $npc;
    }, $npc_tree));

    usort($tree, function (array $a, array $b): int {
      $access_compare = $this->compareManagementAccess($a['access'] ?? NULL, $b['access'] ?? NULL);
      if ($access_compare !== 0) {
        return $access_compare;
      }
      return strcasecmp((string) ($a['npc_name'] ?? ''), (string) ($b['npc_name'] ?? ''));
    });

    return $tree;
  }

  /**
   * Load hydrated storyline rows for one campaign.
   */
  protected function loadCampaignStorylineRows(int $campaign_id): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_storylines')) {
      return [];
    }

    if ($this->storylineManager !== NULL) {
      try {
        return $this->storylineManager->listCampaignStorylines($campaign_id, FALSE);
      }
      catch (\Throwable $exception) {
        $this->logger->warning('Failed to hydrate storyline rows for campaign @campaign_id before quest-summary management build: @message', [
          '@campaign_id' => $campaign_id,
          '@message' => $exception->getMessage(),
        ]);
      }
    }

    return $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->orderBy('is_primary', 'DESC')
      ->orderBy('priority', 'DESC')
      ->orderBy('created_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Load cached storyline-contact summaries from campaign state.
   */
  protected function loadCampaignStorylineContactItems(int $campaign_id): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaigns')) {
      return [];
    }

    $row = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data'])
      ->condition('id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return [];
    }

    $campaign_data = json_decode((string) ($row['campaign_data'] ?? '{}'), TRUE);
    $state = is_array($campaign_data['state'] ?? NULL) ? $campaign_data['state'] : [];
    $contacts = $state['storyline_contacts']['items'] ?? [];
    return is_array($contacts) ? array_values(array_filter($contacts, 'is_array')) : [];
  }

  /**
   * Build the full storyline contract plus nested quest-management metadata.
   */
  protected function buildStorylineManagementContract(
    array $storyline_row,
    array $contact_summary,
    array $quest_entries,
    ?string $current_location_id
  ): array {
    $storyline_id = trim((string) ($storyline_row['storyline_id'] ?? ''));
    if ($storyline_id === '') {
      return [];
    }

    $storyline_data = $this->decodeQuestObjectField($storyline_row['storyline_data'] ?? []);
    $this->assertValidManagementStorylineData($storyline_row, $storyline_data);
    $scene_index = $this->buildStorylineSceneIndex($storyline_data);
    $lead_location = $this->resolveStorylineLeadLocation($storyline_row, $storyline_data, $contact_summary, $scene_index);
    $quests = $this->buildStorylineQuestManagementEntries(
      $storyline_row,
      $storyline_data,
      $quest_entries,
      $lead_location,
      $scene_index,
      $current_location_id
    );
    if ($quests === []) {
      return [];
    }
    $questline = is_array($storyline_data['questline'] ?? NULL) ? $storyline_data['questline'] : [];
    $contacts = array_values(array_filter(is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : [], 'is_array'));
    $metadata = is_array($storyline_data['metadata'] ?? NULL) ? $storyline_data['metadata'] : [];

    $next_step = $this->deriveStorylineNextStep($storyline_data, $quests, $contact_summary);
    $storyline_access = $this->normalizeManagementAccess($quests[0]['access'] ?? [
      'is_clear' => !empty($lead_location['label']),
      'is_accessible' => !empty($lead_location['id']) || !empty($lead_location['label']),
      'sort_bucket' => !empty($lead_location['id']) || !empty($lead_location['label']) ? 'ready' : 'unclear',
    ]);

    return [
      'storyline_id' => $storyline_id,
      'template_id' => $this->normalizeNullableString($storyline_row['template_id'] ?? ($storyline_data['template_id'] ?? NULL)),
      'name' => trim((string) ($storyline_row['name'] ?? $storyline_data['name'] ?? $storyline_id)),
      'synopsis' => trim((string) ($storyline_data['synopsis'] ?? $metadata['synopsis'] ?? '')),
      'status' => trim((string) ($storyline_row['status'] ?? 'available')) ?: 'available',
      'priority' => (int) ($storyline_row['priority'] ?? 0),
      'storyline_type' => trim((string) ($storyline_data['storyline_type'] ?? 'questline')) ?: 'questline',
      'metadata' => $metadata,
      'chapters' => array_values(array_filter(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : [], 'is_array')),
      'linked_quests' => is_array($storyline_data['linked_quests'] ?? NULL) ? $storyline_data['linked_quests'] : [],
      'questline' => $questline,
      'asset_references' => array_values(array_filter(is_array($storyline_data['asset_references'] ?? NULL) ? $storyline_data['asset_references'] : [], 'is_array')),
      'contacts' => $contacts,
      'current_chapter_id' => $this->normalizeNullableString($storyline_row['current_chapter_id'] ?? ($storyline_data['current_chapter_id'] ?? NULL)),
      'current_scene_id' => $this->normalizeNullableString($storyline_row['current_scene_id'] ?? ($storyline_data['current_scene_id'] ?? NULL)),
      'location' => $lead_location,
      'next_step' => $next_step,
      'access' => $storyline_access,
      'broker' => is_array($contact_summary['broker'] ?? NULL) ? $contact_summary['broker'] : NULL,
      'quest_giver' => is_array($contact_summary['quest_giver'] ?? NULL) ? $contact_summary['quest_giver'] : NULL,
      'lead_location' => is_array($contact_summary['lead_location'] ?? NULL) ? $contact_summary['lead_location'] : $lead_location,
      'quests' => $this->sortManagementQuests($quests),
    ];
  }

  /**
   * Fail closed when verification views encounter an invalid stored storyline.
   */
  protected function assertValidManagementStorylineData(array $storyline_row, array $storyline_data): void {
    if ($this->storylineManager === NULL) {
      return;
    }

    $validation = $this->storylineManager->validateRuntimeStorylineContract($storyline_data);
    if (!($validation['valid'] ?? FALSE)) {
      $storyline_id = trim((string) ($storyline_row['storyline_id'] ?? ''));
      $label = $storyline_id !== '' ? "storyline {$storyline_id}" : 'storyline runtime payload';
      throw new \InvalidArgumentException('Storyline management contract failed validation for ' . $label . ': ' . implode('; ', $validation['errors'] ?? []), 400);
    }
  }

  /**
   * Build nested quest entries for one storyline.
   */
  protected function buildStorylineQuestManagementEntries(
    array $storyline_row,
    array $storyline_data,
    array $quest_entries,
    array $lead_location,
    array $scene_index,
    ?string $current_location_id
  ): array {
    $quests = [];
    $seen_materialized_quest_ids = [];
    $quest_nodes = array_values(is_array($storyline_data['questline']['quest_nodes'] ?? NULL) ? $storyline_data['questline']['quest_nodes'] : []);
    $ordered_quest_ids = array_values(is_array($storyline_data['questline']['ordered_quest_ids'] ?? NULL) ? $storyline_data['questline']['ordered_quest_ids'] : []);
    $linked_quests = is_array($storyline_data['linked_quests'] ?? NULL) ? $storyline_data['linked_quests'] : [];

    $matching_entries = [];
    foreach ($quest_entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      if ((string) ($entry['storyline']['storyline_id'] ?? '') !== (string) ($storyline_row['storyline_id'] ?? '')) {
        continue;
      }
      $node_key = (string) ($entry['source_template_id'] ?? $entry['quest_key'] ?? $entry['quest_id'] ?? '');
      if ($node_key !== '') {
        $matching_entries[$node_key] = $entry;
      }
      $matching_entries[(string) ($entry['quest_id'] ?? '')] = $entry;
    }

    $status_by_node = [];
    foreach ($ordered_quest_ids as $ordered_quest_id) {
      $entry = $matching_entries[$ordered_quest_id] ?? NULL;
      $status_by_node[$ordered_quest_id] = strtolower(trim((string) ($entry['status'] ?? ($linked_quests[$ordered_quest_id]['status'] ?? 'lead'))));
    }

    foreach ($ordered_quest_ids as $ordered_quest_id) {
      $quest_node = $this->findQuestNodeById($quest_nodes, $ordered_quest_id);
      $linked_quest = is_array($linked_quests[$ordered_quest_id] ?? NULL) ? $linked_quests[$ordered_quest_id] : [];
      $materialized_entry = $matching_entries[$ordered_quest_id] ?? NULL;
      $blocked = $this->isQuestNodeBlocked($quest_node, $status_by_node);
      $is_discovered = $this->isStorylineQuestDiscovered($storyline_row, $storyline_data, $quest_node, $linked_quest, $materialized_entry);
      if (!$is_discovered && !$materialized_entry) {
        continue;
      }
      $quest_contract = $materialized_entry
        ? $this->buildMaterializedQuestManagementEntry($materialized_entry, $quest_node, $linked_quest, $scene_index, $lead_location, $blocked, $current_location_id, is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : [])
        : $this->buildStorylineQuestPlaceholderEntry($ordered_quest_id, $quest_node, $linked_quest, $scene_index, $lead_location, $blocked, $current_location_id, is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : []);
      if ($quest_contract === []) {
        continue;
      }
      $quests[] = $quest_contract;
      if ($materialized_entry) {
        $seen_materialized_quest_ids[(string) ($quest_contract['quest_id'] ?? '')] = TRUE;
      }
    }

    foreach ($matching_entries as $node_key => $materialized_entry) {
      if (!is_array($materialized_entry) || in_array($node_key, $ordered_quest_ids, TRUE)) {
        continue;
      }
      $quest_id = trim((string) ($materialized_entry['quest_id'] ?? ''));
      if ($quest_id !== '' && isset($seen_materialized_quest_ids[$quest_id])) {
        continue;
      }
      $quest_contract = $this->buildMaterializedQuestManagementEntry(
        $materialized_entry,
        [],
        [],
        $scene_index,
        $lead_location,
        FALSE,
        $current_location_id,
        is_array($storyline_data['contacts'] ?? NULL) ? $storyline_data['contacts'] : []
      );
      if ($quest_contract === []) {
        continue;
      }
      $quests[] = $quest_contract;
      if ($quest_id !== '') {
        $seen_materialized_quest_ids[$quest_id] = TRUE;
      }
    }

    return $quests;
  }

  /**
   * Build one quest entry from a materialized quest row.
   */
  protected function buildMaterializedQuestManagementEntry(
    array $quest_entry,
    array $quest_node,
    array $linked_quest,
    array $scene_index,
    array $lead_location,
    bool $blocked,
    ?string $current_location_id,
    array $storyline_contacts = []
  ): array {
    $quest = $this->buildQuestSummaryEntry($quest_entry);
    $scene_id = $this->normalizeNullableString($quest['storyline']['scene_id'] ?? ($linked_quest['scene_id'] ?? NULL));
    $chapter_id = $this->normalizeNullableString($quest['storyline']['chapter_id'] ?? ($linked_quest['chapter_id'] ?? NULL));
    $location = $this->resolveQuestManagementLocation($quest, $scene_index, $lead_location, $scene_id, $chapter_id);
    $status = strtolower(trim((string) ($quest['status'] ?? 'lead')));
    $access = $this->buildManagementAccessDescriptor($location, !$blocked, $current_location_id, $status);
    $objectives = $this->buildQuestManagementObjectives($quest, $location, $blocked, $current_location_id, $storyline_contacts);
    if ($objectives === [] && !$this->shouldRetainQuestWithoutVisibleObjectives($quest, $quest_node, $linked_quest)) {
      return [];
    }
    if ($objectives !== []) {
      $access = $this->normalizeManagementAccess($objectives[0]['access'] ?? $access);
    }

    $quest['location'] = $location;
    $quest['next_step'] = $this->deriveQuestNextStep($quest, $objectives, $scene_index, $scene_id, $chapter_id, $lead_location, $blocked);
    $quest['access'] = $access;
    $quest['objectives'] = $this->sortManagementObjectives($objectives);
    return $quest;
  }

  /**
   * Build one placeholder quest entry from storyline questline metadata.
   */
  protected function buildStorylineQuestPlaceholderEntry(
    string $quest_node_id,
    array $quest_node,
    array $linked_quest,
    array $scene_index,
    array $lead_location,
    bool $blocked,
    ?string $current_location_id,
    array $storyline_contacts = []
  ): array {
    $scene_id = $this->normalizeNullableString($quest_node['scene_id'] ?? ($linked_quest['scene_id'] ?? NULL));
    $chapter_id = $this->normalizeNullableString($quest_node['chapter_id'] ?? ($linked_quest['chapter_id'] ?? NULL));
    $location = $this->resolveSceneOrLeadLocation($scene_index, $scene_id, $chapter_id, $lead_location);
    $access = $this->buildManagementAccessDescriptor($location, !$blocked, $current_location_id, 'lead');
    $scene_meta = $this->resolveSceneMeta($scene_index, $scene_id, $chapter_id);
    $objective = [
      'objective_id' => $quest_node_id . '--followup',
      'phase' => 1,
      'type' => 'explore',
      'description' => trim((string) ($scene_meta['summary'] ?? 'Follow the lead for this quest.')),
      'completed' => FALSE,
      'revealed' => TRUE,
      'item' => NULL,
      'target' => trim((string) ($scene_meta['name'] ?? $quest_node_id)),
      'completion_criteria' => [
        'kind' => 'flag',
        'metric' => 'discovered',
        'required_value' => TRUE,
        'description' => 'Discover the required location.',
      ],
      'location' => $location,
      'next_step' => trim((string) ($scene_meta['summary'] ?? 'Travel to the next known location.')),
      'access' => $access,
    ];

    return [
      'quest_id' => $quest_node_id,
      'quest_key' => $quest_node_id,
      'source_template_id' => $quest_node_id,
      'title' => trim((string) ($scene_meta['name'] ?? $quest_node_id)),
      'quest_name' => trim((string) ($scene_meta['name'] ?? $quest_node_id)),
      'status' => trim((string) ($quest_node['status'] ?? ($blocked ? 'blocked' : 'lead'))) ?: ($blocked ? 'blocked' : 'lead'),
      'current_phase' => 1,
      'generated_objectives' => [],
      'objective_states' => [],
      'generated_rewards' => [],
      'quest_data' => [],
      'location_id' => $location['id'],
      'storyline' => [
        'storyline_id' => NULL,
        'chapter_id' => $chapter_id,
        'scene_id' => $scene_id,
      ],
      'location' => $location,
      'next_step' => trim((string) ($objective['next_step'] ?? 'Follow the lead.')),
      'access' => $access,
      'objectives' => [$objective],
    ];
  }

  /**
   * Build one standalone quest entry for quests outside storyline tracking.
   */
  protected function buildStandaloneQuestManagementEntry(array $quest_entry, ?string $current_location_id): array {
    $quest = $this->buildQuestSummaryEntry($quest_entry);
    $location = $this->normalizeManagementLocation([
      'id' => $this->normalizeNullableString($quest['location_id'] ?? NULL),
      'label' => $this->humanizeIdentifier($quest['location_id'] ?? 'campaign'),
    ]);
    $access = $this->buildManagementAccessDescriptor($location, TRUE, $current_location_id, (string) ($quest['status'] ?? 'lead'));
    $objectives = $this->buildQuestManagementObjectives($quest, $location, FALSE, $current_location_id, []);

    $quest['location'] = $location;
    $quest['next_step'] = $this->deriveQuestNextStep($quest, $objectives, [], NULL, NULL, $location, FALSE);
    $quest['access'] = $this->normalizeManagementAccess($objectives[0]['access'] ?? $access);
    $quest['objectives'] = $this->sortManagementObjectives($objectives);
    return $quest;
  }

  /**
   * Build objective rows for the quest-management tree.
   */
  protected function buildQuestManagementObjectives(
    array $quest,
    array $fallback_location,
    bool $blocked,
    ?string $current_location_id,
    array $storyline_contacts = []
  ): array {
    $phases = $quest['objective_states'] !== [] ? $quest['objective_states'] : $quest['generated_objectives'];
    $objectives = [];
    foreach ($phases as $phase) {
      $phase_number = max(1, (int) ($phase['phase'] ?? 1));
      foreach ((array) ($phase['objectives'] ?? []) as $objective) {
        if (!is_array($objective)) {
          continue;
        }
        $node = $this->buildQuestManagementObjectiveNode(
          $objective,
          $phase_number,
          $fallback_location,
          $blocked,
          $current_location_id,
          $quest,
          $storyline_contacts
        );
        if ($node !== []) {
          $objectives[] = $node;
        }
      }
    }
    return $objectives;
  }

  /**
   * Build one nested objective-management entry.
   */
  protected function buildQuestManagementObjectiveNode(
    array $objective,
    int $phase_number,
    array $fallback_location,
    bool $blocked,
    ?string $current_location_id,
    array $quest,
    array $storyline_contacts = []
  ): array {
    $completed = !empty($objective['completed']);
    $revealed = $this->isObjectiveVisibleInJournal($objective, $phase_number, $quest);
    $location = $this->resolveQuestManagementObjectiveLocation($objective, $fallback_location, $storyline_contacts);
    $description = trim((string) ($objective['description'] ?? $objective['objective_id'] ?? 'Objective'));
    $access = $this->buildManagementAccessDescriptor(
      $location,
      !$blocked && ($phase_number <= max(1, (int) ($quest['current_phase'] ?? 1))),
      $current_location_id,
      $completed ? 'completed' : (string) ($quest['status'] ?? 'active')
    );

    $children = [];
    foreach ($this->extractNestedObjectiveDefinitions($objective) as $child_objective) {
      $child_node = $this->buildQuestManagementObjectiveNode(
        $child_objective,
        $phase_number,
        $location,
        $blocked,
        $current_location_id,
        $quest,
        $storyline_contacts
      );
      if ($child_node !== []) {
        $children[] = $child_node;
      }
    }
    $children = $this->sortManagementObjectives($children);
    if (!$revealed && !$completed && $children === []) {
      return [];
    }
    if ($children !== []) {
      $access = $this->normalizeManagementAccess($children[0]['access'] ?? $access);
    }

    $resolved_target = $this->resolveQuestManagementObjectiveTarget($objective, $quest, $storyline_contacts);
    $objective_for_next_step = $objective;
    if ($resolved_target !== NULL) {
      $objective_for_next_step['target'] = $resolved_target;
    }

    $entry = [
      'objective_id' => trim((string) ($objective['objective_id'] ?? 'objective')),
      'phase' => $phase_number,
      'type' => trim((string) ($objective['type'] ?? 'objective')) ?: 'objective',
      'description' => $description,
      'completed' => $completed,
      'revealed' => $revealed || $completed || $children !== [],
      'current' => isset($objective['current']) ? (int) $objective['current'] : NULL,
      'target_count' => isset($objective['target_count']) ? (int) $objective['target_count'] : NULL,
      'item' => $this->normalizeNullableString($objective['item'] ?? NULL),
      'target' => $resolved_target ?? $this->normalizeNullableString($objective['target'] ?? NULL),
      'location' => $location,
      'completion_criteria' => $this->normalizeObjectiveCompletionCriteria($objective['completion_criteria'] ?? [], $objective),
      'next_step' => $this->deriveObjectiveNextStep($objective_for_next_step, $location, $completed, $children),
      'access' => $access,
    ];

    if ($children !== []) {
      $entry['children'] = $children;
    }

    return $entry;
  }

  /**
   * Resolve one objective location without assuming every step happens at the giver.
   */
  protected function resolveQuestManagementObjectiveLocation(array $objective, array $fallback_location, array $storyline_contacts = []): array {
    $type = strtolower(trim((string) ($objective['type'] ?? '')));
    $explicit_location_id = $this->normalizeNullableString($objective['location_id'] ?? $objective['destination_id'] ?? NULL);
    $explicit_location_label = $this->normalizeNullableString($objective['location'] ?? $objective['destination'] ?? NULL);

    if ($explicit_location_id !== NULL || $explicit_location_label !== NULL) {
      return $this->normalizeManagementLocation([
        'id' => $explicit_location_id ?? $explicit_location_label,
        'label' => $explicit_location_label,
      ]);
    }

    if (in_array($type, ['interact', 'escort'], TRUE)) {
      $contact_location = $this->resolveQuestManagementContactLocation($objective, $storyline_contacts, $fallback_location);
      if ($contact_location !== []) {
        return $contact_location;
      }

      if ($this->resolveStorylineContactForObjective($objective, $storyline_contacts) !== NULL) {
        return $this->normalizeManagementLocation([
          'id' => NULL,
          'label' => NULL,
        ]);
      }

      return $this->normalizeManagementLocation($fallback_location);
    }

    return $this->normalizeManagementLocation([
      'id' => NULL,
      'label' => NULL,
    ]);
  }

  /**
   * Resolve an anchored contact location for one interact-style objective.
   */
  protected function resolveQuestManagementContactLocation(array $objective, array $storyline_contacts, array $fallback_location): array {
    $contact = $this->resolveStorylineContactForObjective($objective, $storyline_contacts);
    if ($contact === NULL) {
      return [];
    }

    $relationship_state = is_array($contact['relationship_state'] ?? NULL) ? $contact['relationship_state'] : [];
    $location_id = $this->normalizeNullableString(
      $relationship_state['scene_id'] ?? $relationship_state['points_to_room_id'] ?? $relationship_state['chapter_id'] ?? $relationship_state['points_to_dungeon_id'] ?? NULL
    );
    $location_label = $this->normalizeNullableString(
      $relationship_state['scene_name'] ?? $relationship_state['room_name'] ?? $relationship_state['chapter_name'] ?? $relationship_state['dungeon_name'] ?? NULL
    );

    if ($location_id === NULL && $location_label === NULL) {
      return [];
    }

    if ($location_label === NULL && $location_id !== NULL) {
      $fallback_id = $this->normalizeNullableString($fallback_location['id'] ?? NULL);
      $fallback_label = $this->normalizeNullableString($fallback_location['label'] ?? NULL);
      $location_label = $fallback_id !== NULL && $fallback_id === $location_id
        ? $fallback_label
        : $this->humanizeIdentifier($location_id);
    }

    return $this->normalizeManagementLocation([
      'id' => $location_id,
      'label' => $location_label,
    ]);
  }

  /**
   * Resolve one storyline contact targeted by an objective.
   */
  protected function resolveStorylineContactForObjective(array $objective, array $storyline_contacts): ?array {
    $target = $this->normalizeNullableString($objective['target'] ?? NULL);
    if ($target === NULL) {
      return NULL;
    }

    foreach (array_values(array_filter($storyline_contacts, 'is_array')) as $contact) {
      $entity_id = $this->normalizeNullableString($contact['entity_id'] ?? NULL);
      $display_name = $this->normalizeNullableString($contact['display_name'] ?? NULL);
      if ($entity_id !== NULL && $entity_id === $target) {
        return $contact;
      }
      if ($display_name !== NULL && strcasecmp($display_name, $target) === 0) {
        return $contact;
      }
    }

    return NULL;
  }

  /**
   * Determine whether a storyline quest node is currently discovered.
   */
  protected function isStorylineQuestDiscovered(
    array $storyline_row,
    array $storyline_data,
    array $quest_node,
    array $linked_quest,
    ?array $materialized_entry
  ): bool {
    $statuses = [
      strtolower(trim((string) ($quest_node['status'] ?? ''))),
      strtolower(trim((string) ($linked_quest['status'] ?? ''))),
      strtolower(trim((string) ($materialized_entry['status'] ?? ''))),
    ];
    foreach ($statuses as $status) {
      if (in_array($status, ['active', 'completed', 'failed', 'abandoned', 'archived'], TRUE)) {
        return TRUE;
      }
    }

    $scene_id = $this->normalizeNullableString($quest_node['scene_id'] ?? ($linked_quest['scene_id'] ?? NULL));
    $chapter_id = $this->normalizeNullableString($quest_node['chapter_id'] ?? ($linked_quest['chapter_id'] ?? NULL));
    $current_scene_id = $this->normalizeNullableString($storyline_row['current_scene_id'] ?? ($storyline_data['current_scene_id'] ?? NULL));
    $current_chapter_id = $this->normalizeNullableString($storyline_row['current_chapter_id'] ?? ($storyline_data['current_chapter_id'] ?? NULL));
    $unlocked_scene_ids = array_values(array_filter(array_map('strval', is_array($storyline_data['unlocked_scene_ids'] ?? NULL) ? $storyline_data['unlocked_scene_ids'] : [])));
    $unlocked_chapter_ids = array_values(array_filter(array_map('strval', is_array($storyline_data['unlocked_chapter_ids'] ?? NULL) ? $storyline_data['unlocked_chapter_ids'] : [])));

    if ($scene_id !== NULL) {
      return $scene_id === $current_scene_id || in_array($scene_id, $unlocked_scene_ids, TRUE);
    }
    if ($chapter_id !== NULL) {
      return $chapter_id === $current_chapter_id || in_array($chapter_id, $unlocked_chapter_ids, TRUE);
    }

    return FALSE;
  }

  /**
   * Keep visible materialized quest rows even if they currently have no child nodes.
   */
  protected function shouldRetainQuestWithoutVisibleObjectives(array $quest, array $quest_node, array $linked_quest): bool {
    $status = strtolower(trim((string) ($quest['status'] ?? '')));
    if (in_array($status, ['active', 'completed', 'failed', 'abandoned', 'archived'], TRUE)) {
      return TRUE;
    }

    if (($quest['storyline']['storyline_id'] ?? NULL) === NULL) {
      return TRUE;
    }

    return !empty($quest_node) || !empty($linked_quest);
  }

  /**
   * Determine whether one objective should appear in the quest journal.
   */
  protected function isObjectiveVisibleInJournal(array $objective, int $phase_number, array $quest): bool {
    if (!empty($objective['completed'])) {
      return TRUE;
    }

    if (array_key_exists('revealed', $objective)) {
      return !empty($objective['revealed']);
    }

    $status = strtolower(trim((string) ($quest['status'] ?? 'active')));
    if (in_array($status, ['completed', 'failed', 'abandoned', 'archived'], TRUE)) {
      return TRUE;
    }

    return $phase_number <= max(1, (int) ($quest['current_phase'] ?? 1));
  }

  /**
   * Resolve storyline quest-giver and broker anchors for the tree root.
   */
  protected function resolveStorylineNpcAnchors(array $storyline_contract): array {
    $anchors = [];
    $lead_location = $this->normalizeManagementLocation($storyline_contract['lead_location'] ?? $storyline_contract['location'] ?? []);

    foreach (['quest_giver' => 'quest_giver', 'broker' => 'broker'] as $field => $role) {
      $contact = $storyline_contract[$field] ?? NULL;
      if (!is_array($contact)) {
        continue;
      }

      $npc_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($npc_id === '') {
        continue;
      }

      $anchors[] = [
        'npc_id' => $npc_id,
        'npc_name' => trim((string) ($contact['display_name'] ?? $npc_id)),
        'role' => $role,
        'location' => $lead_location,
        'next_step' => trim((string) ($storyline_contract['next_step'] ?? 'Review this storyline.')),
        'access' => $storyline_contract['access'] ?? NULL,
      ];
    }

    return $anchors;
  }

  /**
   * Resolve the best NPC anchor for standalone quests.
   */
  protected function resolveStandaloneQuestNpcAnchor(int $campaign_id, array $quest_entry, array $quest_contract): array {
    $quest_data = is_array($quest_contract['quest_data'] ?? NULL) ? $quest_contract['quest_data'] : [];
    $variables = is_array($quest_data['variables'] ?? NULL) ? $quest_data['variables'] : [];
    $npc_id = $this->normalizeNullableString($variables['giver_npc_id'] ?? ($quest_entry['giver_npc_id'] ?? NULL))
      ?? $this->normalizeNullableString($quest_entry['source_template_id'] ?? NULL)
      ?? 'unknown-quest-giver';
    $campaign_character = $this->loadCampaignCharacterReference($campaign_id, $npc_id);
    $npc_name = $this->normalizeNullableString($variables['giver_name'] ?? $variables['giver_display_name'] ?? NULL)
      ?? $this->normalizeNullableString($campaign_character['name'] ?? NULL)
      ?? $this->humanizeIdentifier($npc_id);
    $location = $quest_contract['location'] ?? [];
    if (is_array($campaign_character) && $campaign_character !== []) {
      $location = $this->normalizeManagementLocation([
        'id' => $campaign_character['location_ref'] ?? ($location['id'] ?? NULL),
        'label' => $campaign_character['location_label'] ?? ($location['label'] ?? NULL),
      ]);
    }

    return [
      'npc_id' => $npc_id,
      'npc_name' => $npc_name,
      'role' => 'quest_giver',
      'location' => $location,
      'next_step' => $quest_contract['next_step'] ?? 'Review this quest.',
      'access' => $quest_contract['access'] ?? NULL,
    ];
  }

  /**
   * Resolve one campaign character reference by numeric id or instance id.
   */
  protected function loadCampaignCharacterReference(int $campaign_id, string $npc_reference): array {
    $npc_reference = trim($npc_reference);
    if ($campaign_id <= 0 || $npc_reference === '') {
      return [];
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_characters')) {
      return [];
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'instance_id', 'name', 'location_type', 'location_ref'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1);

    if (ctype_digit($npc_reference)) {
      $query->condition('id', (int) $npc_reference);
    }
    else {
      $query->condition('instance_id', $npc_reference);
    }

    $row = $query->execute()->fetchAssoc();
    if (!$row) {
      return [];
    }

    return [
      'id' => (int) ($row['id'] ?? 0),
      'instance_id' => trim((string) ($row['instance_id'] ?? '')),
      'name' => trim((string) ($row['name'] ?? '')),
      'location_type' => trim((string) ($row['location_type'] ?? '')),
      'location_ref' => $this->normalizeNullableString($row['location_ref'] ?? NULL),
      'location_label' => $this->normalizeNullableString($row['location_ref'] ?? NULL) !== NULL
        ? $this->humanizeIdentifier((string) $row['location_ref'])
        : NULL,
    ];
  }

  /**
   * Build a scene index for quick label and summary lookups.
   */
  protected function buildStorylineSceneIndex(array $storyline_data): array {
    $scene_index = [];
    foreach (array_values(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : []) as $chapter) {
      if (!is_array($chapter)) {
        continue;
      }
      $chapter_id = trim((string) ($chapter['chapter_id'] ?? ''));
      $chapter_name = trim((string) ($chapter['name'] ?? $chapter_id));
      $chapter_summary = trim((string) ($chapter['summary'] ?? ''));
      $scene_index[$chapter_id] = [
        'chapter_id' => $chapter_id,
        'chapter_name' => $chapter_name,
        'chapter_summary' => $chapter_summary,
        'scene_id' => NULL,
        'name' => $chapter_name,
        'summary' => $chapter_summary,
      ];
      foreach (array_values(is_array($chapter['scenes'] ?? NULL) ? $chapter['scenes'] : []) as $scene) {
        if (!is_array($scene)) {
          continue;
        }
        $scene_id = trim((string) ($scene['scene_id'] ?? ''));
        if ($scene_id === '') {
          continue;
        }
        $scene_index[$scene_id] = [
          'chapter_id' => $chapter_id,
          'chapter_name' => $chapter_name,
          'chapter_summary' => $chapter_summary,
          'scene_id' => $scene_id,
          'name' => trim((string) ($scene['name'] ?? $scene_id)),
          'summary' => trim((string) ($scene['summary'] ?? '')),
        ];
      }
    }
    return $scene_index;
  }

  /**
   * Resolve the best lead location for a storyline.
   */
  protected function resolveStorylineLeadLocation(array $storyline_row, array $storyline_data, array $contact_summary, array $scene_index): array {
    if (is_array($contact_summary['lead_location'] ?? NULL)) {
      $contact_location = $contact_summary['lead_location'];
      return $this->normalizeManagementLocation([
        'id' => $contact_location['scene_id'] ?? $contact_location['chapter_id'] ?? ($contact_location['id'] ?? NULL),
        'label' => $contact_location['scene_name'] ?? $contact_location['label'] ?? $contact_location['chapter_name'] ?? NULL,
      ]);
    }

    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    $entry_dungeon = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $lead_location_id = $this->normalizeNullableString($entry_dungeon['lead_location_id'] ?? NULL);
    $lead_location_label = $this->normalizeNullableString($entry_dungeon['lead_location_label'] ?? NULL)
      ?? ($lead_location_id !== NULL ? $this->humanizeIdentifier($lead_location_id) : NULL);

    if ($lead_location_id !== NULL || $lead_location_label !== NULL) {
      return $this->normalizeManagementLocation([
        'id' => $lead_location_id,
        'label' => $lead_location_label,
      ]);
    }

    $current_scene_id = $this->normalizeNullableString($storyline_row['current_scene_id'] ?? NULL);
    $current_chapter_id = $this->normalizeNullableString($storyline_row['current_chapter_id'] ?? NULL);
    return $this->resolveSceneOrLeadLocation($scene_index, $current_scene_id, $current_chapter_id, []);
  }

  /**
   * Resolve scene/chapter location metadata with lead-location fallback.
   */
  protected function resolveSceneOrLeadLocation(array $scene_index, ?string $scene_id, ?string $chapter_id, array $lead_location): array {
    $scene_meta = $this->resolveSceneMeta($scene_index, $scene_id, $chapter_id);
    if ($scene_meta !== []) {
      return $this->normalizeManagementLocation([
        'id' => $scene_id ?? $chapter_id,
        'label' => $scene_meta['name'] ?? ($scene_id ?? $chapter_id),
      ]);
    }

    return $this->normalizeManagementLocation($lead_location);
  }

  /**
   * Resolve one quest location from quest/storyline metadata.
   */
  protected function resolveQuestManagementLocation(
    array $quest,
    array $scene_index,
    array $lead_location,
    ?string $scene_id,
    ?string $chapter_id
  ): array {
    if ($scene_id !== NULL || $chapter_id !== NULL) {
      $scene_location = $this->resolveSceneOrLeadLocation($scene_index, $scene_id, $chapter_id, []);
      if (!empty($scene_location['id']) || !empty($scene_location['label'])) {
        return $scene_location;
      }
    }

    $location_id = $this->normalizeNullableString($quest['location_id'] ?? NULL);
    if ($location_id !== NULL) {
      return $this->normalizeManagementLocation([
        'id' => $location_id,
        'label' => $this->humanizeIdentifier($location_id),
      ]);
    }

    return $this->resolveSceneOrLeadLocation($scene_index, $scene_id, $chapter_id, $lead_location);
  }

  /**
   * Resolve one scene metadata row.
   */
  protected function resolveSceneMeta(array $scene_index, ?string $scene_id, ?string $chapter_id): array {
    if ($scene_id !== NULL && isset($scene_index[$scene_id]) && is_array($scene_index[$scene_id])) {
      return $scene_index[$scene_id];
    }
    if ($chapter_id !== NULL && isset($scene_index[$chapter_id]) && is_array($scene_index[$chapter_id])) {
      return $scene_index[$chapter_id];
    }
    return [];
  }

  /**
   * Find one quest node by quest id.
   */
  protected function findQuestNodeById(array $quest_nodes, string $quest_id): array {
    foreach ($quest_nodes as $quest_node) {
      if (is_array($quest_node) && (string) ($quest_node['quest_id'] ?? '') === $quest_id) {
        return $quest_node;
      }
    }
    return [];
  }

  /**
   * Determine whether a quest node is still blocked by prerequisites.
   */
  protected function isQuestNodeBlocked(array $quest_node, array $status_by_node): bool {
    $dependencies = array_values(array_filter(array_map('strval', is_array($quest_node['unlocks_after'] ?? NULL) ? $quest_node['unlocks_after'] : [])));
    if ($dependencies === []) {
      return FALSE;
    }

    foreach ($dependencies as $dependency) {
      $status = strtolower(trim((string) ($status_by_node[$dependency] ?? '')));
      if ($status !== 'completed') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Derive the best next step for one storyline.
   */
  protected function deriveStorylineNextStep(array $storyline_data, array $quests, array $contact_summary): string {
    if ($quests !== []) {
      $next_step = trim((string) ($quests[0]['next_step'] ?? ''));
      if ($next_step !== '') {
        return $next_step;
      }
    }

    $lead_text = trim((string) ($storyline_data['metadata']['generated_outline']['bootstrap_handoff']['lead_text'] ?? ''));
    if ($lead_text !== '') {
      return $lead_text;
    }

    $contact_note = trim((string) ($contact_summary['quest_giver']['notes'] ?? $contact_summary['synopsis'] ?? ''));
    if ($contact_note !== '') {
      return $contact_note;
    }

    return 'Follow the next available lead for this storyline.';
  }

  /**
   * Derive the next step for one quest.
   */
  protected function deriveQuestNextStep(
    array $quest,
    array $objectives,
    array $scene_index,
    ?string $scene_id,
    ?string $chapter_id,
    array $lead_location,
    bool $blocked
  ): string {
    foreach ($objectives as $objective) {
      if (empty($objective['completed'])) {
        return trim((string) ($objective['next_step'] ?? $objective['description'] ?? 'Advance this objective.'));
      }
    }

    if ($blocked) {
      return 'Finish earlier storyline work to unlock this quest.';
    }

    $scene_meta = $this->resolveSceneMeta($scene_index, $scene_id, $chapter_id);
    if (!empty($scene_meta['summary'])) {
      return trim((string) $scene_meta['summary']);
    }

    if (!empty($lead_location['label'])) {
      return 'Travel to ' . (string) $lead_location['label'] . '.';
    }

    return 'Review the quest objectives and continue progress.';
  }

  /**
   * Derive the next step for one objective.
   */
  protected function deriveObjectiveNextStep(array $objective, array $location, bool $completed, array $children = []): string {
    if ($completed) {
      return 'Objective complete.';
    }

    foreach ($children as $child) {
      if (is_array($child) && empty($child['completed'])) {
        return trim((string) ($child['next_step'] ?? $child['description'] ?? 'Complete the next nested objective.'));
      }
    }

    if ($children !== []) {
      return 'Complete the nested objectives for this objective.';
    }

    $type = strtolower(trim((string) ($objective['type'] ?? '')));
    $target = trim((string) ($objective['target'] ?? $objective['item'] ?? $location['label'] ?? ''));

    return match ($type) {
      'collect' => $target !== '' ? 'Collect ' . $target . '.' : 'Collect the required items.',
      'explore', 'travel' => $target !== '' ? 'Travel to ' . $target . '.' : 'Travel to the next location.',
      'escort' => $target !== '' ? 'Escort the target to ' . $target . '.' : 'Escort the target to safety.',
      'interact' => $target !== ''
        ? 'Speak with or interact with ' . $target . (!empty($location['label']) ? ' at ' . $location['label'] : '') . '.'
        : (!empty($location['label']) ? 'Complete the required interaction at ' . $location['label'] . '.' : 'Complete the required interaction.'),
      'investigate' => $target !== '' ? 'Investigate ' . $target . '.' : 'Investigate the next clue.',
      default => trim((string) ($objective['description'] ?? 'Advance this objective.')),
    };
  }

  /**
   * Resolve a human-readable objective target using storyline contacts when possible.
   */
  protected function resolveQuestManagementObjectiveTarget(array $objective, array $quest, array $storyline_contacts = []): ?string {
    $target = $this->normalizeNullableString($objective['target'] ?? NULL);
    if ($target === NULL) {
      return NULL;
    }

    foreach (array_values(array_filter($storyline_contacts, 'is_array')) as $contact) {
      if ((string) ($contact['entity_id'] ?? '') !== $target) {
        continue;
      }
      $display_name = $this->normalizeNullableString($contact['display_name'] ?? NULL);
      if ($display_name !== NULL) {
        return $display_name;
      }
    }

    $label = $this->resolveObjectiveReferenceLabel($quest, $target);
    return $label !== '' ? $label : $target;
  }

  /**
   * Normalize a location contract for quest-management rendering.
   */
  protected function normalizeManagementLocation(mixed $location): array {
    if (!is_array($location)) {
      $location = [
        'id' => $this->normalizeNullableString($location),
        'label' => $this->normalizeNullableString($location),
      ];
    }

    $id = $this->normalizeNullableString($location['id'] ?? NULL);
    $label = $this->normalizeNullableString($location['label'] ?? NULL)
      ?? ($id !== NULL ? $this->humanizeIdentifier($id) : NULL);

    return [
      'id' => $id,
      'label' => $label,
    ];
  }

  /**
   * Build a normalized access descriptor and sort bucket.
   */
  protected function buildManagementAccessDescriptor(
    array $location,
    bool $is_accessible,
    ?string $current_location_id,
    string $status = 'available'
  ): array {
    $status = strtolower(trim($status));
    $is_clear = !empty($location['id']) || !empty($location['label']);

    $sort_bucket = 'ready';
    if (!$is_clear) {
      $sort_bucket = 'unclear';
    }
    elseif (!$is_accessible || in_array($status, ['blocked', 'locked'], TRUE)) {
      $sort_bucket = 'blocked';
    }
    elseif ($current_location_id !== NULL && !empty($location['id']) && $location['id'] === $current_location_id) {
      $sort_bucket = 'current';
    }
    elseif (in_array($status, ['completed', 'failed', 'abandoned', 'archived'], TRUE)) {
      $sort_bucket = 'completed';
    }

    return [
      'is_clear' => $is_clear,
      'is_accessible' => $is_accessible && !in_array($status, ['blocked', 'locked'], TRUE),
      'sort_bucket' => $sort_bucket,
      'sort_rank' => $this->managementSortRank($sort_bucket),
    ];
  }

  /**
   * Normalize an access descriptor into a stable shape.
   */
  protected function normalizeManagementAccess(mixed $access): array {
    if (!is_array($access)) {
      return [
        'is_clear' => FALSE,
        'is_accessible' => FALSE,
        'sort_bucket' => 'unclear',
        'sort_rank' => $this->managementSortRank('unclear'),
      ];
    }

    $sort_bucket = trim((string) ($access['sort_bucket'] ?? 'unclear')) ?: 'unclear';
    return [
      'is_clear' => !empty($access['is_clear']),
      'is_accessible' => !empty($access['is_accessible']),
      'sort_bucket' => $sort_bucket,
      'sort_rank' => isset($access['sort_rank'])
        ? (int) $access['sort_rank']
        : $this->managementSortRank($sort_bucket),
    ];
  }

  /**
   * Sort storyline rows by access and name.
   */
  protected function sortManagementStorylines(array $storylines): array {
    usort($storylines, function (array $a, array $b): int {
      $access_compare = $this->compareManagementAccess($a['access'] ?? NULL, $b['access'] ?? NULL);
      if ($access_compare !== 0) {
        return $access_compare;
      }
      return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    foreach ($storylines as &$storyline) {
      $storyline['quests'] = $this->sortManagementQuests(is_array($storyline['quests'] ?? NULL) ? $storyline['quests'] : []);
    }

    return $storylines;
  }

  /**
   * Sort quest rows by access and title.
   */
  protected function sortManagementQuests(array $quests): array {
    usort($quests, function (array $a, array $b): int {
      $access_compare = $this->compareManagementAccess($a['access'] ?? NULL, $b['access'] ?? NULL);
      if ($access_compare !== 0) {
        return $access_compare;
      }
      return strcasecmp((string) ($a['quest_name'] ?? $a['title'] ?? ''), (string) ($b['quest_name'] ?? $b['title'] ?? ''));
    });

    foreach ($quests as &$quest) {
      $quest['objectives'] = $this->sortManagementObjectives(is_array($quest['objectives'] ?? NULL) ? $quest['objectives'] : []);
    }

    return $quests;
  }

  /**
   * Sort objective rows by access, phase, and description.
   */
  protected function sortManagementObjectives(array $objectives): array {
    usort($objectives, function (array $a, array $b): int {
      $access_compare = $this->compareManagementAccess($a['access'] ?? NULL, $b['access'] ?? NULL);
      if ($access_compare !== 0) {
        return $access_compare;
      }
      $phase_compare = ((int) ($a['phase'] ?? 1)) <=> ((int) ($b['phase'] ?? 1));
      if ($phase_compare !== 0) {
        return $phase_compare;
      }
      return strcasecmp((string) ($a['description'] ?? ''), (string) ($b['description'] ?? ''));
    });

    foreach ($objectives as &$objective) {
      if (is_array($objective['children'] ?? NULL)) {
        $objective['children'] = $this->sortManagementObjectives($objective['children']);
      }
    }

    return $objectives;
  }

  /**
   * Compare access descriptors for sorting.
   */
  protected function compareManagementAccess(mixed $left, mixed $right): int {
    $left_access = $this->normalizeManagementAccess($left);
    $right_access = $this->normalizeManagementAccess($right);
    return ((int) ($left_access['sort_rank'] ?? 99)) <=> ((int) ($right_access['sort_rank'] ?? 99));
  }

  /**
   * Map access buckets to stable sort weights.
   */
  protected function managementSortRank(string $bucket): int {
    return match (strtolower(trim($bucket))) {
      'current' => 0,
      'ready' => 1,
      'completed' => 2,
      'blocked' => 3,
      default => 4,
    };
  }

  /**
   * Humanize a snake/kebab/camel identifier.
   */
  protected function humanizeIdentifier(mixed $value): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
      return 'Unknown';
    }

    $normalized = preg_replace('/([a-z])([A-Z])/', '$1 $2', $normalized) ?? $normalized;
    $normalized = str_replace(['_', '-'], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return ucwords(trim($normalized));
  }

}
