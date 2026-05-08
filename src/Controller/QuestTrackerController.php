<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for quest tracking endpoints.
 */
class QuestTrackerController extends ControllerBase {

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
  protected $logger;

  /**
   * Room chat service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomChatService
   */
  protected RoomChatService $chatService;

  /**
   * Constructs a QuestTrackerController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory, RoomChatService $chat_service) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->chatService = $chat_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('dungeoncrawler_content.room_chat_service')
    );
  }

  /**
   * Get available quests for a campaign.
   *
   * GET /api/campaign/{campaign_id}/quests/available
   *
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getAvailableQuests(int $campaign_id): JsonResponse {
    try {
      $available = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', [
          'quest_id',
          'quest_name',
          'quest_description',
          'quest_type',
          'generated_objectives',
          'generated_rewards',
          'giver_npc_id',
          'location_id',
          'status',
        ])
        ->condition('q.campaign_id', $campaign_id)
        ->condition('q.status', 'available')
        ->execute()
        ->fetchAllAssoc('quest_id');

      $quests = array_map(function ($quest) {
        $quest->generated_objectives = json_decode($quest->generated_objectives, TRUE) ?? [];
        $quest->generated_rewards = json_decode($quest->generated_rewards, TRUE) ?? [];
        return $quest;
      }, $available);

      return new JsonResponse([
        'success' => TRUE,
        'quests' => array_values($quests),
        'count' => count($quests),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch available quests: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Start a quest.
   *
   * POST /api/campaign/{campaign_id}/quests/{quest_id}/start
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function startQuest(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      $character_id = $payload['character_id'] ?? NULL;
      $party_id = $payload['party_id'] ?? NULL;

      if (empty($character_id) && empty($party_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Either character_id or party_id is required',
        ], 400);
      }

      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $result = $quest_tracker->startQuest($campaign_id, $quest_id, $character_id, $party_id);

      if (!$result) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to start quest',
        ], 500);
      }

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Quest started successfully',
        'quest_id' => $quest_id,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to start quest: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Update quest progress.
   *
   * PUT /api/campaign/{campaign_id}/quests/{quest_id}/progress
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function updateProgress(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (empty($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid request body',
        ], 400);
      }

      $required_fields = ['objective_id', 'action', 'entity_id'];
      foreach ($required_fields as $field) {
        if (empty($payload[$field])) {
          return new JsonResponse([
            'success' => FALSE,
            'error' => "Missing required field: {$field}",
          ], 400);
        }
      }

      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $entity_type = $payload['entity_type'] ?? 'party';
      $amount = (int) ($payload['amount'] ?? 1);
      $character_id = !empty($payload['character_id']) ? (int) $payload['character_id'] : NULL;

      $result = $quest_tracker->updateObjectiveProgress(
        $campaign_id,
        $quest_id,
        $payload['objective_id'],
        $amount,
        $character_id
      );

      if (empty($result)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to update quest progress',
        ], 500);
      }

      return new JsonResponse([
        'success' => TRUE,
        'objective_state' => $result,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update quest progress: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Complete a quest.
   *
   * POST /api/campaign/{campaign_id}/quests/{quest_id}/complete
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function completeQuest(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      $character_id = !empty($payload['character_id']) ? (int) $payload['character_id'] : NULL;
      if ($character_id === NULL && !empty($payload['entity_id'])) {
        $character_id = (int) $payload['entity_id'];
      }

      if ($character_id === NULL) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Missing required field: character_id',
        ], 400);
      }

      $outcome = $payload['outcome'] ?? 'success';

      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $result = $quest_tracker->completeQuest(
        $campaign_id,
        $quest_id,
        $character_id,
        $outcome
      );

      if (empty($result)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to complete quest',
        ], 500);
      }

      $this->postQuestCompletionDialog($campaign_id, $quest_id, $character_id);

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Quest completed',
        'quest_id' => $quest_id,
        'outcome' => $outcome,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to complete quest: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Get quest journal for a character.
   *
   * GET /api/campaign/{campaign_id}/character/{character_id}/quest-journal
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $character_id
   *   The character ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getQuestJournal(int $campaign_id, string $character_id): JsonResponse {
    try {
      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $tracking = $quest_tracker->getCharacterQuestTracking($campaign_id, (int) $character_id);
      $log = $quest_tracker->getCharacterQuestLog($campaign_id, (int) $character_id);

      $normalize_tracking = static function (array $entry): array {
        $entry['objective_states'] = json_decode((string) ($entry['objective_states'] ?? '[]'), TRUE) ?? [];
        $entry['generated_objectives'] = json_decode((string) ($entry['generated_objectives'] ?? '[]'), TRUE) ?? [];
        $entry['generated_rewards'] = json_decode((string) ($entry['generated_rewards'] ?? '[]'), TRUE) ?? [];
        $entry['quest_data'] = json_decode((string) ($entry['quest_data'] ?? '{}'), TRUE) ?? [];
        $entry['title'] = (string) ($entry['title'] ?? $entry['quest_name'] ?? $entry['name'] ?? $entry['quest_id'] ?? '');
        $entry['quest_key'] = (string) ($entry['quest_key'] ?? $entry['source_template_id'] ?? $entry['quest_id'] ?? '');
        return $entry;
      };

      $normalize_log = static function (array $entry): array {
        $entry['event_data'] = json_decode((string) ($entry['event_data'] ?? '{}'), TRUE) ?? [];
        return $entry;
      };

      $journal_tracking = array_map($normalize_tracking, $tracking);
      $journal_log = array_map($normalize_log, $log);

      return new JsonResponse([
        'success' => TRUE,
        'character_id' => (int) $character_id,
        'tracking' => array_values($journal_tracking),
        'log' => array_values($journal_log),
        'counts' => [
          'tracking' => count($journal_tracking),
          'log' => count($journal_log),
        ],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch quest journal: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Get campaign-level quest journal and tracking.
   *
   * GET /api/campaign/{campaign_id}/quest-journal
   *
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Campaign-level tracking + log payload.
   */
  public function getCampaignQuestJournal(int $campaign_id): JsonResponse {
    try {
      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $tracking = $quest_tracker->getCampaignQuestTracking($campaign_id);
      $log = $quest_tracker->getCampaignQuestLog($campaign_id);

      $normalize_tracking = static function (array $entry): array {
        $entry['objective_states'] = json_decode((string) ($entry['objective_states'] ?? '[]'), TRUE) ?? [];
        $entry['generated_objectives'] = json_decode((string) ($entry['generated_objectives'] ?? '[]'), TRUE) ?? [];
        $entry['generated_rewards'] = json_decode((string) ($entry['generated_rewards'] ?? '[]'), TRUE) ?? [];
        $entry['quest_data'] = json_decode((string) ($entry['quest_data'] ?? '{}'), TRUE) ?? [];
        $entry['title'] = (string) ($entry['title'] ?? $entry['quest_name'] ?? $entry['name'] ?? $entry['quest_id'] ?? '');
        $entry['quest_key'] = (string) ($entry['quest_key'] ?? $entry['source_template_id'] ?? $entry['quest_id'] ?? '');
        return $entry;
      };

      $normalize_log = static function (array $entry): array {
        $entry['event_data'] = json_decode((string) ($entry['event_data'] ?? '{}'), TRUE) ?? [];
        return $entry;
      };

      $campaign_tracking = array_map($normalize_tracking, $tracking);
      $campaign_log = array_map($normalize_log, $log);

      return new JsonResponse([
        'success' => TRUE,
        'campaign_id' => $campaign_id,
        'tracking' => array_values($campaign_tracking),
        'log' => array_values($campaign_log),
        'counts' => [
          'tracking' => count($campaign_tracking),
          'log' => count($campaign_log),
        ],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch campaign quest journal: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Ingest a quest touchpoint event.
   *
   * POST /api/campaign/{campaign_id}/quest-touchpoints
   */
  public function ingestTouchpoint(int $campaign_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid request body',
        ], 400);
      }

      /** @var \Drupal\dungeoncrawler_content\Service\QuestTouchpointService $touchpoint_service */
      $touchpoint_service = \Drupal::service('dungeoncrawler_content.quest_touchpoint');
      $result = $touchpoint_service->ingestEvent($campaign_id, $payload);

      $status_code = !empty($result['success']) ? 200 : 400;
      return new JsonResponse($result, $status_code);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to ingest quest touchpoint: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * List pending touchpoint confirmations.
   *
   * GET /api/campaign/{campaign_id}/quest-confirmations
   */
  public function listTouchpointConfirmations(int $campaign_id, Request $request): JsonResponse {
    try {
      $character_id = (int) $request->query->get('character_id', 0);
      $character_filter = $character_id > 0 ? $character_id : NULL;

      /** @var \Drupal\dungeoncrawler_content\Service\QuestConfirmationService $confirmation_service */
      $confirmation_service = \Drupal::service('dungeoncrawler_content.quest_confirmation');
      $rows = $confirmation_service->listPending($campaign_id, $character_filter);

      return new JsonResponse([
        'success' => TRUE,
        'campaign_id' => $campaign_id,
        'character_id' => $character_filter,
        'confirmations' => array_values($rows),
        'count' => count($rows),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to list quest confirmations: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Resolve a pending touchpoint confirmation.
   *
   * POST /api/campaign/{campaign_id}/quest-confirmations/{confirmation_id}/resolve
   */
  public function resolveTouchpointConfirmation(int $campaign_id, string $confirmation_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE) ?? [];
      $resolution = (string) ($payload['resolution'] ?? 'rejected');
      $selected_objective_id = !empty($payload['selected_objective_id']) ? (string) $payload['selected_objective_id'] : NULL;
      $resolved_by = (string) ($payload['resolved_by'] ?? 'player');

      /** @var \Drupal\dungeoncrawler_content\Service\QuestConfirmationService $confirmation_service */
      $confirmation_service = \Drupal::service('dungeoncrawler_content.quest_confirmation');
      $existing = $confirmation_service->get($confirmation_id);

      if (empty($existing)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Confirmation not found',
        ], 404);
      }

      if ((int) ($existing['campaign_id'] ?? 0) !== $campaign_id) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Confirmation does not belong to campaign',
        ], 400);
      }

      $resolved = $confirmation_service->resolve($confirmation_id, $resolution, $selected_objective_id, $resolved_by);
      if (empty($resolved)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to resolve confirmation',
        ], 500);
      }

      $apply_result = NULL;
      if (($resolved['status'] ?? '') === 'approved') {
        $touchpoint_payload = $resolved['touchpoint_event'] ?? [];
        if (is_array($touchpoint_payload)) {
          if (!isset($touchpoint_payload['touchpoint']) || !is_array($touchpoint_payload['touchpoint'])) {
            $touchpoint_payload['touchpoint'] = [];
          }

          if (!empty($selected_objective_id)) {
            $touchpoint_payload['touchpoint']['objective_id'] = (string) $selected_objective_id;
          }
          $touchpoint_payload['touchpoint']['confidence'] = 'high';

          /** @var \Drupal\dungeoncrawler_content\Service\QuestTouchpointService $touchpoint_service */
          $touchpoint_service = \Drupal::service('dungeoncrawler_content.quest_touchpoint');
          $apply_result = $touchpoint_service->ingestEvent($campaign_id, $touchpoint_payload);
        }
      }

      return new JsonResponse([
        'success' => TRUE,
        'confirmation' => $resolved,
        'apply_result' => $apply_result,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to resolve quest confirmation: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Post a quest completion message to room chat.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID completing the quest.
   */
  protected function postQuestCompletionDialog(int $campaign_id, string $quest_id, int $character_id): void {
    try {
      $quest = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', ['quest_name', 'giver_npc_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->execute()
        ->fetchAssoc();

      if (empty($quest)) {
        return;
      }

      $speaker = 'Quest Giver';
      if (!empty($quest['giver_npc_id'])) {
        $npc_name = $this->database->select('dc_campaign_characters', 'cc')
          ->fields('cc', ['name'])
          ->condition('campaign_id', $campaign_id)
          ->condition('id', (int) $quest['giver_npc_id'])
          ->execute()
          ->fetchField();
        if (!empty($npc_name)) {
          $speaker = $npc_name;
        }
      }

      $message = sprintf('Quest complete: %s', $quest['quest_name'] ?? $quest_id);
      $this->chatService->postMessage(
        $campaign_id,
        'tavern_entrance',
        $speaker,
        $message,
        'npc',
        $character_id
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to post quest completion dialog: @error', ['@error' => $e->getMessage()]);
    }
  }

}
