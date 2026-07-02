<?php

namespace Drupal\Tests\dungeoncrawler_tester\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_tester\Service\GithubIssuePrClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for GithubIssuePrClient mutation dedupe behavior.
 *
 * @group dungeoncrawler_tester
 * @coversDefaultClass \Drupal\dungeoncrawler_tester\Service\GithubIssuePrClient
 */
class GithubIssuePrClientTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->state = $this->createMock(StateInterface::class);

    $this->loggerFactory
      ->method('get')
      ->with('dungeoncrawler_tester')
      ->willReturn($this->logger);

    $emptyConfig = $this->createMock(\Drupal\Core\Config\ImmutableConfig::class);
    $emptyConfig
      ->method('get')
      ->willReturn('');

    $this->configFactory
      ->method('get')
      ->willReturn($emptyConfig);

    $this->clearStateFiles();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->clearStateFiles();
    parent::tearDown();
  }

  /**
   * @covers ::mutate
   */
  public function testDuplicateCommentMutationIsSuppressed(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient
      ->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://api.github.com/repos/example/repo/issues/42/comments',
        $this->arrayHasKey('json')
      )
      ->willReturn(new Response(201, [], '{}'));

    $client = new GithubIssuePrClient(
      $httpClient,
      $this->configFactory,
      $this->state,
      $this->loggerFactory,
    );

    $first = $client->mutate(
      'POST',
      'https://api.github.com/repos/example/repo/issues/42/comments',
      ['body' => 'Duplicate-safe comment.'],
      'test-token',
      5,
    );

    $second = $client->mutate(
      'POST',
      'https://api.github.com/repos/example/repo/issues/42/comments',
      ['body' => 'Duplicate-safe comment.'],
      'test-token',
      5,
    );

    $this->assertTrue($first);
    $this->assertTrue($second);
  }

  /**
   * @covers ::mutate
   */
  public function testNonDedupeMutationStillExecutesRepeatedly(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient
      ->expects($this->exactly(2))
      ->method('request')
      ->with(
        'POST',
        'https://api.github.com/repos/example/repo/issues/42/assignees',
        $this->arrayHasKey('json')
      )
      ->willReturnOnConsecutiveCalls(
        new Response(201, [], '{}'),
        new Response(201, [], '{}'),
      );

    $client = new GithubIssuePrClient(
      $httpClient,
      $this->configFactory,
      $this->state,
      $this->loggerFactory,
    );

    $first = $client->mutate(
      'POST',
      'https://api.github.com/repos/example/repo/issues/42/assignees',
      ['assignees' => ['copilot']],
      'test-token',
      5,
    );

    $second = $client->mutate(
      'POST',
      'https://api.github.com/repos/example/repo/issues/42/assignees',
      ['assignees' => ['copilot']],
      'test-token',
      5,
    );

    $this->assertTrue($first);
    $this->assertTrue($second);
  }

  /**
   * Removes local state files used by client throttling/retry layers.
   */
  private function clearStateFiles(): void {
    $tmp = rtrim(sys_get_temp_dir(), '/\\');
    $paths = array_merge(
      glob($tmp . DIRECTORY_SEPARATOR . 'dungeoncrawler_tester_github_*') ?: [],
      [
        $tmp . DIRECTORY_SEPARATOR . 'dungeoncrawler_tester_github_mutation_dedupe.json',
        $tmp . DIRECTORY_SEPARATOR . 'dungeoncrawler_tester_github_cooldown.json',
        $tmp . DIRECTORY_SEPARATOR . 'dungeoncrawler_tester_github_mutation.lock',
      ],
    );

    foreach ($paths as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }
  }

}
