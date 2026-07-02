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
    NumberGenerationService $number_generation
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->numberGeneration = $number_generation;
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

      // Generate unique quest ID
      $quest_id = $this->generateQuestId($campaign_id, $template_id);

      // Resolve variables
      $variables = $this->buildVariables($template, $context);
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
        'status' => 'available',
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
    return $template_id . '_' . $campaign_id . '_' . uniqid();
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
  protected function buildVariables(array $template, array $context): array {
    // TODO: Implement intelligent variable extraction from context
    // For now, return context as-is
    return $context;
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
    $objectives = [];

    foreach ($objectives_schema as $phase_data) {
      $phase_objectives = [];

      foreach ($phase_data['objectives'] as $obj) {
        $generated_obj = [
          'objective_id' => $obj['objective_id'],
          'type' => $obj['type'],
          'description' => $this->resolveVariables($obj['description'], $variables),
          'completed' => FALSE,
        ];

        // Add type-specific fields
        switch ($obj['type']) {
          case 'kill':
            $target_count = $obj['target_count'] ?? $this->numberGeneration->rollRange(
              $obj['target_count_range'][0] ?? 5,
              $obj['target_count_range'][1] ?? 10
            );
            $generated_obj['target'] = $this->resolveVariables($obj['target'], $variables);
            $generated_obj['current'] = 0;
            $generated_obj['target_count'] = $target_count;
            break;

          case 'collect':
            $target_count = $obj['target_count'] ?? $this->numberGeneration->rollRange(3, 8);
            $generated_obj['item'] = $this->resolveVariables($obj['item'] ?? $obj['target'], $variables);
            $generated_obj['current'] = 0;
            $generated_obj['target_count'] = $target_count;
            break;

          case 'explore':
            $generated_obj['location'] = $this->resolveVariables($obj['target'], $variables);
            $generated_obj['discovered'] = FALSE;
            break;

          case 'escort':
            $generated_obj['npc_id'] = $context['escort_npc_id'] ?? NULL;
            $generated_obj['destination'] = $this->resolveVariables($obj['destination'], $variables);
            $generated_obj['arrived'] = FALSE;
            break;

          case 'interact':
            $generated_obj['target'] = $this->resolveVariables($obj['target'], $variables);
            $generated_obj['completed'] = FALSE;
            break;
        }

        $phase_objectives[] = $generated_obj;
      }

      $objectives[] = [
        'phase' => $phase_data['phase'],
        'objectives' => $phase_objectives,
      ];
    }

    return $objectives;
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

}
