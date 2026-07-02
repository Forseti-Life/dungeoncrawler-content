<?php

namespace Drupal\professional_website_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a Professional Services Overview block.
 */
#[Block(
  id: 'professional_services_overview_block',
  admin_label: new TranslatableMarkup('Professional Services Overview'),
  category: new TranslatableMarkup('Professional Website')
)]
class ProfessionalServicesOverviewBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Define the services overview sections
    $overview_sections = [
      [
        'title' => $this->t('Our Services'),
        'description' => $this->t('Discover our comprehensive AI and data engineering solutions tailored for your industry.'),
        'url' => Url::fromRoute('professional_website_content.services'),
        'icon_class' => 'bi bi-gear-fill',
        'button_text' => $this->t('View Services'),
        'color_scheme' => 'primary',
      ],
      [
        'title' => $this->t('Case Studies'),
        'description' => $this->t('Learn how we\'ve helped Fortune 500 companies achieve measurable business outcomes.'),
        'url' => Url::fromUserInput('/case-studies'),
        'icon_class' => 'bi bi-bar-chart-line-fill',
        'button_text' => $this->t('Read Case Studies'),
        'color_scheme' => 'success',
      ],
      [
        'title' => $this->t('Get Started'),
        'description' => $this->t('Ready to transform your business? Let\'s discuss your specific requirements.'),
        'url' => Url::fromUserInput('/contact-us'),
        'icon_class' => 'bi bi-rocket-takeoff-fill',
        'button_text' => $this->t('Contact Us'),
        'color_scheme' => 'info',
      ],
    ];

    $build['professional_services_overview'] = [
      '#theme' => 'professional_services_overview',
      '#overview_sections' => $overview_sections,
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache for 1 hour
    return 3600;
  }

}