<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\GameplayActionProcessor;
use Drupal\dungeoncrawler_content\Service\NarrationEngine;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Covers NarrationEngine service wiring safety.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\NarrationEngine
 */
class NarrationEngineWiringTest extends UnitTestCase {

  /**
   * Tests the constructor keeps the explicit number-generation handoff.
   *
   * @covers ::__construct
   */
  public function testConstructorUsesExplicitNumberGenerationDependency(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')
      ->with('dungeoncrawler_narration')
      ->willReturn($logger);
    $numberGeneration = $this->createMock(NumberGenerationService::class);

    $engine = new NarrationEngine(
      $this->createMock(Connection::class),
      $loggerFactory,
      $this->createMock(ChatSessionManager::class),
      $this->createMock(AIApiService::class),
      $this->createMock(PromptManager::class),
      $this->createMock(GameplayActionProcessor::class),
      $numberGeneration
    );

    $property = new \ReflectionProperty(NarrationEngine::class, 'numberGeneration');
    $property->setAccessible(TRUE);

    $this->assertSame($numberGeneration, $property->getValue($engine));
  }

  /**
   * Tests the service definition matches the constructor signature exactly.
   */
  public function testServiceDefinitionMatchesConstructorDependencies(): void {
    $servicesPath = dirname(__DIR__, 4) . '/dungeoncrawler_content.services.yml';
    $parsed = Yaml::parseFile($servicesPath);
    $serviceDefinition = $parsed['services']['dungeoncrawler_content.narration_engine'] ?? NULL;

    $this->assertIsArray($serviceDefinition);
    $this->assertSame([
      '@database',
      '@logger.factory',
      '@dungeoncrawler_content.chat_session_manager',
      '@ai_conversation.ai_api_service',
      '@ai_conversation.prompt_manager',
      '@dungeoncrawler_content.gameplay_action_processor',
      '@dungeoncrawler_content.number_generation',
    ], $serviceDefinition['arguments'] ?? NULL);

    $constructor = new \ReflectionMethod(NarrationEngine::class, '__construct');
    $this->assertCount(count($serviceDefinition['arguments']), $constructor->getParameters());
  }

}
