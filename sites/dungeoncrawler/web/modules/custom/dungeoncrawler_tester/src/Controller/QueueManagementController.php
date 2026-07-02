<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Queue management AJAX endpoints for the testing module.
 *
 * This controller provides AJAX endpoints used by the testing dashboard
 * for queue operations (run, status, logs, delete, rerun).
 * The queue UI is embedded directly in the testing dashboard.
 */
class QueueManagementController extends ControllerBase {

  /**
   * Single queue definition for tester runs.
   */
  private const QUEUE_DEFINITIONS = [
    'dungeoncrawler_tester_runs' => [
      'name' => 'Testing Runs',
      'description' => 'Background execution of dashboard run jobs.',
      'icon' => '🧪',
    ],
  ];

  public function __construct(
    private QueueFactory $queueFactory,
    private QueueWorkerManagerInterface $queueManager,
    private StateInterface $state,
    private Connection $database,
    private CsrfTokenGenerator $csrfToken,
    private UuidInterface $uuid,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('state'),
      $container->get('database'),
      $container->get('csrf_token'),
      $container->get('uuid'),
    );
  }

  /**
   * AJAX: run the tester queue.
   */
  public function runQueueAjax(Request $request): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $limit = (int) ($request->request->get('limit') ?? 5);
    if ($limit < 1) {
      $limit = 1;
    }

    $queue_id = 'dungeoncrawler_tester_runs';
    try {
      $this->reclaimExpiredQueueClaims($queue_id);
      $processed = $this->processQueue($queue_id, $limit, 60);
      $remaining = $this->queueFactory->get($queue_id)->numberOfItems();
      $this->releaseRegressionLockIfIdle();
      return new JsonResponse([
        'success' => TRUE,
        'processed' => $processed,
        'remaining' => $remaining,
        'message' => "Processed {$processed} item(s); {$remaining} remaining",
      ]);
    }
    catch (\Throwable $e) {
      $this->getLogger('dungeoncrawler_tester')->error('Queue run failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX: queue status.
   */
  public function getQueueStatusAjax(): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    return new JsonResponse([
      'success' => TRUE,
      'queues' => $this->getQueueStatus(),
    ]);
  }

  /**
   * AJAX: queue logs (recent watchdog entries).
   */
  public function getQueueLogsAjax(): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'timestamp', 'type', 'severity', 'message', 'variables'])
      ->condition('type', 'dungeoncrawler_tester')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 20);

    $rows = $query->execute()->fetchAll();
    $logs = [];
    foreach ($rows as $row) {
      $vars = $this->safeUnserializeArray($row->variables);
      $message = strtr($row->message, $vars);
      $logs[] = [
        'timestamp' => $row->timestamp,
        'message' => $message,
        'severity' => (int) $row->severity,
      ];
    }

    return new JsonResponse([
      'success' => TRUE,
      'logs' => $logs,
    ]);
  }

  /**
   * AJAX: delete one queue item.
   */
  public function deleteQueueItemAjax(Request $request): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $item_id = $this->getRequestInt($request, 'item_id');
    if ($item_id <= 0) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Missing or invalid item_id'], 400);
    }

    $item = $this->loadQueueItemById($item_id);
    if (!$item) {
      return new JsonResponse([
        'success' => FALSE,
        'stale' => TRUE,
        'remaining' => $this->queueFactory->get('dungeoncrawler_tester_runs')->numberOfItems(),
        'message' => "Queue item {$item_id} no longer exists (queue view is stale). Refreshing recommended.",
      ]);
    }

    $deleted = (int) $this->database->delete('queue')
      ->condition('item_id', $item_id)
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->execute();

    if ($deleted < 1) {
      return new JsonResponse(['success' => FALSE, 'message' => "Queue item {$item_id} could not be deleted"], 500);
    }

    if (!empty($item['data']['stage_id'])) {
      $stage_id = (string) $item['data']['stage_id'];
      $this->updateRun($stage_id, [
        'status' => 'failed',
        'exit_code' => -1,
        'ended' => time(),
        'duration' => NULL,
        'output' => 'Queue item deleted from Queue Management before execution completed.',
      ]);
    }

    $this->releaseRegressionLockIfIdle();
    $remaining = $this->queueFactory->get('dungeoncrawler_tester_runs')->numberOfItems();

    return new JsonResponse([
      'success' => TRUE,
      'remaining' => $remaining,
      'message' => "Deleted queue item {$item_id}.",
    ]);
  }

  /**
   * AJAX: re-queue one queue item for another attempt.
   */
  public function rerunQueueItemAjax(Request $request): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $item_id = $this->getRequestInt($request, 'item_id');
    if ($item_id <= 0) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Missing or invalid item_id'], 400);
    }

    $item = $this->loadQueueItemById($item_id);
    if (!$item) {
      return new JsonResponse([
        'success' => FALSE,
        'stale' => TRUE,
        'remaining' => $this->queueFactory->get('dungeoncrawler_tester_runs')->numberOfItems(),
        'message' => "Queue item {$item_id} no longer exists (queue view is stale). Refreshing recommended.",
      ]);
    }

    $data = $item['data'];
    if (empty($data) || !is_array($data) || empty($data['args']) || empty($data['stage_id'])) {
      return new JsonResponse(['success' => FALSE, 'message' => "Queue item {$item_id} has invalid payload"], 400);
    }

    $data['job_id'] = $this->uuid->generate();
    $display = (string) ($data['display'] ?? implode(' ', $data['args']));

    $this->database->delete('queue')
      ->condition('item_id', $item_id)
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->execute();

    $this->queueFactory->get('dungeoncrawler_tester_runs')->createItem($data);

    $this->updateRun((string) $data['stage_id'], [
      'job_id' => $data['job_id'],
      'command' => $display,
      'status' => 'pending',
      'exit_code' => NULL,
      'started' => NULL,
      'ended' => NULL,
      'duration' => NULL,
      'output' => '',
    ]);

    $this->state->set('dungeoncrawler_tester.regression_batch_active', TRUE);
    $remaining = $this->queueFactory->get('dungeoncrawler_tester_runs')->numberOfItems();

    return new JsonResponse([
      'success' => TRUE,
      'remaining' => $remaining,
      'message' => "Re-queued queue item {$item_id} as job {$data['job_id']}.",
    ]);
  }

  /**
   * Process queue items with basic timeout.
   */
  private function processQueue(string $queue_id, int $max_items, int $timeout): int {
    $start = microtime(TRUE);
    $queue = $this->queueFactory->get($queue_id);
    $worker = $this->queueManager->createInstance($queue_id);
    $processed = 0;

    $this->state->set('dungeoncrawler_tester.manual_queue_runner', TRUE);
    try {
      while ($processed < $max_items && ($item = $queue->claimItem())) {
        $elapsed = microtime(TRUE) - $start;
        if ($elapsed > $timeout) {
          $this->getLogger('dungeoncrawler_tester')->warning('Queue processing timed out after @s seconds', ['@s' => round($elapsed, 2)]);
          break;
        }
        try {
          $worker->processItem($item->data);
          $queue->deleteItem($item);
          $processed++;
        }
        catch (\Throwable $e) {
          $this->getLogger('dungeoncrawler_tester')->error('Queue item failed: @msg', ['@msg' => $e->getMessage()]);
          $queue->releaseItem($item);
          break;
        }
      }
    }
    finally {
      $this->state->set('dungeoncrawler_tester.manual_queue_runner', FALSE);
    }

    return $processed;
  }

  /**
   * Reset expired queue claims so stale jobs can be reclaimed.
   */
  private function reclaimExpiredQueueClaims(string $queue_id): void {
    $now = time();

    $reclaimed = (int) $this->database->update('queue')
      ->fields(['expire' => 0])
      ->condition('name', $queue_id)
      ->condition('expire', 0, '>')
      ->condition('expire', $now, '<')
      ->execute();

    if ($reclaimed > 0) {
      $this->getLogger('dungeoncrawler_tester')->notice('Reclaimed @count expired queue claim(s) for @queue.', [
        '@count' => $reclaimed,
        '@queue' => $queue_id,
      ]);
    }
  }

  /**
   * Load active queue items for display.
   */
  private function loadQueueItems(): array {
    $connection = $this->database;
    $queue_items = [];

    $query = $connection->select('queue', 'q')
      ->fields('q', ['item_id', 'data', 'expire', 'created'])
      ->condition('name', 'dungeoncrawler_tester_runs');
    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $data = $this->safeUnserializeArray($row->data);
      $preview = $this->getQueueItemPreview($data);
      $queue_items[] = [
        'item_id' => $row->item_id,
        'queue_name' => 'dungeoncrawler_tester_runs',
        'queue_label' => self::QUEUE_DEFINITIONS['dungeoncrawler_tester_runs']['name'],
        'created' => $row->created,
        'expire' => $row->expire,
        'data' => $data,
        'data_preview' => $preview,
      ];
    }

    usort($queue_items, fn($a, $b) => $b['created'] <=> $a['created']);
    return $queue_items;
  }

  /**
   * Load a queue row and payload by item id.
   */
  private function loadQueueItemById(int $item_id): ?array {
    $row = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'name', 'data', 'expire', 'created'])
      ->condition('item_id', $item_id)
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->range(0, 1)
      ->execute()
      ->fetchObject();

    if (!$row) {
      return NULL;
    }

    $data = $this->safeUnserializeArray($row->data);

    return [
      'item_id' => (int) $row->item_id,
      'name' => (string) $row->name,
      'expire' => (int) $row->expire,
      'created' => (int) $row->created,
      'data' => $data,
    ];
  }

  /**
   * Read an integer request value from form or JSON body.
   */
  private function getRequestInt(Request $request, string $key): int {
    $value = $request->request->get($key);
    if ($value !== NULL) {
      return (int) $value;
    }

    $content = (string) $request->getContent();
    if ($content !== '') {
      $decoded = json_decode($content, TRUE);
      if (is_array($decoded) && array_key_exists($key, $decoded)) {
        return (int) $decoded[$key];
      }
    }

    return 0;
  }

  private function getQueueItemPreview($data): array {
    $preview = [];
    if (is_array($data)) {
      if (!empty($data['stage_id'])) {
        $preview['stage'] = $data['stage_id'];
      }
      if (!empty($data['display'])) {
        $preview['command'] = $data['display'];
      }
      if (!empty($data['job_id'])) {
        $preview['job_id'] = $data['job_id'];
      }
    }
    return $preview;
  }

  /**
   * Safely decode a serialized payload into an array.
   */
  private function safeUnserializeArray(mixed $value): array {
    $decoded = $this->safeUnserializeValue($value);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Safely decode serialized values without allowing object instantiation.
   */
  private function safeUnserializeValue(mixed $value): mixed {
    if (!is_string($value) || $value === '') {
      return NULL;
    }

    set_error_handler(static function (): bool {
      return TRUE;
    });

    try {
      $decoded = unserialize($value, ['allowed_classes' => FALSE]);
    }
    finally {
      restore_error_handler();
    }

    if ($decoded === FALSE && $value !== 'b:0;') {
      return NULL;
    }

    return $decoded;
  }

  /**
   * Persist a run update for a specific stage.
   */
  private function updateRun(string $stage_id, array $data): void {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $current = $runs[$stage_id] ?? [];
    $runs[$stage_id] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.runs', $runs);
  }

  /**
   * Release regression lock if no work remains in queue or running.
   */
  private function releaseRegressionLockIfIdle(): void {
    $remaining = $this->queueFactory->get('dungeoncrawler_tester_runs')->numberOfItems();
    if ($remaining > 0) {
      return;
    }

    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    foreach ($runs as $run) {
      $status = $run['status'] ?? '';
      if (in_array($status, ['pending', 'running'], TRUE)) {
        return;
      }
    }

    $this->state->set('dungeoncrawler_tester.regression_batch_active', FALSE);
  }

  /**
   * Build queue status for UI.
   */
  private function getQueueStatus(): array {
    $status = [];
    foreach (self::QUEUE_DEFINITIONS as $queue_id => $info) {
      $queue = $this->queueFactory->get($queue_id);
      $status[$queue_id] = [
        'id' => $queue_id,
        'name' => $info['name'],
        'description' => $info['description'],
        'icon' => $info['icon'],
        'items' => $queue->numberOfItems(),
      ];
    }
    return $status;
  }

}
