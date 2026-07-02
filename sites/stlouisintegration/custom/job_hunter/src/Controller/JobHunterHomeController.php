<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Job Hunter home page.
 */
class JobHunterHomeController extends ControllerBase {

  /**
   * Queue definitions with display names and descriptions.
   */
  protected const QUEUE_DEFINITIONS = [
    'job_hunter_genai_parsing' => [
      'name' => 'Resume AI Parsing',
      'description' => 'Extracts structured data from uploaded resumes using Claude AI',
      'icon' => '📄',
    ],
    'job_hunter_job_posting_parsing' => [
      'name' => 'Job Posting AI Parsing',
      'description' => 'Extracts job requirements, skills, and company info from job postings',
      'icon' => '📋',
    ],
    'job_hunter_resume_tailoring' => [
      'name' => 'Resume Tailoring',
      'description' => 'Generates tailored resumes matching job requirements',
      'icon' => '✨',
    ],
    'job_hunter_text_extraction' => [
      'name' => 'Resume Text Extraction',
      'description' => 'Extracts raw text from PDF/DOCX resume files',
      'icon' => '📝',
    ],
    'job_hunter_profile_text_extraction' => [
      'name' => 'Profile Text Extraction',
      'description' => 'Extracts text from profile attachments',
      'icon' => '👤',
    ],
  ];

  /**
   * Display the Job Hunter home page.
   *
   * @return array
   *   Render array for the home page.
   */
  public function home() {
    $current_user = $this->currentUser();
    $user_id = $current_user->id();

    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();

    $libraries = [
      'job_hunter/job-hunter-home',
    ];

    // Add queue controls library for admin users
    if ($current_user->hasPermission('administer job application automation')) {
      $libraries[] = 'job_hunter/queue-controls';
    }

    $build = [
      '#theme' => 'job_hunter_home',
      '#attached' => [
        'library' => $libraries,
      ],
      '#navigation' => $navigation_block,
    ];

    // User profile section
    $build['#user_profile'] = [
      'view_url' => Url::fromRoute('job_hunter.user_job_seeker_view')->toString(),
      'edit_url' => Url::fromRoute('job_hunter.job_seeker_edit', ['job_seeker_id' => $user_id])->toString(),
    ];

    // Job discovery section
    $build['#job_discovery'] = [
      'start_url' => Url::fromRoute('job_hunter.start_job_discovery')->toString(),
    ];

    // Dashboard section
    $build['#dashboard'] = [
      'main_url' => Url::fromRoute('job_hunter.dashboard')->toString(),
      'companies_url' => Url::fromRoute('job_hunter.companies_overview')->toString(),
    ];

    // Statistics (if available)
    $stats = $this->getUserStatistics($user_id);
    $build['#statistics'] = $stats;

    // Queue status for admin users
    if ($current_user->hasPermission('administer job application automation')) {
      $build['#queue_status'] = $this->getQueueStatus();
      $build['#show_queue_controls'] = TRUE;
    }
    else {
      $build['#show_queue_controls'] = FALSE;
    }

    return $build;
  }

  /**
   * Get status of all Job Hunter queues.
   *
   * @return array
   *   Array of queue status information.
   */
  protected function getQueueStatus(): array {
    $queue_factory = \Drupal::service('queue');
    $status = [];

    foreach (self::QUEUE_DEFINITIONS as $queue_id => $info) {
      $queue = $queue_factory->get($queue_id);
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

  /**
   * AJAX endpoint to run a specific queue.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with results.
   */
  public function runQueueAjax(Request $request): JsonResponse {
    // Check permission
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    $queue_id = $request->request->get('queue_id');
    
    if (!$queue_id || !isset(self::QUEUE_DEFINITIONS[$queue_id])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid queue ID',
      ], 400);
    }

    try {
      $processed = $this->processQueue($queue_id);
      
      return new JsonResponse([
        'success' => TRUE,
        'message' => "Processed {$processed} items from " . self::QUEUE_DEFINITIONS[$queue_id]['name'],
        'processed' => $processed,
        'queue_id' => $queue_id,
        'remaining' => \Drupal::service('queue')->get($queue_id)->numberOfItems(),
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Queue processing error: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX endpoint to run all queues.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with results.
   */
  public function runAllQueuesAjax(Request $request): JsonResponse {
    // Check permission
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    $results = [];
    $total_processed = 0;

    // Process queues in logical order
    $queue_order = [
      'job_hunter_text_extraction',
      'job_hunter_profile_text_extraction',
      'job_hunter_genai_parsing',
      'job_hunter_job_posting_parsing',
      'job_hunter_resume_tailoring',
    ];

    foreach ($queue_order as $queue_id) {
      try {
        $processed = $this->processQueue($queue_id);
        $total_processed += $processed;
        $results[$queue_id] = [
          'processed' => $processed,
          'remaining' => \Drupal::service('queue')->get($queue_id)->numberOfItems(),
        ];
      }
      catch (\Exception $e) {
        $results[$queue_id] = [
          'error' => $e->getMessage(),
        ];
      }
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => "Processed {$total_processed} total items across all queues",
      'total_processed' => $total_processed,
      'results' => $results,
    ]);
  }

  /**
   * AJAX endpoint to get current queue status.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with queue status.
   */
  public function getQueueStatusAjax(): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    return new JsonResponse([
      'success' => TRUE,
      'queues' => $this->getQueueStatus(),
    ]);
  }

  /**
   * Process items from a queue.
   *
   * @param string $queue_id
   *   The queue ID.
   * @param int $max_items
   *   Maximum items to process.
   *
   * @return int
   *   Number of items processed.
   */
  protected function processQueue(string $queue_id, int $max_items = 10): int {
    $queue_factory = \Drupal::service('queue');
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
    
    $queue = $queue_factory->get($queue_id);
    $worker = $queue_worker_manager->createInstance($queue_id);
    
    $processed = 0;
    
    while ($processed < $max_items && ($item = $queue->claimItem())) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        // Release item back to queue on failure
        $queue->releaseItem($item);
        \Drupal::logger('job_hunter')->error('Queue @queue item failed: @error', [
          '@queue' => $queue_id,
          '@error' => $e->getMessage(),
        ]);
        // Continue to next item
      }
    }
    
    return $processed;
  }

  /**
   * Get user statistics for display on home page.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return array
   *   Array of statistics.
   */
  protected function getUserStatistics($user_id) {
    $stats = [
      'total_applications' => 0,
      'active_applications' => 0,
      'companies_tracked' => 0,
      'jobs_saved' => 0,
    ];

    try {
      // Count job postings
      $job_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'job_posting')
        ->condition('uid', $user_id);
      $stats['jobs_saved'] = $job_query->count()->execute();

      // Count companies
      $company_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'company')
        ->condition('uid', $user_id);
      $stats['companies_tracked'] = $company_query->count()->execute();

      // Count applications (if application content type exists)
      $application_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'application')
        ->condition('uid', $user_id);
      $stats['total_applications'] = $application_query->count()->execute();

      // Count active applications (status = in_progress, applied, etc.)
      $active_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'application')
        ->condition('uid', $user_id)
        ->condition('status', 1);
      $stats['active_applications'] = $active_query->count()->execute();
    }
    catch (\Exception $e) {
      $this->getLogger('job_hunter')->error('Error fetching user statistics: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $stats;
  }

}
