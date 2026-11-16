<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\dungeoncrawler_content\Form\DungeonCrawlerSettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('dungeoncrawler_content')]
class DiscordSettingsValidationTest extends UnitTestCase {

  private function errors(array $values): array {
    $form = [];
    $state = (new FormState())->setValues($values);
    $settings = new DungeonCrawlerSettingsForm(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(TypedConfigManagerInterface::class),
    );
    $settings->setStringTranslation($this->getStringTranslationStub());
    $settings->validateForm($form, $state);
    $errors = $state->getErrors();
    $state->clearErrors();
    return $errors;
  }

  public function testOptionalDiscordConfigurationAndValidIds(): void {
    $this->assertSame([], $this->errors([]));
    $this->assertSame([], $this->errors([
      'discord_guild_id' => ' 123456789012345678 ',
      'discord_voice_category_id' => '12345678901234567890',
    ]));
  }

  public function testInvalidIdsFailServerValidation(): void {
    foreach (['not-an-id', '-1', '123456789012345678901', '1.5'] as $id) {
      $errors = $this->errors([
        'discord_guild_id' => $id,
        'discord_voice_category_id' => $id,
      ]);
      $this->assertArrayHasKey('discord_guild_id', $errors);
      $this->assertArrayHasKey('discord_voice_category_id', $errors);
    }
  }

  public function testCategoryRequiresItsServer(): void {
    $errors = $this->errors(['discord_voice_category_id' => '123456789012345678']);
    $this->assertArrayHasKey('discord_guild_id', $errors);
    $this->assertStringContainsString('before choosing a voice channel category', (string) $errors['discord_guild_id']);
  }

}
