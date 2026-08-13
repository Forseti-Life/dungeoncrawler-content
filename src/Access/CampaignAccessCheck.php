<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;

/**
 * Checks access for campaign operations using campaign-scoped membership.
 *
 * ## Schema Conformance (DCC-0257)
 *
 * This access check conforms to the dc_campaigns table schema defined in
 * dungeoncrawler_content.install and the campaign.schema.json specification.
 *
 * ### Table Reference
 * - **dc_campaigns**: Campaign headers and lifecycle state
 *
 * ### Hot Column Usage
 * This access check defers to CampaignAuthorizationService, which uses:
 * - **dc_campaign_members(campaign_id, uid)** when available
 * - **dc_campaigns.uid** owner fallback during migration
 * - **dc_campaign_characters.uid** compatibility fallback during migration
 *
 * ### JSON Column Structure
 * The `campaign_data` JSON column contains the full campaign state payload
 * conforming to campaign.schema.json, but is NOT queried by this access check
 * for performance reasons. Ownership verification uses the indexed `uid` column.
 *
 * @see campaign.schema.json
 * @see dungeoncrawler_content_schema()
 */
class CampaignAccessCheck implements AccessInterface {

  /**
   * Campaign authorization policy service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService
   */
  protected CampaignAuthorizationService $campaignAuthorizationService;

  /**
   * Constructs a CampaignAccessCheck object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService $campaign_authorization_service
   *   Campaign authorization policy service.
   */
  public function __construct(CampaignAuthorizationService $campaign_authorization_service) {
    $this->campaignAuthorizationService = $campaign_authorization_service;
  }

  /**
   * Checks access to campaign based on ownership and permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $campaign_id
   *   The campaign ID from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $campaign_id = NULL) {
    // Anonymous users are never allowed to access campaign pages.
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Admin can access any campaign.
    if ($account->hasPermission('administer dungeoncrawler content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Campaign ID is required.
    if (!$campaign_id) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    if ($this->campaignAuthorizationService->canAccessCampaign((int) $campaign_id, (int) $account->id())) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheTags(['dc_campaign:' . $campaign_id]);
    }

    return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
  }

}
