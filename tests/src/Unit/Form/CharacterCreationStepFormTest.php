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
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
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

  private function buildFormObject(CharacterManager $character_manager): CharacterCreationStepForm {
    $database = $this->createMock(Connection::class);
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);
    $database->method('schema')->willReturn($schema);

    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/charactersetup', 'GET', ['campaign_id' => 70]));
    $container = new ContainerBuilder();
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);

    $form = new CharacterCreationStepForm(
      $character_manager,
      $this->createMock(SchemaLoader::class),
      $database,
      $this->createMock(UuidInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(CharacterPortraitGenerationService::class),
      $this->createMock(AbilityScoreTracker::class),
      $this->createMock(ImageGenerationIntegrationService::class),
      $this->createMock(CharacterCreationGmService::class),
      $this->createMock(CsrfTokenGenerator::class),
    );

    $form->setStringTranslation($this->getStringTranslationStub());

    return $form;
  }

}
