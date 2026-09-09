<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use Drupal\dungeoncrawler_content\Service\CanonicalQuestTemplateService;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\QuestStateService;
use Drupal\dungeoncrawler_content\Service\QuestStateStore;
use PHPUnit\Framework\TestCase;

/**
 * Owner-assembly tests for the canonical quest current-state authority (Phase 4).
 *
 * QuestStateService owns direct single-quest retrieval, binds the canonical
 * quest template explicitly, merges the best applicable progress scope by
 * explicit precedence, and guards the owning campaign. These tests mock the
 * pure persistence lane (QuestStateStore), the quest-template definition
 * authority (CanonicalQuestTemplateService), and the campaign lifecycle guard.
 *
 * @group dungeoncrawler_content
 * @group quest_state
 */
class QuestStateServiceTest extends TestCase {

  /**
   * A canonical quest template ref returned by the definition authority.
   *
   * @return array<string,string>
   */
  private function templateRef(string $template_id = 'tavern_storyline_leads'): array {
    return [
      'template_id' => $template_id,
      'version' => '1.0.0',
      'name' => 'Gather Storyline Leads in the Tavern',
      'quest_type' => 'main_quest',
      'source_table' => 'dc_canonical_quests',
    ];
  }

  /**
   * A raw runtime quest instance row.
   *
   * @param array<string,mixed> $overrides
   *
   * @return array<string,mixed>
   */
  private function instance(array $overrides = []): array {
    return $overrides + [
      'id' => 2951,
      'campaign_id' => 986,
      'quest_id' => 'tavern_storyline_leads_986_abc',
      'source_template_id' => 'tavern_storyline_leads',
      'template_version' => '1.0.0',
      'quest_name' => 'Gather Storyline Leads',
      'quest_type' => 'main_quest',
      'status' => 'active',
      'generated_objectives' => json_encode([
        ['phase' => 1, 'objectives' => [['objective_id' => 'speak_to_eldric', 'completed' => FALSE]]],
      ]),
      'generated_rewards' => '{}',
      'created_at' => 1000,
    ];
  }

  /**
   * A raw progress row.
   *
   * @param array<string,mixed> $overrides
   *
   * @return array<string,mixed>
   */
  private function progress(array $overrides = []): array {
    return $overrides + [
      'id' => 2305,
      'campaign_id' => 986,
      'quest_id' => 'tavern_storyline_leads_986_abc',
      'character_id' => 5435,
      'party_id' => NULL,
      'objective_states' => json_encode([
        ['phase' => 1, 'objectives' => [['objective_id' => 'speak_to_eldric', 'completed' => TRUE]]],
      ]),
      'current_phase' => 1,
      'branch_choice' => NULL,
      'started_at' => 900,
      'last_updated' => 2000,
      'completed_at' => NULL,
      'outcome' => NULL,
    ];
  }

  /**
   * Build a service with stubbed store, template authority and lifecycle guard.
   *
   * @param array<string,mixed>|null $instance
   * @param array<int,array<string,mixed>> $progress_rows
   * @param array<int> $tracking_ids
   */
  private function service(
    ?array $instance,
    array $progress_rows = [],
    array $tracking_ids = [5435, 1033],
    ?array $template_ref = NULL,
    bool $archived = FALSE,
    ?\Throwable $template_exception = NULL,
  ): QuestStateService {
    $store = $this->createMock(QuestStateStore::class);
    $store->method('loadQuestInstanceRow')->willReturn($instance);
    $store->method('loadProgressRows')->willReturn($progress_rows);
    $store->method('resolveTrackingCharacterIds')->willReturn($tracking_ids);

    $templates = $this->createMock(CanonicalQuestTemplateService::class);
    if ($template_exception !== NULL) {
      $templates->method('requireCanonicalQuestTemplateRef')->willThrowException($template_exception);
    }
    else {
      $templates->method('requireCanonicalQuestTemplateRef')->willReturn($template_ref ?? $this->templateRef());
    }

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    if ($archived) {
      $lifecycle->method('assertLaunchable')->willThrowException(
        new LegacyCampaignArchivedException('legacy_campaign_archived: campaign 310 is archived and cannot be launched or read by the current runtime.')
      );
    }

    return new QuestStateService($store, $templates, $lifecycle);
  }

  /**
   * Campaign-scoped read: unscoped campaign progress applies, scope=campaign.
   */
  public function testCampaignScopedQuest(): void {
    $progress = $this->progress(['character_id' => NULL, 'party_id' => NULL]);
    $state = $this->service($this->instance(), [$progress])->getState(986, 'tavern_storyline_leads_986_abc');

    $this->assertSame('campaign', $state['scope']);
    $this->assertSame('tavern_storyline_leads', $state['template']['template_id']);
    $this->assertSame('1.0.0', $state['template']['version']);
    $this->assertSame(986, $state['campaign_id']);
    $this->assertTrue($state['has_progress']);
    $this->assertSame(1, $state['current_phase']);
  }

  /**
   * Character-scoped read: the character's own progress applies, scope=character.
   */
  public function testCharacterScopedQuest(): void {
    $state = $this->service($this->instance(), [$this->progress()])
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertSame('character', $state['scope']);
    $this->assertSame('character', $state['owner']['type']);
    $this->assertSame('5435', $state['owner']['ref']);
    $this->assertTrue($state['has_progress']);
  }

  /**
   * Precedence: character-owned progress beats unscoped campaign progress.
   */
  public function testCharacterPrecedenceBeatsCampaignScope(): void {
    $rows = [
      $this->progress(['id' => 1, 'character_id' => NULL, 'party_id' => NULL, 'current_phase' => 5, 'last_updated' => 9999]),
      $this->progress(['id' => 2, 'character_id' => 5435, 'party_id' => NULL, 'current_phase' => 2, 'last_updated' => 100]),
    ];
    $state = $this->service($this->instance(), $rows)->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertSame('character', $state['scope']);
    // The character row wins on precedence even though it is older/less advanced.
    $this->assertSame(2, $state['current_phase']);
  }

  /**
   * Precedence: for a campaign read, party progress beats character progress.
   */
  public function testPartyPrecedenceForCampaignScope(): void {
    $rows = [
      $this->progress(['id' => 1, 'character_id' => 5435, 'party_id' => NULL, 'current_phase' => 2, 'last_updated' => 9999]),
      $this->progress(['id' => 2, 'character_id' => NULL, 'party_id' => 77, 'current_phase' => 4, 'last_updated' => 100]),
    ];
    $state = $this->service($this->instance(), $rows)->getState(986, 'tavern_storyline_leads_986_abc');

    $this->assertSame('party', $state['scope']);
    $this->assertSame('party', $state['owner']['type']);
    $this->assertSame('77', $state['owner']['ref']);
    $this->assertSame(4, $state['current_phase']);
  }

  /**
   * Objectives and progress evidence are surfaced from the selected scope.
   */
  public function testObjectivesAndProgressEvidence(): void {
    $state = $this->service($this->instance(), [$this->progress()])
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertIsArray($state['objective_states']);
    $this->assertSame('speak_to_eldric', $state['objective_states'][0]['objectives'][0]['objective_id']);
    $this->assertTrue($state['objective_states'][0]['objectives'][0]['completed']);
    $this->assertSame(900, $state['progress']['started_at']);
    $this->assertSame(2000, $state['progress']['last_updated']);
  }

  /**
   * Complete: an explicit completed outcome projects status=completed.
   */
  public function testCompletedStatus(): void {
    $rows = [$this->progress(['outcome' => 'completed', 'completed_at' => 2500])];
    $state = $this->service($this->instance(['status' => 'active']), $rows)
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertSame('completed', $state['status']);
  }

  /**
   * Failed: a failed outcome projects status=failed.
   */
  public function testFailedStatus(): void {
    $rows = [$this->progress(['outcome' => 'failed'])];
    $state = $this->service($this->instance(), $rows)
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertSame('failed', $state['status']);
  }

  /**
   * Abandoned: an abandoned outcome projects status=abandoned.
   */
  public function testAbandonedStatus(): void {
    $rows = [$this->progress(['outcome' => 'abandoned'])];
    $state = $this->service($this->instance(), $rows)
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertSame('abandoned', $state['status']);
  }

  /**
   * No progress overlay: status comes from the instance, objectives not started.
   */
  public function testMissingProgressReturnsInstanceStatus(): void {
    $state = $this->service($this->instance(['status' => 'offered']), [])
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    $this->assertFalse($state['has_progress']);
    $this->assertSame('offered', $state['status']);
    $this->assertSame(0, $state['current_phase']);
    // Objective states fall back to the not-yet-started generated objectives.
    $this->assertFalse($state['objective_states'][0]['objectives'][0]['completed']);
  }

  /**
   * Missing quest instance hard-fails visibly.
   */
  public function testMissingQuestInstanceHardFails(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Quest not found for campaign 986: nope');
    $this->service(NULL)->getState(986, 'nope');
  }

  /**
   * Wrong-campaign scope is treated as not found (never substituted).
   *
   * The store scopes by campaign + quest_id, so a quest that exists only in a
   * different campaign resolves to NULL and hard-fails here.
   */
  public function testWrongCampaignNotFound(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Quest not found for campaign 986');
    $this->service(NULL)->getState(986, 'tavern_storyline_leads_310_xyz');
  }

  /**
   * Instance with no source template id hard-fails before any substitution.
   */
  public function testMissingTemplateIdHardFails(): void {
    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('quest_template_missing');
    $this->service($this->instance(['source_template_id' => '']))
      ->getState(986, 'tavern_storyline_leads_986_abc');
  }

  /**
   * Missing/quarantined template hard-fails (propagated from the authority).
   */
  public function testQuarantinedTemplateHardFails(): void {
    $service = $this->service(
      $this->instance(),
      template_exception: new \OutOfBoundsException('quest_template_quarantined:tavern_storyline_leads'),
    );

    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('quest_template_quarantined:tavern_storyline_leads');
    $service->getState(986, 'tavern_storyline_leads_986_abc');
  }

  /**
   * Ambiguous progress: two equally applicable rows at the winning tier fail.
   */
  public function testAmbiguousProgressHardFails(): void {
    $rows = [
      $this->progress(['id' => 1, 'character_id' => 5435, 'party_id' => NULL, 'last_updated' => 2000]),
      $this->progress(['id' => 2, 'character_id' => 1033, 'party_id' => NULL, 'last_updated' => 2000]),
    ];
    $service = $this->service($this->instance(), $rows, [5435, 1033]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('quest_progress_ambiguous');
    $service->getState(986, 'tavern_storyline_leads_986_abc', 5435);
  }

  /**
   * A foreign character's progress is never applicable to a character read.
   */
  public function testForeignCharacterProgressIgnored(): void {
    $rows = [$this->progress(['character_id' => 9999, 'party_id' => NULL])];
    $state = $this->service($this->instance(), $rows, [5435, 1033])
      ->getState(986, 'tavern_storyline_leads_986_abc', 5435);

    // No applicable progress → no overlay, scope defaults to character.
    $this->assertFalse($state['has_progress']);
    $this->assertSame('character', $state['scope']);
  }

  /**
   * Archived campaign rejects with legacy_campaign_archived before assembly.
   */
  public function testArchivedCampaignRejected(): void {
    $service = $this->service($this->instance(['campaign_id' => 310]), archived: TRUE);

    $this->expectException(LegacyCampaignArchivedException::class);
    $this->expectExceptionMessage('legacy_campaign_archived');
    $service->getState(310, 'tavern_storyline_leads_310_xyz');
  }

  /**
   * Empty scope input hard-fails.
   */
  public function testEmptyQuestIdHardFails(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Quest state requires campaign_id and quest_id.');
    $this->service($this->instance())->getState(986, '   ');
  }

  /**
   * getObjectState returns a canonical envelope with self-owner and projection.
   */
  public function testObjectStateEnvelope(): void {
    $service = $this->service($this->instance(), [$this->progress()]);

    $envelope = $service->getObjectState(
      ObjectRef::create('quest', 'tavern_storyline_leads_986_abc', ['campaign_id' => 986, 'character_id' => 5435])
    );
    $array = $envelope->toArray();

    $this->assertSame(1, $array['envelope_version']);
    $this->assertSame('quest', $array['object_type']);
    $this->assertSame('tavern_storyline_leads_986_abc', $array['object_id']);
    $this->assertSame(QuestStateService::class, $array['authority']['owner']);
    $this->assertSame(QuestStateService::class, $array['authority']['provider']);
    $this->assertSame(QuestStateService::AUTHORITY_SOURCE, $array['authority']['source']);
    $this->assertSame('character', $array['projection']['scope']);
    $this->assertSame(1, $array['projection']['current_phase']);
    $this->assertTrue($array['projection']['has_progress']);
    $this->assertSame('1.0.0', $array['projection']['template_version']);
  }

  /**
   * getObjectState requires campaign_id context.
   */
  public function testObjectStateRequiresCampaignContext(): void {
    $this->expectException(\Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException::class);
    $this->service($this->instance())->getObjectState(ObjectRef::create('quest', 'q1'));
  }

  /**
   * The canonical owner declares the quest object type.
   */
  public function testObjectTypeAndSupports(): void {
    $service = $this->service($this->instance());
    $this->assertSame('quest', $service->objectType());
    $this->assertTrue($service->supports('quest'));
    $this->assertFalse($service->supports('item'));
  }

}
