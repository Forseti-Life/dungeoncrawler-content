<?php

namespace Drupal\theory_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Footer Bottom' Block.
 *
 * @Block(
 *   id = "footer_bottom_block",
 *   admin_label = @Translation("Footer Bottom Block"),
 *   category = @Translation("Theory of Conspiracies"),
 * )
 */
class FooterBottomBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'footer_bottom_block',
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}