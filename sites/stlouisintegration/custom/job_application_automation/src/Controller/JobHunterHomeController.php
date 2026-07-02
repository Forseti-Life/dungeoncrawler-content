<?php

namespace Drupal\job_application_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for Job Hunter home page.
 */
class JobHunterHomeController extends ControllerBase {

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

    $build = [
      '#theme' => 'job_hunter_home',
      '#attached' => [
        'library' => [
          'job_application_automation/job-hunter-home',
        ],
      ],
      '#navigation' => $navigation_block,
    ];

    // User profile section
    $build['#user_profile'] = [
      'view_url' => Url::fromRoute('job_application_automation.user_job_seeker_view')->toString(),
      'edit_url' => Url::fromRoute('job_application_automation.job_seeker_edit', ['job_seeker_id' => $user_id])->toString(),
    ];

    // Job discovery section
    $build['#job_discovery'] = [
      'start_url' => Url::fromRoute('job_application_automation.start_job_discovery')->toString(),
    ];

    // Dashboard section
    $build['#dashboard'] = [
      'main_url' => Url::fromRoute('job_application_automation.dashboard')->toString(),
      'companies_url' => Url::fromRoute('job_application_automation.companies_overview')->toString(),
    ];

    // Statistics (if available)
    $stats = $this->getUserStatistics($user_id);
    $build['#statistics'] = $stats;

    return $build;
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
      $this->getLogger('job_application_automation')->error('Error fetching user statistics: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $stats;
  }

}
