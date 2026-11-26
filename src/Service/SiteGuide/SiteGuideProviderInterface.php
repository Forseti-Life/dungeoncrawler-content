<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\Core\Session\AccountInterface;

/**
 * Read-only knowledge and navigation contribution to the Site Guide.
 */
interface SiteGuideProviderInterface {

  public function id(): string;

  public function label(): string;

  /**
   * @return string[]
   */
  public function personas(AccountInterface $account, array $context): array;

  /**
   * @return array<int, array<string, mixed>>
   */
  public function navigation(AccountInterface $account, array $context): array;

  /**
   * @return array<int, array<string, mixed>>
   */
  public function resources(AccountInterface $account, array $context): array;

  /**
   * @return array<int, array<string, mixed>>
   */
  public function handoffs(AccountInterface $account, array $context): array;

}
