<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple toggle page for automation validation.
 */
class TheTestController extends ControllerBase {

  public function __construct(
    private StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('state'),
    );
  }

  /**
   * Render the /dungeoncrawler/testing/thetest page.
   */
  public function page(): array {
    $status = $this->resolveStatus();
    $text = $status === 'fail' ? $this->t('TEST:FAIL') : $this->t('TEST:PASS');

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['thetest-page']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $text,
        '#attributes' => ['class' => ['thetest-heading']],
      ],
      'desc' => [
        '#markup' => '<p>' . $this->t('Toggle this page to force the automation check to pass or fail.') . '</p>',
      ],
    ];

    return $build;
  }

  /**
   * Resolve TheTest status from env override, then state, then default.
   */
  private function resolveStatus(): string {
    $envStatus = strtolower((string) getenv('TESTER_THETEST_STATUS'));
    if (in_array($envStatus, ['pass', 'fail'], TRUE)) {
      return $envStatus;
    }

    $status = strtolower((string) $this->state->get('dungeoncrawler_tester.thetest_status', 'pass'));
    return in_array($status, ['pass', 'fail'], TRUE) ? $status : 'pass';
  }

}
