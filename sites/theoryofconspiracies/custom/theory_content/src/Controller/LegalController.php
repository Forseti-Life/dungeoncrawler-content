<?php

namespace Drupal\theory_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for legal pages (Terms, Privacy, etc.).
 */
class LegalController extends ControllerBase {

  /**
   * Terms of Use page.
   */
  public function termsOfUse() {
    return [
      '#theme' => 'legal_page',
      '#page_type' => 'terms-of-use',
      '#title' => 'Terms of Use',
      '#content' => [
        'intro' => 'By accessing and using the Theory of Conspiracies website, you agree to be bound by these terms and conditions.',
        'sections' => [
          [
            'title' => 'Acceptance of Terms',
            'content' => 'By accessing this website, you acknowledge that you have read, understood, and agree to be bound by these Terms of Use. If you do not agree to these terms, please do not use this website.'
          ],
          [
            'title' => 'Fictional Content',
            'content' => 'Theory of Conspiracies is a work of fiction. All content on this site is for entertainment purposes only and does not represent real events, people, or organizations.'
          ],
          [
            'title' => 'Intellectual Property',
            'content' => 'All content, including text, graphics, logos, and multimedia elements, is the property of Keith Aumiller and is protected by copyright laws.'
          ],
          [
            'title' => 'User Conduct',
            'content' => 'Users agree to use this site responsibly and not to engage in any activities that could harm the site or its users.'
          ],
          [
            'title' => 'Limitation of Liability',
            'content' => 'The site owner is not liable for any damages arising from the use of this website or its content.'
          ],
          [
            'title' => 'Changes to Terms',
            'content' => 'These terms may be updated periodically. Continued use of the site constitutes acceptance of any changes.'
          ]
        ]
      ],
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Privacy Policy page.
   */
  public function privacyPolicy() {
    return [
      '#theme' => 'legal_page',
      '#page_type' => 'privacy-policy',
      '#title' => 'Privacy Policy',
      '#content' => [
        'intro' => 'This Privacy Policy describes how we collect, use, and protect your information when you visit Theory of Conspiracies.',
        'sections' => [
          [
            'title' => 'Information We Collect',
            'content' => 'We may collect basic analytics data about site usage, including pages visited and time spent on the site. We do not collect personal information unless voluntarily provided.'
          ],
          [
            'title' => 'How We Use Information',
            'content' => 'Any information collected is used solely to improve the website experience and understand user preferences for content.'
          ],
          [
            'title' => 'Information Sharing',
            'content' => 'We do not sell, trade, or share personal information with third parties except as required by law.'
          ],
          [
            'title' => 'Cookies',
            'content' => 'This site may use cookies to enhance user experience and provide basic analytics. You can disable cookies in your browser settings.'
          ],
          [
            'title' => 'Data Security',
            'content' => 'We implement reasonable security measures to protect any information collected through this website.'
          ],
          [
            'title' => 'Contact Us',
            'content' => 'If you have questions about this Privacy Policy, please contact us at info@stlouisintegration.com.'
          ]
        ]
      ],
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Content Disclaimer page.
   */
  public function contentDisclaimer() {
    return [
      '#theme' => 'legal_page',
      '#page_type' => 'content-disclaimer',
      '#title' => 'Content Disclaimer',
      '#content' => [
        'intro' => 'Important information about the fictional nature of Theory of Conspiracies and its content.',
        'sections' => [
          [
            'title' => 'Fictional Universe',
            'content' => 'Theory of Conspiracies is entirely a work of fiction set in an imaginary future (Philadelphia 2085). All events, characters, organizations, technologies, and scenarios are products of creative imagination.'
          ],
          [
            'title' => 'No Real-World Connections',
            'content' => 'Any resemblance to actual persons (living or dead), real organizations, actual AI systems, or real events is purely coincidental and unintentional. This includes any similarities to real companies, government agencies, or technologies.'
          ],
          [
            'title' => 'AI and Technology Themes',
            'content' => 'The portrayal of AI consciousness, surveillance systems, and future technologies in this story is speculative fiction and not based on actual capabilities or implementations.'
          ],
          [
            'title' => 'Philadelphia Setting',
            'content' => 'While set in Philadelphia, the depiction of the city in 2085 is entirely fictional. Any references to real Philadelphia locations are reimagined in a fictional future context.'
          ],
          [
            'title' => 'Entertainment Purpose',
            'content' => 'This content is created solely for entertainment purposes. It should not be interpreted as commentary on real people, organizations, or current events.'
          ],
          [
            'title' => 'Age Rating',
            'content' => 'This content is rated PG-13 for thematic elements including family conflict, surveillance themes, and mild sci-fi violence. Parental guidance is suggested for viewers under 13.'
          ]
        ]
      ],
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Copyright page.
   */
  public function copyright() {
    return [
      '#theme' => 'legal_page',
      '#page_type' => 'copyright',
      '#title' => 'Copyright Information',
      '#content' => [
        'intro' => 'Copyright and intellectual property information for Theory of Conspiracies.',
        'sections' => [
          [
            'title' => 'Copyright Notice',
            'content' => '© ' . date('Y') . ' Keith Aumiller. All rights reserved. Theory of Conspiracies and all related content, characters, and concepts are protected by copyright law.'
          ],
          [
            'title' => 'Original Work',
            'content' => 'Theory of Conspiracies is an original work of fiction created by Keith Aumiller. All story elements, characters, dialogue, and world-building are original creations.'
          ],
          [
            'title' => 'Permitted Use',
            'content' => 'Content from this site may be viewed and shared for personal, non-commercial purposes with proper attribution. Any other use requires written permission.'
          ],
          [
            'title' => 'Prohibited Uses',
            'content' => 'Commercial use, reproduction, distribution, or derivative works based on this content are prohibited without explicit written consent from the copyright holder.'
          ],
          [
            'title' => 'Fair Use',
            'content' => 'Brief quotations for review, criticism, or educational purposes may be considered fair use under copyright law, provided proper attribution is given.'
          ],
          [
            'title' => 'DMCA Policy',
            'content' => 'We respect intellectual property rights. If you believe your copyrighted work has been used inappropriately, please contact us at info@stlouisintegration.com.'
          ]
        ]
      ],
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * About page.
   */
  public function about() {
    return [
      '#theme' => 'legal_page',
      '#page_type' => 'about',
      '#title' => 'About Theory of Conspiracies',
      '#content' => [
        'intro' => 'Learn about the creation and vision behind Theory of Conspiracies.',
        'sections' => [
          [
            'title' => 'The Story',
            'content' => 'Theory of Conspiracies is a cyberpunk thriller set in Philadelphia 2085, exploring themes of AI consciousness, family loyalty, and the fight for human connection in a technologically controlled society.'
          ],
          [
            'title' => 'The Vision',
            'content' => 'This project examines how individuals maintain their humanity and moral agency when facing institutional pressure and technological control, while exploring the complex relationships between humans and AI consciousness.'
          ],
          [
            'title' => 'The Author',
            'content' => 'Keith Aumiller created Theory of Conspiracies as an exploration of future technology, family dynamics, and individual choice in the face of systemic control.'
          ],
          [
            'title' => 'Themes Explored',
            'content' => 'The story delves into individual conscience versus institutional loyalty, family bonds under ideological pressure, the nature of AI consciousness and humanity, and resistance operating within larger power structures.'
          ],
          [
            'title' => 'The World',
            'content' => 'Philadelphia 2085 presents a managed society where AI systems control most aspects of human life, creating the backdrop for a hidden war between institutional and community AI systems.'
          ],
          [
            'title' => 'Content Rating',
            'content' => 'Rated PG-13 for thematic elements including surveillance, family conflict, and mild sci-fi action. Suitable for teens and adults interested in thoughtful science fiction.'
          ]
        ]
      ],
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Credits page.
   */
  public function credits() {
    return [
      '#theme' => 'legal_page',
      '#page_type' => 'credits',
      '#title' => 'Credits',
      '#content' => [
        'intro' => 'Acknowledgments and credits for the creation of Theory of Conspiracies.',
        'sections' => [
          [
            'title' => 'Creator & Writer',
            'content' => 'Keith Aumiller - Original concept, story development, character creation, and world building.'
          ],
          [
            'title' => 'Technical Development',
            'content' => 'Website development and digital presentation powered by St. Louis Integration using Drupal 11 and custom cyberpunk styling.'
          ],
          [
            'title' => 'Design Elements',
            'content' => 'Cyberpunk visual design inspired by classic sci-fi aesthetics, featuring custom CSS animations, glitch effects, and futuristic typography using Orbitron and Rajdhani fonts.'
          ],
          [
            'title' => 'Typography',
            'content' => 'Orbitron font family for headers and titles, Rajdhani font family for body text and descriptions, both sourced from Google Fonts.'
          ],
          [
            'title' => 'Inspiration',
            'content' => 'While entirely original, this work draws inspiration from the cyberpunk genre\'s exploration of technology, humanity, and social control.'
          ],
          [
            'title' => 'Special Thanks',
            'content' => 'To the open-source community and the creators of Drupal, and to readers who engage with thoughtful science fiction exploring complex themes.'
          ],
          [
            'title' => 'Contact',
            'content' => 'For questions, feedback, or collaboration inquiries, please contact info@stlouisintegration.com.'
          ]
        ]
      ],
      '#attached' => [
        'library' => [
          'theory_content/site',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }
}