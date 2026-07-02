<?php

namespace Drupal\theory_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Site Footer' Block.
 *
 * @Block(
 *   id = "site_footer_block",
 *   admin_label = @Translation("Site Footer Block"),
 *   category = @Translation("Theory of Conspiracies"),
 * )
 */
class SiteFooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'site_footer_block',
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}