<?php

namespace Drupal\dungeoncrawler_content\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dungeoncrawler_content\Service\EncounterPhaseHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Background pre-seeding of directly linked rooms after navigation.
 *
 * @QueueWorker(
 *   id = "dungeoncrawler_content.navigation_neighbor_preseed",
 *   title = @Translation("Dungeoncrawler navigation neighbor preseed"),
 *   cron = {"time" = 30}
 * )
 */
final class NavigationNeighborPreseedQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EncounterPhaseHandler $encounterPhaseHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dungeoncrawler_content.encounter_phase_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Linked-room preseed queue worker requires array payload.');
    }

    $campaign_id = (int) ($data['campaign_id'] ?? 0);
    $dungeon_id = trim((string) ($data['dungeon_id'] ?? ''));
    $anchor_room_id = trim((string) ($data['anchor_room_id'] ?? ''));

    $this->encounterPhaseHandler->processLinkedRoomPreseedQueueItem($campaign_id, $dungeon_id, $anchor_room_id);
  }

}

