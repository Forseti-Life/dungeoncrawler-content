<?php

namespace Drupal\job_application_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\job_application_automation\Service\UserProfileService;
use Drupal\job_application_automation\Service\JobSeekerService;

/**
 * Controller for user profile management functionality.
 */
class UserProfileController extends ControllerBase {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user profile service.
   *
   * @var \Drupal\job_application_automation\Service\UserProfileService
   */
  protected $userProfileService;

  /**
   * The job seeker service.
   *
   * @var \Drupal\job_application_automation\Service\JobSeekerService
   */
  protected $jobSeekerService;

  /**
   * The AI API service.
   *
   * @var \Drupal\ai_conversation\Service\AIApiService
   */
  protected $aiApiService;

  /**
   * Constructs a new UserProfileController object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\job_application_automation\Service\UserProfileService $user_profile_service
   *   The user profile service.
   * @param \Drupal\job_application_automation\Service\JobSeekerService $job_seeker_service
   *   The job seeker service.
   * @param \Drupal\ai_conversation\Service\AIApiService $ai_api_service
   *   The AI API service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, UserProfileService $user_profile_service, JobSeekerService $job_seeker_service, $ai_api_service = NULL) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->userProfileService = $user_profile_service;
    $this->jobSeekerService = $job_seeker_service;
    $this->aiApiService = $ai_api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Check if ai_conversation service is available
    $ai_service = NULL;
    if ($container->has('ai_conversation.ai_api_service')) {
      $ai_service = $container->get('ai_conversation.ai_api_service');
    }
    
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('job_application_automation.user_profile_service'),
      $container->get('job_application_automation.job_seeker_service'),
      $ai_service
    );
  }

  /**
   * Displays the user profile dashboard.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   A render array for the profile dashboard.
   */
  public function dashboard($user = NULL) {
    // Load the user entity - either specified user or current user
    $uid = $user ? $user->id() : $this->currentUser->id();
    $user_entity = $user ?: User::load($uid);

    if (!$user_entity) {
      $this->messenger()->addError($this->t('User not found.'));
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Check access - users can only view their own profile unless admin
    if ($uid != $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $build = [];

    // Page header
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-dashboard-header']],
    ];

    $build['header']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('My Job Application Profile'),
      '#attributes' => ['class' => ['profile-title']],
    ];

    // Profile completeness widget
    $completeness = $this->userProfileService->calculateProfileCompleteness($user_entity);
    $build['completeness'] = $this->buildCompletenessWidget($user_entity, $completeness);

    // Quick stats
    $build['stats'] = $this->buildProfileStats($user_entity);

    // Profile sections summary
    $build['sections'] = $this->buildProfileSections($user_entity);

    // Actions
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-actions']],
    ];

    $build['actions']['edit_profile'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit Profile'),
      '#url' => Url::fromRoute('job_application_automation.user_profile_edit', ['user' => $uid]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['actions']['view_applications'] = [
      '#type' => 'link',
      '#title' => $this->t('View My Applications'),
      '#url' => Url::fromRoute('job_application_automation.home'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Add CSS and JS
    $build['#attached']['library'][] = 'job_application_automation/user_profile';

    // Use custom template for professional styling
    $build['#theme'] = 'user_profile_dashboard';
    $build['#user'] = $user_entity;

    return $build;
  }

  /**
   * Builds the profile completeness widget.
   *
   * @param \Drupal\user\Entity\User $user_entity
   *   The user entity.
   * @param int $completeness
   *   The completeness percentage.
   *
   * @return array
   *   Render array for the completeness widget.
   */
  protected function buildCompletenessWidget(User $user_entity, $completeness) {
    $status_class = 'low';
    $status_text = $this->t('Getting Started');
    
    if ($completeness >= 70) {
      $status_class = 'high';
      $status_text = $this->t('Profile Complete');
    } elseif ($completeness >= 40) {
      $status_class = 'medium';
      $status_text = $this->t('Almost There');
    }

    $widget = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['profile-completeness-widget', "completeness-{$status_class}"],
      ],
    ];

    $widget['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Profile Completeness'),
      '#attributes' => ['class' => ['widget-title']],
    ];

    $widget['progress_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['progress-container']],
    ];

    $widget['progress_container']['progress_bar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['progress-bar'],
      ],
    ];

    $widget['progress_container']['progress_bar']['fill'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['progress-fill'],
        'style' => "width: {$completeness}%",
      ],
    ];

    $widget['progress_container']['progress_text'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('@percent% Complete', ['@percent' => $completeness]),
      '#attributes' => ['class' => ['progress-text']],
    ];

    $widget['status'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $status_text,
      '#attributes' => ['class' => ['completeness-status']],
    ];

    // Add recommendations for improvement
    if ($completeness < 70) {
      $missing_fields = $this->userProfileService->getMissingFieldRecommendations($user_entity);
      if (!empty($missing_fields)) {
        $widget['recommendations'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['completeness-recommendations']],
        ];
        
        $widget['recommendations']['title'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Complete these to reach 70%:'),
          '#attributes' => ['class' => ['recommendations-title']],
        ];

        $widget['recommendations']['list'] = [
          '#theme' => 'item_list',
          '#items' => $missing_fields,
          '#attributes' => ['class' => ['recommendations-list']],
        ];
      }
    }

    return $widget;
  }

  /**
   * Builds profile statistics.
   *
   * @param \Drupal\user\Entity\User $user_entity
   *   The user entity.
   *
   * @return array
   *   Render array for profile stats.
   */
  protected function buildProfileStats(User $user_entity) {
    $stats = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-stats']],
    ];

    $stats['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Profile Overview'),
      '#attributes' => ['class' => ['section-title']],
    ];

    $stats['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stats-grid']],
    ];

    // Last updated
    $last_update = 'Never';
    if ($user_entity->hasField('field_last_profile_update') && !$user_entity->get('field_last_profile_update')->isEmpty()) {
      $last_update_timestamp = $user_entity->get('field_last_profile_update')->value;
      $last_update = \Drupal::service('date.formatter')->format(strtotime($last_update_timestamp), 'short');
    }

    $stats['grid']['last_updated'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['last_updated']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Last Updated'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['last_updated']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $last_update,
      '#attributes' => ['class' => ['stat-value']],
    ];

    // Work Authorization
    $work_auth = $this->t('Not specified');
    if ($user_entity->hasField('field_work_authorization') && !$user_entity->get('field_work_authorization')->isEmpty()) {
      $work_auth_value = $user_entity->get('field_work_authorization')->value;
      $work_auth_options = [
        'us_citizen' => $this->t('US Citizen'),
        'permanent_resident' => $this->t('Permanent Resident'),
        'h1b' => $this->t('Work Visa (H1B)'),
        'f1' => $this->t('Student Visa (F1)'),
        'visa_required' => $this->t('Visa Sponsorship Required'),
        'other' => $this->t('Other'),
      ];
      $work_auth = $work_auth_options[$work_auth_value] ?? $work_auth_value;
    }

    $stats['grid']['work_auth'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['work_auth']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Work Authorization'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['work_auth']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $work_auth,
      '#attributes' => ['class' => ['stat-value']],
    ];

    // Experience Years
    $experience = $this->t('Not specified');
    if ($user_entity->hasField('field_experience_years') && !$user_entity->get('field_experience_years')->isEmpty()) {
      $years = $user_entity->get('field_experience_years')->value;
      $experience = $this->formatPlural($years, '1 year', '@count years');
    }

    $stats['grid']['experience'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['experience']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Experience'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['experience']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $experience,
      '#attributes' => ['class' => ['stat-value']],
    ];

    // Remote Preference
    $remote_pref = $this->t('Not specified');
    if ($user_entity->hasField('field_remote_preference') && !$user_entity->get('field_remote_preference')->isEmpty()) {
      $remote_value = $user_entity->get('field_remote_preference')->value;
      $remote_options = [
        'remote' => $this->t('Remote'),
        'hybrid' => $this->t('Hybrid'),
        'onsite' => $this->t('On-site'),
        'no_preference' => $this->t('No Preference'),
      ];
      $remote_pref = $remote_options[$remote_value] ?? $remote_value;
    }

    $stats['grid']['remote_pref'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-item']],
    ];
    $stats['grid']['remote_pref']['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Remote Preference'),
      '#attributes' => ['class' => ['stat-label']],
    ];
    $stats['grid']['remote_pref']['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $remote_pref,
      '#attributes' => ['class' => ['stat-value']],
    ];

    return $stats;
  }

  /**
   * Builds profile sections summary.
   *
   * @param \Drupal\user\Entity\User $user_entity
   *   The user entity.
   *
   * @return array
   *   Render array for profile sections.
   */
  protected function buildProfileSections(User $user_entity) {
    $sections = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-sections']],
    ];

    $sections['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Profile Sections'),
      '#attributes' => ['class' => ['section-title']],
    ];

    // Core Information
    $has_resume = $user_entity->hasField('field_resume_file') && !$user_entity->get('field_resume_file')->isEmpty();
    $has_summary = $user_entity->hasField('field_professional_summary') && !$user_entity->get('field_professional_summary')->isEmpty();
    $has_skills = $user_entity->hasField('field_skills_summary') && !$user_entity->get('field_skills_summary')->isEmpty();
    
    $core_completed = 0;
    if ($has_resume) $core_completed++;
    if ($has_summary) $core_completed++;
    if ($has_skills) $core_completed++;

    $sections['core'] = $this->buildSectionSummary(
      $this->t('Core Professional Information'),
      $this->t('Resume, professional summary, and skills'),
      $core_completed,
      3,
      'core'
    );

    // Employment Preferences
    $employment_fields = ['field_work_authorization', 'field_salary_expectation_min', 'field_remote_preference'];
    $employment_completed = 0;
    foreach ($employment_fields as $field) {
      if ($user_entity->hasField($field) && !$user_entity->get($field)->isEmpty()) {
        $employment_completed++;
      }
    }

    $sections['employment'] = $this->buildSectionSummary(
      $this->t('Employment Preferences'),
      $this->t('Work authorization, salary expectations, and preferences'),
      $employment_completed,
      count($employment_fields),
      'employment'
    );

    // Online Presence
    $online_fields = ['field_linkedin_url', 'field_portfolio_url', 'field_github_url'];
    $online_completed = 0;
    foreach ($online_fields as $field) {
      if ($user_entity->hasField($field) && !$user_entity->get($field)->isEmpty()) {
        $uri = $user_entity->get($field)->uri;
        if (!empty($uri)) {
          $online_completed++;
        }
      }
    }

    $sections['online'] = $this->buildSectionSummary(
      $this->t('Online Presence'),
      $this->t('LinkedIn, portfolio, and GitHub profiles'),
      $online_completed,
      count($online_fields),
      'online'
    );

    return $sections;
  }

  /**
   * Builds a section summary widget.
   *
   * @param string $title
   *   The section title.
   * @param string $description
   *   The section description.
   * @param int $completed
   *   Number of completed items.
   * @param int $total
   *   Total number of items.
   * @param string $section_key
   *   The section key for CSS classes.
   *
   * @return array
   *   Render array for the section summary.
   */
  protected function buildSectionSummary($title, $description, $completed, $total, $section_key) {
    $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
    $status = $percentage == 100 ? 'complete' : ($percentage > 0 ? 'partial' : 'empty');

    $section = [
      '#type' => 'container',
      '#attributes' => ['class' => ['section-summary', "section-{$section_key}", "status-{$status}"]],
    ];

    $section['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $title,
      '#attributes' => ['class' => ['section-summary-title']],
    ];

    $section['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $description,
      '#attributes' => ['class' => ['section-summary-description']],
    ];

    $section['progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('@completed of @total completed (@percent%)', [
        '@completed' => $completed,
        '@total' => $total,
        '@percent' => $percentage,
      ]),
      '#attributes' => ['class' => ['section-summary-progress']],
    ];

    $section['progress_bar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['section-progress-bar']],
    ];

    $section['progress_bar']['fill'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['section-progress-fill'],
        'style' => "width: {$percentage}%",
      ],
    ];

    return $section;
  }

  /**
   * Redirects to current user's profile dashboard.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the current user's profile.
   */
  public function myProfile() {
    return $this->redirect('job_application_automation.user_profile_dashboard', [
      'user' => $this->currentUser->id(),
    ]);
  }

  /**
   * Redirects to current user's profile edit form.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the current user's profile edit form.
   */
  public function myProfileEdit() {
    return $this->redirect('job_application_automation.user_profile_edit', [
      'user' => $this->currentUser->id(),
    ]);
  }

  /**
   * Displays the job seeker profile in view mode (not edit mode).
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   The render array for the profile view.
   */
  public function viewJobSeekerProfile() {
    $user = User::load($this->currentUser->id());
    $profile = $this->jobSeekerService->loadByUserId($user->id());

    if (!$profile) {
      // If no profile exists, redirect to create one
      return $this->redirect('job_application_automation.job_seeker_add');
    }

    // Build the profile view
    $build = [
      '#theme' => 'job_seeker_profile',
      '#profile' => $profile,
      '#user' => $user,
    ];

    return $build;
  }

  /**
   * Start job discovery page - shows company selection.
   *
   * @return array
   *   The render array for the company selection page.
   */
  public function startJobDiscovery() {
    $user = User::load($this->currentUser->id());
    
    // Load user's job seeker profile
    $profile = $this->jobSeekerService->loadByUserId($user->id());
    
    if (!$profile) {
      $this->messenger()->addError($this->t('Please complete your job seeker profile first before starting job discovery.'));
      return $this->redirect('job_application_automation.job_seeker_add');
    }

    // Load available companies
    $companies = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'company',
      'status' => 1, // Published
    ]);

    // Extract keywords from profile for preview
    $keywords = !empty($profile->skills) ? $profile->skills : [];
    
    // Build the render array for the company selection page
    $build = [
      '#theme' => 'job_discovery_company_selection',
      '#user' => $user,
      '#profile' => $profile,
      '#companies' => $companies,
      '#keywords' => $keywords,
      '#attached' => [
        'library' => [
          'job_application_automation/job_discovery',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Company-specific job discovery page.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param \Drupal\node\Entity\Node $company
   *   The company entity.
   *
   * @return array
   *   The render array for the company job discovery page.
   */
  public function companyJobDiscovery(User $user, $company) {
    // Check access - user can only access their own job discovery
    if ($user->id() != $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
      throw new AccessDeniedHttpException();
    }

    // Load company entity
    $company_entity = $this->entityTypeManager->getStorage('node')->load($company);
    if (!$company_entity || $company_entity->bundle() !== 'company') {
      throw new NotFoundHttpException();
    }

    // Load user's job seeker profile
    $profile = $this->jobSeekerService->loadByUserId($user->id());
    
    if (!$profile) {
      $this->messenger()->addError($this->t('Please complete your job seeker profile first before starting job discovery.'));
      return $this->redirect('job_application_automation.job_seeker_add');
    }

    // Load job opportunities for this specific company
    $job_opportunities = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'job_posting',
      'status' => 1, // Published
      'field_company_ref' => $company_entity->id(),
    ]);
    
    // Build the render array for the company-specific job discovery page
    $build = [
      '#theme' => 'job_discovery_company_search',
      '#user' => $user,
      '#company' => $company_entity,
      '#job_opportunities' => $job_opportunities,
      '#attached' => [
        'library' => [
          'job_application_automation/job_discovery',
        ],
      ],
    ];

    return $build;
  }

  /**
   * AJAX endpoint for job discovery search.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with job search results.
   */
  public function jobDiscoverySearch() {
    try {
      $request = \Drupal::request();
      $user_id = $request->request->get('user_id');
      $company_id = $request->request->get('company_id');
      
      // Debug logging
      \Drupal::logger('job_application_automation')->info('Job discovery search started for user @user_id, company @company_id', [
        '@user_id' => $user_id,
        '@company_id' => $company_id,
      ]);
      
      if (!$user_id || !is_numeric($user_id)) {
        \Drupal::logger('job_application_automation')->error('Invalid user ID: @user_id', [
          '@user_id' => $user_id,
        ]);
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Invalid user ID',
        ], 400);
      }

      if (!$company_id || !is_numeric($company_id)) {
        \Drupal::logger('job_application_automation')->error('Invalid company ID: @company_id', [
          '@company_id' => $company_id,
        ]);
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Invalid company ID',
        ], 400);
      }

      // Load user and company
      $user = \Drupal\user\Entity\User::load($user_id);
      if (!$user) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'User not found',
        ], 404);
      }

      $company = $this->entityTypeManager->getStorage('node')->load($company_id);
      if (!$company || $company->bundle() !== 'company') {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Company not found',
        ], 404);
      }
      
      // Load job seeker profile
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profiles = $profile_storage->loadByProperties([
        'uid' => $user->id(),
        'type' => 'job_seeker',
      ]);
      
      $profile = reset($profiles);
      $keywords = [];
      
      if ($profile) {
        // Extract keywords from profile
        $keywords = $this->extractKeywordsFromProfile($profile);
      }
      
      // If no keywords found, use default ones for testing
      if (empty($keywords)) {
        $keywords = ['Data Science', 'Analytics', 'Machine Learning'];
        \Drupal::logger('job_application_automation')->info('Using default keywords for testing: @keywords', [
          '@keywords' => implode(', ', $keywords),
        ]);
      }
      
      // Debug log the final keywords being used
      \Drupal::logger('job_application_automation')->info('Final keywords being passed to scraping service: @keywords', [
        '@keywords' => print_r($keywords, true),
      ]);
      
      // Determine which scraping service to use based on company
      $company_name = strtolower($company->getTitle());
      $jobs = [];
      
      if ($company_name === 'abbvie') {
        // Use AbbVie scraping service
        $scraping_service = \Drupal::service('job_application_automation.abbvie_job_scraping_service');
        $jobs = $scraping_service->searchJobs($keywords, [
          'company' => 'abbvie',
        ]);
      } else {
        // For other companies, return a message indicating scraping is not yet implemented
        \Drupal::logger('job_application_automation')->info('Job scraping not yet implemented for company: @company', [
          '@company' => $company->getTitle(),
        ]);
        
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'jobs' => [],
          'keywords_used' => $keywords,
          'total_found' => 0,
          'message' => 'Job scraping for ' . $company->getTitle() . ' is coming soon! Currently only AbbVie is supported.',
        ]);
      }
      
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'jobs' => $jobs,
        'keywords_used' => $keywords,
        'total_found' => count($jobs),
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('job_application_automation')->error('Job discovery search error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => 'Search failed: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Extract keywords from job seeker profile.
   *
   * @param \Drupal\profile\Entity\Profile $profile
   *   The job seeker profile.
   *
   * @return array
   *   Array of keywords extracted from profile fields.
   */
  private function extractKeywordsFromProfile($profile) {
    $keywords = [];
    
    // Extract from various fields that contain relevant keywords
    // Using actual field names from the job seeker profile
    $keyword_fields = [
      'field_target_job_titles',        // "Desired job titles and roles"
      'field_keywords_interested',      // "Job Search Keywords"
      'field_skills_summary',           // Skills summary
      'field_professional_summary',     // Professional summary
      'field_certifications',           // Certifications
    ];
    
    foreach ($keyword_fields as $field_name) {
      if ($profile->hasField($field_name) && !$profile->get($field_name)->isEmpty()) {
        $field_value = $profile->get($field_name)->value;
        if (!empty($field_value)) {
          // Strip HTML tags and decode HTML entities
          $field_value = html_entity_decode(strip_tags($field_value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
          
          // Split by common delimiters and clean up
          $field_keywords = preg_split('/[,;\n\r]+/', $field_value);
          foreach ($field_keywords as $keyword) {
            $keyword = trim($keyword);
            // Remove surrounding quotes
            $keyword = trim($keyword, '"\'');
            if (strlen($keyword) > 2) { // Only include meaningful keywords
              $keywords[] = $keyword;
            }
          }
        }
      }
    }
    
    // Remove duplicates and return
    return array_unique($keywords);
  }

  /**
   * Save a job from job discovery to job_posting content type.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error status.
   */
  public function saveJob() {
    try {
      // Check if user is authenticated
      if ($this->currentUser->isAnonymous()) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'User must be logged in to save jobs',
        ], 403);
      }

      $request = \Drupal::request();
      $job_data = json_decode($request->getContent(), TRUE);
      
      if (!$job_data || empty($job_data['jobId'])) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Invalid job data provided',
        ], 400);
      }

      // Check if job already exists to avoid duplicates
      $existing_job = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'job_posting',
          'field_job_id' => $job_data['jobId'],
          'uid' => $this->currentUser->id(),
        ]);

      if (!empty($existing_job)) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'success' => true,
          'message' => 'Job already saved to your dashboard',
          'node_id' => reset($existing_job)->id(),
        ]);
      }

      // Create new job posting node
      $node = \Drupal\node\Entity\Node::create([
        'type' => 'job_posting',
        'title' => $job_data['title'],
        'uid' => $this->currentUser->id(),
      ]);

      // Map job discovery data to job posting fields
      if (!empty($job_data['title'])) {
        $node->set('field_job_title', $job_data['title']);
      }

      if (!empty($job_data['jobId'])) {
        $node->set('field_job_id', $job_data['jobId']);
      }

      if (!empty($job_data['location'])) {
        $node->set('field_location', $job_data['location']);
      }

      if (!empty($job_data['description'])) {
        $node->set('field_job_description', [
          'value' => $job_data['description'],
          'format' => 'basic_html',
        ]);
      }

      if (!empty($job_data['url'])) {
        $node->set('field_job_url', [
          'uri' => $job_data['url'],
          'title' => 'View Job at AbbVie',
        ]);
      }

      // Map additional fields if available
      if (!empty($job_data['jobType'])) {
        $node->set('field_employment_type', $job_data['jobType']);
      }

      if (!empty($job_data['experienceLevel'])) {
        $node->set('field_experience_level', $job_data['experienceLevel']);
      }

      // Set posting date to current date
      $node->set('field_posting_date', date('Y-m-d\TH:i:s'));

      // Set job status to saved
      $node->set('field_job_status', 'saved');

      // Save the node
      $node->save();

      \Drupal::logger('job_application_automation')->info('Job saved: @title (@job_id) for user @uid', [
        '@title' => $job_data['title'],
        '@job_id' => $job_data['jobId'],
        '@uid' => $this->currentUser->id(),
      ]);

      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => true,
        'message' => 'Job saved to your dashboard successfully',
        'node_id' => $node->id(),
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('job_application_automation')->error('Error saving job: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => 'Failed to save job: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Tailor resume for a specific job opportunity.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param int $job
   *   The job posting node ID.
   *
   * @return array
   *   The render array for the tailor resume page.
   */
  public function tailorResume(User $user, $job) {
    // Check access - user can only tailor their own resume
    if ($user->id() != $this->currentUser->id() && !$this->currentUser->hasPermission('administer users')) {
      throw new AccessDeniedHttpException();
    }

    // Load job posting entity
    $job_entity = $this->entityTypeManager->getStorage('node')->load($job);
    if (!$job_entity || $job_entity->bundle() !== 'job_posting') {
      throw new NotFoundHttpException();
    }

    // Load resume from node 10
    $resume_entity = $this->entityTypeManager->getStorage('node')->load(10);
    if (!$resume_entity) {
      $this->messenger()->addError($this->t('Resume not found. Please contact the administrator.'));
      throw new NotFoundHttpException();
    }

    // Load user's job seeker profile
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $user->id(),
      'type' => 'job_seeker',
    ]);

    $profile = reset($profiles);
    
    if (!$profile) {
      $this->messenger()->addError($this->t('Please complete your job seeker profile first before tailoring your resume.'));
      return $this->redirect('job_application_automation.user_job_seeker_view', ['user' => $user->id()]);
    }

    // Build the render array for the tailor resume page
    $build = [
      '#theme' => 'tailor_resume',
      '#user' => $user,
      '#profile' => $profile,
      '#job' => $job_entity,
      '#resume' => $resume_entity,
      '#attached' => [
        'library' => [
          'job_application_automation/tailor_resume',
        ],
      ],
    ];

    return $build;
  }

  /**
   * AJAX endpoint for AI resume tailoring.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with tailored resume.
   */
  public function tailorResumeAjax() {
    try {
      $request = \Drupal::request();
      $job_id = $request->request->get('job_id');
      $user_id = $request->request->get('user_id', \Drupal::currentUser()->id());
      
      // Load job posting
      $job_entity = $this->entityTypeManager->getStorage('node')->load($job_id);
      if (!$job_entity || $job_entity->bundle() !== 'job_posting') {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Invalid job posting',
        ], 400);
      }

      // Load resume from node 10
      $resume_entity = $this->entityTypeManager->getStorage('node')->load(10);
      if (!$resume_entity) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'error' => 'Resume not found',
        ], 400);
      }

      // Generate tailored resume content using AI service
      $tailored_content = $this->generateTailoredResume($resume_entity, $job_entity, []);
      
      // Create Tailored Resume node
      $tailored_resume_node = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'tailored_resume',
        'title' => 'Tailored Resume: ' . $job_entity->getTitle(),
        'body' => [
          'value' => $tailored_content,
          'format' => 'basic_html',
        ],
        'field_job_posting' => $job_entity->id(),
        'field_resume' => 10, // Reference to node 10 (Keith Aumiller Base Resume)
        'uid' => $user_id,
        'status' => 1,
      ]);
      $tailored_resume_node->save();
      
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => TRUE,
        'message' => 'Tailored resume created successfully!',
        'job_title' => $job_entity->getTitle(),
        'tailored_resume_node_id' => $tailored_resume_node->id(),
        'tailored_resume' => $tailored_content,
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('job_application_automation')->error('Error creating tailored resume: @error', ['@error' => $e->getMessage()]);
      
      return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => 'Failed to create tailored resume: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Generate tailored resume (mock in dev, real AI in prod).
   */
  private function generateTailoredResume($resume_entity, $job_entity, $options) {
    // Check if we're in a development environment
    $is_dev_environment = $this->isDevelopmentEnvironment();
    
    if ($is_dev_environment) {
      // Return mock response for development
      return $this->getMockTailoredResume($resume_entity, $job_entity, $options);
    }
    else {
      // Use real AI service in production
      return $this->getAiTailoredResume($resume_entity, $job_entity, $options);
    }
  }

  /**
   * Check if we're in a development environment.
   */
  private function isDevelopmentEnvironment() {
    // Check environment variables that indicate development environment
    // Supports GitHub Codespaces, Gitpod, Cloud9, and other development platforms
    $env_indicators = ['CODESPACE_NAME', 'GITPOD_WORKSPACE_ID', 'C9_USER', 'DOCKER_HOST'];
    foreach ($env_indicators as $indicator) {
      if (getenv($indicator)) {
        return TRUE;
      }
    }
    
    // Check if running in non-standard port (development indicator)
    if (isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], [80, 443])) {
      return TRUE;
    }
    
    // Check if we're in local development
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    if (in_array($host, ['localhost', '127.0.0.1', 'local.dev'])) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Get mock tailored resume for development.
   */
  private function getMockTailoredResume($resume_entity, $job_entity, $options) {
    return "This is where the tailored resume would be.";
  }

  /**
   * Get AI-generated tailored resume for production.
   */
  private function getAiTailoredResume($resume_entity, $job_entity, $options) {
    try {
      // Use injected AI service or get from container if not available
      $ai_service = $this->aiApiService ?: \Drupal::service('ai_conversation.ai_api_service');
      
      // Create a temporary conversation node for this tailoring request
      $conversation = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'ai_conversation',
        'title' => 'Resume Tailoring: ' . $job_entity->getTitle(),
        'field_context' => $this->buildResumeTailoringSystemPrompt($resume_entity, $job_entity, $options),
        'field_ai_model' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'uid' => \Drupal::currentUser()->id(),
      ]);
      $conversation->save();

      // Build tailoring request message
      $tailoring_message = $this->buildTailoringMessage($resume_entity, $job_entity, $options);
      
      // Get AI response
      $tailored_resume = $ai_service->sendMessage($conversation, $tailoring_message);
      
      // Clean up temporary conversation
      $conversation->delete();
      
      return $tailored_resume;
    }
    catch (\Exception $e) {
      \Drupal::logger('job_application_automation')->error('AI tailoring failed: @error', ['@error' => $e->getMessage()]);
      return $this->getMockTailoredResume($resume_entity, $job_entity, $options);
    }
  }

  /**
   * Build system prompt for resume tailoring.
   */
  private function buildResumeTailoringSystemPrompt($resume_entity, $job_entity, $options) {
    $prompt = "You are an expert resume tailoring assistant. Your task is to analyze a job posting and tailor an existing resume to better match the position requirements.\n\n";
    
    $prompt .= "INSTRUCTIONS:\n";
    $prompt .= "1. Analyze the job description, requirements, and skills needed\n";
    $prompt .= "2. Tailor the resume to emphasize relevant experience and skills\n";
    $prompt .= "3. Maintain all factual information - do not fabricate experience\n";
    $prompt .= "4. Reorder sections and bullet points to highlight most relevant items first\n";
    $prompt .= "5. Use keywords from the job posting naturally throughout the resume\n";
    $prompt .= "6. Format as clean HTML that can be easily converted to PDF\n\n";
    
    if (in_array('emphasize-keywords', $options)) {
      $prompt .= "- EMPHASIZE KEYWORDS: Naturally incorporate job posting keywords\n";
    }
    if (in_array('reorder-sections', $options)) {
      $prompt .= "- REORDER SECTIONS: Prioritize most relevant sections and experiences\n";
    }
    if (in_array('highlight-achievements', $options)) {
      $prompt .= "- HIGHLIGHT ACHIEVEMENTS: Emphasize accomplishments relevant to the role\n";
    }
    
    $prompt .= "\nOutput only the tailored resume in clean HTML format suitable for professional presentation.";
    
    return $prompt;
  }

  /**
   * Build tailoring message with resume and job details.
   */
  private function buildTailoringMessage($resume_entity, $job_entity, $options) {
    $message = "Please tailor this resume for the following job posting:\n\n";
    
    $message .= "=== JOB POSTING ===\n";
    $message .= "Title: " . $job_entity->getTitle() . "\n\n";
    
    if (!$job_entity->get('field_job_description')->isEmpty()) {
      $message .= "Description:\n" . strip_tags($job_entity->get('field_job_description')->value) . "\n\n";
    }
    
    if (!$job_entity->get('field_requirements')->isEmpty()) {
      $message .= "Requirements:\n" . strip_tags($job_entity->get('field_requirements')->value) . "\n\n";
    }
    
    if (!$job_entity->get('field_skills_required')->isEmpty()) {
      $message .= "Skills Required:\n" . strip_tags($job_entity->get('field_skills_required')->value) . "\n\n";
    }
    
    $message .= "=== ORIGINAL RESUME ===\n";
    if (!$resume_entity->get('body')->isEmpty()) {
      $message .= strip_tags($resume_entity->get('body')->value) . "\n\n";
    }
    
    $message .= "Please provide the tailored resume optimized for this specific position.";
    
    return $message;
  }

}