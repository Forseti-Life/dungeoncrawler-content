<?php

namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Thrown when a legacy (non-canonical) archived campaign is used illegally.
 *
 * Board cutover decision (HQ 57871ad098): legacy campaigns lacking the
 * canonical `campaign_data.authority` contract are archived, never migrated,
 * and can never be unarchived into the current runtime. Attempting to
 * unarchive such a campaign, or to launch/read game state for an archived
 * campaign, hard-fails with this exception and the machine code
 * `legacy_campaign_archived`. There is no compatibility fallback.
 */
class LegacyCampaignArchivedException extends DungeonCrawlerException {

  /**
   * Machine-readable failure code surfaced to callers/UI/HTTP.
   */
  public const CODE = 'legacy_campaign_archived';

  public function __construct(string $message = 'legacy_campaign_archived', ?\Throwable $previous = NULL) {
    parent::__construct($message, 0, $previous);
  }

  /**
   * Stable machine code for this failure.
   */
  public function failureCode(): string {
    return self::CODE;
  }

}
