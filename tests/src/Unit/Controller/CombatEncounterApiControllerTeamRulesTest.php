<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\dungeoncrawler_content\Controller\CombatEncounterApiController;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Phase 2: the encounter API controller is a thin presentation/read consumer.
 *
 * It owns no encounter assembly and no raw store; every current-state read is
 * delegated to the canonical owner EncounterStateService, and archived/legacy
 * campaigns hard-fail with HTTP 409. The encounter-map-v1 presentation contract
 * itself is covered by EncounterStateServiceTest (the owner).
 *
 * @group dungeoncrawler_content
 * @group controller
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CombatEncounterApiController
 */
class CombatEncounterApiControllerTeamRulesTest extends UnitTestCase {

  protected function buildController(EncounterStateService $encounter_state): CombatEncounterApiController {
    return new CombatEncounterApiController($encounter_state);
  }

  /**
   * @covers ::get
   */
  public function testGetDelegatesEncounterReadToCanonicalOwner(): void {
    $owner_state = [
      'encounter_id' => 101,
      'status' => 'active',
      'current_round' => 2,
      'encounter_presentation' => ['schema_version' => 'encounter-map-v1'],
    ];

    $encounter_state = $this->createMock(EncounterStateService::class);
    $encounter_state->expects($this->once())
      ->method('tryGetState')
      ->with(101)
      ->willReturn($owner_state);

    $controller = $this->buildController($encounter_state);
    $request = Request::create('/api/combat/encounter/get', 'POST', [], [], [], [], json_encode(['encounterId' => 101]));
    $response = $controller->get($request);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame($owner_state, json_decode((string) $response->getContent(), TRUE));
  }

  /**
   * @covers ::get
   */
  public function testGetReturnsNotFoundWhenOwnerHasNoState(): void {
    $encounter_state = $this->createMock(EncounterStateService::class);
    $encounter_state->method('tryGetState')->willReturn(NULL);

    $controller = $this->buildController($encounter_state);
    $request = Request::create('/api/combat/encounter/get', 'POST', [], [], [], [], json_encode(['encounterId' => 404]));
    $response = $controller->get($request);

    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * @covers ::get
   */
  public function testGetRejectsArchivedCampaignWithConflict(): void {
    $encounter_state = $this->createMock(EncounterStateService::class);
    $encounter_state->method('tryGetState')
      ->willThrowException(new LegacyCampaignArchivedException('legacy_campaign_archived: campaign 310 is archived'));

    $controller = $this->buildController($encounter_state);
    $request = Request::create('/api/combat/encounter/get', 'POST', [], [], [], [], json_encode(['encounterId' => 555]));
    $response = $controller->get($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame(LegacyCampaignArchivedException::CODE, $payload['error_code'] ?? NULL);
  }

  /**
   * @covers ::currentState
   */
  public function testCurrentStateDelegatesContextReadToCanonicalOwner(): void {
    $context_state = ['status' => 'idle', 'encounter_presentation' => ['schema_version' => 'encounter-map-v1']];

    $encounter_state = $this->createMock(EncounterStateService::class);
    $encounter_state->expects($this->once())
      ->method('getActiveEncounterStateForContext')
      ->with(77, 'crypt_chamber_a')
      ->willReturn($context_state);

    $controller = $this->buildController($encounter_state);
    $request = Request::create('/api/combat/encounter/current', 'GET', [
      'campaignId' => 77,
      'roomId' => 'crypt_chamber_a',
    ]);
    $response = $controller->currentState($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['success'] ?? FALSE);
    $this->assertSame($context_state, $payload['data'] ?? NULL);
  }

  /**
   * @covers ::currentState
   */
  public function testCurrentStateRejectsArchivedCampaignWithConflict(): void {
    $encounter_state = $this->createMock(EncounterStateService::class);
    $encounter_state->method('getActiveEncounterStateForContext')
      ->willThrowException(new LegacyCampaignArchivedException('legacy_campaign_archived: campaign 310 is archived'));

    $controller = $this->buildController($encounter_state);
    $request = Request::create('/api/combat/encounter/current', 'GET', [
      'campaignId' => 310,
      'roomId' => 'crypt_chamber_a',
    ]);
    $response = $controller->currentState($request);
    $payload = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame(LegacyCampaignArchivedException::CODE, $payload['error_code'] ?? NULL);
  }

}
