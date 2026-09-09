<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\AggressionStateStoreService;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use Drupal\dungeoncrawler_content\Service\DispositionStateStoreService;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\RelationshipAttitudeStateStoreService;
use Drupal\dungeoncrawler_content\Service\SocialStateService;
use Drupal\dungeoncrawler_content\Service\StanceStateStoreService;
use PHPUnit\Framework\TestCase;

/**
 * Owner matrix for the single social current-state authority (Phase 5).
 *
 * @group dungeoncrawler_content
 * @group social_state
 */
class SocialStateServiceTest extends TestCase {

  private function aggression(): AggressionStateStoreService {
    return $this->createMock(AggressionStateStoreService::class);
  }

  private function disposition(): DispositionStateStoreService {
    return $this->createMock(DispositionStateStoreService::class);
  }

  private function relationship(): RelationshipAttitudeStateStoreService {
    return $this->createMock(RelationshipAttitudeStateStoreService::class);
  }

  private function stance(): StanceStateStoreService {
    return $this->createMock(StanceStateStoreService::class);
  }

  private function lifecycle(): CampaignLifecycleService {
    return $this->createMock(CampaignLifecycleService::class);
  }

  /**
   * The provider owns exactly the canonical social object type.
   */
  public function testProviderOwnsSocialType(): void {
    $service = new SocialStateService(
      $this->aggression(),
      $this->disposition(),
      $this->relationship(),
      $this->stance(),
      $this->lifecycle(),
    );
    $this->assertSame('social', $service->objectType());
    $this->assertTrue($service->supports('social'));
    $this->assertFalse($service->supports('actor'));
  }

  /**
   * getSocialState merges the four explicitly named components.
   */
  public function testMergesFourNamedComponents(): void {
    $aggression = $this->aggression();
    $aggression->method('loadLatestState')->willReturn([
      'status' => 'hostile',
      'room_id' => 'room_1',
      'updated_at' => 111,
      'aggression_summary' => ['x' => 1],
    ]);
    $disposition = $this->disposition();
    $disposition->method('loadLatestState')->willReturn([
      'entity_ref' => 'npc_1033',
      'updated_at' => 222,
      'summary' => ['mood' => 'wary'],
    ]);
    $stance = $this->stance();
    $stance->method('loadLatestState')->willReturn([
      'entity_ref' => 'npc_1033',
      'updated_at' => 333,
      'summary' => ['posture' => 'defensive'],
    ]);
    $relationship = $this->relationship();
    $relationship->method('findStrongestDisposition')->willReturn([
      'attitude' => 'unfriendly',
      'score' => -20,
    ]);

    $service = new SocialStateService($aggression, $disposition, $relationship, $stance, $this->lifecycle());
    $state = $service->getSocialState(986, 'npc_1033', 'room_1', ['npc_target']);

    $this->assertSame(986, $state['campaign_id']);
    $this->assertSame('npc_1033', $state['entity_ref']);
    $this->assertSame('room_1', $state['room_id']);
    $this->assertSame('hostile', $state['aggression_state']['status']);
    $this->assertSame('wary', $state['disposition_state']['summary']['mood']);
    $this->assertSame('defensive', $state['stance_state']['summary']['posture']);
    $this->assertSame('unfriendly', $state['relationship_attitude']['attitude']);
  }

  /**
   * A missing component is reported NULL, never invented/defaulted.
   */
  public function testMissingComponentsAreNullNotDefaulted(): void {
    $aggression = $this->aggression();
    $aggression->method('loadLatestState')->willReturn(NULL);
    $disposition = $this->disposition();
    $disposition->method('loadLatestState')->willReturn(NULL);
    $stance = $this->stance();
    $stance->method('loadLatestState')->willReturn(NULL);

    $service = new SocialStateService($aggression, $disposition, $this->relationship(), $stance, $this->lifecycle());
    // No room, no targets: aggression + relationship are not even queried.
    $state = $service->getSocialState(986, 'npc_1033');

    $this->assertNull($state['aggression_state']);
    $this->assertNull($state['disposition_state']);
    $this->assertNull($state['stance_state']);
    $this->assertNull($state['relationship_attitude']);
    $this->assertNull($state['room_id']);
  }

  /**
   * getObjectState returns a canonical envelope naming the social owner.
   */
  public function testObjectStateEnvelopeNamesOwner(): void {
    $disposition = $this->disposition();
    $disposition->method('loadLatestState')->willReturn(['entity_ref' => 'npc_1033', 'updated_at' => 500, 'summary' => []]);
    $stance = $this->stance();
    $stance->method('loadLatestState')->willReturn(NULL);

    $service = new SocialStateService($this->aggression(), $disposition, $this->relationship(), $stance, $this->lifecycle());
    $ref = ObjectRef::create('social', 'npc_1033', ['campaign_id' => 986]);
    $envelope = $service->getObjectState($ref)->toArray();

    $this->assertSame(1, $envelope['envelope_version']);
    $this->assertSame('social', $envelope['object_type']);
    $this->assertSame('npc_1033', $envelope['object_id']);
    $this->assertSame(SocialStateService::class, $envelope['authority']['owner']);
    $this->assertSame(SocialStateService::class, $envelope['authority']['provider']);
    $this->assertSame('social_state_stores', $envelope['authority']['source']);
    $this->assertSame('500', $envelope['updated_at']);
  }

  /**
   * Social reads require a campaign scope; a missing campaign hard-fails.
   */
  public function testObjectStateRequiresCampaignScope(): void {
    $service = new SocialStateService(
      $this->aggression(),
      $this->disposition(),
      $this->relationship(),
      $this->stance(),
      $this->lifecycle(),
    );
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('social_state_requires_campaign_scope');
    $service->getObjectState(ObjectRef::create('social', 'npc_1033', []));
  }

  /**
   * Archived/legacy campaigns hard-fail the campaign guard before any read.
   */
  public function testArchivedCampaignHardFails(): void {
    $lifecycle = $this->lifecycle();
    $lifecycle->method('assertLaunchable')->willThrowException(
      new LegacyCampaignArchivedException('legacy_campaign_archived: campaign 310 is archived.')
    );

    $service = new SocialStateService(
      $this->aggression(),
      $this->disposition(),
      $this->relationship(),
      $this->stance(),
      $lifecycle,
    );
    $this->expectException(LegacyCampaignArchivedException::class);
    $service->getObjectState(ObjectRef::create('social', 'npc_1033', ['campaign_id' => 310]));
  }

}
