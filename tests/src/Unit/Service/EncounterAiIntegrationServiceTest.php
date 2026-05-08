<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
use Drupal\dungeoncrawler_content\Service\EncounterAiProviderInterface;

/**
 * Tests for EncounterAiIntegrationService.
 *
 * @group dungeoncrawler_content
 * @group combat
 * @group ai
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService
 */
class EncounterAiIntegrationServiceTest extends UnitTestCase {

  protected EncounterAiProviderInterface $provider;

  protected TimeInterface $time;

  protected LoggerChannelFactoryInterface $loggerFactory;

  protected EncounterAiIntegrationService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->provider = $this->createMock(EncounterAiProviderInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);
    $this->time->method('getCurrentTime')->willReturn(1700000000);

    $this->service = new EncounterAiIntegrationService(
      $this->provider,
      $this->time,
      $this->loggerFactory
    );
  }

  /**
   * @covers ::buildEncounterContext
   */
  public function testBuildEncounterContextThrowsWhenEncounterMissing(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Encounter context requires encounter snapshot.');

    $this->service->buildEncounterContext(0, 10, NULL);
  }

  /**
   * @covers ::buildEncounterContext
   */
  public function testBuildEncounterContextReturnsNormalizedEnvelope(): void {
    $encounter = [
      'status' => 'active',
      'current_round' => 3,
      'turn_index' => 1,
      'participants' => [
        ['entity_ref' => 'pc-1', 'team' => 'player'],
        ['entity_ref' => 'npc-2', 'team' => 'npc'],
      ],
    ];

    $context = $this->service->buildEncounterContext(77, 501, $encounter);

    $this->assertSame(77, $context['campaign_id']);
    $this->assertSame(501, $context['encounter_id']);
    $this->assertSame('active', $context['status']);
    $this->assertSame(3, $context['current_round']);
    $this->assertSame('npc-2', $context['current_actor']['entity_ref']);
    $this->assertContains('strike', $context['allowed_actions']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationReturnsValidForNpcStrike(): void {
    $context = [
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['strike', 'end_turn'],
    ];

    $recommendation = [
      'actor_instance_id' => 'npc-1',
      'recommended_action' => [
        'type' => 'strike',
        'action_cost' => 1,
      ],
    ];

    $validation = $this->service->validateRecommendation($recommendation, $context);

    $this->assertTrue($validation['valid']);
    $this->assertSame([], $validation['errors']);
  }

  /**
   * @covers ::validateRecommendation
   */
  public function testValidateRecommendationReturnsErrorsForInvalidActorAndCost(): void {
    $context = [
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'player',
        'actions_remaining' => 1,
      ],
      'allowed_actions' => ['strike'],
    ];

    $recommendation = [
      'actor_instance_id' => 'npc-2',
      'recommended_action' => [
        'type' => 'unsupported_action',
        'action_cost' => 3,
      ],
    ];

    $validation = $this->service->validateRecommendation($recommendation, $context);

    $this->assertFalse($validation['valid']);
    $this->assertNotEmpty($validation['errors']);
    $this->assertContains('actor_instance_id must match active turn actor.', $validation['errors']);
    $this->assertContains('active turn actor is a player; NPC recommendation is not applicable.', $validation['errors']);
  }

  /**
   * @covers ::requestNpcActionRecommendation
   */
  public function testRequestNpcActionRecommendationWrapsProviderResponse(): void {
    $context = [
      'encounter_id' => 901,
      'campaign_id' => 0,
      'current_actor' => [
        'entity_ref' => 'npc-1',
        'team' => 'npc',
        'actions_remaining' => 3,
      ],
      'allowed_actions' => ['strike'],
    ];

    $this->provider->method('getProviderName')->willReturn('stub');
    $this->provider->method('recommendNpcAction')->willReturn([
      'actor_instance_id' => 'npc-1',
      'recommended_action' => [
        'type' => 'strike',
        'action_cost' => 1,
      ],
    ]);

    $response = $this->service->requestNpcActionRecommendation($context);

    $this->assertTrue($response['success']);
    $this->assertSame('stub', $response['provider']);
    $this->assertTrue($response['validation']['valid']);
    $this->assertSame(1700000000, $response['requested_at']);
  }

  /**
   * @covers ::requestEncounterNarration
   */
  public function testRequestEncounterNarrationWrapsProviderNarration(): void {
    $this->provider->method('getProviderName')->willReturn('stub');
    $this->provider->method('generateEncounterNarration')->willReturn([
      'narration' => 'A measured tactical beat.',
    ]);

    $response = $this->service->requestEncounterNarration(['encounter_id' => 1001]);

    $this->assertTrue($response['success']);
    $this->assertSame('stub', $response['provider']);
    $this->assertSame('A measured tactical beat.', $response['narration']['narration']);
    $this->assertSame(1700000000, $response['requested_at']);
  }

}
