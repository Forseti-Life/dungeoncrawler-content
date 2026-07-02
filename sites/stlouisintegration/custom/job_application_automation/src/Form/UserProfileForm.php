<?php

namespace Drupal\job_application_automation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\job_application_automation\Service\UserProfileService;

/**
 * Provides a form for editing user job application profile.
 */
class UserProfileForm extends FormBase {

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The user profile service.
   *
   * @var \Drupal\job_application_automation\Service\UserProfileService
   */
  protected $userProfileService;

  /**
   * Constructs a new UserProfileForm.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\job_application_automation\Service\UserProfileService $user_profile_service
   *   The user profile service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, UserProfileService $user_profile_service) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->userProfileService = $user_profile_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('job_application_automation.user_profile_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'job_application_automation_user_profile_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    // Load the user entity - either specified user or current user
    $uid = $user ?: $this->currentUser->id();
    $user_entity = User::load($uid);

    if (!$user_entity) {
      $this->messenger->addError($this->t('User not found.'));
      return [];
    }

    // Store user entity for submit handler
    $form_state->set('user_entity', $user_entity);

    $form['#prefix'] = '<div class="user-profile-form job-application-profile">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'job_application_automation/user_profile';

    // Profile completion progress
    $completeness = $this->userProfileService->calculateProfileCompleteness($user_entity);
    $form['profile_progress'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['profile-progress']],
    ];
    $form['profile_progress']['progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Profile Completeness: @percent%', ['@percent' => $completeness]),
      '#attributes' => [
        'class' => ['profile-progress-text'],
        'data-progress' => $completeness,
      ],
    ];
    $form['profile_progress']['bar'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['profile-progress-bar'],
      ],
    ];
    $form['profile_progress']['bar']['fill'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['profile-progress-fill'],
        'style' => "width: {$completeness}%",
      ],
    ];

    // Core Information Section
    $form['core_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Core Professional Information'),
      '#description' => $this->t('Essential information for job applications'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['core_info']['field_resume_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Primary Resume'),
      '#description' => $this->t('Upload your primary resume file (PDF or Word format, max 10MB)'),
      '#required' => TRUE,
      '#upload_location' => 'public://job_application_automation/resumes',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx'],
        'file_validate_size' => [10 * 1024 * 1024], // 10MB
      ],
      '#default_value' => $user_entity->hasField('field_resume_file') && !$user_entity->get('field_resume_file')->isEmpty() 
        ? [$user_entity->get('field_resume_file')->target_id] : [],
    ];

    $form['core_info']['field_professional_summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional Summary'),
      '#description' => $this->t('Brief professional summary or objective statement (2-3 sentences)'),
      '#rows' => 4,
      '#maxlength' => 500,
      '#default_value' => $user_entity->hasField('field_professional_summary') 
        ? $user_entity->get('field_professional_summary')->value : '',
    ];

    $form['core_info']['field_skills_summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skills Summary'),
      '#description' => $this->t('Overview of your technical and professional skills'),
      '#rows' => 5,
      '#default_value' => $user_entity->hasField('field_skills_summary') 
        ? $user_entity->get('field_skills_summary')->value : '',
    ];

    // Employment Information Section
    $form['employment_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Employment Preferences & Status'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['employment_info']['field_work_authorization'] = [
      '#type' => 'select',
      '#title' => $this->t('Work Authorization'),
      '#description' => $this->t('Your legal work authorization status'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Select -'),
        'us_citizen' => $this->t('US Citizen'),
        'permanent_resident' => $this->t('Permanent Resident'),
        'h1b' => $this->t('Work Visa (H1B)'),
        'f1' => $this->t('Student Visa (F1)'),
        'visa_required' => $this->t('Visa Sponsorship Required'),
        'other' => $this->t('Other'),
      ],
      '#default_value' => $user_entity->hasField('field_work_authorization') 
        ? $user_entity->get('field_work_authorization')->value : '',
    ];

    $form['employment_info']['salary_range'] = [
      '#type' => 'container',
      '#title' => $this->t('Salary Expectations'),
      '#title_display' => 'above',
    ];

    $form['employment_info']['salary_range']['field_salary_expectation_min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Salary Expectation'),
      '#description' => $this->t('Annual salary (USD)'),
      '#min' => 0,
      '#max' => 999999,
      '#step' => 1000,
      '#field_suffix' => '$',
      '#default_value' => $user_entity->hasField('field_salary_expectation_min') 
        ? $user_entity->get('field_salary_expectation_min')->value : '',
    ];

    $form['employment_info']['salary_range']['field_salary_expectation_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Salary Expectation'),
      '#description' => $this->t('Annual salary (USD)'),
      '#min' => 0,
      '#max' => 999999,
      '#step' => 1000,
      '#field_suffix' => '$',
      '#default_value' => $user_entity->hasField('field_salary_expectation_max') 
        ? $user_entity->get('field_salary_expectation_max')->value : '',
    ];

    $form['employment_info']['field_available_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Available Start Date'),
      '#description' => $this->t('Earliest date you can start work'),
      '#default_value' => $user_entity->hasField('field_available_start_date') && !$user_entity->get('field_available_start_date')->isEmpty()
        ? $user_entity->get('field_available_start_date')->date->format('Y-m-d') : '',
    ];

    $form['employment_info']['field_remote_preference'] = [
      '#type' => 'select',
      '#title' => $this->t('Remote Work Preference'),
      '#description' => $this->t('Your preference for remote work arrangements'),
      '#options' => [
        '' => $this->t('- Select -'),
        'remote' => $this->t('Remote'),
        'hybrid' => $this->t('Hybrid'),
        'onsite' => $this->t('On-site'),
        'no_preference' => $this->t('No Preference'),
      ],
      '#default_value' => $user_entity->hasField('field_remote_preference') 
        ? $user_entity->get('field_remote_preference')->value : '',
    ];

    $form['employment_info']['field_relocation_willing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Willing to Relocate'),
      '#description' => $this->t('Are you willing to relocate for the right opportunity?'),
      '#default_value' => $user_entity->hasField('field_relocation_willing') 
        ? $user_entity->get('field_relocation_willing')->value : 0,
    ];

    // Experience & Education Section
    $form['experience_education'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Experience & Education'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['experience_education']['field_experience_years'] = [
      '#type' => 'number',
      '#title' => $this->t('Years of Professional Experience'),
      '#description' => $this->t('Total years of relevant professional experience'),
      '#min' => 0,
      '#max' => 50,
      '#default_value' => $user_entity->hasField('field_experience_years') 
        ? $user_entity->get('field_experience_years')->value : '',
    ];

    $form['experience_education']['field_education_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Education Level'),
      '#description' => $this->t('Highest level of education completed'),
      '#options' => [
        '' => $this->t('- Select -'),
        'high_school' => $this->t('High School'),
        'associates' => $this->t('Associates Degree'),
        'bachelors' => $this->t('Bachelors Degree'),
        'masters' => $this->t('Masters Degree'),
        'doctoral' => $this->t('Doctoral Degree'),
        'professional' => $this->t('Professional Degree'),
      ],
      '#default_value' => $user_entity->hasField('field_education_level') 
        ? $user_entity->get('field_education_level')->value : '',
    ];

    $form['experience_education']['field_certifications'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional Certifications'),
      '#description' => $this->t('List your professional certifications and licenses'),
      '#rows' => 3,
      '#default_value' => $user_entity->hasField('field_certifications') 
        ? $user_entity->get('field_certifications')->value : '',
    ];

    // Online Presence Section
    $form['online_presence'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Online Professional Presence'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['online_presence']['field_portfolio_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Portfolio URL'),
      '#description' => $this->t('Portfolio or personal website URL'),
      '#default_value' => $user_entity->hasField('field_portfolio_url') && !$user_entity->get('field_portfolio_url')->isEmpty()
        ? $user_entity->get('field_portfolio_url')->uri : '',
    ];

    $form['online_presence']['field_linkedin_url'] = [
      '#type' => 'url',
      '#title' => $this->t('LinkedIn Profile URL'),
      '#description' => $this->t('Your LinkedIn profile URL'),
      '#default_value' => $user_entity->hasField('field_linkedin_url') && !$user_entity->get('field_linkedin_url')->isEmpty()
        ? $user_entity->get('field_linkedin_url')->uri : '',
    ];

    $form['online_presence']['field_github_url'] = [
      '#type' => 'url',
      '#title' => $this->t('GitHub Profile URL'),
      '#description' => $this->t('Your GitHub profile URL'),
      '#default_value' => $user_entity->hasField('field_github_url') && !$user_entity->get('field_github_url')->isEmpty()
        ? $user_entity->get('field_github_url')->uri : '',
    ];

    // Job Preferences Section
    $form['job_preferences'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Job Search Preferences'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['job_preferences']['field_keywords_interested'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Job Keywords of Interest'),
      '#description' => $this->t('Keywords and job types you are interested in (one per line)'),
      '#rows' => 4,
      '#default_value' => $user_entity->hasField('field_keywords_interested') 
        ? $user_entity->get('field_keywords_interested')->value : '',
    ];

    $form['job_preferences']['field_target_job_titles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Target Job Titles'),
      '#description' => $this->t('Desired job titles and roles (one per line)'),
      '#rows' => 4,
      '#default_value' => $user_entity->hasField('field_target_job_titles') 
        ? $user_entity->get('field_target_job_titles')->value : '',
    ];

    $form['job_preferences']['field_cover_letter_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cover Letter Template'),
      '#description' => $this->t('Default cover letter template for applications'),
      '#rows' => 6,
      '#default_value' => $user_entity->hasField('field_cover_letter_template') 
        ? $user_entity->get('field_cover_letter_template')->value : '',
    ];

    // Additional Information Section
    $form['additional_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional Information'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['additional_info']['field_references_available'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('References Available Upon Request'),
      '#description' => $this->t('Check if you can provide professional references'),
      '#default_value' => $user_entity->hasField('field_references_available') 
        ? $user_entity->get('field_references_available')->value : 0,
    ];

    // Actions
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Profile'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('entity.user.canonical', ['user' => $uid]),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate salary range
    $min_salary = $form_state->getValue('field_salary_expectation_min');
    $max_salary = $form_state->getValue('field_salary_expectation_max');

    if (!empty($min_salary) && !empty($max_salary) && $min_salary > $max_salary) {
      $form_state->setErrorByName('field_salary_expectation_max', 
        $this->t('Maximum salary must be greater than minimum salary.'));
    }

    // Validate URLs
    $urls = [
      'field_portfolio_url' => 'Portfolio URL',
      'field_linkedin_url' => 'LinkedIn URL',
      'field_github_url' => 'GitHub URL',
    ];

    foreach ($urls as $field => $label) {
      $url = $form_state->getValue($field);
      if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
        $form_state->setErrorByName($field, 
          $this->t('@label must be a valid URL.', ['@label' => $label]));
      }
    }

    // Validate LinkedIn URL format
    $linkedin_url = $form_state->getValue('field_linkedin_url');
    if (!empty($linkedin_url) && strpos($linkedin_url, 'linkedin.com') === FALSE) {
      $form_state->setErrorByName('field_linkedin_url', 
        $this->t('LinkedIn URL should contain linkedin.com'));
    }

    // Validate GitHub URL format
    $github_url = $form_state->getValue('field_github_url');
    if (!empty($github_url) && strpos($github_url, 'github.com') === FALSE) {
      $form_state->setErrorByName('field_github_url', 
        $this->t('GitHub URL should contain github.com'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_entity = $form_state->get('user_entity');
    
    // Save all form values to user entity
    $fields = [
      'field_professional_summary',
      'field_skills_summary',
      'field_work_authorization',
      'field_salary_expectation_min',
      'field_salary_expectation_max',
      'field_remote_preference',
      'field_relocation_willing',
      'field_experience_years',
      'field_education_level',
      'field_certifications',
      'field_keywords_interested',
      'field_target_job_titles',
      'field_cover_letter_template',
      'field_references_available',
    ];

    foreach ($fields as $field_name) {
      $value = $form_state->getValue($field_name);
      if ($user_entity->hasField($field_name)) {
        $user_entity->set($field_name, $value);
      }
    }

    // Handle file upload for resume
    $resume_file = $form_state->getValue('field_resume_file');
    if (!empty($resume_file[0]) && $user_entity->hasField('field_resume_file')) {
      $file = \Drupal\file\Entity\File::load($resume_file[0]);
      if ($file) {
        $file->setPermanent();
        $file->save();
        $user_entity->set('field_resume_file', $resume_file[0]);
      }
    }

    // Handle URL fields
    $url_fields = [
      'field_portfolio_url',
      'field_linkedin_url', 
      'field_github_url',
    ];

    foreach ($url_fields as $field_name) {
      $url = $form_state->getValue($field_name);
      if (!empty($url) && $user_entity->hasField($field_name)) {
        $user_entity->set($field_name, ['uri' => $url]);
      }
    }

    // Handle date field
    $start_date = $form_state->getValue('field_available_start_date');
    if (!empty($start_date) && $user_entity->hasField('field_available_start_date')) {
      $user_entity->set('field_available_start_date', $start_date);
    }

    // Update profile completeness and last update timestamp
    $completeness = $this->userProfileService->updateProfileCompleteness($user_entity, false);

    if ($user_entity->hasField('field_last_profile_update')) {
      $user_entity->set('field_last_profile_update', date('Y-m-d\TH:i:s'));
    }

    // Save the user entity
    $user_entity->save();

    $this->messenger->addMessage($this->t('Your profile has been updated successfully. Profile completeness: @percent%', 
      ['@percent' => $completeness]));

    $form_state->setRedirect('entity.user.canonical', ['user' => $user_entity->id()]);
  }

}