<?php

namespace Drupal\job_application_automation\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;

/**
 * Service for user profile validation and completeness calculation.
 */
class UserProfileService {
  
  use StringTranslationTrait;

  /**
   * Field weights for completeness calculation.
   *
   * @var array
   */
  const FIELD_WEIGHTS = [
    'field_resume_file' => 20,
    'field_work_authorization' => 15,
    'field_professional_summary' => 10,
    'field_skills_summary' => 10,
    'field_experience_years' => 8,
    'field_education_level' => 8,
    'field_remote_preference' => 5,
    'field_linkedin_url' => 5,
    'field_salary_expectation_min' => 5,
    'field_available_start_date' => 5,
    'field_portfolio_url' => 4,
    'field_github_url' => 3,
    'field_certifications' => 2,
  ];

  /**
   * Calculates user profile completeness percentage.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return int
   *   Profile completeness percentage (0-100).
   */
  public function calculateProfileCompleteness(User $user) {
    // Use the new profile-based calculation method to align with field entity type migration
    return $this->calculateProfileCompletenessFromProfile($user);
  }

  /**
   * Checks if a specific field is completed for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is completed, FALSE otherwise.
   */
  public function isFieldCompleted(User $user, $field_name) {
    if (!$user->hasField($field_name)) {
      return FALSE;
    }

    $field_value = $user->get($field_name);
    
    if ($field_value->isEmpty()) {
      return FALSE;
    }

    // Special handling for different field types
    if ($field_name === 'field_resume_file') {
      // File field - check if file exists
      return !$field_value->isEmpty();
    } elseif (in_array($field_name, ['field_portfolio_url', 'field_linkedin_url', 'field_github_url'])) {
      // URL fields - check if URI is valid
      $uri = $field_value->uri;
      return !empty($uri) && filter_var($uri, FILTER_VALIDATE_URL);
    } else {
      // Regular fields - check if value exists and is not empty
      $value = $field_value->value;
      return !empty($value);
    }
  }

  /**
   * Gets missing required fields for profile recommendations.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param int $limit
   *   Maximum number of recommendations to return.
   *
   * @return array
   *   Array of missing field recommendations.
   */
  public function getMissingFieldRecommendations(User $user, $limit = 5) {
    // Use the new profile-based method to align with field entity type migration
    return $this->getMissingFieldRecommendationsFromProfile($user, $limit);
  }

  /**
   * Updates profile completeness field for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param bool $save
   *   Whether to save the user entity after updating.
   *
   * @return int
   *   The calculated completeness percentage.
   */
  public function updateProfileCompleteness(User $user, $save = TRUE) {
    // Use the new profile-based method to align with field entity type migration
    return $this->updateProfileCompletenessFromProfile($user, $save);
  }

  /**
   * Gets profile completeness status information.
   *
   * @param int $completeness
   *   The completeness percentage.
   *
   * @return array
   *   Array with status information including class, message, and level.
   */
  public function getCompletenessStatus($completeness) {
    if ($completeness >= 70) {
      return [
        'class' => 'complete',
        'level' => 'high',
        'message' => $this->t('Profile Complete'),
        'description' => $this->t('Your profile is ready for job applications.'),
      ];
    } elseif ($completeness >= 40) {
      return [
        'class' => 'partial',
        'level' => 'medium',
        'message' => $this->t('Almost There'),
        'description' => $this->t('Complete a few more fields to reach 70%.'),
      ];
    } else {
      return [
        'class' => 'incomplete',
        'level' => 'low',
        'message' => $this->t('Getting Started'),
        'description' => $this->t('Add more information to improve your profile.'),
      ];
    }
  }

  /**
   * Validates profile data for job application readiness.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Validation result with status and messages.
   */
  public function validateForJobApplication(User $user) {
    // Use profile-based validation to align with field entity type migration
    return $this->validateForJobApplicationFromProfile($user);
  }

  /**
   * Validates user profile for job application using profile entity data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Validation results with ready, errors, warnings, and recommendations.
   */
  public function validateForJobApplicationFromProfile(User $user) {
    $profile = $this->getJobSeekerProfile($user);
    $completeness = $this->calculateProfileCompletenessFromProfile($user);
    $errors = [];
    $warnings = [];
    $recommendations = [];

    // Critical Requirements (Blocking - cannot apply without these)
    if (!$profile || !$this->isProfileFieldCompleted($profile, 'field_resume_file')) {
      $errors[] = $this->t('Resume upload is required - employers need to see your qualifications.');
    }

    if (!$profile || !$this->isProfileFieldCompleted($profile, 'field_work_authorization')) {
      $errors[] = $this->t('Work authorization status is required - employers must verify eligibility.');
    }

    // Contact Information Validation (Critical for employer contact)
    $email = $user->getEmail();
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = $this->t('Valid email address is required for application responses.');
    }

    // Enhanced Data Quality Checks
    if ($this->isFieldCompleted($user, 'field_salary_expectation_min') && $this->isFieldCompleted($user, 'field_salary_expectation_max')) {
      $min_salary = $user->get('field_salary_expectation_min')->value;
      $max_salary = $user->get('field_salary_expectation_max')->value;
      
      if ($min_salary >= $max_salary) {
        $errors[] = $this->t('Minimum salary must be less than maximum salary.');
      }
      
      if ($min_salary < 20000 || $max_salary > 500000) {
        $warnings[] = $this->t('Salary expectations seem unusual - verify amounts are accurate.');
      }
    }

    // Professional Presence Validation
    if ($this->isFieldCompleted($user, 'field_linkedin_url')) {
      $linkedin_url = $user->get('field_linkedin_url')->uri;
      if (!preg_match('/linkedin\.com\/in\//i', $linkedin_url)) {
        $warnings[] = $this->t('LinkedIn URL should be a profile link (linkedin.com/in/yourname).');
      }
    }

    if ($this->isFieldCompleted($user, 'field_github_url')) {
      $github_url = $user->get('field_github_url')->uri;
      if (!preg_match('/github\.com\//i', $github_url)) {
        $warnings[] = $this->t('GitHub URL should be a valid GitHub profile or repository link.');
      }
    }

    // Application Success Factors (High-impact recommendations)
    if (!$this->isFieldCompleted($user, 'field_professional_summary')) {
      $recommendations[] = $this->t('Professional summary significantly improves application success rates.');
    }

    if (!$this->isFieldCompleted($user, 'field_skills_summary')) {
      $recommendations[] = $this->t('Skills summary helps employers match you to relevant positions.');
    }

    if (!$this->isFieldCompleted($user, 'field_linkedin_url')) {
      $recommendations[] = $this->t('LinkedIn profile adds credibility and helps employers learn about you.');
    }

    if (!$this->isFieldCompleted($user, 'field_experience_years')) {
      $warnings[] = $this->t('Years of experience helps employers assess your career level.');
    }

    if (!$this->isFieldCompleted($user, 'field_available_start_date')) {
      $warnings[] = $this->t('Start date availability is commonly requested in applications.');
    }

    // Completeness-based Validation
    if ($completeness < 50) {
      $errors[] = $this->t('Profile must be at least 50% complete for reliable application submissions.');
    } elseif ($completeness < 70) {
      $warnings[] = $this->t('Profile completeness below 70% significantly reduces application success rate.');
    }

    // Application Readiness Score
    $readiness_score = $this->calculateApplicationReadinessScore($user, $completeness, $errors, $warnings);

    return [
      'ready' => empty($errors),
      'completeness' => $completeness,
      'readiness_score' => $readiness_score,
      'errors' => $errors,
      'warnings' => $warnings,
      'recommendations' => $recommendations,
    ];
  }

  /**
   * Gets profile statistics for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   Array of profile statistics.
   */
  public function getProfileStats(User $user) {
    $stats = [];
    
    // Basic completeness
    $stats['completeness'] = $this->calculateProfileCompleteness($user);
    
    // Field counts
    $total_fields = count(self::FIELD_WEIGHTS);
    $completed_fields = 0;
    
    foreach (array_keys(self::FIELD_WEIGHTS) as $field_name) {
      if ($this->isFieldCompleted($user, $field_name)) {
        $completed_fields++;
      }
    }
    
    $stats['completed_fields'] = $completed_fields;
    $stats['total_fields'] = $total_fields;
    
    // Last update
    $stats['last_updated'] = NULL;
    if ($user->hasField('field_last_profile_update') && !$user->get('field_last_profile_update')->isEmpty()) {
      $stats['last_updated'] = $user->get('field_last_profile_update')->value;
    }
    
    // Application readiness
    $validation = $this->validateForJobApplication($user);
    $stats['ready_for_applications'] = $validation['ready'];
    $stats['validation'] = $validation;
    
    return $stats;
  }

  /**
   * Gets or creates a job_seeker profile for a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return \Drupal\profile\Entity\Profile|null
   *   The job_seeker profile or null if it can't be created.
   */
  public function getJobSeekerProfile(User $user) {
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    
    // Try to load existing profile
    $profiles = $profile_storage->loadByProperties([
      'uid' => $user->id(),
      'type' => 'job_seeker',
    ]);
    
    if (!empty($profiles)) {
      return reset($profiles);
    }
    
    // Create new profile if none exists
    try {
      $profile = $profile_storage->create([
        'type' => 'job_seeker',
        'uid' => $user->id(),
        'status' => 1,
      ]);
      $profile->save();
      
      return $profile;
    } catch (\Exception $e) {
      \Drupal::logger('job_application_automation')->error('Failed to create job_seeker profile for user @uid: @error', [
        '@uid' => $user->id(),
        '@error' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * Calculates profile completeness using profile entity fields.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return int
   *   Profile completeness percentage (0-100).
   */
  public function calculateProfileCompletenessFromProfile(User $user) {
    $profile = $this->getJobSeekerProfile($user);
    if (!$profile) {
      return 0;
    }
    
    $completed_weight = 0;
    $total_weight = array_sum(self::FIELD_WEIGHTS);

    foreach (self::FIELD_WEIGHTS as $field_name => $weight) {
      if ($this->isProfileFieldCompleted($profile, $field_name)) {
        $completed_weight += $weight;
      }
    }

    return round(($completed_weight / $total_weight) * 100);
  }

  /**
   * Checks if a profile field is completed.
   *
   * @param \Drupal\profile\Entity\Profile $profile
   *   The profile entity.
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is completed, FALSE otherwise.
   */
  protected function isProfileFieldCompleted(Profile $profile, $field_name) {
    if (!$profile->hasField($field_name)) {
      return false;
    }

    $field_value = $profile->get($field_name);
    
    if ($field_value->isEmpty()) {
      return false;
    }

    // Special handling for specific field types
    if ($field_name === 'field_resume_file') {
      $file_value = $field_value->first();
      return !empty($file_value) && !empty($file_value->target_id);
    }

    // For other fields, just check if they have a non-empty value
    $first_value = $field_value->first();
    if (!$first_value) {
      return false;
    }

    $value = $first_value->value ?? $first_value->target_id ?? null;
    return !empty($value);
  }

  /**
   * Updates profile completeness using profile entity data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param bool $save
   *   Whether to save the profile after updating.
   *
   * @return int
   *   The calculated completeness percentage.
   */
  public function updateProfileCompletenessFromProfile(User $user, $save = TRUE) {
    $profile = $this->getJobSeekerProfile($user);
    if (!$profile) {
      return 0;
    }
    
    $completeness = $this->calculateProfileCompletenessFromProfile($user);
    
    if ($profile->hasField('field_profile_completeness')) {
      $profile->set('field_profile_completeness', $completeness);
      
      // Also update last profile update timestamp
      if ($profile->hasField('field_last_profile_update')) {
        $profile->set('field_last_profile_update', \Drupal::time()->getRequestTime());
      }
      
      if ($save) {
        $profile->save();
      }
    }

    return $completeness;
  }

  /**
   * Gets missing field recommendations using profile data.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   * @param int $limit
   *   Maximum number of recommendations to return.
   *
   * @return array
   *   Array of missing field recommendations.
   */
  public function getMissingFieldRecommendationsFromProfile(User $user, $limit = 5) {
    $profile = $this->getJobSeekerProfile($user);
    if (!$profile) {
      return [];
    }
    
    $field_labels = [
      'field_resume_file' => $this->t('Upload your resume'),
      'field_work_authorization' => $this->t('Specify work authorization'),
      'field_professional_summary' => $this->t('Add professional summary'),
      'field_skills_summary' => $this->t('List your skills'),
      'field_experience_years' => $this->t('Add years of experience'),
      'field_education_level' => $this->t('Select education level'),
      'field_remote_preference' => $this->t('Set remote work preference'),
      'field_linkedin_url' => $this->t('Add LinkedIn profile'),
      'field_salary_expectation_min' => $this->t('Set minimum salary expectation'),
    ];

    $missing = [];
    foreach ($field_labels as $field_name => $label) {
      if (!$this->isProfileFieldCompleted($profile, $field_name)) {
        $missing[] = $label;
      }
    }

    return array_slice($missing, 0, $limit);
  }

}