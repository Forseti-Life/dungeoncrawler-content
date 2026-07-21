<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\QuestGeneratorService;
use Drupal\dungeoncrawler_content\Service\StorylineQuestLifecycleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for quest generation endpoints.
 */
class QuestGeneratorController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The quest generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\QuestGeneratorService
   */
  protected QuestGeneratorService $questGenerator;
  protected StorylineQuestLifecycleService $storylineQuestLifecycleService;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a QuestGeneratorController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\dungeoncrawler_content\Service\QuestGeneratorService $quest_generator
   *   The quest generator service.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    QuestGeneratorService $quest_generator,
    StorylineQuestLifecycleService $storyline_quest_lifecycle_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->questGenerator = $quest_generator;
    $this->storylineQuestLifecycleService = $storyline_quest_lifecycle_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('dungeoncrawler_content.quest_generator'),
      $container->get('dungeoncrawler_content.storyline_quest_lifecycle')
    );
  }

  /**
   * Generate a quest from a template.
   *
   * POST /api/campaign/{campaign_id}/quests/generate
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function generate(int $campaign_id, Request $request): JsonResponse {
    try {
      // Parse request body
      $payload = json_decode($request->getContent(), TRUE);
      if (empty($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid request body',
        ], 400);
      }

      // Validate input
      if (empty($payload['template_id'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Missing required field: template_id',
        ], 400);
      }

      // Build context
      $context = $payload['context'] ?? [];
      $context['party_level'] = $context['party_level'] ?? 1;
      $context['difficulty'] = $context['difficulty'] ?? 'moderate';

      // Generate quest
      $quest_data = $this->questGenerator->generateQuestFromTemplate(
        $payload['template_id'],
        $campaign_id,
        $context
      );

      if (empty($quest_data)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to generate quest from template',
        ], 500);
      }

      $template_id = trim((string) ($quest_data['source_template_id'] ?? ''));
      if ($template_id === '') {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Generated quest is missing source_template_id',
        ], 500);
      }
      $stored_quest = $this->storylineQuestLifecycleService->ensureOfferedQuestFromTemplateAndLoad(
        $campaign_id,
        $template_id,
        static fn(): array => $quest_data
      );
      if (!is_array($stored_quest)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to persist generated quest',
        ], 500);
      }

      $quest_contract = $this->questGenerator->buildQuestSummaryEntry($stored_quest);
      $quest_summary = $this->questGenerator->buildQuestSummaryPayload(
        (string) ($quest_contract['location_id'] ?? ('campaign-' . $campaign_id)),
        [],
        in_array((string) ($stored_quest['status'] ?? ''), ['offered', 'available'], TRUE) ? [$stored_quest] : [],
        (string) ($stored_quest['status'] ?? '') === 'lead' ? [$stored_quest] : []
      );

      return new JsonResponse([
        'success' => TRUE,
        'quest' => [
          'quest_id' => $stored_quest['quest_id'],
          'name' => $stored_quest['quest_name'],
          'description' => $stored_quest['quest_description'],
          'quest_type' => $stored_quest['quest_type'],
          'objectives' => json_decode((string) ($stored_quest['generated_objectives'] ?? '[]'), TRUE),
          'rewards' => json_decode((string) ($stored_quest['generated_rewards'] ?? '[]'), TRUE),
          'status' => $stored_quest['status'],
        ],
        'quest_contract' => $quest_contract,
        'quest_summary' => $quest_summary,
        'objective_options' => $this->questGenerator->getObjectiveTypeOptions(),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Quest generation failed: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Generate multiple quests for a location.
   *
   * POST /api/campaign/{campaign_id}/quests/generate-for-location
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function generateForLocation(int $campaign_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (empty($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid request body',
        ], 400);
      }

      // Build context
      $context = $payload['context'] ?? [];
      $context['party_level'] = $context['party_level'] ?? 1;
      $context['location'] = $payload['location_id'] ?? 'unknown';
      $context['location_tags'] = $payload['location_tags'] ?? [];

      // Generate quests
      $count = $payload['count'] ?? 3;
      $quests = $this->questGenerator->generateQuestsForLocation($campaign_id, $context, $count);

      $stored_quests = [];
      foreach ($quests as $quest_data) {
        if (!is_array($quest_data)) {
          continue;
        }
        $template_id = trim((string) ($quest_data['source_template_id'] ?? ''));
        if ($template_id === '') {
          continue;
        }
        $stored = $this->storylineQuestLifecycleService->ensureOfferedQuestFromTemplateAndLoad(
          $campaign_id,
          $template_id,
          static fn(): array => $quest_data
        );
        if (is_array($stored)) {
          $stored_quests[] = $stored;
        }
      }

      $quest_contracts = array_values(array_map([$this->questGenerator, 'buildQuestSummaryEntry'], $stored_quests));
      $quest_summary = $this->questGenerator->buildQuestSummaryPayload(
        (string) ($context['location'] ?? ('campaign-' . $campaign_id)),
        [],
        array_values(array_filter($stored_quests, static fn(array $quest): bool => in_array((string) ($quest['status'] ?? ''), ['offered', 'available'], TRUE))),
        array_values(array_filter($stored_quests, static fn(array $quest): bool => (string) ($quest['status'] ?? '') === 'lead'))
      );

      $response_quests = array_map(function ($q) {
        return [
          'quest_id' => $q['quest_id'],
          'name' => $q['quest_name'],
          'description' => $q['quest_description'],
          'type' => $q['quest_type'],
        ];
      }, $stored_quests);

      return new JsonResponse([
        'success' => TRUE,
        'quests' => $response_quests,
        'quest_contracts' => $quest_contracts,
        'quest_summary' => $quest_summary,
        'objective_options' => $this->questGenerator->getObjectiveTypeOptions(),
        'count' => count($stored_quests),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Location quest generation failed: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

}
