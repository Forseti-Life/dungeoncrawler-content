<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Controller\CharacterCreationStepController;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group dungeoncrawler_content
 */
class CharacterCreationStepControllerTest extends TestCase {

  private function controller(bool $csrf_valid = TRUE, ?object $record = NULL): CharacterCreationStepController {
    $manager = $this->createMock(CharacterManager::class);
    $manager->method('loadCharacter')->willReturn($record);
    $manager->expects($this->never())->method('updateCharacter');
    $csrf = $this->createMock(CsrfTokenGenerator::class);
    $csrf->method('validate')->willReturn($csrf_valid);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    \Drupal::setContainer($container);
    return new CharacterCreationStepController($manager, $csrf, $this->createMock(Connection::class));
  }

  public function testMissingCsrfCannotReachFormSubmission(): void {
    $response = $this->controller()->saveStep(1, new Request());
    $this->assertSame(403, $response->getStatusCode());
  }

  public function testCampaignScopedAjaxFlowStillRequiresTheCampaignWizard(): void {
    $request = new Request(['campaign_id' => 97]);
    $request->headers->set('X-CSRF-Token', 'valid-token');
    $this->assertSame(400, $this->controller()->saveStep(6, $request)->getStatusCode());
  }

  public function testStaleVersionCannotReachFormSubmission(): void {
    $record = (object) ['id' => 77, 'uid' => 7, 'campaign_id' => 0, 'version' => 4];
    $request = new Request([], ['character_id' => 77, 'character_version' => 3]);
    $request->headers->set('X-CSRF-Token', 'valid-token');
    $this->assertSame(409, $this->controller(TRUE, $record)->saveStep(1, $request)->getStatusCode());
  }

  public function testLegacySaveDelegatesToNativeFormInsteadOfAnotherMutationPath(): void {
    $controller = $this->controller();
    $builder = $this->createMock(FormBuilderInterface::class);
    $builder->expects($this->once())->method('submitForm')
      ->with(CharacterCreationStepForm::class, $this->isInstanceOf(FormStateInterface::class), 1, NULL, NULL)
      ->willReturnCallback(static function (string $class, FormStateInterface $state): void {
        $state->setErrorByName('name', 'Choose a name.');
      });
    \Drupal::getContainer()->set('form_builder', $builder);
    $request = new Request();
    $request->headers->set('X-CSRF-Token', 'valid-token');
    $response = $controller->saveStep(1, $request);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame(['name' => 'Choose a name.'], json_decode($response->getContent(), TRUE)['errors']);
  }

}
