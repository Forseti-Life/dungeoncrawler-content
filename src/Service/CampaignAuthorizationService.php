<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves campaign-scoped capabilities for users.
 */
class CampaignAuthorizationService {

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Returns TRUE when a user can view/interact with a campaign.
   */
  public function canAccessCampaign(int $campaign_id, int $uid): bool {
    if ($campaign_id <= 0 || $uid <= 0) {
      return FALSE;
    }

    $membership = $this->getMembership($campaign_id, $uid);
    if ($membership !== NULL) {
      return in_array($membership['status'], ['active', 'invited'], TRUE);
    }

    return $this->isCampaignOwner($campaign_id, $uid) || $this->hasCampaignCharacterBinding($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can view campaign surfaces.
   */
  public function canViewCampaign(int $campaign_id, int $uid): bool {
    return $this->canAccessCampaign($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can perform campaign-GM actions.
   */
  public function canManageCampaign(int $campaign_id, int $uid): bool {
    if ($campaign_id <= 0 || $uid <= 0) {
      return FALSE;
    }

    $membership = $this->getMembership($campaign_id, $uid);
    if ($membership !== NULL) {
      if ($membership['status'] !== 'active') {
        return FALSE;
      }
      return in_array($membership['role'], ['owner_gm', 'gm'], TRUE);
    }

    return $this->isCampaignOwner($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can invite/manage campaign members.
   */
  public function canInviteMembers(int $campaign_id, int $uid): bool {
    return $this->canManageCampaign($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can operate GM mode for the campaign.
   */
  public function canUseGmMode(int $campaign_id, int $uid): bool {
    return $this->canManageCampaign($campaign_id, $uid);
  }

  /**
   * Returns TRUE when a user can operate player mode for the campaign.
   */
  public function canUsePlayerMode(int $campaign_id, int $uid): bool {
    if ($campaign_id <= 0 || $uid <= 0) {
      return FALSE;
    }
    if (!$this->canAccessCampaign($campaign_id, $uid)) {
      return FALSE;
    }

    return $this->canManageCampaign($campaign_id, $uid)
      || $this->listPlayablePrincipals($campaign_id, $uid, 'player') !== [];
  }

  /**
   * Returns TRUE when a user can use a specific campaign character principal.
   */
  public function canPlayAsCharacter(int $campaign_id, int $uid, int $character_id): bool {
    if ($campaign_id <= 0 || $uid <= 0 || $character_id <= 0) {
      return FALSE;
    }
    if (!$this->canAccessCampaign($campaign_id, $uid)) {
      return FALSE;
    }
    if (!$this->database->schema()->tableExists('dc_campaign_characters')) {
      return FALSE;
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.id', $character_id)
      ->condition('cc.is_active', 1);
    if (!$this->canManageCampaign($campaign_id, $uid)) {
      $query->condition('cc.uid', $uid);
    }

    $row_id = $query->range(0, 1)->execute()->fetchField();
    return is_numeric($row_id) && (int) $row_id > 0;
  }

  /**
   * Returns playable campaign principals visible to user for a given mode.
   *
   * @return array<int, array<string, mixed>>
   *   Principal rows with character_id, uid, name, instance_id, and type.
   */
  public function listPlayablePrincipals(int $campaign_id, int $uid, string $mode = 'player'): array {
    if ($campaign_id <= 0 || $uid <= 0) {
      return [];
    }
    if (!$this->canAccessCampaign($campaign_id, $uid)) {
      return [];
    }
    if (!$this->database->schema()->tableExists('dc_campaign_characters')) {
      return [];
    }

    $normalized_mode = strtolower(trim($mode));
    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'uid', 'name', 'instance_id', 'type'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.is_active', 1)
      ->condition('cc.type', 'pc');

    if ($normalized_mode !== 'gm' || !$this->canManageCampaign($campaign_id, $uid)) {
      $query->condition('cc.uid', $uid);
    }

    $rows = $query->orderBy('cc.id', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $principals = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $character_id = isset($row['id']) ? (int) $row['id'] : 0;
      if ($character_id <= 0) {
        continue;
      }
      $principals[] = [
        'character_id' => $character_id,
        'uid' => isset($row['uid']) ? (int) $row['uid'] : 0,
        'label' => trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : ('Character ' . $character_id),
        'instance_id' => (string) ($row['instance_id'] ?? ''),
        'kind' => (string) ($row['type'] ?? 'pc'),
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
    $membership = $this->getMembership($campaign_id, $uid);
    $owner_uid = $this->loadCampaignOwnerUid($campaign_id);
    $is_owner = $owner_uid > 0 && $owner_uid === $uid;
    $role = (string) ($membership['role'] ?? ($is_owner ? 'owner_gm' : 'player'));
    $status = (string) ($membership['status'] ?? ($this->canAccessCampaign($campaign_id, $uid) ? 'active' : 'none'));

    $can_use_gm_mode = $this->canUseGmMode($campaign_id, $uid);
    $can_use_player_mode = $this->canUsePlayerMode($campaign_id, $uid);
    $stored_mode = $this->loadStoredModePreference($campaign_id, $uid);
    $default_mode = $can_use_gm_mode ? 'gm' : 'player';
    $current_mode = in_array($stored_mode, ['player', 'gm'], TRUE) ? $stored_mode : $default_mode;
    if ($current_mode === 'gm' && !$can_use_gm_mode) {
      $current_mode = 'player';
    }
    if ($current_mode === 'player' && !$can_use_player_mode && $can_use_gm_mode) {
      $current_mode = 'gm';
    }

    return [
      'campaign_id' => $campaign_id,
      'membership_role' => $role,
      'membership_status' => $status,
      'can_use_player_mode' => $can_use_player_mode,
      'can_use_gm_mode' => $can_use_gm_mode,
      'default_mode' => $default_mode,
      'current_mode' => $current_mode,
      'playable_principals' => $this->listPlayablePrincipals($campaign_id, $uid, 'player'),
      'gm_principals' => $this->listPlayablePrincipals($campaign_id, $uid, 'gm'),
    ];
  }

  /**
   * Returns active membership payload for a user/campaign if present.
   *
   * @return array{role:string,status:string,default_character_id:int|null}|null
   *   Membership payload or NULL.
   */
  public function getMembership(int $campaign_id, int $uid): ?array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_members')) {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_members', 'm')
      ->fields('m', ['role', 'status', 'default_character_id'])
      ->condition('m.campaign_id', $campaign_id)
      ->condition('m.uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }

    $role = strtolower(trim((string) ($row['role'] ?? 'player')));
    if (!in_array($role, ['owner_gm', 'gm', 'player'], TRUE)) {
      $role = 'player';
    }
    $status = strtolower(trim((string) ($row['status'] ?? 'active')));
    if (!in_array($status, ['active', 'invited', 'revoked'], TRUE)) {
      $status = 'active';
    }

    return [
      'role' => $role,
      'status' => $status,
      'default_character_id' => isset($row['default_character_id']) && is_numeric($row['default_character_id'])
        ? (int) $row['default_character_id']
        : NULL,
    ];
  }

  /**
   * Returns TRUE when the uid is campaign owner.
   */
  private function isCampaignOwner(int $campaign_id, int $uid): bool {
    $owner_uid = $this->loadCampaignOwnerUid($campaign_id);
    return $owner_uid > 0 && $owner_uid === $uid;
  }

  /**
   * Compatibility fallback while membership backfill/cutover rolls out.
   */
  private function hasCampaignCharacterBinding(int $campaign_id, int $uid): bool {
    $schema = $this->database->schema();
    if (!$schema->tableExists('dc_campaign_characters')) {
      return FALSE;
    }

    $row_id = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('cc.campaign_id', $campaign_id)
      ->condition('cc.uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_numeric($row_id) && (int) $row_id > 0;
  }

  /**
   * Load campaign owner UID from campaign header.
   */
  private function loadCampaignOwnerUid(int $campaign_id): int {
    if ($campaign_id <= 0) {
      return 0;
    }
    $owner_uid = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('c.id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return is_numeric($owner_uid) ? (int) $owner_uid : 0;
  }

  /**
   * Load persisted mode preference from campaign_data settings.
   */
  private function loadStoredModePreference(int $campaign_id, int $uid): string {
    if ($campaign_id <= 0 || $uid <= 0) {
      return '';
    }
    $campaign_data_raw = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['campaign_data'])
      ->condition('c.id', $campaign_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!is_string($campaign_data_raw) || trim($campaign_data_raw) === '') {
      return '';
    }
    $campaign_data = json_decode($campaign_data_raw, TRUE);
    if (!is_array($campaign_data)) {
      return '';
    }
    $preferences = is_array($campaign_data['settings']['user_mode_preferences'] ?? NULL)
      ? $campaign_data['settings']['user_mode_preferences']
      : [];
    $mode = strtolower(trim((string) ($preferences[(string) $uid] ?? '')));
    return in_array($mode, ['player', 'gm'], TRUE) ? $mode : '';
  }

}

