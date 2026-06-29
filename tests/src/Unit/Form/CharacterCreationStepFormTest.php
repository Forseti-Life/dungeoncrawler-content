<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\CharacterWizardHardeningService;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 * @group feats
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm
 */
class CharacterCreationStepFormTest extends UnitTestCase {

  /**
   * @covers ::buildAdaptedCantripSelectionSection
   */
  public function testAdaptedCantripSelectionIncludesNativeTradition(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('getSpellsByTradition')
      ->with('arcane', 0)
      ->willReturn([
        [
          'id' => 'detect-magic',
          'name' => 'Detect Magic',
          'description' => 'Sense whether magic is nearby.',
          'school' => 'divination',
        ],
      ]);

    $form = $this->buildFormObject($character_manager);
    $form_state = (new FormState())->setValues([
      'feat_selections' => [
        'adapted-cantrip' => [
          'selected_tradition' => 'arcane',
        ],
      ],
    ]);
    $form_array = [];

    $method = new \ReflectionMethod($form, 'buildAdaptedCantripSelectionSection');
    $method->setAccessible(TRUE);
    $character_data = [];
    $arguments = [&$form_array, $form_state, $character_data, 'arcane'];
    $method->invokeArgs($form, $arguments);

    $options = $form_array['class_dynamic']['feat_selections']['adapted-cantrip']['selected_tradition']['#options'];
    $this->assertArrayHasKey('arcane', $options);
    $this->assertSame('Arcane', $options['arcane']);
    $this->assertArrayHasKey('selected_cantrip', $form_array['class_dynamic']['feat_selections']['adapted-cantrip']);
    $this->assertArrayNotHasKey('reference', $form_array['class_dynamic']['feat_selections']['adapted-cantrip']);
  }

  /**
   * @covers ::validateAdaptedCantripSelection
   */
  public function testAdaptedCantripValidationAllowsNativeTraditionSelection(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('resolveClassTradition')
      ->with('wizard', ['class' => 'wizard'])
      ->willReturn('arcane');
    $character_manager->method('getSpellsByTradition')
      ->with('arcane', 0)
      ->willReturn([
        ['id' => 'detect-magic'],
      ]);

    $form = $this->buildFormObject($character_manager);
    $form_state = (new FormState())->setValues([
      'feat_selections' => [
        'adapted-cantrip' => [
          'selected_tradition' => 'arcane',
          'selected_cantrip' => 'detect-magic',
        ],
      ],
    ]);

    $method = new \ReflectionMethod($form, 'validateAdaptedCantripSelection');
    $method->setAccessible(TRUE);
    $method->invoke($form, $form_state, ['class' => 'wizard']);

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * @covers ::buildQuickPlayButton
   */
  public function testBuildQuickPlayButtonUsesDedicatedQuickPlayRoute(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class));

    $method = new \ReflectionMethod($form, 'buildQuickPlayButton');
    $method->setAccessible(TRUE);
    $button = $method->invoke($form);

    $this->assertSame('link', $button['#type']);
    $this->assertSame('I Just Want to Play', (string) $button['#title']);
    $this->assertSame('dungeoncrawler_content.campaign_quick_play_character', $button['#url']->getRouteName());
  }

  /**
   * @covers ::buildStep7Fields
   */
  public function testBuildStep7FieldsFallsBackToGmEquipmentIds(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class));
    $form_state = new FormState();
    $form_array = [];
    $character_data = [
      'strength' => 10,
      'inventory' => [
        'carried' => [
          ['id' => 'leather'],
        ],
      ],
      'gm_equipment_ids' => ['staff', 'leather'],
    ];

    $method = new \ReflectionMethod($form, 'buildStep7Fields');
    $method->setAccessible(TRUE);
    $arguments = [&$form_array, $form_state, $character_data, []];
    $method->invokeArgs($form, $arguments);

    $this->assertContains('staff', $form_array['equipment_weapons']['weapons']['#default_value']);
    $this->assertContains('leather', $form_array['equipment_armor']['armor']['#default_value']);
  }

  /**
   * @covers ::buildStep7Fields
   */
  public function testBuildStep7FieldsAddsClassDefaultLoadoutPreset(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class));
    $form_state = new FormState();
    $form_array = [];
    $character_data = [
      'class' => 'fighter',
      'strength' => 16,
      'inventory' => ['carried' => []],
      'gm_equipment_ids' => [],
    ];

    $method = new \ReflectionMethod($form, 'buildStep7Fields');
    $method->setAccessible(TRUE);
    $arguments = [&$form_array, $form_state, $character_data, []];
    $method->invokeArgs($form, $arguments);

    $this->assertArrayHasKey('class_default_loadout', $form_array);
    $this->assertSame('html_tag', $form_array['class_default_loadout']['apply']['#type']);
    $this->assertSame('button', $form_array['class_default_loadout']['apply']['#attributes']['type']);
    $this->assertSame('fighter_default', $form_array['class_default_loadout']['apply']['#attributes']['data-step7-loadout-apply']);

    $presets = $form_array['#attached']['drupalSettings']['characterStep7']['presets'] ?? [];
    $this->assertArrayHasKey('fighter_default', $presets);
    $this->assertContains('longsword', $presets['fighter_default']['ids']);
    $this->assertContains('chain_mail', $presets['fighter_default']['ids']);
    $this->assertContains('wooden_shield', $presets['fighter_default']['ids']);
  }

  /**
   * @covers ::validateGeneralTrainingSelection
   */
  public function testValidateGeneralTrainingSelectionAcceptsCanonicalFeatLibraryChoice(): void {
    $feat_library = $this->createMock(FeatLibraryService::class);
    $feat_library->expects($this->once())
      ->method('getGeneralFeats')
      ->willReturn([
        ['id' => 'toughness', 'name' => 'Toughness'],
      ]);

    $form = $this->buildFormObject($this->createMock(CharacterManager::class), $feat_library);
    $form_state = (new FormState())->setValues([
      'feat_selections' => [
        'general-training' => [
          'bonus_general_feat' => 'toughness',
        ],
      ],
    ]);

    $method = new \ReflectionMethod($form, 'validateGeneralTrainingSelection');
    $method->setAccessible(TRUE);
    $method->invoke($form, $form_state);

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * @covers ::validateNaturalAmbitionSelection
   */
  public function testValidateNaturalAmbitionSelectionAcceptsCanonicalFeatLibraryChoice(): void {
    $feat_library = $this->createMock(FeatLibraryService::class);
    $feat_library->expects($this->once())
      ->method('getClassFeats')
      ->with('fighter')
      ->willReturn([
        ['id' => 'power-attack', 'name' => 'Power Attack'],
      ]);

    $form = $this->buildFormObject($this->createMock(CharacterManager::class), $feat_library);
    $form_state = (new FormState())->setValues([
      'feat_selections' => [
        'natural-ambition' => [
          'bonus_class_feat' => 'power-attack',
        ],
      ],
    ]);

    $method = new \ReflectionMethod($form, 'validateNaturalAmbitionSelection');
    $method->setAccessible(TRUE);
    $method->invoke($form, $form_state, 'fighter');

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * @covers ::buildStep6Fields
   */
  public function testBuildStep6FieldsAddsStructuredCampaignAffiliationSelectors(): void {
    $feat_library = $this->createMock(FeatLibraryService::class);
    $feat_library->method('getGeneralFeats')->willReturn([]);
    $database = $this->buildSubjectRegistryDatabaseMock(TRUE, [
      [
        'campaign_id' => 70,
        'subject_kind' => 'institution',
        'subject_domain' => 'settlement',
        'subject_id' => 'institution_settlement_fordwatch',
        'display_name' => 'Fordwatch',
      ],
      [
        'campaign_id' => 70,
        'subject_kind' => 'institution',
        'subject_domain' => 'security',
        'subject_id' => 'institution_security_city-watch',
        'display_name' => 'City Watch',
      ],
    ]);

    $form = $this->buildFormObject(
      $this->createMock(CharacterManager::class),
      $feat_library,
      $database
    );
    $form_state = new FormState();
    $form_state->set('campaign_id', 70);
    $form_array = [];
    $character_data = [
      'class' => 'fighter',
      'intelligence' => 10,
      'home_settlement_ref' => 'institution_settlement_fordwatch',
      'security_affiliation_refs' => ['institution_security_city-watch'],
    ];

    $method = new \ReflectionMethod($form, 'buildStep6Fields');
    $method->setAccessible(TRUE);
    $method->invokeArgs($form, [&$form_array, $form_state, $character_data, []]);

    $this->assertArrayHasKey('structured_affiliations', $form_array);
    $this->assertSame('institution_settlement_fordwatch', $form_array['structured_affiliations']['home_settlement_ref']['#default_value']);
    $this->assertArrayHasKey('institution_settlement_fordwatch', $form_array['structured_affiliations']['home_settlement_ref']['#options']);
    $this->assertArrayHasKey('__create__', $form_array['structured_affiliations']['home_settlement_ref']['#options']);
    $this->assertTrue($form_array['structured_affiliations']['security_affiliation_refs']['#multiple']);
    $this->assertContains('institution_security_city-watch', $form_array['structured_affiliations']['security_affiliation_refs']['#default_value']);
    $this->assertArrayHasKey('home_settlement_ref__create_details', $form_array['structured_affiliations']);
    $this->assertArrayHasKey('home_settlement_ref__create_labels', $form_array['structured_affiliations']['home_settlement_ref__create_details']);
  }

  /**
   * @covers ::buildStep3Fields
   */
  public function testBuildStep3FieldsRequiresBackgroundBeforeShowingBoostSelector(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class));
    $form_state = new FormState();
    $form_array = [];
    $character_data = [
      'background' => '',
      'background_boosts' => [],
    ];

    $method = new \ReflectionMethod($form, 'buildStep3Fields');
    $method->setAccessible(TRUE);
    $method->invokeArgs($form, [&$form_array, $form_state, $character_data, []]);

    $this->assertArrayHasKey('background_dynamic', $form_array);
    $this->assertArrayHasKey('background_boosts_pending', $form_array['background_dynamic']);
    $this->assertArrayHasKey('background_boosts', $form_array['background_dynamic']);
    $this->assertArrayNotHasKey('background_boosts_selector', $form_array['background_dynamic']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsStructuredAffiliationInWrongDomain(): void {
    $database = $this->buildSubjectRegistryDatabaseMock(TRUE, [
      [
        'campaign_id' => 70,
        'subject_kind' => 'institution',
        'subject_domain' => 'family',
        'subject_id' => 'institution_family_house-briar',
        'display_name' => 'House Briar',
      ],
    ]);

    $form = $this->buildFormObject(
      $this->createMock(CharacterManager::class),
      $this->buildGeneralFeatLibraryMock(),
      $database
    );
    $form_state = (new FormState())->setValues([
      'alignment' => 'NG',
      'general_feat' => 'toughness',
      'security_affiliation_refs' => ['institution_family_house-briar'],
    ]);
    $form_state->set('step', 6);
    $form_state->set('campaign_id', 70);
    $form_state->set('character_id', 0);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertArrayHasKey('security_affiliation_refs', $form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormRejectsDuplicateStructuredAffiliationCreation(): void {
    $database = $this->buildSubjectRegistryDatabaseMock(TRUE, [
      [
        'campaign_id' => 70,
        'subject_kind' => 'institution',
        'subject_domain' => 'settlement',
        'subject_id' => 'institution_settlement_fordwatch',
        'display_name' => 'Fordwatch',
        'normalized_label' => 'fordwatch',
      ],
    ]);

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);

    $form = $this->buildFormObject(
      $this->createMock(CharacterManager::class),
      $this->buildGeneralFeatLibraryMock(),
      $database,
      70,
      $registry
    );
    $form_state = (new FormState())->setValues([
      'alignment' => 'NG',
      'general_feat' => 'toughness',
      'home_settlement_ref' => '__create__',
      'home_settlement_ref__create_labels' => 'Fordwatch',
    ]);
    $form_state->set('step', 6);
    $form_state->set('campaign_id', 70);
    $form_state->set('character_id', 0);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertArrayHasKey('home_settlement_ref__create_labels', $form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormDoesNotRequireCreateLabelsWhenCreateSentinelIsSelectedAlone(): void {
    $database = $this->buildSubjectRegistryDatabaseMock(TRUE, []);
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('isSubjectRegistryReady')->willReturn(TRUE);

    $form = $this->buildFormObject(
      $this->createMock(CharacterManager::class),
      $this->buildGeneralFeatLibraryMock(),
      $database,
      70,
      $registry
    );
    $form_state = (new FormState())->setValues([
      'alignment' => 'NG',
      'general_feat' => 'toughness',
      'home_settlement_ref' => '__create__',
      'government_ref' => '__create__',
      'security_affiliation_refs' => ['__create__'],
      'home_settlement_ref__create_labels' => '',
      'government_ref__create_labels' => '',
      'security_affiliation_refs__create_labels' => '',
    ]);
    $form_state->set('step', 6);
    $form_state->set('campaign_id', 70);
    $form_state->set('character_id', 0);

    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertArrayNotHasKey('home_settlement_ref__create_labels', $form_state->getErrors());
    $this->assertArrayNotHasKey('government_ref__create_labels', $form_state->getErrors());
    $this->assertArrayNotHasKey('security_affiliation_refs__create_labels', $form_state->getErrors());
  }

  /**
   * @covers ::resolveStructuredAffiliationCreations
   */
  public function testResolveStructuredAffiliationCreationsAppendsCreatedSubjectIds(): void {
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->with(
        70,
        $this->callback(static function (array $input): bool {
          return ($input['domain'] ?? '') === 'settlement'
            && ($input['display_name'] ?? '') === 'Moon Harbor'
            && ($input['metadata']['created_via'] ?? '') === 'character_creation_step6';
        })
      )
      ->willReturn([
        'subject_id' => 'institution_settlement_moon-harbor',
      ]);

    $form = $this->buildFormObject(
      $this->createMock(CharacterManager::class),
      $this->createMock(FeatLibraryService::class),
      $this->buildSubjectRegistryDatabaseMock(FALSE),
      70,
      $registry
    );
    $form_state = (new FormState())->setValues([
      'home_settlement_ref' => '__create__',
      'home_settlement_ref__create_labels' => 'Moon Harbor',
      'home_settlement_ref__create_note' => 'Authored during character setup',
    ]);
    $character_data = [];

    $method = new \ReflectionMethod($form, 'resolveStructuredAffiliationCreations');
    $method->setAccessible(TRUE);
    $method->invokeArgs($form, [$form_state, &$character_data, 70, 0]);

    $this->assertSame('institution_settlement_moon-harbor', $character_data['home_settlement_ref']);
  }

  /**
   * @covers ::saveCharacter
   */
  public function testSaveCharacterSyncsCampaignMembershipsForCampaignBoundRecord(): void {
    $schema_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 8,
      'class' => 'wizard',
      'government_ref' => 'institution_government_free-city',
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ];
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->once())
      ->method('canonicalizeCharacterData')
      ->with($schema_data)
      ->willReturn($schema_data);
    $character_manager->expects($this->once())
      ->method('extractHotColumnsFromData')
      ->with($this->callback(static function (array $input): bool {
        return ($input['name'] ?? NULL) === 'Meris'
          && ($input['government_ref'] ?? NULL) === 'institution_government_free-city'
          && isset($input['created_at'], $input['updated_at']);
      }))
      ->willReturn([
        'hp_current' => 12,
        'hp_max' => 12,
        'armor_class' => 16,
      ]);
    $character_manager->expects($this->once())
      ->method('loadCharacter')
      ->with(77)
      ->willReturn((object) [
        'campaign_id' => 70,
        'instance_id' => 'pc-meris-77',
      ]);

    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_characters')
      ->willReturn(new class() {
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
      });

    $institution_membership = $this->createMock(InstitutionMembershipService::class);
    $institution_membership->expects($this->once())
      ->method('syncCampaignCharacterMemberships')
      ->with(
        70,
        'pc-meris-77',
        $this->callback(static function (array $input): bool {
          return ($input['name'] ?? NULL) === 'Meris'
            && ($input['government_ref'] ?? NULL) === 'institution_government_free-city'
            && isset($input['created_at'], $input['updated_at']);
        })
      );

    $form = $this->buildFormObject(
      $character_manager,
      $this->createMock(FeatLibraryService::class),
      $database,
      70,
      $this->createMock(CampaignSubjectRegistryService::class),
      new InstitutionNormalizationService(),
      $this->createMock(FactionGenerationService::class),
      $institution_membership
    );

    $method = new \ReflectionMethod($form, 'saveCharacter');
    $method->setAccessible(TRUE);

    $saved_character_id = $method->invoke($form, 77, $schema_data, 4, 70);

    $this->assertSame(77, $saved_character_id);
  }

  /**
   * @covers ::saveCharacter
   */
  public function testSaveCharacterResolvesStructuredAffiliationsInsideTransaction(): void {
    $order = [];
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->once())
      ->method('canonicalizeCharacterData')
      ->with($this->callback(static function (array $input): bool {
        return ($input['home_settlement_ref'] ?? NULL) === 'institution_settlement_moon-harbor';
      }))
      ->willReturnCallback(static fn(array $input): array => $input);
    $character_manager->expects($this->once())
      ->method('extractHotColumnsFromData')
      ->willReturn([
        'hp_current' => 12,
        'hp_max' => 12,
        'armor_class' => 16,
      ]);
    $character_manager->expects($this->once())
      ->method('loadCharacter')
      ->with(77)
      ->willReturn((object) [
        'campaign_id' => 70,
        'instance_id' => 'pc-meris-77',
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
    $database->expects($this->once())
      ->method('update')
      ->with('dc_campaign_characters')
      ->willReturn(new class() {
        public function fields(array $fields): self { return $this; }
        public function condition(string $field, mixed $value): self { return $this; }
        public function execute(): int { return 1; }
      });

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $request) use (&$order): array {
        $order[] = 'resolve';
        return ['subject_id' => 'institution_settlement_moon-harbor'];
      });

    $form = $this->buildFormObject(
      $character_manager,
      $this->createMock(FeatLibraryService::class),
      $database,
      70,
      $registry,
      new InstitutionNormalizationService(),
      $this->createMock(FactionGenerationService::class),
      $this->createMock(InstitutionMembershipService::class)
    );

    $form_state = (new FormState())->setValues([
      'home_settlement_ref' => '__create__',
      'home_settlement_ref__create_labels' => 'Moon Harbor',
    ]);
    $character_data = [
      'name' => 'Meris',
      'level' => 1,
      'step' => 6,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ];

    $method = new \ReflectionMethod($form, 'saveCharacter');
    $method->setAccessible(TRUE);
    $method->invoke($form, 77, $character_data, 4, 70, $form_state, 6);

    $this->assertSame(['transaction', 'resolve'], array_slice($order, 0, 2));
  }

  /**
   * @covers ::saveCharacter
   */
  public function testSaveCharacterUsesStoredCampaignForStructuredAffiliations(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('canonicalizeCharacterData')
      ->willReturnCallback(static fn(array $input): array => $input);
    $character_manager->method('extractHotColumnsFromData')
      ->willReturn([
        'hp_current' => 12,
        'hp_max' => 12,
        'armor_class' => 16,
      ]);
    $character_manager->expects($this->once())
      ->method('loadCharacter')
      ->with(77)
      ->willReturn((object) [
        'campaign_id' => 70,
        'instance_id' => 'pc-meris-77',
      ]);

    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $database->method('update')->willReturn(new class() {
      public function fields(array $fields): self { return $this; }
      public function condition(string $field, mixed $value): self { return $this; }
      public function execute(): int { return 1; }
    });

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->with(
        70,
        $this->callback(static function (array $request): bool {
          return ($request['display_name'] ?? NULL) === 'Moon Harbor'
            && ($request['source_asset_id'] ?? NULL) === '77';
        })
      )
      ->willReturn(['subject_id' => 'institution_settlement_moon-harbor']);

    $form = $this->buildFormObject(
      $character_manager,
      $this->createMock(FeatLibraryService::class),
      $database,
      12,
      $registry,
      new InstitutionNormalizationService(),
      $this->createMock(FactionGenerationService::class),
      $this->createMock(InstitutionMembershipService::class)
    );

    $form_state = (new FormState())->setValues([
      'home_settlement_ref' => '__create__',
      'home_settlement_ref__create_labels' => 'Moon Harbor',
    ]);

    $method = new \ReflectionMethod($form, 'saveCharacter');
    $method->setAccessible(TRUE);
    $method->invoke($form, 77, [
      'name' => 'Meris',
      'level' => 1,
      'step' => 6,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ], 4, 12, $form_state, 6);
  }

  /**
   * @covers ::saveCharacter
   */
  public function testSaveCharacterUsesRequestedCampaignForUnboundDraftStructuredAffiliations(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('canonicalizeCharacterData')
      ->willReturnCallback(static fn(array $input): array => $input);
    $character_manager->method('extractHotColumnsFromData')
      ->willReturn([
        'hp_current' => 12,
        'hp_max' => 12,
        'armor_class' => 16,
      ]);
    $character_manager->expects($this->once())
      ->method('loadCharacter')
      ->with(77)
      ->willReturn((object) [
        'campaign_id' => 0,
        'instance_id' => 'pc-meris-77',
      ]);

    $update_query = new class() {
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
    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new class() {
      public function rollBack(): void {}
    });
    $database->method('update')->with('dc_campaign_characters')->willReturn($update_query);

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->expects($this->once())
      ->method('resolveOrCreateInstitutionSubject')
      ->with(
        70,
        $this->callback(static function (array $request): bool {
          return ($request['display_name'] ?? NULL) === 'Moon Harbor';
        })
      )
      ->willReturn(['subject_id' => 'institution_settlement_moon-harbor']);

    $form = $this->buildFormObject(
      $character_manager,
      $this->createMock(FeatLibraryService::class),
      $database,
      70,
      $registry,
      new InstitutionNormalizationService(),
      $this->createMock(FactionGenerationService::class),
      $this->createMock(InstitutionMembershipService::class)
    );

    $form_state = (new FormState())->setValues([
      'home_settlement_ref' => '__create__',
      'home_settlement_ref__create_labels' => 'Moon Harbor',
    ]);

    $method = new \ReflectionMethod($form, 'saveCharacter');
    $method->setAccessible(TRUE);
    $method->invoke($form, 77, [
      'name' => 'Meris',
      'level' => 1,
      'step' => 6,
      'position' => ['q' => 0, 'r' => 0, 'room_id' => ''],
    ], 4, 70, $form_state, 6);

    $this->assertSame(70, $update_query->fields['campaign_id'] ?? NULL);
  }

  /**
   * @covers ::resolveSaveCharacterContext
   */
  public function testResolveSaveCharacterContextPrefersStoredCampaignBinding(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->once())
      ->method('loadCharacter')
      ->with(77)
      ->willReturn((object) [
        'campaign_id' => 70,
        'instance_id' => 'pc-meris-77',
      ]);

    $form = $this->buildFormObject($character_manager, NULL, NULL, 12);
    $method = new \ReflectionMethod($form, 'resolveSaveCharacterContext');
    $method->setAccessible(TRUE);

    $context = $method->invoke($form, 77, 12);

    $this->assertSame(70, $context['resolved_campaign_id']);
    $this->assertSame('pc-meris-77', $context['instance_id']);
    $this->assertNotNull($context['existing_record']);
  }

  /**
   * @covers ::resolveSaveCharacterContext
   */
  public function testResolveSaveCharacterContextUsesRequestedCampaignForNewRecord(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->never())
      ->method('loadCharacter');

    $form = $this->buildFormObject($character_manager, NULL, NULL, 12);
    $method = new \ReflectionMethod($form, 'resolveSaveCharacterContext');
    $method->setAccessible(TRUE);

    $context = $method->invoke($form, NULL, 12);

    $this->assertSame(12, $context['resolved_campaign_id']);
    $this->assertSame('', $context['instance_id']);
    $this->assertNull($context['existing_record']);
  }

  /**
   * @covers ::resolveEffectiveCampaignId
   */
  public function testResolveEffectiveCampaignIdPrefersStoredBinding(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class), NULL, NULL, 12);
    $method = new \ReflectionMethod($form, 'resolveEffectiveCampaignId');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($form, (object) ['campaign_id' => 70], 12);

    $this->assertSame(70, $resolved);
  }

  /**
   * @covers ::resolveEffectiveCampaignId
   */
  public function testResolveEffectiveCampaignIdUsesRequestForUnboundDraft(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class), NULL, NULL, 12);
    $method = new \ReflectionMethod($form, 'resolveEffectiveCampaignId');
    $method->setAccessible(TRUE);

    $resolved = $method->invoke($form, (object) ['campaign_id' => 0], 12);

    $this->assertSame(12, $resolved);
  }

  /**
   * @covers ::loadCharacterData
   */
  public function testLoadCharacterDataPrefersCanonicalTopLevelPayloadOverStaleWizardDraft(): void {
    $record = (object) [
      'uid' => 0,
      'character_data' => json_encode([
        'basicInfo' => [
          'name' => 'Burasco',
          'level' => 1,
          'experiencePoints' => 0,
          'ancestry' => 'human',
          'heritage' => 'versatile',
          'background' => 'scholar',
          'class' => 'wizard',
          'appearance' => 'Hooded cloak',
          'personality' => '',
          'backstory' => '',
        ],
        'resources' => [
          'hitPoints' => ['current' => 8, 'max' => 8, 'temporary' => 0],
          'heroPoints' => ['current' => 1, 'max' => 3],
        ],
        'defenses' => [
          'armorClass' => 10,
          'fortitude' => 3,
          'reflex' => 3,
          'will' => 3,
        ],
        'step' => 8,
        'wizard' => [
          'step' => 2,
          'ancestry' => '',
          'background' => '',
          'class' => '',
        ],
      ], JSON_PRETTY_PRINT),
    ];

    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('id')->willReturn(0);

    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->method('loadCharacter')
      ->with(1016)
      ->willReturn($record);

    $form = $this->buildFormObject($character_manager);
    $ref = new \ReflectionProperty($form, 'currentUser');
    $ref->setValue($form, $current_user);

    $method = new \ReflectionMethod($form, 'loadCharacterData');
    $method->setAccessible(TRUE);
    $loaded = $method->invoke($form, 1016);

    $this->assertSame('human', $loaded['ancestry']);
    $this->assertSame('scholar', $loaded['background']);
    $this->assertSame('wizard', $loaded['class']);
    $this->assertSame(8, $loaded['step']);
  }

  /**
   * @covers ::syncWizardDraftFromCharacterData
   */
  public function testSyncWizardDraftRebuildsMirrorFromCanonicalPayload(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class));
    $method = new \ReflectionMethod($form, 'syncWizardDraftFromCharacterData');
    $method->setAccessible(TRUE);

    $character_data = [
      'name' => 'Burasco',
      'step' => 8,
      'ancestry' => 'human',
      'class' => 'wizard',
      'basicInfo' => [
        'name' => 'Stale Nested Name',
      ],
      'resources' => [
        'hitPoints' => ['current' => 1, 'max' => 1, 'temporary' => 0],
      ],
      'defenses' => [
        'armorClass' => 11,
      ],
      'wizard' => [
        'name' => 'Stale Nested Name',
        'step' => 2,
        'stale_only' => 'should be dropped',
      ],
    ];

    $result = $method->invoke($form, $character_data);

    $this->assertSame('Burasco', $result['wizard']['name']);
    $this->assertSame(8, $result['wizard']['step']);
    $this->assertSame('human', $result['wizard']['ancestry']);
    $this->assertArrayNotHasKey('stale_only', $result['wizard']);
  }

  /**
   * @covers ::ensureCampaignCharacterHasCanonicalSource
   */
  public function testEnsureCampaignCharacterHasCanonicalSourceReplacesSelfLinkWithLibraryRow(): void {
    $character_manager = $this->createMock(CharacterManager::class);
    $character_manager->expects($this->once())
      ->method('loadCharacter')
      ->with(77)
      ->willReturn((object) [
        'id' => 77,
        'uid' => 9,
        'campaign_id' => 12,
        'character_id' => 77,
        'source_character_id' => 77,
        'instance_id' => 'pc-self-77',
        'role' => 'player',
        'type' => 'pc',
        'status' => 0,
        'portrait' => '/images/burasco.png',
        'default_locations' => json_encode(['room' => 'starter-room'], JSON_UNESCAPED_UNICODE),
        'location_type' => 'global',
        'location_ref' => '',
        'character_data' => json_encode([
          'name' => 'Burasco',
          'level' => 8,
          'step' => 8,
          'ancestry' => 'human',
          'class' => 'wizard',
        ]),
      ]);
    $character_manager->method('canonicalizeCharacterData')
      ->willReturnCallback(static fn(array $input): array => $input);
    $character_manager->method('extractHotColumnsFromData')
      ->willReturn([
        'hp_current' => 8,
        'hp_max' => 8,
        'armor_class' => 15,
      ]);

    $runtime_resolver = $this->createMock(CampaignCharacterRuntimeResolverService::class);
    $runtime_resolver->expects($this->once())
      ->method('resolveStarterRoomIdForCampaign')
      ->with(12)
      ->willReturn('starter-room');

    $insert_builder = NULL;
    $character_update_builder = NULL;
    $campaign_update_builder = NULL;
    $database = $this->createMock(Connection::class);
    $database->method('insert')
      ->willReturnCallback(static function (string $table) use (&$insert_builder): object {
        $insert_builder = new class() {
          public array $fields = [];

          public function fields(array $fields): self {
            $this->fields = $fields;
            return $this;
          }

          public function execute(): int {
            return 101;
          }
        };

        return $insert_builder;
      });
    $database->method('update')
      ->willReturnCallback(static function (string $table) use (&$character_update_builder, &$campaign_update_builder): object {
        if ($table === 'dc_campaign_characters') {
          $character_update_builder = new class() {
            public array $fields = [];
            public array $conditions = [];

            public function fields(array $fields): self {
              $this->fields = $fields;
              return $this;
            }

            public function condition(string $field, mixed $value): self {
              $this->conditions[] = [$field, $value];
              return $this;
            }

            public function execute(): int {
              return 1;
            }
          };

          return $character_update_builder;
        }

        if ($table === 'dc_campaigns') {
          $campaign_update_builder = new class() {
            public array $fields = [];
            public array $conditions = [];

            public function fields(array $fields): self {
              $this->fields = $fields;
              return $this;
            }

            public function condition(string $field, mixed $value): self {
              $this->conditions[] = [$field, $value];
              return $this;
            }

            public function execute(): int {
              return 1;
            }
          };

          return $campaign_update_builder;
        }

        throw new \LogicException(sprintf('Unexpected table %s', $table));
      });

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('library-instance-uuid');

    $form = $this->buildFormObject(
      $character_manager,
      NULL,
      $database,
      12,
      NULL,
      NULL,
      NULL,
      NULL,
      $runtime_resolver,
      $time,
      $uuid
    );

    $method = new \ReflectionMethod($form, 'ensureCampaignCharacterHasCanonicalSource');
    $method->setAccessible(TRUE);
    $method->invoke($form, 77, 12);

    $this->assertSame('library-instance-uuid', $insert_builder->fields['uuid'] ?? NULL);
    $this->assertSame(0, $insert_builder->fields['campaign_id'] ?? NULL);
    $this->assertSame(NULL, $insert_builder->fields['source_character_id'] ?? 'missing');
    $this->assertSame('/images/burasco.png', $insert_builder->fields['portrait'] ?? NULL);
    $this->assertSame('{"room":"starter-room"}', $insert_builder->fields['default_locations'] ?? NULL);
    $this->assertSame(101, $character_update_builder->fields['character_id'] ?? NULL);
    $this->assertSame(101, $character_update_builder->fields['source_character_id'] ?? NULL);
    $this->assertSame('starter-room', $character_update_builder->fields['last_room_id'] ?? NULL);
    $this->assertSame(101, $campaign_update_builder->fields['active_character_id'] ?? NULL);
  }

  private function buildFormObject(CharacterManager $character_manager, ?FeatLibraryService $feat_library = NULL, ?Connection $database = NULL, int $campaign_id = 70, ?CampaignSubjectRegistryService $campaign_subject_registry = NULL, ?InstitutionNormalizationService $institution_normalization = NULL, ?FactionGenerationService $faction_generation = NULL, ?InstitutionMembershipService $institution_membership = NULL, ?CampaignCharacterRuntimeResolverService $runtime_resolver = NULL, ?TimeInterface $time = NULL, ?UuidInterface $uuid = NULL): CharacterCreationStepForm {
    $database ??= $this->buildSubjectRegistryDatabaseMock(FALSE);
    $campaign_subject_registry ??= $this->createMock(CampaignSubjectRegistryService::class);
    $institution_normalization ??= new InstitutionNormalizationService();
    $faction_generation ??= $this->createMock(FactionGenerationService::class);
    $institution_membership ??= $this->createMock(InstitutionMembershipService::class);
    $runtime_resolver ??= $this->createMock(CampaignCharacterRuntimeResolverService::class);
    $ability_score_tracker = $this->createMock(AbilityScoreTracker::class);
    $ability_score_tracker->method('calculateAbilityScores')->willReturn([
      'scores' => [
        'strength' => 10,
        'dexterity' => 10,
        'constitution' => 10,
        'intelligence' => 10,
        'wisdom' => 10,
        'charisma' => 10,
      ],
      'modifiers' => [
        'strength' => 0,
        'dexterity' => 0,
        'constitution' => 0,
        'intelligence' => 0,
        'wisdom' => 0,
        'charisma' => 0,
      ],
      'sources' => [],
    ]);
    $time ??= $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $uuid ??= $this->createMock(UuidInterface::class);
    $current_user = $this->createMock(AccountProxyInterface::class);

    $wizard_hardening = new CharacterWizardHardeningService(
      $character_manager,
      $database,
      $runtime_resolver,
      $time,
      $uuid,
      $current_user,
    );

    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/charactersetup', 'GET', ['campaign_id' => $campaign_id]));
    $container = new ContainerBuilder();
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);

    $form = new CharacterCreationStepForm(
      $character_manager,
      $this->createMock(SchemaLoader::class),
      $database,
      $uuid,
      $current_user,
      $this->createMock(DateFormatterInterface::class),
      $time,
      $this->createMock(CharacterPortraitGenerationService::class),
      $ability_score_tracker,
      $this->createMock(ImageGenerationIntegrationService::class),
      $this->createMock(CharacterCreationGmService::class),
      $feat_library ?? $this->createMock(FeatLibraryService::class),
      $this->createMock(CsrfTokenGenerator::class),
      $campaign_subject_registry,
      $institution_membership,
      $institution_normalization,
      $faction_generation,
      $runtime_resolver,
      $wizard_hardening,
    );

    $form->setStringTranslation($this->getStringTranslationStub());

    return $form;
  }

  private function buildGeneralFeatLibraryMock(): FeatLibraryService {
    $feat_library = $this->createMock(FeatLibraryService::class);
    $feat_library->method('getGeneralFeats')->willReturn([
      ['id' => 'toughness', 'name' => 'Toughness'],
    ]);
    return $feat_library;
  }

  private function buildSubjectRegistryDatabaseMock(bool $table_exists, array $rows = []): Connection {
    $database = $this->createMock(Connection::class);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturnCallback(static function (string $table) use ($table_exists): bool {
      return $table === 'dc_campaign_subject_registry' ? $table_exists : FALSE;
    });
    $database->method('schema')->willReturn($schema);

    if ($table_exists) {
      $database->method('select')
        ->willReturnCallback(static function (string $table, string $alias) use ($rows): object {
          return new class($table, $alias, $rows) {
            private array $conditions = [];

            public function __construct(
              private string $table,
              private string $alias,
              private array $rows,
            ) {}

            public function fields(string $table_alias, array $fields = []): static {
              return $this;
            }

            public function condition(string $field, mixed $value, ?string $operator = NULL): static {
              $this->conditions[] = [$field, $value, $operator];
              return $this;
            }

            public function orderBy(string $field, string $direction = 'ASC'): static {
              return $this;
            }

            public function execute(): object {
              $rows = $this->rows;
              foreach ($this->conditions as [$field, $value, $operator]) {
                $rows = array_values(array_filter($rows, static function (array $row) use ($field, $value, $operator): bool {
                  $candidate = $row[$field] ?? NULL;
                  if ($operator === 'IN' && is_array($value)) {
                    return in_array($candidate, $value, TRUE);
                  }
                  return $candidate === $value;
                }));
              }

              usort($rows, static function (array $left, array $right): int {
                $left_key = ($left['subject_domain'] ?? '') . ':' . ($left['display_name'] ?? '');
                $right_key = ($right['subject_domain'] ?? '') . ':' . ($right['display_name'] ?? '');
                return strcmp($left_key, $right_key);
              });

              return new class($rows) {
                public function __construct(private array $rows) {}

                public function fetchAll(int $mode): array {
                  return $this->rows;
                }
              };
            }
          };
        });
    }

    return $database;
  }

}
