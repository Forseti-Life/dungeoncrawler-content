<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves campaign-scoped capabilities for users.
 *
 * Every capability follows from membership: CampaignMembershipService decides
 * who belongs to a campaign, and all members are equal.
 */
class CampaignAuthorizationService {

  public function __construct(
    protected Connection $database,
    protected CampaignMembershipService $membership,
  ) {}

  /**
   * Returns TRUE when a user can view/interact with a campaign.
   */
  public function canAccessCampaign(int $campaign_id, int $uid): bool {
    return $this->membership->isMember($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can use the campaign's management tools.
   */
  public function canManageCampaign(int $campaign_id, int $uid): bool {
    return $this->membership->isMember($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can use a specific campaign character principal.
   */
  public function canPlayAsCharacter(int $campaign_id, int $uid, int $character_id): bool {
    if ($character_id <= 0 || !$this->membership->isMember($campaign_id, $uid)) {
      return FALSE;
    }

    return (bool) $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.id', $character_id)
      ->condition('cc.is_active', 1)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns playable campaign principals visible to user for a given mode.
   *
   * Player mode lists the member's own characters; Game Master mode lists
   * every player character in the campaign.
   *
   * @return array<int, array<string, mixed>>
   *   Principal rows with character_id, uid, label, instance_id, and kind.
   */
  public function listPlayablePrincipals(int $campaign_id, int $uid, string $mode = 'player'): array {
    if (!$this->membership->isMember($campaign_id, $uid)) {
      return [];
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'uid', 'name', 'instance_id', 'type'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.is_active', 1)
      ->condition('cc.type', 'pc');
    if (strtolower(trim($mode)) !== 'gm') {
      $query->condition('cc.uid', $uid);
    }

    $principals = [];
    foreach ($query->orderBy('cc.id', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $character_id = (int) $row['id'];
      $principals[] = [
        'character_id' => $character_id,
        'uid' => (int) $row['uid'],
        'label' => trim((string) $row['name']) !== '' ? (string) $row['name'] : ('Character ' . $character_id),
        'instance_id' => (string) $row['instance_id'],
        'kind' => (string) $row['type'],
      ];
    }

    return $principals;
  }

  /**
   * Build compact campaign access payload for UI bootstrap.
   *
   * @return array<string, mixed>
   *   Campaign access payload.
   */
  public function buildCampaignAccessContext(int $campaign_id, int $uid): array {
    $is_member = $this->membership->isMember($campaign_id, $uid);
    $stored_mode = $is_member ? $this->loadStoredModePreference($campaign_id, $uid) : '';

    return [
      'campaign_id' => $campaign_id,
      'is_member' => $is_member,
      'can_use_player_mode' => $is_member,
      'can_use_gm_mode' => $is_member,
      'default_mode' => 'player',
      'current_mode' => $stored_mode !== '' ? $stored_mode : 'player',
      'playable_principals' => $this->listPlayablePrincipals($campaign_id, $uid, 'player'),
      'gm_principals' => $this->listPlayablePrincipals($campaign_id, $uid, 'gm'),
    ];
  }

  /**
   * Load persisted mode preference from campaign_data settings.
   */
  private function loadStoredModePreference(int $campaign_id, int $uid): string {
    $campaign_data_raw = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data'])
      ->condition('c.id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $campaign_data = is_string($campaign_data_raw) ? json_decode($campaign_data_raw, TRUE) : NULL;
    $mode = strtolower(trim((string) ($campaign_data['settings']['user_mode_preferences'][(string) $uid] ?? '')));

    return in_array($mode, ['player', 'gm'], TRUE) ? $mode : '';
  }

}
