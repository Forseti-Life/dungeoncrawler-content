<?php

namespace Drupal\job_application_automation\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Provides a 'Job Hunter Navigation' Block.
 *
 * @Block(
 *   id = "job_hunter_navigation",
 *   admin_label = @Translation("Job Hunter Navigation"),
 *   category = @Translation("Job Application Automation"),
 * )
 */
class JobHunterNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new JobHunterNavigationBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $navigation = [
      'home' => [
        'title' => $this->t('Dashboard'),
        'url' => Url::fromRoute('job_application_automation.dashboard'),
        'icon' => 'home',
        'weight' => 0,
      ],
      'profile' => [
        'title' => $this->t('My Profile'),
        'url' => Url::fromRoute('job_application_automation.user_job_seeker_view'),
        'icon' => 'user',
        'weight' => 10,
      ],
      'job_discovery' => [
        'title' => $this->t('Job Discovery'),
        'url' => Url::fromRoute('job_application_automation.start_job_discovery'),
        'icon' => 'search',
        'weight' => 20,
      ],
      'companies' => [
        'title' => $this->t('Companies'),
        'url' => Url::fromRoute('job_application_automation.companies_overview'),
        'icon' => 'building',
        'weight' => 30,
      ],
      'target_companies' => [
        'title' => $this->t('Target Companies'),
        'url' => Url::fromRoute('job_application_automation.manage_target_companies'),
        'icon' => 'target',
        'weight' => 40,
      ],
      'bulk_import' => [
        'title' => $this->t('Bulk Import'),
        'url' => Url::fromRoute('job_application_automation.bulk_import_companies'),
        'icon' => 'upload',
        'weight' => 50,
      ],
      'add_company' => [
        'title' => $this->t('Add Company'),
        'url' => Url::fromRoute('node.add', ['node_type' => 'company']),
        'icon' => 'plus',
        'weight' => 60,
      ],
      'add_job_posting' => [
        'title' => $this->t('Add Job Posting'),
        'url' => Url::fromRoute('node.add', ['node_type' => 'job_posting']),
        'icon' => 'plus-circle',
        'weight' => 70,
      ],
      'documentation' => [
        'title' => $this->t('Documentation'),
        'url' => Url::fromRoute('job_application_automation.documentation'),
        'icon' => 'book',
        'weight' => 80,
      ],
    ];

    // Add admin links if user has permission
    if ($this->currentUser->hasPermission('administer job application automation')) {
      $navigation['settings'] = [
        'title' => $this->t('Settings'),
        'url' => Url::fromRoute('job_application_automation.settings'),
        'icon' => 'cog',
        'weight' => 100,
      ];
    }

    // Sort by weight
    uasort($navigation, function($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    return [
      '#theme' => 'job_hunter_navigation',
      '#navigation' => $navigation,
      '#attached' => [
        'library' => [
          'job_application_automation/job-hunter-navigation',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

}
