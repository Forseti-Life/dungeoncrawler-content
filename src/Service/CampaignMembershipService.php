<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * The one authority for who belongs to a campaign.
 *
 * A campaign is its member list. Every member is equal: each may play, use
 * the campaign tools, and add or remove members. `dc_campaigns.uid` records
 * who created the campaign; the creator alone may delete it.
 *
 * Refusals are \DomainException with a stable code as the message.
 */
class CampaignMembershipService {

  private const TABLE = 'dc_campaign_members';

  public const CANDIDATE_LIMIT = 25;

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
  ) {}

  public function isMember(int $campaign_id, int $uid): bool {
    if ($campaign_id <= 0 || $uid <= 0) {
      return FALSE;
    }

    return (bool) $this->database->select(self::TABLE, 'm')
      ->fields('m', ['id'])
      ->condition('m.campaign_id', $campaign_id)
      ->condition('m.uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Whether the user created the campaign. Only the creator may delete it.
   */
  public function isCreator(int $campaign_id, int $uid): bool {
    return $uid > 0 && (int) $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('c.id', $campaign_id)
      ->execute()
      ->fetchField() === $uid;
  }

  /**
   * @throws \DomainException
   *   `campaign_member_required` when the user is not a member.
   */
  public function assertMember(int $campaign_id, int $uid): void {
    if (!$this->isMember($campaign_id, $uid)) {
      throw new \DomainException('campaign_member_required');
    }
  }

  /**
   * Campaign ids the user belongs to.
   *
   * @return int[]
   */
  public function campaignIds(int $uid): array {
    return array_map('intval', $this->database->select(self::TABLE, 'm')
      ->fields('m', ['campaign_id'])
      ->condition('m.uid', $uid)
      ->execute()
      ->fetchCol());
  }

  /**
   * Members of a campaign, in the order they joined.
   *
   * @return array<int, array{uid:int, display_name:string, added_by_uid:int|null, added_by_name:string|null, joined:int, character_id:int|null, character_name:string|null}>
   */
  public function members(int $campaign_id): array {
    $query = $this->database->select(self::TABLE, 'm');
    $query->join('users_field_data', 'u', 'u.uid = m.uid');
    $query->leftJoin('users_field_data', 'a', 'a.uid = m.invited_by_uid');
    $query->leftJoin('dc_campaign_characters', 'c', 'c.id = m.default_character_id');
    $query->fields('m', ['uid', 'invited_by_uid', 'default_character_id', 'created']);
    $query->addField('u', 'name', 'display_name');
    $query->addField('a', 'name', 'added_by_name');
    $query->addField('c', 'name', 'character_name');
    $query->condition('m.campaign_id', $campaign_id)
      ->orderBy('m.created')
      ->orderBy('m.id');

    $members = [];
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $members[] = [
        'uid' => (int) $row['uid'],
        'display_name' => (string) $row['display_name'],
        'added_by_uid' => $row['invited_by_uid'] === NULL ? NULL : (int) $row['invited_by_uid'],
        'added_by_name' => $row['added_by_name'],
        'joined' => (int) $row['created'],
        'character_id' => $row['default_character_id'] === NULL ? NULL : (int) $row['default_character_id'],
        'character_name' => $row['character_name'],
      ];
    }

    return $members;
  }

  /**
   * Active site users who are not yet members, filtered by name.
   *
   * @return array<int, array{uid:int, display_name:string}>
   */
  public function candidates(int $campaign_id, string $filter = ''): array {
    $member_uids = $this->database->select(self::TABLE, 'm')
      ->fields('m', ['uid'])
      ->condition('m.campaign_id', $campaign_id);

    $query = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid', 'name'])
      ->condition('u.uid', 0, '>')
      ->condition('u.status', 1)
      ->condition('u.uid', $member_uids, 'NOT IN')
      ->orderBy('u.name')
      ->range(0, self::CANDIDATE_LIMIT);
    $filter = trim($filter);
    if ($filter !== '') {
      $query->condition('u.name', '%' . $this->database->escapeLike($filter) . '%', 'LIKE');
    }

    $candidates = [];
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $candidates[] = ['uid' => (int) $row['uid'], 'display_name' => (string) $row['name']];
    }

    return $candidates;
  }

  /**
   * Enrols the user who created a campaign as its first member.
   */
  public function enrolCreator(int $campaign_id, int $uid): void {
    $this->insert($campaign_id, $uid, NULL);
  }

  /**
   * A member adds another site user.
   *
   * @throws \DomainException
   *   `campaign_member_required`, `campaign_member_user_unknown`,
   *   `campaign_member_exists`.
   */
  public function add(int $campaign_id, int $actor_uid, int $uid): void {
    $this->assertMember($campaign_id, $actor_uid);
    $is_active_user = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid'])
      ->condition('u.uid', $uid)
      ->condition('u.uid', 0, '>')
      ->condition('u.status', 1)
      ->execute()
      ->fetchField();
    if (!$is_active_user) {
      throw new \DomainException('campaign_member_user_unknown');
    }
    if ($this->isMember($campaign_id, $uid)) {
      throw new \DomainException('campaign_member_exists');
    }
    $this->insert($campaign_id, $uid, $actor_uid);
  }

  /**
   * A member removes a member (including themselves).
   *
   * @throws \DomainException
   *   `campaign_member_required`, `campaign_member_unknown`,
   *   `campaign_member_last`.
   */
  public function remove(int $campaign_id, int $actor_uid, int $uid): void {
    $this->assertMember($campaign_id, $actor_uid);
    if (!$this->isMember($campaign_id, $uid)) {
      throw new \DomainException('campaign_member_unknown');
    }
    $count = (int) $this->database->select(self::TABLE, 'm')
      ->condition('m.campaign_id', $campaign_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($count <= 1) {
      throw new \DomainException('campaign_member_last');
    }
    $this->database->delete(self::TABLE)
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $uid)
      ->execute();
  }

  /**
   * The library character this member plays in the campaign, if chosen.
   */
  public function characterId(int $campaign_id, int $uid): ?int {
    $id = $this->database->select(self::TABLE, 'm')
      ->fields('m', ['default_character_id'])
      ->condition('m.campaign_id', $campaign_id)
      ->condition('m.uid', $uid)
      ->execute()
      ->fetchField();

    return $id ? (int) $id : NULL;
  }

  /**
   * Records the library character a member plays in the campaign.
   *
   * @throws \DomainException
   *   `campaign_member_required` when the user is not a member.
   */
  public function setCharacter(int $campaign_id, int $uid, int $character_id): void {
    $this->assertMember($campaign_id, $uid);
    $this->database->update(self::TABLE)
      ->fields(['default_character_id' => $character_id, 'changed' => $this->time->getRequestTime()])
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $uid)
      ->execute();
  }

  private function insert(int $campaign_id, int $uid, ?int $added_by_uid): void {
    $now = $this->time->getRequestTime();
    $this->database->insert(self::TABLE)
      ->fields([
        'campaign_id' => $campaign_id,
        'uid' => $uid,
        'invited_by_uid' => $added_by_uid,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
  }

}
