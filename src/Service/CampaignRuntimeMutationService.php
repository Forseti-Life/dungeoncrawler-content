<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Typed mutation lane for campaign-scoped runtime state writes.
 */
class CampaignRuntimeMutationService {

  protected LoggerInterface $logger;

  public function __construct(
    protected readonly CampaignRuntimeStateStore $campaignRuntimeStateStore,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Persist campaign runtime state through the campaign-state mutation lane.
   */
  public function persistGameState(int $campaign_id, array $game_state, ?string $active_room_id = NULL): bool {
    if ($campaign_id <= 0) {
      throw new \RuntimeException('Campaign runtime mutation contract violation: campaign_id must be > 0.');
    }

    $persisted = $this->campaignRuntimeStateStore->persistGameState($campaign_id, $game_state, $active_room_id);
    if (!$persisted) {
      throw new \RuntimeException(sprintf(
        'Campaign runtime mutation contract violation: failed to persist game_state for campaign %d.',
        $campaign_id
      ));
    }

    $this->logger->debug('Campaign runtime state persisted for campaign @id.', [
      '@id' => $campaign_id,
    ]);
    return TRUE;
  }

}

