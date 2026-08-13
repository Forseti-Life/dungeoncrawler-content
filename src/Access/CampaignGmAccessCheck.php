<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;

/**
 * Checks access for campaign-GM mutation routes.
 *
 * For campaign-scoped routes, GM authority comes from campaign membership
 * role owner_gm/gm (active status). For non-campaign routes, this checker
 * remains admin-gated.
 */
class CampaignGmAccessCheck implements AccessInterface {

  /**
   * Constructs access checker.
   */
  public function __construct(
    protected CampaignAuthorizationService $campaignAuthorizationService,
  ) {}

  /**
   * Checks access for GM operations.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int|null $campaign_id
   *   Campaign ID from route when present.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $campaign_id = NULL) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    if ($account->hasPermission('administer dungeoncrawler content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($campaign_id && $this->campaignAuthorizationService->canManageCampaign((int) $campaign_id, (int) $account->id())) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheTags(['dc_campaign:' . (int) $campaign_id]);
    }

    return AccessResult::forbidden()->cachePerPermissions();
  }

}
