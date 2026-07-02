<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides route responses for the Job Application Automation module.
 */
class JobApplicationController extends ControllerBase {

  /**
   * Returns a simple homepage for authenticated users.
   *
   * @return array
   *   A simple renderable array with Hello World message.
   */
  public function home() {
    return [
      '#markup' => '<h1>Hello World!</h1><p>Job Application Automation Module is working for regular users!</p><p>Welcome, you are logged in successfully!</p>',
    ];
  }

  /**
   * Returns an administrative dashboard for job applications.
   *
   * @return array
   *   A comprehensive renderable array for the administrative dashboard.
   */
  public function dashboard() {
    $current_user = \Drupal::currentUser();
    
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Build dashboard content directly
    $content = [];
    $content['#attached']['library'][] = 'job_hunter/job-hunter-home';
    
    // Dashboard Header
    $content['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['job-application-hero']],
      '#value' => '<h1>Job Application Dashboard</h1>
                   <div class="subtitle">Your Complete Job Search Management System</div>',
    ];
    
    // Check if user is authenticated
    if ($current_user->isAuthenticated() && $current_user->id() > 0) {
      $content = $this->buildAuthenticatedView($content, $current_user);
    } else {
      $content = $this->buildUnauthenticatedView($content);
    }
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * View a specific job application.
   *
   * @param mixed $job_application
   *   The job application entity.
   *
   * @return array
   *   A renderable array for the job application view.
   */
  public function view($job_application) {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#markup' => '<h2>Job Application View</h2><p>Details for job application ID: ' . $job_application . '</p>',
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * Build the view for unauthenticated users.
   */
  private function buildUnauthenticatedView($build) {
    $build['auth_required'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['process-phase', 'phase-1', 'authentication-required']],
      '#value' => '<h3>Please Sign In</h3>
                   <p>Sign in or create an account to access your job application dashboard.</p>
                   <a href="/user/login" class="auth-button primary">Sign In</a>
                   <a href="/user/register" class="auth-button secondary">Create Account</a>',
    ];
    
    $build['#prefix'] = '<div class="job-application-home unauthenticated">';
    $build['#suffix'] = '</div>';
    
    return $build;
  }

  /**
   * Build the view for authenticated users.
   */
  private function buildAuthenticatedView($build, $current_user) {
    $user_name = $current_user->getDisplayName();
    
    // Calculate stats
    $profile_completion = $this->calculateProfileCompletion($current_user);
    $target_companies = $this->getTargetCompaniesCount($current_user);
    $saved_jobs = $this->getSavedJobsCount($current_user);
    
    // URLs
    $user_edit_url = Url::fromRoute('job_hunter.user_profile_edit');
    $job_paste_url = Url::fromRoute('job_hunter.job_paste');
    $jobs_list_url = Url::fromRoute('job_hunter.jobs_list');
    
    // Welcome message
    $build['welcome'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['user-welcome']],
      '#value' => '<div class="user-welcome">Welcome back, ' . $user_name . '!</div>',
    ];
    
    // Queue Controls Section (Admin only)
    if ($current_user->hasPermission('administer job application automation')) {
      $build['queue_controls'] = $this->buildQueueControlsSection();
    }
    
    // ========================================
    // PROCESS FLOW 1: Tailored Resume
    // ========================================
    $build['flow1_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['flow-header', 'flow-tailored']],
      '#value' => '<h2>📄 Tailored Resume Process</h2>
                   <p class="flow-description">Create job-specific resumes by uploading your profile and matching to job descriptions.</p>',
    ];
    
    // Step 1: Profile Setup
    $build['tailored_step1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-profile']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 1</div>
                     <div class="phase-info">
                       <h3>Upload Resume & Clean Up Profile</h3>
                       <p>Import your resume, parse it with AI, and refine your consolidated profile.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $profile_completion . '%</div>
                       <div class="stat-label">Profile Complete</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $user_edit_url->toString() . '" class="phase-button primary">Edit Profile</a>
                     </div>',
      ],
    ];
    
    // Step 2: Job Description & Tailored Resume
    $build['tailored_step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-tailoring']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 2</div>
                     <div class="phase-info">
                       <h3>Submit Job Description → Get Tailored Resume</h3>
                       <p>Paste a job posting to generate an AI-tailored resume and downloadable PDF.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $saved_jobs . '</div>
                       <div class="stat-label">Saved Jobs</div>
                     </div>
                     <div class="phase-actions">
                       <a href="' . $job_paste_url->toString() . '" class="phase-button primary">+ Add Job Posting</a>
                       <a href="' . $jobs_list_url->toString() . '" class="phase-button">View All Jobs</a>
                     </div>',
      ],
    ];
    
    // ========================================
    // PROCESS FLOW 2: Automated Job Applications (Future)
    // ========================================
    $build['flow2_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['flow-header', 'flow-automated']],
      '#value' => '<h2>🤖 Automated Job Applications</h2>
                   <p class="flow-description">Coming soon: Automated job discovery and application submission.</p>
                   <span class="status-badge not-implemented">Not Fully Implemented</span>',
    ];
    
    // Step 1: Profile (shared)
    $build['automated_step1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-profile', 'disabled']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 1</div>
                     <div class="phase-info">
                       <h3>Upload Resume & Clean Up Profile</h3>
                       <p>Same as above - your profile is shared across both workflows.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $profile_completion . '%</div>
                       <div class="stat-label">Profile Complete</div>
                     </div>',
      ],
    ];
    
    // Step 2: Target Companies
    $build['automated_step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-companies', 'disabled']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        '#value' => '<div class="step-indicator">Step 2</div>
                     <div class="phase-info">
                       <h3>Target Companies</h3>
                       <p>Build a list of companies you want to work for.</p>
                     </div>
                     <div class="phase-stat">
                       <div class="stat-number">' . $target_companies . '</div>
                       <div class="stat-label">Target Companies</div>
                     </div>
                     <div class="phase-actions">
                       <a href="/jobhunter/target-companies" class="phase-button">Manage Companies</a>
                     </div>',
      ],
    ];
    
    // Future steps placeholder
    $build['automated_future'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['future-steps']],
      '#value' => '<div class="future-placeholder">
                     <p><strong>Coming Soon:</strong></p>
                     <ul>
                       <li>Step 3: AI Job Discovery - Find matching jobs at target companies</li>
                       <li>Step 4: Application Submission - Auto-apply with tailored resumes</li>
                       <li>Step 5: Interview & Follow-up - Track application status</li>
                       <li>Step 6: Analytics - Measure success rates and optimize</li>
                     </ul>
                   </div>',
    ];
    
    // Add CSS styles
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-dashboard { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
          .user-welcome { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center; font-size: 1.2em; }
          
          /* Flow Headers */
          .flow-header { margin: 40px 0 20px 0; padding: 20px; border-radius: 10px; }
          .flow-header h2 { margin: 0 0 10px 0; font-size: 1.5em; }
          .flow-header .flow-description { margin: 0; color: #4a5568; }
          .flow-tailored { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; }
          .flow-tailored .flow-description { color: rgba(255,255,255,0.9); }
          .flow-automated { background: #e2e8f0; color: #2d3748; border: 2px dashed #a0aec0; }
          .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; margin-top: 10px; }
          .status-badge.not-implemented { background: #fbd38d; color: #744210; }
          
          /* Phase Sections */
          .phase-section { background: white; border: 2px solid #e2e8f0; border-radius: 12px; margin: 15px 0; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
          .phase-section.disabled { opacity: 0.7; }
          .phase-profile { border-left: 5px solid #48bb78; }
          .phase-tailoring { border-left: 5px solid #d69e2e; }
          .phase-companies { border-left: 5px solid #4299e1; }
          
          /* Phase Content */
          .phase-content { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
          .step-indicator { background: #1a365d; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 0.9em; white-space: nowrap; }
          .phase-info { flex: 1; min-width: 200px; }
          .phase-info h3 { margin: 0 0 5px 0; color: #2d3748; font-size: 1.1em; }
          .phase-info p { margin: 0; color: #718096; font-size: 0.9em; }
          .phase-stat { text-align: center; min-width: 100px; }
          .stat-number { font-size: 2em; font-weight: bold; color: #1a365d; }
          .stat-label { color: #4a5568; font-size: 0.85em; }
          .phase-actions { display: flex; gap: 10px; flex-wrap: wrap; }
          .phase-button { padding: 10px 18px; text-decoration: none; border-radius: 6px; font-size: 0.9em; transition: all 0.2s; }
          .phase-button.primary { background: #4299e1; color: white; }
          .phase-button.primary:hover { background: #3182ce; }
          .phase-button:not(.primary) { background: #e2e8f0; color: #2d3748; }
          .phase-button:not(.primary):hover { background: #cbd5e0; }
          
          /* Future Steps */
          .future-steps { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 15px 0; }
          .future-placeholder ul { margin: 10px 0 0 0; padding-left: 20px; color: #718096; }
          .future-placeholder li { margin: 5px 0; }
          
          @media (max-width: 768px) {
            .phase-content { flex-direction: column; text-align: center; }
            .phase-actions { justify-content: center; }
          }
        ',
      ],
      'job-dashboard-styles'
    ];
    
    $build['#prefix'] = '<div class="job-dashboard">';
    $build['#suffix'] = '</div>';
    
    return $build;
  }

  /**
   * Calculate user profile completion percentage.
   */
  private function calculateProfileCompletion($user) {
    // Use the UserProfileService for real calculation
    $userProfileService = \Drupal::service('job_hunter.user_profile_service');
    $user_entity = User::load($user->id());
    if ($user_entity) {
      return $userProfileService->calculateProfileCompleteness($user_entity);
    }
    return 0;
  }

  /**
   * Get count of target companies.
   */
  private function getTargetCompaniesCount($user) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'company')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    return count($query->execute());
  }

  /**
   * Get count of matched jobs.
   */
  private function getMatchedJobsCount($user) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'job_posting')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    return count($query->execute());
  }

  /**
   * Get count of active applications.
   */
  private function getActiveApplicationsCount($user) {
    return 0; // Placeholder
  }

  /**
   * Get count of saved job postings.
   */
  private function getSavedJobsCount($user) {
    try {
      $count = \Drupal::database()->select('job_hunter_job_requirements', 'j')
        ->countQuery()
        ->execute()
        ->fetchField();
      return (int) $count;
    }
    catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * Manage target companies.
   */
  public function manageTargetCompanies() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [];
    $content['content'] = [
      '#markup' => '<h2>Manage Target Companies</h2><p>Company management interface.</p>',
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * Save target companies.
   */
  public function saveTargetCompanies() {
    return new \Symfony\Component\HttpFoundation\RedirectResponse('/job-applications');
  }

  /**
   * Companies overview.
   */
  public function companiesOverview() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'company')
      ->condition('status', 1)
      ->accessCheck(TRUE);
    $company_ids = $query->execute();
    $company_count = count($company_ids);

    $build = [];
    $build['header'] = [
      '#markup' => '<h2>Companies Overview</h2><p>Total companies: ' . $company_count . '</p>',
    ];

    if ($company_count > 0) {
      // Load companies and build table
      $companies = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($company_ids);
      
      // Table header
      $table_header = '<table class="companies-table">
        <thead>
          <tr>
            <th>Company</th>
            <th>Industry</th>
            <th>Size</th>
            <th>Profile Complete</th>
            <th>Jobs Found</th>
            <th>Applications</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>';
      
      $table_rows = '';
      foreach ($companies as $company) {
        $company_name = $company->getTitle();
        $company_url = $company->hasField('field_company_website') && !$company->get('field_company_website')->isEmpty() 
          ? $company->get('field_company_website')->value : '#';
        $company_industry = $company->hasField('field_company_industry') && !$company->get('field_company_industry')->isEmpty()
          ? $company->get('field_company_industry')->value : 'Not specified';
        $company_size = $company->hasField('field_company_size') && !$company->get('field_company_size')->isEmpty()
          ? $company->get('field_company_size')->value : 'Not specified';
        
        // Calculate completion percentage (mock data for now)
        $completion_fields = 0;
        $total_fields = 5; // Name, industry, size, website, description
        
        if (!empty($company_name)) $completion_fields++;
        if ($company->hasField('field_company_industry') && !$company->get('field_company_industry')->isEmpty()) $completion_fields++;
        if ($company->hasField('field_company_size') && !$company->get('field_company_size')->isEmpty()) $completion_fields++;
        if ($company->hasField('field_company_website') && !$company->get('field_company_website')->isEmpty()) $completion_fields++;
        if ($company->hasField('field_company_description') && !$company->get('field_company_description')->isEmpty()) $completion_fields++;
        
        $completion_percentage = round(($completion_fields / $total_fields) * 100);
        
        // Mock job and application counts (replace with real queries later)
        $jobs_found = rand(0, 15);
        $applications_count = rand(0, 5);
        $status = $completion_percentage >= 80 ? 'Active' : 'Incomplete';
        
        $table_rows .= '<tr>
          <td><a href="/node/' . $company->id() . '">' . $company_name . '</a></td>
          <td>' . $company_industry . '</td>
          <td>' . $company_size . '</td>
          <td>
            <div class="progress-bar">
              <div class="progress-fill" data-width="' . $completion_percentage . '%"></div>
              <span class="progress-text">' . $completion_percentage . '%</span>
            </div>
          </td>
          <td>' . $jobs_found . '</td>
          <td>' . $applications_count . '</td>
          <td><span class="status-badge status-' . strtolower($status) . '">' . $status . '</span></td>
          <td>
            <a href="/node/' . $company->id() . '/edit" class="btn btn-sm">Edit</a>
            <a href="/node/' . $company->id() . '" class="btn btn-sm">View</a>
          </td>
        </tr>';
      }
      
      $table_footer = '</tbody></table>';
      
      $build['companies'] = [
        '#markup' => $table_header . $table_rows . $table_footer,
      ];
      
      // Attach companies table CSS library
      $build['#attached']['library'][] = 'job_hunter/companies_table';
    } else {
      $build['no_companies'] = [
        '#markup' => '<div class="no-companies">
          <p>No companies found. <a href="/job-applications/bulk-import-companies">Add companies via bulk import</a> or <a href="/node/add/company">add a single company</a>.</p>
        </div>',
      ];
    }

    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Wrap with navigation
    $wrapper = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $build,
    ];

    return $wrapper;
  }

  /**
   * Get the title for a job application.
   *
   * @param mixed $job_application
   *   The job application entity.
   *
   * @return string
   *   The title for the job application.
   */
  public function getTitle($job_application) {
    return 'Job Application #' . $job_application;
  }

  /**
   * Build the queue controls section for admins.
   *
   * @return array
   *   Render array for queue controls.
   */
  private function buildQueueControlsSection() {
    $queue_factory = \Drupal::service('queue');
    
    // Queue definitions
    $queues = [
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
    ];
    
    // Build queue rows with enhanced status columns
    $queue_rows = '';
    $total_items = 0;
    
    foreach ($queues as $queue_id => $info) {
      $queue = $queue_factory->get($queue_id);
      $items = $queue->numberOfItems();
      $total_items += $items;
      
      $badge_class = $items > 0 ? 'queue-badge-pending' : 'queue-badge-empty';
      $disabled_attr = $items == 0 ? ' disabled="disabled"' : '';
      
      $queue_rows .= '
        <tr class="queue-row" data-queue-id="' . $queue_id . '">
          <td class="queue-icon">' . $info['icon'] . '</td>
          <td class="queue-name">
            <strong>' . $info['name'] . '</strong>
            <div class="queue-description">' . $info['description'] . '</div>
          </td>
          <td class="queue-count">
            <span class="queue-badge ' . $badge_class . '" data-count>' . $items . '</span>
          </td>
          <td class="queue-status">
            <span class="status-indicator status-idle" data-status>
              <span class="status-dot"></span>
              <span class="status-text">Idle</span>
            </span>
          </td>
          <td class="queue-last-activity">
            <span class="last-activity-text" data-last-activity>-</span>
          </td>
          <td class="queue-actions">
            <button type="button" class="btn-run-queue" data-queue="' . $queue_id . '"' . $disabled_attr . '>▶️ Run</button>
          </td>
        </tr>';
    }
    
    $run_all_disabled = $total_items == 0 ? ' disabled="disabled"' : '';
    
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['queue-controls-section'], 'id' => 'queue-controls-panel'],
      'content' => [
        '#type' => 'inline_template',
        '#template' => '
          <div class="queue-controls-wrapper">
            <div class="queue-controls-header">
              <h3>🎛️ Queue Processing Dashboard</h3>
              <p class="queue-controls-subtitle">Monitor and manage background processing queues</p>
              <div class="queue-global-actions">
                <button type="button" id="run-all-queues" class="btn-run-all"{{ run_all_disabled|raw }}>
                  Run All Queues (<span id="total-queue-items">{{ total_items }}</span> items)
                </button>
                <button type="button" id="refresh-queue-status" class="btn-refresh">🔄 Refresh Status</button>
                <label class="auto-refresh-toggle">
                  <input type="checkbox" id="auto-refresh-toggle" checked>
                  <span>Auto-refresh (<span id="auto-refresh-countdown">5</span>s)</span>
                </label>
              </div>
            </div>
            <div id="queue-status-message" class="queue-status-message" style="display:none;"></div>
            <table class="queue-controls-table">
              <thead>
                <tr>
                  <th></th>
                  <th>Queue</th>
                  <th>Pending</th>
                  <th>Status</th>
                  <th>Last Activity</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="queue-table-body">{{ queue_rows|raw }}</tbody>
            </table>
            <div class="queue-processing-log" id="processing-log">
              <h4>📋 Recent Activity</h4>
              <div class="log-entries" id="log-entries">
                <div class="log-entry log-info">Waiting for activity...</div>
              </div>
            </div>
          </div>
        ',
        '#context' => [
          'total_items' => $total_items,
          'run_all_disabled' => $run_all_disabled,
          'queue_rows' => $queue_rows,
        ],
      ],
      '#attached' => [
        'library' => ['job_hunter/queue-controls'],
      ],
    ];
    
    return $build;
  }

}