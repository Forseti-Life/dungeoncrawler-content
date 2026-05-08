<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\AbilityScoreApiController;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fixed-background boost handling in the ability score API controller.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\AbilityScoreApiController
 */
class AbilityScoreApiControllerTest extends UnitTestCase {

  /**
   * Tests available boosts exclude the fixed background boost.
   *
   * @covers ::getAvailableBoosts
   */
  public function testGetAvailableBoostsUsesFixedBackgroundLimit(): void {
    $controller = new AbilityScoreApiController($this->buildTrackerStub());
    $request = new Request([
      'character_data' => json_encode([
        'background' => 'acolyte',
        'background_boosts' => [],
      ]),
      'selected_boosts' => json_encode([]),
    ]);

    $response = $controller->getAvailableBoosts('background', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(1, $payload['max_selections']);
    $this->assertContains('wisdom', $payload['disabled']);
    $this->assertNotContains('wisdom', $payload['available']);
  }

  /**
   * Tests selecting the fixed background boost is rejected.
   *
   * @covers ::validateBoost
   */
  public function testValidateBoostRejectsFixedBackgroundAbility(): void {
    $controller = new AbilityScoreApiController($this->buildTrackerStub());
    $request = Request::create(
      '/api/characters/ability-scores/validate-boost',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'ability' => 'wis',
        'step' => 'background',
        'selected_boosts' => [],
        'current_character_data' => [
          'background' => 'acolyte',
        ],
      ])
    );

    $response = $controller->validateBoost($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertFalse($payload['valid']);
    $this->assertSame('Cannot apply two boosts to the same ability score from a single background', $payload['error']);
  }

  /**
   * Tests human ancestry exposes two free ancestry boosts.
   *
   * @covers ::getAvailableBoosts
   */
  public function testGetAvailableBoostsUsesHumanAncestryLimit(): void {
    $controller = new AbilityScoreApiController($this->buildTrackerStub());
    $request = new Request([
      'character_data' => json_encode([
        'ancestry' => 'human',
        'heritage' => 'versatile',
        'ancestry_boosts' => [],
      ]),
      'selected_boosts' => json_encode([]),
    ]);

    $response = $controller->getAvailableBoosts('ancestry', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(2, $payload['max_selections']);
    $this->assertSame([], $payload['disabled']);
    $this->assertContains('strength', $payload['available']);
  }

  /**
   * Tests available boosts accept JSON POST payloads.
   *
   * @covers ::getAvailableBoosts
   */
  public function testGetAvailableBoostsAcceptsJsonPostPayload(): void {
    $controller = new AbilityScoreApiController($this->buildTrackerStub());
    $request = Request::create(
      '/api/characters/ability-scores/available-boosts/ancestry',
      'POST',
      [],
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode([
        'character_data' => [
          'ancestry' => 'human',
          'heritage' => 'versatile',
          'ancestry_boosts' => [],
        ],
        'selected_boosts' => [],
      ])
    );

    $response = $controller->getAvailableBoosts('ancestry', $request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(2, $payload['max_selections']);
    $this->assertContains('charisma', $payload['available']);
  }

  /**
   * Tests fixed ancestry boosts are blocked from free ancestry selection.
   *
   * @covers ::validateBoost
   */
  public function testValidateBoostRejectsFixedAncestryAbility(): void {
    $controller = new AbilityScoreApiController($this->buildTrackerStub());
    $request = Request::create(
      '/api/characters/ability-scores/validate-boost',
      'POST',
      [],
      [],
      [],
      [],
      json_encode([
        'ability' => 'wis',
        'step' => 'ancestry',
        'selected_boosts' => [],
        'current_character_data' => [
          'ancestry' => 'dwarf',
        ],
      ])
    );

    $response = $controller->validateBoost($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertFalse($payload['valid']);
    $this->assertSame('Cannot apply a free ancestry boost to an ability that already receives an ancestry boost.', $payload['error']);
  }

  /**
   * Builds a lightweight tracker stub for controller tests.
   */
  private function buildTrackerStub(): AbilityScoreTracker {
    return new class extends AbilityScoreTracker {

      /**
       * {@inheritdoc}
       */
      public function __construct() {}

      /**
       * {@inheritdoc}
       */
      public function normalizeAbilityKey(string $key): ?string {
        $key = strtolower(trim($key));
        $map = [
          'str' => 'strength',
          'strength' => 'strength',
          'dex' => 'dexterity',
          'dexterity' => 'dexterity',
          'con' => 'constitution',
          'constitution' => 'constitution',
          'int' => 'intelligence',
          'intelligence' => 'intelligence',
          'wis' => 'wisdom',
          'wisdom' => 'wisdom',
          'cha' => 'charisma',
          'charisma' => 'charisma',
        ];

        return $map[$key] ?? NULL;
      }

      /**
       * {@inheritdoc}
       */
      public function calculateAbilityScores(array $character_data): array {
        return [
          'scores' => [],
          'modifiers' => [],
          'validation' => [],
        ];
      }

    };
  }

}
