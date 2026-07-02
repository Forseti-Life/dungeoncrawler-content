<?php

namespace Drupal\resume_tailoring\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Controller for Resume Tailoring dashboard and pages.
 */
class ResumeTailoringController extends ControllerBase {

  /**
   * Dashboard page for resume tailoring.
   */
  public function dashboard() {
    $current_user = \Drupal::currentUser();
    
    // User is authenticated (enforced by routing), now check permissions
    if (!$current_user->hasPermission('access resume tailoring')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You do not have permission to access resume tailoring.');
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['resume-tailoring-dashboard']],
      '#attached' => [
        'library' => ['resume_tailoring/dashboard'],
      ],
    ];

    // Dashboard header.
    $build['header'] = [
      '#markup' => '<h1>Resume Tailoring Dashboard</h1><p class="lead">Follow the 5-step process to create tailored resumes for your job applications.</p>',
    ];

    // Process flow steps.
    $build['process_steps'] = $this->getProcessSteps();
    
    // Current status overview.
    $build['status_overview'] = $this->getStatusOverview();
    
    // Quick actions.
    $build['quick_actions'] = $this->getQuickActions();

    // Recent activity.
    $build['recent_activity'] = $this->getRecentActivity();

    return $build;
  }

  /**
   * Get process overview for non-authenticated users.
   */
  private function getProcessOverview() {
    return '
      <div class="process-overview">
        <h2>Resume Tailoring Process</h2>
        <div class="process-steps">
          <div class="step">
            <h3>Step 1: Create Profile</h3>
            <p>Create an account and complete your user profile.</p>
          </div>
          <div class="step">
            <h3>Step 2: Create Master Resume</h3>
            <p>Create a comprehensive resume with all your experience, skills, and achievements.</p>
          </div>
          <div class="step">
            <h3>Step 3: Add Job Postings</h3>
            <p>Create job posting entries for positions you want to apply for.</p>
          </div>
          <div class="step">
            <h3>Step 4: Generate Tailored Resume</h3>
            <p>Select a job posting and click "Generate" to create a tailored resume.</p>
          </div>
          <div class="step">
            <h3>Step 5: Download & Apply</h3>
            <p>Access your tailored resume and use it for your job application.</p>
          </div>
        </div>
      </div>';
  }

  /**
   * Get process steps with completion status.
   */
  private function getProcessSteps() {
    $current_user_id = \Drupal::currentUser()->id();
    
    // Check completion status for each step.
    $steps = [
      1 => [
        'title' => 'Create Profile',
        'description' => 'Complete your user profile',
        'completed' => $this->isProfileComplete(),
        'action_url' => '/user/' . $current_user_id . '/edit',
        'action_text' => 'Edit Profile',
      ],
      2 => [
        'title' => 'Create Master Resume',
        'description' => 'Create your comprehensive resume',
        'completed' => $this->hasResume(),
        'action_url' => '/node/add/resume',
        'action_text' => 'Create Resume',
      ],
      3 => [
        'title' => 'Add Job Postings',
        'description' => 'Add job postings you want to apply for',
        'completed' => $this->hasJobPostings(),
        'action_url' => '/node/add/job_posting',
        'action_text' => 'Add Job Posting',
      ],
      4 => [
        'title' => 'Generate Tailored Resume',
        'description' => 'Create tailored resumes for specific jobs',
        'completed' => $this->hasTailoredResumes(),
        'action_url' => '#job-postings-table',
        'action_text' => 'View Job Postings',
      ],
      5 => [
        'title' => 'Download & Apply',
        'description' => 'Access and use your tailored resumes',
        'completed' => $this->hasTailoredResumes(),
        'action_url' => '#tailored-resumes',
        'action_text' => 'View Tailored Resumes',
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['process-steps']],
      'title' => [
        '#markup' => '<h2>Process Flow</h2>',
      ],
    ];

    foreach ($steps as $step_num => $step) {
      $status_class = $step['completed'] ? 'completed' : 'pending';
      $status_icon = $step['completed'] ? '✅' : '⏳';
      
      $build['step_' . $step_num] = [
        '#markup' => sprintf(
          '<div class="step step-%d %s">
            <div class="step-number">%s %d</div>
            <div class="step-content">
              <h3>%s</h3>
              <p>%s</p>
              <a href="%s" class="btn btn-primary">%s</a>
            </div>
          </div>',
          $step_num,
          $status_class,
          $status_icon,
          $step_num,
          $step['title'],
          $step['description'],
          $step['action_url'],
          $step['action_text']
        ),
      ];
    }

    return $build;
  }

  /**
   * Get status overview.
   */
  private function getStatusOverview() {
    $resume_count = $this->getResumeCount();
    $job_posting_count = $this->getJobPostingCount();
    $tailored_resume_count = $this->getTailoredResumeCount();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['status-overview']],
      'title' => [
        '#markup' => '<h2>Your Progress</h2>',
      ],
      'stats' => [
        '#markup' => sprintf(
          '<div class="stats-grid">
            <div class="stat-card">
              <h3>%d</h3>
              <p>Master Resumes</p>
            </div>
            <div class="stat-card">
              <h3>%d</h3>
              <p>Job Postings</p>
            </div>
            <div class="stat-card">
              <h3>%d</h3>
              <p>Tailored Resumes</p>
            </div>
          </div>',
          $resume_count,
          $job_posting_count,
          $tailored_resume_count
        ),
      ],
    ];
  }

  /**
   * Get quick actions.
   */
  private function getQuickActions() {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['quick-actions']],
      'title' => [
        '#markup' => '<h2>Quick Actions</h2>',
      ],
      'actions' => [
        '#markup' => '
          <div class="action-buttons">
            <a href="/node/add/resume" class="btn btn-primary">Create New Resume</a>
            <a href="/node/add/job_posting" class="btn btn-secondary">Add Job Posting</a>
            <a href="#job-postings-table" class="btn btn-success">Generate Tailored Resume</a>
          </div>',
      ],
    ];
  }

  /**
   * Get recent activity including job postings with generation options.
   */
  private function getRecentActivity() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['recent-activity']],
    ];

    // Job postings table.
    $build['job_postings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'job-postings-table', 'class' => ['job-postings-section']],
      'title' => [
        '#markup' => '<h2>Job Postings</h2>',
      ],
      'table' => $this->getJobPostingsTable(),
    ];

    // Tailored resumes section.
    $build['tailored_resumes'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'tailored-resumes', 'class' => ['tailored-resumes-section']],
      'title' => [
        '#markup' => '<h2>Tailored Resumes</h2>',
      ],
      'table' => $this->getTailoredResumesTable(),
    ];

    return $build;
  }

  /**
   * Get job postings table with generate buttons.
   */
  private function getJobPostingsTable() {
    $current_user_id = \Drupal::currentUser()->id();
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'job_posting')
      ->condition('uid', $current_user_id)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE)
      ->range(0, 10);
    
    $nids = $query->execute();
    
    if (empty($nids)) {
      return [
        '#markup' => '<p>No job postings found. <a href="/node/add/job_posting">Create your first job posting</a>.</p>',
      ];
    }

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    
    $rows = [];
    foreach ($nodes as $node) {
      $tailored_count = $this->getTailoredResumeCountForJob($node->id());
      $rows[] = [
        'title' => $node->getTitle(),
        'company' => $node->hasField('field_company_ref') && !$node->get('field_company_ref')->isEmpty() ? 
          $node->get('field_company_ref')->entity->getTitle() : 'N/A',
        'created' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
        'tailored_count' => $tailored_count,
        'actions' => sprintf(
          '<a href="/resume-tailoring/generate/%d" class="btn btn-sm btn-success">Generate Tailored Resume</a> 
           <a href="/node/%d" class="btn btn-sm btn-secondary">View</a>',
          $node->id(),
          $node->id()
        ),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => ['Job Title', 'Company', 'Created', 'Tailored Resumes', 'Actions'],
      '#rows' => $rows,
      '#empty' => 'No job postings found.',
    ];
  }

  /**
   * Get tailored resumes table.
   */
  private function getTailoredResumesTable() {
    $current_user_id = \Drupal::currentUser()->id();
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'tailored_resume')
      ->condition('uid', $current_user_id)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE)
      ->range(0, 10);
    
    $nids = $query->execute();
    
    if (empty($nids)) {
      return [
        '#markup' => '<p>No tailored resumes found. Generate your first tailored resume from a job posting above.</p>',
      ];
    }

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    
    $rows = [];
    foreach ($nodes as $node) {
      $job_posting = $node->hasField('field_job_posting_ref') && !$node->get('field_job_posting_ref')->isEmpty() ? 
        $node->get('field_job_posting_ref')->entity : NULL;
      $status = $node->hasField('field_tailoring_status') ? $node->get('field_tailoring_status')->value : 'unknown';
      
      $rows[] = [
        'title' => $node->getTitle(),
        'job_posting' => $job_posting ? $job_posting->getTitle() : 'N/A',
        'status' => $this->formatStatus($status),
        'created' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short'),
        'actions' => sprintf(
          '<a href="/node/%d" class="btn btn-sm btn-primary">View</a> 
           <a href="/node/%d/edit" class="btn btn-sm btn-secondary">Edit</a>',
          $node->id(),
          $node->id()
        ),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => ['Resume Title', 'Job Posting', 'Status', 'Created', 'Actions'],
      '#rows' => $rows,
      '#empty' => 'No tailored resumes found.',
    ];
  }

  /**
   * Format tailoring status.
   */
  private function formatStatus($status) {
    $statuses = [
      'pending' => '<span class="badge badge-warning">Pending</span>',
      'generating' => '<span class="badge badge-info">Generating</span>',
      'completed' => '<span class="badge badge-success">Completed</span>',
      'error' => '<span class="badge badge-danger">Error</span>',
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : '<span class="badge badge-secondary">Unknown</span>';
  }

  /**
   * Check if user profile is complete.
   */
  private function isProfileComplete() {
    $current_user = \Drupal::currentUser();
    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());
    
    // Basic check for email and username.
    return !empty($user_entity->getEmail()) && !empty($user_entity->getDisplayName());
  }

  /**
   * Check if user has any resumes.
   */
  private function hasResume() {
    return $this->getResumeCount() > 0;
  }

  /**
   * Check if user has any job postings.
   */
  private function hasJobPostings() {
    return $this->getJobPostingCount() > 0;
  }

  /**
   * Check if user has any tailored resumes.
   */
  private function hasTailoredResumes() {
    return $this->getTailoredResumeCount() > 0;
  }

  /**
   * Get count of user's resumes.
   */
  private function getResumeCount() {
    $current_user_id = \Drupal::currentUser()->id();
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'resume')
      ->condition('uid', $current_user_id)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->count();
    
    return $query->execute();
  }

  /**
   * Get count of user's job postings.
   */
  private function getJobPostingCount() {
    $current_user_id = \Drupal::currentUser()->id();
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'job_posting')
      ->condition('uid', $current_user_id)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->count();
    
    return $query->execute();
  }

  /**
   * Get count of user's tailored resumes.
   */
  private function getTailoredResumeCount() {
    $current_user_id = \Drupal::currentUser()->id();
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'tailored_resume')
      ->condition('uid', $current_user_id)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->count();
    
    return $query->execute();
  }

  /**
   * Get count of tailored resumes for a specific job posting.
   */
  private function getTailoredResumeCountForJob($job_posting_id) {
    $current_user_id = \Drupal::currentUser()->id();
    
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'tailored_resume')
      ->condition('uid', $current_user_id)
      ->condition('field_job_posting_ref', $job_posting_id)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->count();
    
    return $query->execute();
  }

  /**
   * Generate tailored resume page.
   */
  public function generateTailoredResume($job_posting_id) {
    $current_user = \Drupal::currentUser();
    
    // Check permissions
    if (!$current_user->hasPermission('create tailored resumes')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You do not have permission to create tailored resumes.');
    }

    // Validate job posting exists and user has access to it
    $job_posting = \Drupal::entityTypeManager()->getStorage('node')->load($job_posting_id);
    if (!$job_posting || $job_posting->bundle() !== 'job_posting') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Job posting not found.');
    }

    // Check if user owns this job posting or has admin permissions
    if ($job_posting->getOwnerId() !== $current_user->id() && 
        !$current_user->hasPermission('view all tailored resumes')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You do not have access to this job posting.');
    }
    
    // TODO: Implement tailored resume generation.
    $build = [
      '#markup' => sprintf('<h1>Generate Tailored Resume</h1><p>Generating tailored resume for job posting: %s (ID: %d)</p>', $job_posting->getTitle(), $job_posting_id),
    ];
    
    return $build;
  }
}