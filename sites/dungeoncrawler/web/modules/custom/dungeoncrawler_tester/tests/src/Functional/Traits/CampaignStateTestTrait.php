<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Traits;

use Drupal\Core\Database\Connection;
use Drupal\Core\Uuid\UuidInterface;
use Drupal\user\UserInterface;

/**
 * Shared helpers for campaign-state functional tests.
 */
trait CampaignStateTestTrait {

  /**
   * Create a campaign owned by the given user and return campaign ID.
   */
  protected function createCampaignForUser(UserInterface $user, string $name = 'Test Campaign'): int {
    $campaignId = $this->getDatabase()->insert('dc_campaigns')
      ->fields([
        'uuid' => $this->getUuid()->generate(),
        'uid' => $user->id(),
        'name' => $name,
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $user->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    return (int) $campaignId;
  }

  /**
   * Issue a JSON request and return decoded response.
   */
  protected function requestJson(string $method, string $path, ?array $payload = NULL): array {
    $body = $payload !== NULL ? json_encode($payload) : NULL;
    return $this->requestRaw($method, $path, $body ?? '');
  }

  /**
   * Issue a JSON request with raw body and return decoded response.
   */
  protected function requestRaw(string $method, string $path, string $body): array {
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $body
    );

    $content = $this->getSession()->getPage()->getContent();
    return json_decode($content, TRUE) ?? [];
  }

  /**
   * Database connection.
   */
  private function getDatabase(): Connection {
    return $this->container->get('database');
  }

  /**
   * UUID service.
   */
  private function getUuid(): UuidInterface {
    return $this->container->get('uuid');
  }

}
