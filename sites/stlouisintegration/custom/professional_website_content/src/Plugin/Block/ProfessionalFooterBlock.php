<?php

namespace Drupal\professional_website_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a Professional Footer block.
 */
#[Block(
  id: 'professional_footer_block',
  admin_label: new TranslatableMarkup('Professional Footer'),
  category: new TranslatableMarkup('Professional Website')
)]
class ProfessionalFooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Define the footer sections
    $footer_data = [
      'hero_section' => [
        'company_name' => $this->t('St. Louis Integration'),
        'tagline' => $this->t('Transforming businesses through AI, automation, and digital innovation.'),
      ],
      'cta_sections' => [
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
      ],
      'company_info' => [
        'name' => $this->t('St. Louis Integration'),
        'tagline' => $this->t('Technology That Drives Business Outcomes'),
        'description' => $this->t('Empowering Fortune 500 companies with cutting-edge AI and data solutions across Financial Services, Healthcare, and Energy sectors.'),
        'social_links' => [
          [
            'title' => $this->t('LinkedIn'),
            'url' => 'https://www.linkedin.com/company/st-louis-integration',
            'icon' => 'linkedin',
          ],
          [
            'title' => $this->t('GitHub'),
            'url' => 'https://github.com/keithaumiller',
            'icon' => 'github',
          ],
        ],
      ],
      'industries_links' => [
        'title' => $this->t('Industries'),
        'links' => [
          ['title' => $this->t('Financial Services'), 'url' => Url::fromRoute('professional_website_content.fintech')],
          ['title' => $this->t('Healthcare'), 'url' => Url::fromRoute('professional_website_content.healthcare')],
          ['title' => $this->t('Energy & Utilities'), 'url' => Url::fromRoute('professional_website_content.energy')],
          ['title' => $this->t('Case Studies'), 'url' => Url::fromUserInput('/case-studies')],
          ['title' => $this->t('Leadership'), 'url' => Url::fromUserInput('/leadership')],
        ],
      ],

      'company_info' => [
        'name' => $this->t('St. Louis Integration LLC'),
        'copyright_year' => date('Y'),
        'tagline' => $this->t('Powered by innovation, driven by results. Serving clients across Financial Services, Healthcare, and Energy industries.'),
        'legal_links' => [
          ['title' => $this->t('Privacy Policy'), 'url' => '/privacy-policy'],
          ['title' => $this->t('Terms of Service'), 'url' => '/terms-of-service'],
          ['title' => $this->t('Sitemap'), 'url' => '/sitemap'],
          ['title' => $this->t('Accessibility'), 'url' => '/accessibility'],
        ],
      ],
    ];

    $build['professional_footer'] = [
      '#theme' => 'professional_footer',
      '#footer_data' => $footer_data,
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