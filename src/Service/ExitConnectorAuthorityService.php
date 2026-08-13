<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Central authority entrypoint for canonical and campaign connector lifecycle.
 *
 * This service is the consolidation layer for connector ownership. It currently
 * delegates to ConnectorDefinitionService, but callers should depend on this
 * service so connector lifecycle policy can be centralized without rewriting
 * every caller again later.
 */
class ExitConnectorAuthorityService {

  public function __construct(
    protected readonly ConnectorDefinitionService $connectorDefinitionService,
  ) {}

  /**
   * Save one canonical connector definition.
   */
  public function saveCanonicalConnector(array $data): string {
    return $this->connectorDefinitionService->saveCanonicalConnector($data);
  }

  /**
   * Save one campaign connector instance.
   */
  public function saveCampaignConnector(int $campaign_id, array $data): string {
    return $this->connectorDefinitionService->saveCampaignConnector($campaign_id, $data);
  }

  /**
   * Load canonical connectors for one dungeon.
   *
   * @return array<int, array<string, mixed>>
   *   Canonical connector rows.
   */
  public function loadCanonicalConnectorsForDungeon(string $dungeon_id): array {
    return $this->connectorDefinitionService->loadCanonicalConnectorsForDungeon($dungeon_id);
  }

  /**
   * Load canonical connectors that touch one room.
   *
   * @return array<int, array<string, mixed>>
   *   Canonical connector rows.
   */
  public function loadCanonicalConnectorsForRoom(string $room_id, string $dungeon_id = ''): array {
    return $this->connectorDefinitionService->loadCanonicalConnectorsForRoom($room_id, $dungeon_id);
  }

}
