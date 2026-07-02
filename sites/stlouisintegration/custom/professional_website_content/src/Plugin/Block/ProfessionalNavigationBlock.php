<?php

namespace Drupal\professional_website_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a Professional Website Navigation block.
 */
#[Block(
  id: 'professional_navigation_block',
  admin_label: new TranslatableMarkup('Professional Website Navigation'),
  category: new TranslatableMarkup('Professional Website')
)]
class ProfessionalNavigationBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Define the navigation menu structure
    $menu_items = [
      [
        'title' => $this->t('About Us'),
        'url' => Url::fromRoute('professional_website_content.about'),
        'class' => 'nav-item dropdown',
        'children' => [
          [
            'title' => $this->t('About Us'),
            'url' => Url::fromRoute('professional_website_content.about'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Leadership'),
            'url' => Url::fromRoute('professional_website_content.leadership'),
            'class' => 'dropdown-item',
          ],
        ],
      ],
      [
        'title' => $this->t('Services'),
        'url' => Url::fromRoute('professional_website_content.services'),
        'class' => 'nav-item dropdown',
        'children' => [
          [
            'title' => $this->t('Services'),
            'url' => Url::fromRoute('professional_website_content.services'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('AI & Machine Learning'),
            'url' => Url::fromRoute('professional_website_content.ai_machine_learning'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Data Engineering & Architecture'),
            'url' => Url::fromRoute('professional_website_content.data_engineering'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Product Prototyping'),
            'url' => Url::fromRoute('professional_website_content.product_prototyping'),
            'class' => 'dropdown-item',
          ],
        ],
      ],
      [
        'title' => $this->t('Industries'),
        'url' => Url::fromRoute('<none>'),
        'class' => 'nav-item dropdown',
        'children' => [
          [
            'title' => $this->t('FinTech'),
            'url' => Url::fromRoute('professional_website_content.fintech'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Healthcare'),
            'url' => Url::fromRoute('professional_website_content.healthcare'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Energy'),
            'url' => Url::fromRoute('professional_website_content.energy'),
            'class' => 'dropdown-item',
          ],
        ],
      ],
      [
        'title' => $this->t('Case Studies'),
        'url' => Url::fromUserInput('/case-studies'),
        'class' => 'nav-item',
        'children' => [],
      ],
      [
        'title' => $this->t('Contact'),
        'url' => Url::fromUserInput('/contact-us'),
        'class' => 'nav-item',
        'children' => [],
      ],
    ];

    // Add Job Hunter link only if route exists and user is authenticated
    $route_provider = \Drupal::service('router.route_provider');
    $access_manager = \Drupal::service('access_manager');
    $current_user = \Drupal::currentUser();
    
    try {
      $route_provider->getRouteByName('job_hunter.dashboard');
      // Check if user is authenticated (not anonymous)
      if ($current_user->isAuthenticated()) {
        $menu_items[] = [
          'title' => $this->t('Job Hunter'),
          'url' => Url::fromRoute('job_hunter.dashboard'),
          'class' => 'nav-item',
          'children' => [],
        ];
      }
    }
    catch (\Exception $e) {
      // Route doesn't exist, skip Job Hunter link
    }

    // Add AI Chat link only if route exists and user has admin permission
    try {
      $route_provider->getRouteByName('ai_conversation.start_chat');
      // Check if user has the admin permission directly
      if ($current_user->hasPermission('administer site configuration')) {
        $menu_items[] = [
          'title' => $this->t('AI Chat'),
          'url' => Url::fromRoute('ai_conversation.start_chat'),
          'class' => 'nav-item',
          'children' => [],
        ];
      }
    }
    catch (\Exception $e) {
      // Route doesn't exist or access denied, skip AI Chat link
    }

    $build['professional_navigation'] = [
      '#theme' => 'professional_navigation_menu',
      '#menu_items' => $menu_items,
      '#cache' => [
        'contexts' => ['route', 'user.permissions'],
      ],
    ];

    return $build;
  }

  /**
   * Helper function to get node URL by title.
   *
   * @param string $title
   *   The node title to search for.
   *
   * @return \Drupal\Core\Url
   *   The URL object for the node, or front page if not found.
   */
  protected function getNodeUrlByTitle($title) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', $title)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);

    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      return Url::fromRoute('entity.node.canonical', ['node' => $nid]);
    }

    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache for 1 hour
    return 3600;
  }

}