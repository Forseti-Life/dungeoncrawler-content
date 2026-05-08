<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for character operations based on ownership.
 */
class CharacterAccessCheck implements AccessInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a CharacterAccessCheck object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Checks access to character based on ownership and permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $character_id
   *   The character ID from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $character_id = NULL) {
    // Anonymous users are never allowed to access character pages.
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Admin can access any character.
    if ($account->hasPermission('administer dungeoncrawler content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Character ID is required.
    if (!$character_id) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Load character from unified dc_campaign_characters table.
    // This table stores both library characters (campaign_id = 0) and
    // campaign-scoped instances (campaign_id > 0).
    // Uses hot columns for high-frequency fields and JSON for flexible data.
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['uid'])
      ->condition('c.id', $character_id)
      ->execute();
    
    $character = $query->fetchAssoc();
    
    if (!$character) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Check if user owns the character.
    if ($character['uid'] == $account->id()) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheTags(['dungeoncrawler_character:' . $character_id]);
    }

    return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
  }

}
