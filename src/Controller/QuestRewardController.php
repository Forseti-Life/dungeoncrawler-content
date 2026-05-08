<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for quest reward endpoints.
 */
class QuestRewardController extends ControllerBase {

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
   * Constructs a QuestRewardController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('database'),
      $container->get('logger.factory')
    );
  }

  /**
   * Claim quest rewards.
   *
   * POST /api/campaign/{campaign_id}/quests/{quest_id}/rewards/claim
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
  public function claimRewards(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (empty($payload['character_id'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Missing required field: character_id',
        ], 400);
      }

      $character_id = $payload['character_id'];

      $quest_reward_service = \Drupal::service('dungeoncrawler_content.quest_reward');

      // Claim rewards
      $reward_result = $quest_reward_service->claimQuestRewards(
        $campaign_id,
        $quest_id,
        $character_id
      );

      if (empty($reward_result) || empty($reward_result['success'])) {
        $error = (string) ($reward_result['error'] ?? 'Failed to claim rewards');
        $status_code = str_contains($error, 'not found') ? 404 : 400;
        return new JsonResponse([
          'success' => FALSE,
          'error' => $error,
        ], $status_code);
      }

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Rewards claimed successfully',
        'rewards' => $reward_result['rewards_granted'] ?? [],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to claim quest rewards: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Get reward summary for a quest.
   *
   * GET /api/campaign/{campaign_id}/quests/{quest_id}/rewards
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getRewardSummary(int $campaign_id, string $quest_id): JsonResponse {
    try {
      $quest = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', ['quest_id', 'quest_name', 'generated_rewards'])
        ->condition('q.campaign_id', $campaign_id)
        ->condition('q.quest_id', $quest_id)
        ->execute()
        ->fetchAssoc();

      if (empty($quest)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Quest not found',
        ], 404);
      }

      $rewards = json_decode($quest['generated_rewards'], TRUE) ?? [];

      return new JsonResponse([
        'success' => TRUE,
        'quest_id' => $quest_id,
        'quest_name' => $quest['quest_name'],
        'rewards' => $rewards,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch reward summary: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

}
