<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Access checks for campaign and character scoped room chat operations.
 */
final class RoomChatAccessGuard {

  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Check if current user has access to campaign.
   */
  public function hasCampaignAccess(int $campaign_id): bool {
    if ($this->currentUserCanManageCampaign($campaign_id)) {
      return TRUE;
    }

    $uid = $this->currentUser->id();
    $user_in_campaign = $this->database->select('dc_campaign_characters', 'c')
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $user_in_campaign > 0;
  }

  /**
   * Determine whether current user may access one specific character.
   */
  public function hasCharacterAccess(int $campaign_id, int $character_id): bool {
    if ($character_id <= 0) {
      return FALSE;
    }

    if ($this->currentUserCanManageCampaign($campaign_id)) {
      return TRUE;
    }

    $uid = $this->currentUser->id();
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $uid);

    $character_match = $query->orConditionGroup()
      ->condition('id', $character_id)
      ->condition('character_id', $character_id);

    $owned_character = $query
      ->condition($character_match)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $owned_character > 0;
  }

  /**
   * Determine whether current user can manage the campaign.
   */
  private function currentUserCanManageCampaign(int $campaign_id): bool {
    $uid = $this->currentUser->id();
    $account = \Drupal\user\Entity\User::load($uid);

    if ($account && $account->hasPermission('administer dungeoncrawler')) {
      return TRUE;
    }

    $owner_uid = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();

    return $owner_uid && $owner_uid == $uid;
  }

}
