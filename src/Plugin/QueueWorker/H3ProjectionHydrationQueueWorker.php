<?php

namespace Drupal\dungeoncrawler_content\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dungeoncrawler_content\Service\H3ProjectionQueueService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Background campaign H3 projection hydration worker.
 *
 * @QueueWorker(
 *   id = "dungeoncrawler_content.h3_projection_hydration",
 *   title = @Translation("Dungeoncrawler H3 projection hydration"),
 *   cron = {"time" = 45}
 * )
 */
final class H3ProjectionHydrationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly H3ProjectionQueueService $projectionQueueService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dungeoncrawler_content.h3_projection_queue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_array($data)) {
      throw new \InvalidArgumentException('H3 projection hydration queue worker requires array payload.');
    }
    $this->projectionQueueService->processQueuedHydrationItem($data);
  }

}
