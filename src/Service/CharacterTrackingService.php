<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for tracking campaign characters and managing their reappearance.
 *
 * Tracks NPCs and creatures that survive encounters, generates backstories,
 * and manages their potential reappearance in future encounters.
 */
class CharacterTrackingService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The AI service (optional).
   *
   * @var object|null
   */
  protected $aiService;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a CharacterTrackingService.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');

    // Try to load AI service if available.
    try {
      if (\Drupal::hasService('ai_conversation.ai_api_service')) {
        $this->aiService = \Drupal::service('ai_conversation.ai_api_service');
      }
    }
    catch (\Exception $e) {
      $this->logger->info('AI service not available for character backstories');
    }
  }

  /**
   * Process survivors from an encounter.
   *
   * Creates or updates character records for entities that survived.
   *
   * @param array $context
   *   Encounter context including:
   *   - campaign_id: Campaign ID
   *   - room_id: Room where encounter occurred
   *   - party_level: Party level at time of encounter
   *   - survivors: Array of survivor data
   *
   * @return array
   *   Array of created/updated character IDs.
   */
  public function processSurvivors(array $context): array {
    $campaign_id = $context['campaign_id'] ?? 0;
    $room_id = $context['room_id'] ?? 'unknown';
    $party_level = $context['party_level'] ?? 1;
    $survivors = $context['survivors'] ?? [];

    $character_ids = [];

    foreach ($survivors as $survivor) {
      $entity_instance = $survivor['entity_instance'] ?? [];
      $outcome = $survivor['outcome'] ?? 'alive'; // alive, escaped, friendly
      $final_hp = $survivor['final_hp'] ?? null;

      // Determine status and disposition.
      $status = $this->determineStatus($outcome, $final_hp, $entity_instance);
      $disposition = $this->determineDisposition($outcome, $entity_instance);

      // Check if this character already exists (by entity position or ID).
      $existing_id = $this->findExistingCharacter($campaign_id, $entity_instance);

      if ($existing_id) {
        // Update existing character.
        $this->updateCharacter($existing_id, [
          'status' => $status,
          'disposition' => $disposition,
          'current_hp' => $final_hp,
          'last_seen_room_id' => $room_id,
          'last_seen_date' => date('Y-m-d H:i:s'),
        ]);
        $character_ids[] = $existing_id;
      }
      else {
        // Create new character record.
        $character_id = $this->createCharacter([
          'campaign_id' => $campaign_id,
          'entity_id' => $entity_instance['entity_id'] ?? 'unknown',
          'entity_instance' => json_encode($entity_instance),
          'first_encounter_room_id' => $room_id,
          'first_encounter_level' => $party_level,
          'status' => $status,
          'disposition' => $disposition,
          'current_hp' => $final_hp,
          'current_level' => $entity_instance['level'] ?? 1,
        ]);

        // Generate backstory asynchronously (or immediately if needed).
        if ($character_id) {
          $this->generateAndSaveBackstory($character_id, $entity_instance, $context);
          $character_ids[] = $character_id;
        }
      }
    }

    return $character_ids;
  }

  /**
   * Find characters available for reappearance.
   *
   * @param array $context
   *   Context for character selection:
   *   - campaign_id: Campaign ID
   *   - location_type: Where characters might appear (tavern, dungeon, etc.)
   *   - disposition_filter: Preferred dispositions
   *   - max_count: Maximum characters to return
   *
   * @return array
   *   Array of character records suitable for reappearance.
   */
  public function findReusableCharacters(array $context): array {
    $campaign_id = $context['campaign_id'] ?? 0;
    $location_type = $context['location_type'] ?? 'any';
    $disposition_filter = $context['disposition_filter'] ?? ['friendly', 'neutral', 'hostile'];
    $max_count = $context['max_count'] ?? 5;

    $query = $this->database->select('dc_campaign_npcs', 'c')
      ->fields('c')
      ->condition('c.campaign_id', $campaign_id)
      ->condition('c.can_reappear', TRUE)
      ->condition('c.status', ['alive', 'escaped', 'friendly', 'hostile'], 'IN')
      ->condition('c.disposition', $disposition_filter, 'IN')
      ->orderBy('c.reappearance_count', 'ASC') // Prefer characters who haven't appeared much
      ->orderBy('c.last_seen_date', 'ASC') // Prefer characters not seen recently
      ->range(0, $max_count);

    // Filter by location type if specified.
    if ($location_type === 'tavern') {
      $query->condition('c.disposition', ['friendly', 'neutral', 'curious'], 'IN');
    }
    elseif ($location_type === 'dungeon') {
      $query->condition('c.disposition', ['hostile', 'suspicious', 'fearful'], 'IN');
    }

    $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Decode JSON fields.
    foreach ($results as &$character) {
      $character['entity_instance'] = json_decode($character['entity_instance'], TRUE);
      $character['notable_events'] = json_decode($character['notable_events'] ?? '[]', TRUE);
      $character['relationships'] = json_decode($character['relationships'] ?? '{}', TRUE);
      $character['tags'] = json_decode($character['tags'] ?? '[]', TRUE);
      $character['preferred_locations'] = json_decode($character['preferred_locations'] ?? '[]', TRUE);
    }

    return $results;
  }

  /**
   * Record a character's reappearance.
   *
   * @param int $character_id
   *   Character ID.
   * @param array $context
   *   Reappearance context (room_id, date, outcome).
   *
   * @return bool
   *   TRUE if updated successfully.
   */
  public function recordReappearance(int $character_id, array $context): bool {
    $room_id = $context['room_id'] ?? null;
    $outcome = $context['outcome'] ?? 'encountered';

    // Get current character data.
    $character = $this->database->select('dc_campaign_npcs', 'c')
      ->fields('c')
      ->condition('character_id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$character) {
      return FALSE;
    }

    // Update reappearance count and last seen.
    $notable_events = json_decode($character['notable_events'] ?? '[]', TRUE);
    $notable_events[] = [
      'type' => 'reappearance',
      'room_id' => $room_id,
      'date' => date('Y-m-d H:i:s'),
      'outcome' => $outcome,
    ];

    $this->database->update('dc_campaign_npcs')
      ->fields([
        'reappearance_count' => $character['reappearance_count'] + 1,
        'last_seen_room_id' => $room_id,
        'last_seen_date' => date('Y-m-d H:i:s'),
        'notable_events' => json_encode($notable_events),
      ])
      ->condition('character_id', $character_id)
      ->execute();

    return TRUE;
  }

  /**
   * Generate and save backstory for a character.
   *
   * @param int $character_id
   *   Character ID.
   * @param array $entity_instance
   *   Entity instance data.
   * @param array $context
   *   Additional context for backstory generation.
   *
   * @return string|null
   *   Generated backstory or NULL if failed.
   */
  protected function generateAndSaveBackstory(int $character_id, array $entity_instance, array $context): ?string {
    $backstory = $this->generateBackstory($entity_instance, $context);

    if ($backstory) {
      $this->database->update('dc_campaign_npcs')
        ->fields([
          'backstory' => $backstory['backstory'] ?? '',
          'personality_traits' => $backstory['personality_traits'] ?? '',
          'motivations' => $backstory['motivations'] ?? '',
        ])
        ->condition('character_id', $character_id)
        ->execute();

      return $backstory['backstory'] ?? NULL;
    }

    return NULL;
  }

  /**
   * Generate backstory using AI or templates.
   *
   * @param array $entity_instance
   *   Entity instance data.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Backstory components (backstory, personality_traits, motivations).
   */
  protected function generateBackstory(array $entity_instance, array $context): array {
    // Try AI generation first.
    if ($this->aiService) {
      try {
        $backstory = $this->generateAIBackstory($entity_instance, $context);
        if ($backstory) {
          return $backstory;
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('AI backstory generation failed: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Fallback to template-based backstory.
    return $this->generateTemplateBackstory($entity_instance, $context);
  }

  /**
   * Generate AI-powered backstory.
   *
   * @param array $entity_instance
   *   Entity instance data.
   * @param array $context
   *   Additional context.
   *
   * @return array|null
   *   Backstory components or NULL if failed.
   */
  protected function generateAIBackstory(array $entity_instance, array $context): ?array {
    $entity_id = $entity_instance['entity_id'] ?? 'unknown';
    $level = $entity_instance['level'] ?? 1;
    $outcome = $context['outcome'] ?? 'survived';

    $prompt = "Generate a brief backstory for an NPC in a Pathfinder 2E campaign:\n\n";
    $prompt .= "Creature Type: {$entity_id}\n";
    $prompt .= "Level: {$level}\n";
    $prompt .= "Encounter Outcome: {$outcome}\n";
    $prompt .= "Party Level: " . ($context['party_level'] ?? 'unknown') . "\n\n";
    $prompt .= "Provide:\n";
    $prompt .= "1. A brief backstory (2-3 sentences)\n";
    $prompt .= "2. 2-3 personality traits\n";
    $prompt .= "3. Primary motivation\n\n";
    $prompt .= "Format as JSON:\n";
    $prompt .= "{\n";
    $prompt .= '  "backstory": "...",'."\n";
    $prompt .= '  "personality_traits": "...",'."\n";
    $prompt .= '  "motivations": "..."'."\n";
    $prompt .= "}";

    // TODO: Call AI service properly when API method is available.
    // For now, return NULL to trigger template fallback.
    return NULL;
  }

  /**
   * Generate template-based backstory.
   *
   * @param array $entity_instance
   *   Entity instance data.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Backstory components.
   */
  protected function generateTemplateBackstory(array $entity_instance, array $context): array {
    $entity_id = $entity_instance['entity_id'] ?? 'unknown';
    $level = $entity_instance['level'] ?? 1;

    // Simple template-based generation.
    $templates = [
      'goblin_warrior' => [
        'backstory' => 'A scrappy goblin warrior from the Rustfoot tribe. Survived the party\'s assault and now harbors mixed feelings of fear and curiosity.',
        'personality_traits' => 'Cautious, opportunistic, surprisingly clever',
        'motivations' => 'Survival and finding a safer life',
      ],
      'hobgoblin_soldier' => [
        'backstory' => 'A disciplined hobgoblin soldier who escaped the battle. Now questions the chain of command that led to such losses.',
        'personality_traits' => 'Proud, tactical, disillusioned',
        'motivations' => 'Redemption and proving worth',
      ],
      // Add more templates as needed.
    ];

    $default = [
      'backstory' => "A level {$level} {$entity_id} who survived an encounter with adventurers. Now wary of the party's power.",
      'personality_traits' => 'Survivor instincts, adaptable, watchful',
      'motivations' => 'Self-preservation and understanding the party',
    ];

    return $templates[$entity_id] ?? $default;
  }

  /**
   * Create a new character record.
   *
   * @param array $data
   *   Character data.
   *
   * @return int|null
   *   Character ID or NULL if failed.
   */
  protected function createCharacter(array $data): ?int {
    try {
      return $this->database->insert('dc_campaign_npcs')
        ->fields($data)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create character: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Update an existing character record.
   *
   * @param int $character_id
   *   Character ID.
   * @param array $data
   *   Data to update.
   *
   * @return bool
   *   TRUE if updated successfully.
   */
  protected function updateCharacter(int $character_id, array $data): bool {
    try {
      $this->database->update('dc_campaign_npcs')
        ->fields($data)
        ->condition('character_id', $character_id)
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update character: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Find existing character by entity instance.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $entity_instance
   *   Entity instance to match.
   *
   * @return int|null
   *   Character ID or NULL if not found.
   */
  protected function findExistingCharacter(int $campaign_id, array $entity_instance): ?int {
    // Try to match by unique identifier if available.
    $entity_id = $entity_instance['entity_id'] ?? null;
    $position = $entity_instance['position'] ?? null;

    if (!$entity_id) {
      return NULL;
    }

    // Simple matching by entity_id and recent encounter.
    // In production, you'd want more sophisticated matching.
    $result = $this->database->select('dc_campaign_npcs', 'c')
      ->fields('c', ['character_id'])
      ->condition('c.campaign_id', $campaign_id)
      ->condition('c.entity_id', $entity_id)
      ->condition('c.last_seen_date', date('Y-m-d H:i:s', strtotime('-1 hour')), '>')
      ->execute()
      ->fetchField();

    return $result ?: NULL;
  }

  /**
   * Determine character status from encounter outcome.
   *
   * @param string $outcome
   *   Encounter outcome.
   * @param int|null $final_hp
   *   Final HP.
   * @param array $entity_instance
   *   Entity instance.
   *
   * @return string
   *   Status (alive, dead, escaped, etc.)
   */
  protected function determineStatus(string $outcome, ?int $final_hp, array $entity_instance): string {
    if ($outcome === 'dead' || ($final_hp !== NULL && $final_hp <= 0)) {
      return 'dead';
    }
    if ($outcome === 'escaped' || $outcome === 'fled') {
      return 'escaped';
    }
    if ($outcome === 'friendly' || $outcome === 'allied') {
      return 'friendly';
    }
    if ($outcome === 'captured') {
      return 'captured';
    }
    return 'alive';
  }

  /**
   * Determine character disposition from encounter outcome.
   *
   * @param string $outcome
   *   Encounter outcome.
   * @param array $entity_instance
   *   Entity instance.
   *
   * @return string
   *   Disposition (friendly, hostile, etc.)
   */
  protected function determineDisposition(string $outcome, array $entity_instance): string {
    if ($outcome === 'friendly' || $outcome === 'allied') {
      return 'grateful';
    }
    if ($outcome === 'escaped' || $outcome === 'fled') {
      return 'fearful';
    }
    if ($outcome === 'captured') {
      return 'hostile';
    }

    // Check entity behavior if available.
    $behavior = $entity_instance['behavior'] ?? 'neutral';
    if (in_array($behavior, ['aggressive', 'hostile'])) {
      return 'hostile';
    }
    if (in_array($behavior, ['friendly', 'helpful'])) {
      return 'friendly';
    }

    return 'neutral';
  }

  /**
   * Mark a character as dead.
   *
   * @param int $character_id
   *   Character ID.
   * @param array $context
   *   Context (room_id, etc.)
   *
   * @return bool
   *   TRUE if updated.
   */
  public function markCharacterDead(int $character_id, array $context = []): bool {
    return $this->updateCharacter($character_id, [
      'status' => 'dead',
      'can_reappear' => FALSE,
      'last_seen_room_id' => $context['room_id'] ?? NULL,
      'last_seen_date' => date('Y-m-d H:i:s'),
    ]);
  }

}
