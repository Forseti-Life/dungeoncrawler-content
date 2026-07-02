<?php

namespace Drupal\job_application_automation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service for managing job seeker profiles.
 */
class JobSeekerService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a JobSeekerService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user) {
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * Load a job seeker profile by user ID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return object|null
   *   The job seeker profile object or NULL if not found.
   */
  public function loadByUserId($uid) {
    $query = $this->database->select('job_seeker', 'js')
      ->fields('js')
      ->condition('uid', $uid)
      ->execute();
    
    $profile = $query->fetchObject();
    
    if ($profile) {
      // Decode JSON fields
      $profile->skills = $profile->skills ? json_decode($profile->skills, TRUE) : [];
      $profile->target_companies = $profile->target_companies ? json_decode($profile->target_companies, TRUE) : [];
      $profile->preferred_locations = $profile->preferred_locations ? json_decode($profile->preferred_locations, TRUE) : [];
      $profile->job_titles = $profile->job_titles ? json_decode($profile->job_titles, TRUE) : [];
    }
    
    return $profile;
  }

  /**
   * Load a job seeker profile by ID.
   *
   * @param int $id
   *   The job seeker profile ID.
   *
   * @return object|null
   *   The job seeker profile object or NULL if not found.
   */
  public function load($id) {
    $query = $this->database->select('job_seeker', 'js')
      ->fields('js')
      ->condition('id', $id)
      ->execute();
    
    $profile = $query->fetchObject();
    
    if ($profile) {
      // Decode JSON fields
      $profile->skills = $profile->skills ? json_decode($profile->skills, TRUE) : [];
      $profile->target_companies = $profile->target_companies ? json_decode($profile->target_companies, TRUE) : [];
      $profile->preferred_locations = $profile->preferred_locations ? json_decode($profile->preferred_locations, TRUE) : [];
      $profile->job_titles = $profile->job_titles ? json_decode($profile->job_titles, TRUE) : [];
    }
    
    return $profile;
  }

  /**
   * Create a new job seeker profile.
   *
   * @param array $values
   *   An associative array of values for the profile.
   *
   * @return int
   *   The ID of the newly created profile.
   */
  public function create(array $values) {
    $timestamp = \Drupal::time()->getRequestTime();
    
    // Encode JSON fields
    if (isset($values['skills']) && is_array($values['skills'])) {
      $values['skills'] = json_encode($values['skills']);
    }
    if (isset($values['target_companies']) && is_array($values['target_companies'])) {
      $values['target_companies'] = json_encode($values['target_companies']);
    }
    if (isset($values['preferred_locations']) && is_array($values['preferred_locations'])) {
      $values['preferred_locations'] = json_encode($values['preferred_locations']);
    }
    if (isset($values['job_titles']) && is_array($values['job_titles'])) {
      $values['job_titles'] = json_encode($values['job_titles']);
    }
    
    $values['created'] = $timestamp;
    $values['changed'] = $timestamp;
    
    return $this->database->insert('job_seeker')
      ->fields($values)
      ->execute();
  }

  /**
   * Update an existing job seeker profile.
   *
   * @param int $id
   *   The job seeker profile ID.
   * @param array $values
   *   An associative array of values to update.
   *
   * @return int
   *   The number of rows affected.
   */
  public function update($id, array $values) {
    $timestamp = \Drupal::time()->getRequestTime();
    
    // Encode JSON fields
    if (isset($values['skills']) && is_array($values['skills'])) {
      $values['skills'] = json_encode($values['skills']);
    }
    if (isset($values['target_companies']) && is_array($values['target_companies'])) {
      $values['target_companies'] = json_encode($values['target_companies']);
    }
    if (isset($values['preferred_locations']) && is_array($values['preferred_locations'])) {
      $values['preferred_locations'] = json_encode($values['preferred_locations']);
    }
    if (isset($values['job_titles']) && is_array($values['job_titles'])) {
      $values['job_titles'] = json_encode($values['job_titles']);
    }
    
    $values['changed'] = $timestamp;
    
    return $this->database->update('job_seeker')
      ->fields($values)
      ->condition('id', $id)
      ->execute();
  }

  /**
   * Delete a job seeker profile.
   *
   * @param int $id
   *   The job seeker profile ID.
   *
   * @return int
   *   The number of rows affected.
   */
  public function delete($id) {
    return $this->database->delete('job_seeker')
      ->condition('id', $id)
      ->execute();
  }

  /**
   * Check if a user has a job seeker profile.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return bool
   *   TRUE if the user has a profile, FALSE otherwise.
   */
  public function userHasProfile($uid) {
    $count = $this->database->select('job_seeker', 'js')
      ->condition('uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    return $count > 0;
  }

  /**
   * Get the current user's job seeker profile.
   *
   * @return object|null
   *   The job seeker profile object or NULL if not found.
   */
  public function getCurrentUserProfile() {
    return $this->loadByUserId($this->currentUser->id());
  }

}
