<?php

namespace Drupal\dungeoncrawler_tester\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a documentation & utilities block for the Dungeon Crawler tester module.
 *
 * @Block(
 *   id = "dungeoncrawler_tester_nav_block",
 *   admin_label = @Translation("Dungeon Crawler Testing Navigation")
 * )
 */
class TesterNavBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Dedicated testing menu machine name.
   */
  private const TESTING_MENU_NAME = 'dungeoncrawler_testing';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MenuLinkTreeInterface $menuLinkTree,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $parameters = $this->menuLinkTree->getCurrentRouteMenuTreeParameters(self::TESTING_MENU_NAME);
    $parameters->setMaxDepth(3);

    $tree = $this->menuLinkTree->load(self::TESTING_MENU_NAME, $parameters);
    $tree = $this->menuLinkTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $menuBuild = $this->menuLinkTree->build($tree);
    $menuBuild['#title'] = $this->t('Testing Navigation');
    $menuBuild['#attributes']['class'][] = 'dungeoncrawler-tester-nav-block';

    return $menuBuild;
  }

}
