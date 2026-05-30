<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GM chat response decoding.
 *
 * @group dungeoncrawler_content
 * @group service
 * @group unit
 */
class CharacterCreationGmServiceTest extends TestCase {

  /**
   * Tests plain-text model replies degrade to advice instead of an exception.
   */
  public function testDecodeResponsePayloadFallsBackToReplyText(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'decodeResponsePayload');
    $method->setAccessible(TRUE);

    $payload = $method->invoke($service, 'A dwarven fighter sounds like a sturdy frontline choice.');

    $this->assertSame([
      'reply' => 'A dwarven fighter sounds like a sturdy frontline choice.',
      'updates' => [],
    ], $payload);
  }

  /**
   * Tests that summary and history read from nested wizard drafts.
   */
  public function testSummaryAndHistoryReadWizardDraftState(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $character = [
      'wizard' => [
        'name' => 'Burasco',
        'ancestry' => 'human',
        'class' => 'wizard',
        'background' => 'acolyte',
        'step' => 7,
        'gm_chat' => [
          'messages' => [
            ['role' => 'user', 'content' => 'Buy me a staff.'],
            ['role' => 'assistant', 'content' => 'Done.'],
          ],
        ],
      ],
    ];

    $this->assertSame([
      ['role' => 'user', 'content' => 'Buy me a staff.'],
      ['role' => 'assistant', 'content' => 'Done.'],
    ], $service->getChatHistory($character));
    $this->assertSame([
      'name' => 'Burasco',
      'ancestry' => 'human',
      'class' => 'wizard',
      'background' => 'acolyte',
      'step' => 7,
    ], $service->buildSummary($character));
  }

  /**
   * Tests that GM faction draft helper fields resolve into canonical faction refs.
   */
  public function testResolveFactionDraftCreationsPromotesCampaignSubjectIds(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(TRUE);
    $faction_generation->expects($this->once())
      ->method('createOrReuseFactionForNeed')
      ->with(97, $this->callback(function (array $request): bool {
        return ($request['label'] ?? '') === 'Wharf Consortium'
          && ($request['requestSource'] ?? '') === 'character_creation_gm_chat'
          && ($request['whyExistingFactionIsInsufficient'] ?? '') !== '';
      }))
      ->willReturn([
        'campaignSubjectId' => 'institution_allegiance_wharf-consortium',
      ]);

    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $character = [
      'faction_refs_create_labels' => ['Wharf Consortium'],
      'faction_refs_create_why' => 'Need a dockside labor bloc with covert leverage.',
      'faction_refs_create_public_face' => 'Dock labor union',
      'faction_refs' => ['institution_allegiance_commonweal'],
    ];

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveFactionDraftCreations');
    $method->setAccessible(TRUE);
    $method->invokeArgs($service, [&$character, 97]);

    $this->assertSame([
      'institution_allegiance_commonweal',
      'institution_allegiance_wharf-consortium',
    ], $character['faction_refs']);
    $this->assertArrayNotHasKey('faction_refs_create_labels', $character);
    $this->assertArrayNotHasKey('faction_refs_create_why', $character);
  }

  /**
   * Tests structured affiliation refs are preserved during faction resolution.
   */
  public function testResolveFactionDraftCreationsPreservesStructuredAffiliationRefs(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(TRUE);
    $faction_generation->expects($this->once())
      ->method('createOrReuseFactionForNeed')
      ->willReturn(['campaignSubjectId' => 'institution_allegiance_new-faction']);

    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $character = [
      'faction_refs_create_labels' => ['New Faction'],
      'faction_refs_create_why' => 'Need a faction.',
      'faction_refs_create_public_face' => 'Public',
      'faction_refs' => [
        ['subject_id' => 'existing_faction_1', 'metadata' => ['source' => 'user_selection']],
        'plain_faction_id',
      ],
    ];

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveFactionDraftCreations');
    $method->setAccessible(TRUE);
    $method->invokeArgs($service, [&$character, 97]);

    // Both existing subject IDs should be extracted, plus the new faction.
    $this->assertSame([
      'existing_faction_1',
      'plain_faction_id',
      'institution_allegiance_new-faction',
    ], $character['faction_refs']);
  }

  /**
   * Tests incomplete GM faction helper bundles fail as validation errors.
   */
  public function testValidateFactionDraftCreationRequestRejectsIncompleteBundle(): void {
    $faction_generation = new FactionGenerationService(
      $this->createMock(Connection::class),
      new InstitutionNormalizationService(),
      $this->createMock(CampaignSubjectRegistryService::class),
    );

    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $character = [
      'faction_refs_create_labels' => ['Wharf Consortium'],
    ];

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'validateFactionDraftCreationRequest');
    $method->setAccessible(TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('GM faction creation update is incomplete:');
    $method->invoke($service, $character);
  }

  /**
   * Tests GM faction creation is rejected when no campaign context exists.
   */
  public function testResolveFactionDraftCreationsRejectsMissingCampaignContext(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $character = [
      'faction_refs_create_labels' => ['Wharf Consortium'],
      'faction_refs_create_why' => 'Need a dockside labor bloc with covert leverage.',
    ];

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveFactionDraftCreations');
    $method->setAccessible(TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('GM faction creation requires a campaign context.');
    $method->invokeArgs($service, [&$character, 0]);
  }

  /**
   * Tests the GM prompt only exposes faction creation fields with campaign context.
   */
  public function testBuildUserPromptOmitsFactionFieldsWithoutCampaignContext(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(TRUE);
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'buildUserPrompt');
    $method->setAccessible(TRUE);

    $without_campaign = $method->invoke($service, 'Make a new faction.', 1, [], [], NULL);
    $with_campaign = $method->invoke($service, 'Make a new faction.', 1, [], [], 97);

    $this->assertStringNotContainsString('faction_refs_create_labels', $without_campaign);
    $this->assertStringContainsString('faction_refs_create_labels', $with_campaign);
  }

  /**
   * Tests resolved draft campaign context enables faction fields when request omits it.
   */
  public function testResolvedDraftCampaignContextKeepsFactionFieldsAvailable(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(TRUE);
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $resolve_method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveDraftCampaignId');
    $resolve_method->setAccessible(TRUE);
    $prompt_method = new \ReflectionMethod(CharacterCreationGmService::class, 'buildUserPrompt');
    $prompt_method->setAccessible(TRUE);

    $resolved_campaign_id = $resolve_method->invoke($service, (object) ['campaign_id' => 97], NULL);
    $prompt = $prompt_method->invoke($service, 'Make a new faction.', 1, [], [], $resolved_campaign_id);

    $this->assertSame(97, $resolved_campaign_id);
    $this->assertStringContainsString('faction_refs_create_labels', $prompt);
  }

  /**
   * Tests the GM prompt hides faction fields when generation storage is unavailable.
   */
  public function testBuildUserPromptOmitsFactionFieldsWithoutGenerationStorage(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(FALSE);
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'buildUserPrompt');
    $method->setAccessible(TRUE);
    $prompt = $method->invoke($service, 'Make a new faction.', 1, [], [], 97);

    $this->assertStringNotContainsString('faction_refs_create_labels', $prompt);
  }

  /**
   * Tests GM faction creation returns a controlled validation error when storage is unavailable.
   */
  public function testResolveFactionDraftCreationsRejectsUnavailableGenerationStorage(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(FALSE);
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $character = [
      'faction_refs_create_labels' => ['Wharf Consortium'],
      'faction_refs_create_why' => 'Need a dockside labor bloc with covert leverage.',
      'faction_refs_create_public_face' => 'Dock labor union',
    ];

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveFactionDraftCreations');
    $method->setAccessible(TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Faction generation requires the library manifest and campaign subject registry storage to be installed.');
    $method->invokeArgs($service, [&$character, 97]);
  }

  /**
   * Tests saving an existing draft preserves its campaign binding when omitted.
   */
  public function testSaveDraftPreservesExistingCampaignWhenCampaignIdOmitted(): void {
    $record = (object) [
      'id' => 42,
      'campaign_id' => 97,
      'version' => 3,
      'instance_id' => 'pc-meris-42',
    ];
    $character_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 1,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ];

    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->once())
      ->method('canonicalizeCharacterData')
      ->with($character_data)
      ->willReturn($character_data);
    $character_manager->expects($this->once())
      ->method('extractHotColumnsFromData')
      ->with($this->callback(static function (array $input): bool {
        return ($input['name'] ?? NULL) === 'Meris'
          && isset($input['created_at'], $input['updated_at']);
      }))
      ->willReturn([
        'hp_current' => 8,
        'hp_max' => 8,
        'armor_class' => 15,
      ]);

    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $query = new class() {
      public array $fields = [];
      public function fields(array $fields): self {
        $this->fields = $fields;
        return $this;
      }
      public function condition(string $field, mixed $value): self {
        return $this;
      }
      public function execute(): int {
        return 1;
      }
    };
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_characters')
      ->willReturn($query);

    $membership = $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class);
    $membership->expects($this->never())
      ->method('syncCampaignCharacterMemberships')
      ->withAnyParameters();

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new CharacterCreationGmService(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $time,
      $this->createMock(UuidInterface::class),
      $character_manager,
      $this->createMock(AbilityScoreTracker::class),
      $membership,
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'saveDraft');
    $method->setAccessible(TRUE);
    $saved_id = $method->invoke($service, $record, $character_data, NULL);

    $this->assertSame(42, $saved_id);
    $this->assertSame(97, $query->fields['campaign_id'] ?? NULL);
  }

  /**
   * Tests new GM-completed drafts persist as complete characters.
   */
  public function testSaveDraftInsertMarksCompletedDraftAsReady(): void {
    $character_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 8,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ];

    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->once())
      ->method('canonicalizeCharacterData')
      ->with($character_data)
      ->willReturn($character_data);
    $character_manager->expects($this->once())
      ->method('extractHotColumnsFromData')
      ->with($this->callback(static function (array $input): bool {
        return ($input['name'] ?? NULL) === 'Meris'
          && (int) ($input['step'] ?? 0) === 8
          && isset($input['created_at'], $input['updated_at']);
      }))
      ->willReturn([
        'hp_current' => 8,
        'hp_max' => 8,
        'armor_class' => 15,
      ]);

    $insert = new class() {
      public array $fields = [];
      public function fields(array $fields): self {
        $this->fields = $fields;
        return $this;
      }
      public function execute(): int {
        return 88;
      }
    };

    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $database->expects($this->once())
      ->method('insert')
      ->with('dc_campaign_characters')
      ->willReturn($insert);

    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(7);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('pc-meris-88');

    $membership = $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class);
    $membership->expects($this->once())
      ->method('syncCampaignCharacterMemberships')
      ->with(97, 'pc-meris-88', $this->isType('array'));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new CharacterCreationGmService(
      $database,
      $account,
      $time,
      $uuid,
      $character_manager,
      $this->createMock(AbilityScoreTracker::class),
      $membership,
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'saveDraft');
    $method->setAccessible(TRUE);
    $saved_id = $method->invoke($service, NULL, $character_data, 97);

    $this->assertSame(88, $saved_id);
    $this->assertSame(1, $insert->fields['status'] ?? NULL);
    $this->assertSame(97, $insert->fields['campaign_id'] ?? NULL);
  }

  /**
   * Tests saving an existing draft ignores conflicting request campaign ids.
   */
  public function testSaveDraftPreservesExistingCampaignWhenCampaignIdConflicts(): void {
    $record = (object) [
      'id' => 42,
      'campaign_id' => 97,
      'version' => 3,
      'instance_id' => 'pc-meris-42',
    ];
    $character_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 1,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ];

    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('canonicalizeCharacterData')
      ->willReturnCallback(static fn(array $input): array => $input);
    $character_manager->method('extractHotColumnsFromData')
      ->willReturn([
        'hp_current' => 8,
        'hp_max' => 8,
        'armor_class' => 15,
      ]);

    $query = new class() {
      public array $fields = [];
      public function fields(array $fields): self { $this->fields = $fields; return $this; }
      public function condition(string $field, mixed $value): self { return $this; }
      public function execute(): int { return 1; }
    };
    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $database->method('update')->willReturn($query);

    $service = new CharacterCreationGmService(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $character_manager,
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'saveDraft');
    $method->setAccessible(TRUE);
    $method->invoke($service, $record, $character_data, 12);

    $this->assertSame(97, $query->fields['campaign_id'] ?? NULL);
  }

  /**
   * Tests that GM chat updates persist to the nested wizard state for canonicalization.
   */
  public function testSaveDraftSyncsGmChatToWizardBeforeSave(): void {
    $character_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 8,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
      'gm_chat' => [
        'messages' => [
          ['role' => 'user', 'content' => 'Make me a wizard'],
          ['role' => 'assistant', 'content' => 'Here is a wizard'],
        ],
        'last_updated' => '2026-05-30T18:00:00+00:00',
      ],
    ];

    $character_manager = $this->createMock(CharacterManager::class);
    $capture_canonical = [];
    $character_manager->expects($this->once())
      ->method('canonicalizeCharacterData')
      ->willReturnCallback(function (array $input) use (&$capture_canonical): array {
        $capture_canonical = $input;
        return $input;
      });
    $character_manager->expects($this->once())
      ->method('extractHotColumnsFromData')
      ->willReturn(['hp_current' => 8, 'hp_max' => 8, 'armor_class' => 15]);

    $insert = new class() {
      public array $fields = [];
      public function fields(array $fields): self { $this->fields = $fields; return $this; }
      public function execute(): int { return 88; }
    };

    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $database->expects($this->once())
      ->method('insert')
      ->willReturn($insert);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('pc-meris-88');

    $membership = $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class);
    $membership->expects($this->once())
      ->method('syncCampaignCharacterMemberships')
      ->with(97, 'pc-meris-88', $this->isType('array'));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $service = new CharacterCreationGmService(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $time,
      $uuid,
      $character_manager,
      $this->createMock(AbilityScoreTracker::class),
      $membership,
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'saveDraft');
    $method->setAccessible(TRUE);
    $method->invoke($service, NULL, $character_data, 97);

    // Verify that the nested wizard state received the gm_chat before canonicalization.
    $this->assertSame(
      $character_data['gm_chat'],
      $capture_canonical['wizard']['gm_chat'] ?? NULL,
      'GM chat should be synced to nested wizard state before canonicalization'
    );
  }
  public function testResolveDraftCampaignIdPreservesExistingBindingWhenOmitted(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveDraftCampaignId');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($service, (object) ['campaign_id' => 97], NULL);

    $this->assertSame(97, $resolved);
  }

  /**
   * Tests campaign resolution adopts explicit campaign context for unbound drafts.
   */
  public function testResolveDraftCampaignIdUsesRequestValueForUnboundDraft(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveDraftCampaignId');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($service, (object) ['campaign_id' => 0], 97);

    $this->assertSame(97, $resolved);
  }

  /**
   * Tests campaign resolution ignores conflicting request values for saved drafts.
   */
  public function testResolveDraftCampaignIdIgnoresConflictingRequestValue(): void {
    $service = new CharacterCreationGmService(
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(CharacterManager::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $this->createMock(FactionGenerationService::class),
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'resolveDraftCampaignId');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($service, (object) ['campaign_id' => 97], 12);

    $this->assertSame(97, $resolved);
  }

  /**
   * Tests faction generation runs inside the owning GM draft transaction.
   */
  public function testSaveDraftStartsTransactionBeforeFactionGeneration(): void {
    $order = [];
    $record = (object) [
      'id' => 42,
      'campaign_id' => 97,
      'version' => 3,
      'instance_id' => 'pc-meris-42',
    ];
    $character_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 1,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
      'faction_refs_create_labels' => ['Wharf Consortium'],
      'faction_refs_create_why' => 'Need a dockside labor bloc with covert leverage.',
      'faction_refs_create_public_face' => 'Dock labor union',
    ];

    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('canonicalizeCharacterData')
      ->willReturnCallback(static fn(array $input): array => $input);
    $character_manager->method('extractHotColumnsFromData')
      ->willReturn([
        'hp_current' => 8,
        'hp_max' => 8,
        'armor_class' => 15,
      ]);

    $database = $this->createMock(Connection::class);
    $database->expects($this->once())
      ->method('startTransaction')
      ->willReturnCallback(static function () use (&$order) {
        $order[] = 'transaction';
        return new class() {
          public function rollBack(): void {}
        };
      });
    $database->method('update')->willReturn(new class() {
      public function fields(array $fields): self { return $this; }
      public function condition(string $field, mixed $value): self { return $this; }
      public function execute(): int { return 1; }
    });

    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->method('isGenerationStorageReady')->willReturn(TRUE);
    $faction_generation->expects($this->once())
      ->method('createOrReuseFactionForNeed')
      ->willReturnCallback(static function (int $campaign_id, array $request) use (&$order): array {
        $order[] = 'generation';
        return ['campaignSubjectId' => 'institution_allegiance_wharf-consortium'];
      });

    $service = new CharacterCreationGmService(
      $database,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(UuidInterface::class),
      $character_manager,
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $method = new \ReflectionMethod(CharacterCreationGmService::class, 'saveDraft');
    $method->setAccessible(TRUE);
    $method->invoke($service, $record, $character_data, 97);

    $this->assertSame(['transaction', 'generation'], array_slice($order, 0, 2));
  }
}
