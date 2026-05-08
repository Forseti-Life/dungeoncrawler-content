<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for GM-only library mutation routes.
 *
 * GM-level library operations (creature import/override) require the
 * 'administer dungeoncrawler content' permission. This is a global library
 * permission distinct from per-campaign ownership (_campaign_access).
 *
 * Use _campaign_gm_access: 'TRUE' on routes that mutate shared content
 * libraries (creature import, creature override, etc.).
 */
class CampaignGmAccessCheck implements AccessInterface {

  /**
   * Checks access for GM library mutation operations.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    if ($account->hasPermission('administer dungeoncrawler content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return AccessResult::forbidden()->cachePerPermissions();
  }

}
