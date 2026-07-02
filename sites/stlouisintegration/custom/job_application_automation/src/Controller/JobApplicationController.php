<?php

namespace Drupal\job_application_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides route responses for the Job Application Automation module.
 */
class JobApplicationController extends ControllerBase {

  /**
   *    // Phase 4: Application Manage    // Phase 4: Application Management
    $build['phase4'] = [
      '#type' => 'container',    $build['phase6'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-6']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 6: Analytics & Optimization',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $success_rate . '%</div>
                       <div class="stat-label">Success Rate</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">📊 View Analytics</a>
                       <a href="#" class="phase-button">🎯 Get Recommendations</a>
                       <a href="#" class="phase-button">⚙️ Optimize Process</a>',
        ],
      ],
    ];' => ['class' => ['phase-section', 'phase-4']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 4: Application Management',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $active_applications . '</div>
                       <div class="stat-label">Active Applications</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">Submit Applications</a>
                       <a href="#" class="phase-button">📄 Customize Resume</a>
                       <a href="#" class="phase-button">📊 Track Status</a>',
        ],
      ],
    ];se4'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-4']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 4: Application Management',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $active_applications . '</div>
                       <div class="stat-label">Active Applications</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">Submit Applications</a>
                       <a href="#" class="phase-button">📄 Customize Resume</a>
                       <a href="#" class="phase-button">📊 Track Status</a>',
        ],
      ],
    ]; for authenticated users.
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
    
    // Build dashboard content
    $content = [];
    $content['#attached']['library'][] = 'job_application_automation/home-page';
    
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
    
    // Wrap with navigation layout
    return [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
      '#attached' => [
        'library' => [
          'job_application_automation/job-hunter-home',
        ],
      ],
    ];
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
    return [
      '#markup' => '<h2>Job Application View</h2><p>Details for job application ID: ' . $job_application . '</p>',
    ];
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
    $matched_jobs = $this->getMatchedJobsCount($current_user);
    $active_applications = $this->getActiveApplicationsCount($current_user);
    
    // Welcome message
    $build['welcome'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['user-welcome']],
      '#value' => '<div class="user-welcome">Welcome back, ' . $user_name . '! Let\'s continue your job search journey.</div>',
    ];
    
    // Phase 1: Profile Setup
    $user_edit_url = Url::fromRoute('entity.user.edit_form', ['user' => $current_user->id()]);
    $job_discovery_url = Url::fromRoute('job_application_automation.start_job_discovery', ['user' => $current_user->id()]);
    $build['phase1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-1']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 1: Profile Setup',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $profile_completion . '%</div>
                       <div class="stat-label">Profile Complete</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="' . $user_edit_url->toString() . '" class="phase-button">Edit Profile</a>',
        ],
      ],
    ];
    
    // Phase 2: Target Companies
    $build['phase2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-2']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 2: Target Companies',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $target_companies . '</div>
                       <div class="stat-label">Target Companies</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="/job-applications/companies-overview" class="phase-button">📊 View All</a>
                       <a href="/job-applications/bulk-import-companies" class="phase-button">📥 Bulk Import</a>
                       <a href="/node/add/company" class="phase-button">+ Add One</a>',
        ],
      ],
    ];
    
    // Phase 3: Job Discovery
    $build['phase3'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-3']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 3: AI Job Discovery',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $matched_jobs . '</div>
                       <div class="stat-label">Jobs Discovered</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="' . $job_discovery_url->toString() . '" class="phase-button">Start Discovery</a>
                       <a href="#" class="phase-button">⚙️ Configure Matching</a>
                       <a href="#" class="phase-button">📋 View Matches</a>',
        ],
      ],
    ];
    
    // Phase 4: Application Management
    $build['phase4'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['phase-section', 'phase-4']],
      '#value' => '<div class="phase-header">
                     <h3>Phase 4: Application Management</h3>
                   </div>
                   <div class="phase-content">
                     <div class="phase-stat">
                       <div class="stat-number">' . $active_applications . '</div>
                       <div class="stat-label">Active Applications</div>
                     </div>
                     <div class="phase-actions">
                       <a href="/user/1/job-discovery/start" class="phase-button">Submit Applications</a>
                       <a href="#" class="phase-button">� Customize Resume</a>
                       <a href="#" class="phase-button">📊 Track Status</a>
                     </div>
                   </div>',
    ];
    
    // Phase 5: Interview & Follow-up
    $interviews_scheduled = 0; // Placeholder
    $build['phase5'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['phase-section', 'phase-5']],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => 'Phase 5: Interview & Follow-up',
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['phase-content']],
        'stat' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-stat']],
          '#value' => '<div class="stat-number">' . $interviews_scheduled . '</div>
                       <div class="stat-label">Interviews Scheduled</div>',
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['phase-actions']],
          '#value' => '<a href="#" class="phase-button">📅 Schedule Interviews</a>
                       <a href="#" class="phase-button">💌 Send Follow-ups</a>
                       <a href="#" class="phase-button">📈 Track Progress</a>',
        ],
      ],
    ];
    
    // Phase 6: Analytics & Optimization
    $success_rate = 0; // Placeholder
    $build['phase6'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['phase-section', 'phase-6']],
      '#value' => '<div class="phase-header">
                     <h3>Phase 6: Analytics & Optimization</h3>
                   </div>
                   <div class="phase-content">
                     <div class="phase-stat">
                       <div class="stat-number">' . $success_rate . '%</div>
                       <div class="stat-label">Success Rate</div>
                     </div>
                     <div class="phase-actions">
                       <a href="#" class="phase-button">� View Analytics</a>
                       <a href="#" class="phase-button">🎯 Get Recommendations</a>
                       <a href="#" class="phase-button">⚙️ Optimize Process</a>
                     </div>
                   </div>',
    ];
    
    // Add CSS styles
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .job-dashboard { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
          .user-welcome { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center; font-size: 1.2em; }
          .phase-section { background: white; border: 2px solid #e2e8f0; border-radius: 12px; margin: 20px 0; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
          .phase-section:hover { transform: translateY(-2px); }
          .phase-1 { border-left: 5px solid #48bb78; }
          .phase-2 { border-left: 5px solid #4299e1; }
          .phase-3 { border-left: 5px solid #ed8936; }
          .phase-4 { border-left: 5px solid #9f7aea; }
          .phase-5 { border-left: 5px solid #f56565; }
          .phase-6 { border-left: 5px solid #38b2ac; }
          .phase-header h3 { color: #2d3748; margin: 0 0 20px 0; font-size: 1.3em; font-weight: 600; }
          .phase-content { display: flex; justify-content: space-between; align-items: center; }
          .phase-stat { text-align: center; }
          .stat-number { font-size: 2.2em; font-weight: bold; color: #1a365d; margin-bottom: 5px; }
          .stat-label { color: #4a5568; font-size: 1em; }
          .phase-actions { display: flex; gap: 12px; flex-wrap: wrap; }
          .phase-button { background: #4299e1; color: white; padding: 10px 16px; text-decoration: none; border-radius: 6px; font-size: 0.9em; transition: background 0.2s; }
          .phase-button:hover { background: #3182ce; }
          .phase-1 .phase-button { background: #48bb78; }
          .phase-1 .phase-button:hover { background: #38a169; }
          .phase-2 .phase-button { background: #4299e1; }
          .phase-2 .phase-button:hover { background: #3182ce; }
          .phase-3 .phase-button { background: #ed8936; }
          .phase-3 .phase-button:hover { background: #dd6b20; }
          .phase-4 .phase-button { background: #9f7aea; }
          .phase-4 .phase-button:hover { background: #805ad5; }
          .phase-5 .phase-button { background: #f56565; }
          .phase-5 .phase-button:hover { background: #e53e3e; }
          .phase-6 .phase-button { background: #38b2ac; }
          .phase-6 .phase-button:hover { background: #319795; }
          @media (max-width: 768px) {
            .phase-content { flex-direction: column; gap: 20px; }
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
    // Simplified calculation
    return 75; // Placeholder
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
   * Manage target companies.
   */
  public function manageTargetCompanies() {
    $build = [];
    $build['content'] = [
      '#markup' => '<h2>Manage Target Companies</h2><p>Company management interface.</p>',
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
              <div class="progress-fill" style="width: ' . $completion_percentage . '%"></div>
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
      
      // Add CSS styles for the table
      $build['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'style',
          '#value' => '
            .companies-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .companies-table th { background: #f7fafc; padding: 12px; text-align: left; font-weight: 600; color: #2d3748; border-bottom: 2px solid #e2e8f0; }
            .companies-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .companies-table tr:hover { background: #f7fafc; }
            .progress-bar { position: relative; width: 100px; height: 20px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
            .progress-fill { height: 100%; background: linear-gradient(90deg, #48bb78 0%, #38a169 100%); border-radius: 10px; transition: width 0.3s ease; }
            .progress-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 12px; font-weight: bold; color: #2d3748; }
            .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
            .status-active { background: #c6f6d5; color: #22543d; }
            .status-incomplete { background: #fed7d7; color: #742a2a; }
            .btn { display: inline-block; padding: 6px 12px; margin-right: 4px; text-decoration: none; border-radius: 4px; font-size: 12px; background: #4299e1; color: white; }
            .btn:hover { background: #3182ce; }
            .btn-sm { padding: 4px 8px; font-size: 11px; }
          ',
        ],
        'companies-table-styles'
      ];
    } else {
      $build['no_companies'] = [
        '#markup' => '<div class="no-companies">
          <p>No companies found. <a href="/job-applications/bulk-import-companies">Add companies via bulk import</a> or <a href="/node/add/company">add a single company</a>.</p>
        </div>',
      ];
    }

    return $build;
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

}