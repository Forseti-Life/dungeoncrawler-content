<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
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
  public function testBuildQuickPlayButtonUsesShortcutSubmitHandler(): void {
    $form = $this->buildFormObject($this->createMock(CharacterManager::class));

    $method = new \ReflectionMethod($form, 'buildQuickPlayButton');
    $method->setAccessible(TRUE);
    $button = $method->invoke($form);

    $this->assertSame('submit', $button['#type']);
    $this->assertSame('quick_play', $button['#name']);
    $this->assertSame(['::quickPlaySubmit'], $button['#submit']);
    $this->assertSame([], $button['#limit_validation_errors']);
  }

  private function buildFormObject(CharacterManager $character_manager): CharacterCreationStepForm {
    $form = new CharacterCreationStepForm(
      $character_manager,
      $this->createMock(SchemaLoader::class),
      $this->createMock(Connection::class),
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
