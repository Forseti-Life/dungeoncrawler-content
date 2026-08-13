<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
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
   * Quest contract builder service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\QuestGeneratorService
   */
  protected QuestGeneratorService $questGenerator;

  /**
   * Quest tracker service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\QuestTrackerService
   */
  protected QuestTrackerService $questTracker;

  /**
   * Constructs a QuestTrackerController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory, RoomChatService $chat_service, QuestGeneratorService $quest_generator, QuestTrackerService $quest_tracker) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->chatService = $chat_service;
    $this->questGenerator = $quest_generator;
    $this->questTracker = $quest_tracker;
  }

  /**
   * Partition quest rows into the canonical quest summary buckets.
   */
  protected function partitionQuestRowsForSummary(array $rows): array {
    $deduped_rows = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $template_key = $this->resolveQuestSummaryTemplateKey($row);
      if ($template_key === '') {
        continue;
      }

      $rank = $this->rankQuestSummaryRow($row);
      $updated_at = (int) ($row['last_updated'] ?? 0);
      $created_at = (int) ($row['created_at'] ?? 0);

      if (!isset($deduped_rows[$template_key])) {
        $deduped_rows[$template_key] = [
          'rank' => $rank,
          'updated_at' => $updated_at,
          'created_at' => $created_at,
          'row' => $row,
        ];
        continue;
      }

      $existing = $deduped_rows[$template_key];
      if ($this->shouldReplaceQuestSummaryRow($rank, $updated_at, $created_at, $existing)) {
        $deduped_rows[$template_key] = [
          'rank' => $rank,
          'updated_at' => $updated_at,
          'created_at' => $created_at,
          'row' => $row,
        ];
      }
    }

    $active = [];
    $offers = [];
    $leads = [];
    $completed = [];

    foreach (array_values(array_map(static fn(array $entry): array => $entry['row'], $deduped_rows)) as $row) {
      if (!is_array($row)) {
        continue;
      }

      $status = strtolower(trim((string) ($row['status'] ?? '')));
      if (!empty($row['completed_at']) || $status === 'completed') {
        $completed[] = $row;
        continue;
      }
      if (in_array($status, ['active', 'ready_for_turn_in'], TRUE)) {
        $active[] = $row;
        continue;
      }
      if ($status === 'offered') {
        $offers[] = $row;
        continue;
      }
      if ($status === 'lead') {
        $leads[] = $row;
      }
    }

    return [
      'active' => $active,
      'offers' => $offers,
      'leads' => $leads,
      'completed' => $completed,
    ];
  }

  /**
   * Resolve a stable template key for quest-summary dedupe.
   */
  protected function resolveQuestSummaryTemplateKey(array $row): string {
    $template_key = trim((string) ($row['source_template_id'] ?? ''));
    if ($template_key !== '') {
      return $template_key;
    }
    return trim((string) ($row['quest_id'] ?? ''));
  }

  /**
   * Rank a quest row by canonical summary bucket priority.
   */
  protected function rankQuestSummaryRow(array $row): int {
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    return match (TRUE) {
      in_array($status, ['active', 'ready_for_turn_in'], TRUE) => 1,
      $status === 'offered' => 2,
      $status === 'lead' => 3,
      (!empty($row['completed_at']) || $status === 'completed') => 4,
      default => 5,
    };
  }

  /**
   * Determine whether the incoming row should replace the current dedupe winner.
   */
  protected function shouldReplaceQuestSummaryRow(
    int $candidate_rank,
    int $candidate_updated_at,
    int $candidate_created_at,
    array $existing
  ): bool {
    $existing_rank = (int) ($existing['rank'] ?? PHP_INT_MAX);
    if ($candidate_rank < $existing_rank) {
      return TRUE;
    }
    if ($candidate_rank > $existing_rank) {
      return FALSE;
    }

    $existing_updated = (int) ($existing['updated_at'] ?? 0);
    if ($candidate_updated_at > $existing_updated) {
      return TRUE;
    }
    if ($candidate_updated_at < $existing_updated) {
      return FALSE;
    }

    $existing_created = (int) ($existing['created_at'] ?? 0);
    return $candidate_created_at >= $existing_created;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('dungeoncrawler_content.room_chat_service'),
      $container->get('dungeoncrawler_content.quest_generator'),
      $container->get('dungeoncrawler_content.quest_tracker')
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
  public function getAvailableQuests(int $campaign_id, Request $request): JsonResponse {
    try {
      $character_id = (int) $request->query->get('character_id', 0);
      $location_id = trim((string) $request->query->get('location_id', ''));
      $offers = [];
      $leads = [];
      if ($location_id !== '') {
        $offers = $this->questTracker->getOfferQuests($campaign_id, $location_id, $character_id);
        $leads = $this->questTracker->getLeadQuests($campaign_id, $location_id, $character_id);
      }

      $quest_summary = $this->questGenerator->buildQuestSummaryPayload(
        $location_id !== '' ? $location_id : $this->resolveQuestSummaryLocationId($campaign_id, array_merge($offers, $leads)),
        [],
        $offers,
        $leads,
        $campaign_id
      );
      $quests = array_merge(
        is_array($quest_summary['offers'] ?? NULL) ? $quest_summary['offers'] : [],
        is_array($quest_summary['leads'] ?? NULL) ? $quest_summary['leads'] : []
      );

      return new JsonResponse([
        'success' => TRUE,
        'quests' => $quests,
        'quest_summary' => $quest_summary,
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

      $result = $this->questTracker->startQuest($campaign_id, $quest_id, $character_id, $party_id);

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

      $entity_type = $payload['entity_type'] ?? 'party';
      $amount = (int) ($payload['amount'] ?? 1);
      $character_id = !empty($payload['character_id']) ? (int) $payload['character_id'] : NULL;

      $result = $this->questTracker->updateObjectiveProgress(
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
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON payload',
        ], 400);
      }

      $character_id = NULL;
      if (isset($payload['character_id']) && is_numeric($payload['character_id'])) {
        $candidate = (int) $payload['character_id'];
        if ($candidate > 0) {
          $character_id = $candidate;
        }
      }

      if ($character_id === NULL) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Missing required field: character_id',
        ], 400);
      }

      $outcome = $payload['outcome'] ?? 'success';

      $result = $this->questTracker->completeQuest(
        $campaign_id,
        $quest_id,
        $character_id,
        $outcome
      );

      if (!is_array($result) || empty($result['success'])) {
        throw new \RuntimeException((string) ($result['error'] ?? 'Failed to complete quest'));
      }

      $this->postQuestCompletionDialog($campaign_id, $quest_id, $character_id);

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Quest completed',
        'quest_id' => $quest_id,
        'outcome' => $outcome,
        'rewards' => is_array($result['rewards'] ?? NULL) ? $result['rewards'] : [],
        'rewards_applied' => is_array($result['rewards_applied'] ?? NULL) ? $result['rewards_applied'] : [],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to complete quest: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
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
      $this->logger->info('Quest journal requested: campaign={campaign_id} character={character_id}', [
        'campaign_id' => $campaign_id,
        'character_id' => (int) $character_id,
      ]);

      $tracking = $this->questTracker->getCharacterQuestTracking($campaign_id, (int) $character_id);
      $log = $this->questTracker->getCharacterQuestLog($campaign_id, (int) $character_id);
      $journal_tracking = array_map([$this, 'normalizeQuestJournalTrackingEntry'], $tracking);
      $journal_log = array_map([$this, 'normalizeQuestJournalLogEntry'], $log);
      ['active' => $active, 'offers' => $offers, 'leads' => $leads, 'completed' => $completed] = $this->partitionQuestRowsForSummary($tracking);
      $quest_summary = $this->questGenerator->buildQuestSummaryPayload('campaign', $active, $offers, $leads, $campaign_id, $completed);

      $this->logger->info('Quest journal payload built: campaign={campaign_id} character={character_id} tracking={tracking} active_summary={active_summary}', [
        'campaign_id' => $campaign_id,
        'character_id' => (int) $character_id,
        'tracking' => implode(', ', array_map(static function (array $entry): string {
          return sprintf(
            '%s(status=%s progress_char=%s phase=%s)',
            (string) ($entry['quest_id'] ?? ''),
            (string) ($entry['status'] ?? ''),
            array_key_exists('character_id', $entry) && $entry['character_id'] !== NULL ? (string) $entry['character_id'] : 'NULL',
            array_key_exists('current_phase', $entry) && $entry['current_phase'] !== NULL ? (string) $entry['current_phase'] : 'NULL'
          );
        }, $tracking)),
        'active_summary' => implode(', ', array_map(static function (array $entry): string {
          return (string) ($entry['quest_id'] ?? $entry['quest_key'] ?? '');
        }, is_array($quest_summary['active'] ?? NULL) ? $quest_summary['active'] : [])),
      ]);

      return new JsonResponse([
        'success' => TRUE,
        'character_id' => (int) $character_id,
        'tracking' => array_values($journal_tracking),
        'log' => array_values($journal_log),
        'quest_summary' => $quest_summary,
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
      $tracking = $this->questTracker->getCampaignQuestTracking($campaign_id);
      $log = $this->questTracker->getCampaignQuestLog($campaign_id);
      $campaign_tracking = array_map([$this, 'normalizeQuestJournalTrackingEntry'], $tracking);
      $campaign_log = array_map([$this, 'normalizeQuestJournalLogEntry'], $log);

      return new JsonResponse([
        'success' => TRUE,
        'campaign_id' => $campaign_id,
        'tracking' => array_values($campaign_tracking),
        'log' => array_values($campaign_log),
        'quest_summary' => $this->questGenerator->buildQuestSummaryPayload(
          'campaign',
          $this->partitionQuestRowsForSummary($tracking)['active'],
          $this->partitionQuestRowsForSummary($tracking)['offers'],
          $this->partitionQuestRowsForSummary($tracking)['leads'],
          $campaign_id,
          $this->partitionQuestRowsForSummary($tracking)['completed']
        ),
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

      /** @var \Drupal\dungeoncrawler_content\Service\StateValidationService $state_validation */
      $state_validation = \Drupal::service('dungeoncrawler_content.state_validation_service');
      $validation = $state_validation->validateQuestTouchpointIngest($payload);
      if (empty($validation['valid'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid quest touchpoint ingest payload',
          'details' => array_values(array_filter(array_map('strval', (array) ($validation['errors'] ?? [])))),
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
   * Normalize one quest-tracking row into the canonical client handoff shape.
   */
  protected function normalizeQuestJournalTrackingEntry(array $entry): array {
    $normalized = $this->questGenerator->buildQuestSummaryEntry($entry);
    $normalized['started_at'] = isset($entry['started_at']) ? (int) $entry['started_at'] : 0;
    $normalized['last_updated'] = isset($entry['last_updated']) ? (int) $entry['last_updated'] : 0;
    $normalized['completed_at'] = isset($entry['completed_at']) && $entry['completed_at'] !== NULL
      ? (int) $entry['completed_at']
      : NULL;
    $normalized['outcome'] = isset($entry['outcome']) && $entry['outcome'] !== ''
      ? (string) $entry['outcome']
      : NULL;
    return $normalized;
  }

  /**
   * Normalize one quest-log row into a stable client handoff shape.
   */
  protected function normalizeQuestJournalLogEntry(array $entry): array {
    $entry['event_data'] = json_decode((string) ($entry['event_data'] ?? '{}'), TRUE) ?? [];
    $entry['character_id'] = isset($entry['character_id']) && $entry['character_id'] !== NULL
      ? (int) $entry['character_id']
      : NULL;
    $entry['party_id'] = isset($entry['party_id']) && $entry['party_id'] !== NULL
      ? (int) $entry['party_id']
      : NULL;
    $entry['timestamp'] = (int) ($entry['timestamp'] ?? 0);
    return $entry;
  }

  /**
   * Resolve a canonical location id for quest summary payloads.
   */
  protected function resolveQuestSummaryLocationId(int $campaign_id, array $quests): string {
    foreach ($quests as $quest) {
      if (is_array($quest) && !empty($quest['location_id'])) {
        return (string) $quest['location_id'];
      }
    }

    return 'campaign-' . $campaign_id;
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
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid request body',
        ], 400);
      }

      /** @var \Drupal\dungeoncrawler_content\Service\StateValidationService $state_validation */
      $state_validation = \Drupal::service('dungeoncrawler_content.state_validation_service');
      $payload_validation = $state_validation->validateQuestConfirmationResolve($payload);
      if (empty($payload_validation['valid'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid quest confirmation resolve payload',
          'details' => array_values(array_filter(array_map('strval', (array) ($payload_validation['errors'] ?? [])))),
        ], 400);
      }

      $resolution = (string) $payload['resolution'];
      $selected_objective_id = !empty($payload['selected_objective_id']) ? (string) $payload['selected_objective_id'] : NULL;
      $resolved_by = (string) $payload['resolved_by'];

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

          if (empty($selected_objective_id) && count($resolved['candidates'] ?? []) === 1) {
            $selected_objective_id = (string) (($resolved['candidates'][0]['objective_id'] ?? ''));
          }
          if (!empty($selected_objective_id)) {
            $touchpoint_payload['touchpoint']['objective_id'] = (string) $selected_objective_id;
          }
          $touchpoint_payload['touchpoint']['confidence'] = 'high';
          $touchpoint_payload['touchpoint']['matching_mode'] = 'typed_receipt';

          $touchpoint_validation = $state_validation->validateQuestTouchpointIngest($touchpoint_payload);
          if (empty($touchpoint_validation['valid'])) {
            return new JsonResponse([
              'success' => FALSE,
              'error' => 'Resolved touchpoint payload failed quest touchpoint contract validation',
              'details' => array_values(array_filter(array_map('strval', (array) ($touchpoint_validation['errors'] ?? [])))),
            ], 400);
          }

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
        $character_id,
        'room',
        FALSE,
        FALSE,
        NULL,
        [],
        [
          'response_mode' => 'actor_scoped',
          'include_legacy_overlay' => FALSE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to post quest completion dialog: @error', ['@error' => $e->getMessage()]);
    }
  }

}
