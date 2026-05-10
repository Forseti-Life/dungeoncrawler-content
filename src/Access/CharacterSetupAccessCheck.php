<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Allows character setup access for creators or owners editing a draft.
 */
class CharacterSetupAccessCheck implements AccessInterface {

  /**
   * Constructs the access check.
   */
  public function __construct(
    protected Connection $database,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Checks setup access from the current request context.
   */
  public function access(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    if ($account->hasPermission('administer dungeoncrawler content')
      || $account->hasPermission('create dungeoncrawler characters')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $request = $this->requestStack->getCurrentRequest();
    $character_id = $request?->query->get('character_id') ?? $request?->request->get('character_id');
    if (!$character_id || !is_numeric((string) $character_id)) {
      return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
    }

    $character = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['uid'])
      ->condition('c.id', (int) $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$character) {
      return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
    }

    if ((int) $character['uid'] === (int) $account->id()) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheTags(['dungeoncrawler_character:' . (int) $character_id]);
    }

    return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
  }

}
