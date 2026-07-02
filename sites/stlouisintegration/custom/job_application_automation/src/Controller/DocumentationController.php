<?php

namespace Drupal\job_application_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for documentation pages.
 */
class DocumentationController extends ControllerBase {

  /**
   * Documentation home page.
   *
   * @return array
   *   A render array for the documentation index.
   */
  public function index() {
    // Documentation files with descriptions and route names
    $docs = [
      [
        'title' => 'Documentation Home',
        'route' => 'job_application_automation.documentation.readme',
        'description' => 'Complete documentation index and quick start guide',
        'icon' => '📚',
      ],
      [
        'title' => 'Architecture',
        'route' => 'job_application_automation.documentation.architecture',
        'description' => 'Technical architecture, data model, service layer, and system design',
        'icon' => '🏗️',
      ],
      [
        'title' => 'Process Flows',
        'route' => 'job_application_automation.documentation.process_flow',
        'description' => 'Detailed workflows, sequence diagrams, and user journeys',
        'icon' => '🔄',
      ],
      [
        'title' => 'FAQ',
        'route' => 'job_application_automation.documentation.faq',
        'description' => 'Frequently asked questions, troubleshooting, and common issues',
        'icon' => '❓',
      ],
    ];
    
    $doc_links = [];
    foreach ($docs as $doc) {
      $doc_links[] = [
        '#title' => $doc['title'],
        '#description' => $doc['description'],
        '#icon' => $doc['icon'],
        '#url' => Url::fromRoute($doc['route'])->toString(),
      ];
    }
    
    // Get navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [];
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', $config);
    $navigation = $plugin_block->build();
    
    $content = [
      '#theme' => 'documentation_home',
      '#doc_links' => $doc_links,
      '#attached' => [
        'library' => [
          'job_application_automation/documentation',
        ],
      ],
    ];
    
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * View a specific documentation file.
   *
   * @param string $file
   *   The filename to display.
   *
   * @return array
   *   A render array for the documentation content.
   */
  public function viewDocument($file = 'README.md') {
    $module_path = \Drupal::service('extension.list.module')->getPath('job_application_automation');
    $file_path = DRUPAL_ROOT . '/' . $module_path . '/docs/' . $file;
    
    // Check if file exists
    if (!file_exists($file_path)) {
      \Drupal::messenger()->addError($this->t('Documentation file not found: @file', ['@file' => $file]));
      return [
        '#markup' => '<p>' . $this->t('The requested documentation file could not be found.') . '</p>',
      ];
    }
    
    // Read the markdown file
    $markdown_content = file_get_contents($file_path);
    
    // Convert markdown to HTML (basic conversion)
    // For a more robust solution, consider using a library like league/commonmark
    $html_content = $this->convertMarkdownToHtml($markdown_content);
    
    // Get navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [];
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', $config);
    $navigation = $plugin_block->build();
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['documentation-content']],
      'breadcrumb' => [
        '#markup' => '<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="' . Url::fromRoute('job_application_automation.documentation')->toString() . '">Documentation</a></li><li class="breadcrumb-item active" aria-current="page">' . basename($file, '.md') . '</li></ol></nav>',
      ],
      'content' => [
        '#markup' => $html_content,
      ],
      '#attached' => [
        'library' => [
          'job_application_automation/documentation',
        ],
      ],
    ];
    
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * Simple markdown to HTML converter.
   *
   * @param string $markdown
   *   The markdown content.
   *
   * @return string
   *   The HTML content.
   */
  private function convertMarkdownToHtml($markdown) {
    // Basic markdown conversion
    $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
    
    // Headers
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Bold and italic
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    
    // Links
    $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);
    
    // Code blocks
    $html = preg_replace('/```([a-z]*)\n(.+?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
    $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
    
    // Lists
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
    $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);
    
    // Paragraphs
    $html = preg_replace('/\n\n/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // Clean up empty paragraphs and fix HTML tag issues
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);
    $html = preg_replace('/<p>\s*(<h[1-6]>)/', '$1', $html);
    $html = preg_replace('/(<\/h[1-6]>)\s*<\/p>/', '$1', $html);
    $html = preg_replace('/<p>\s*(<ul>)/', '$1', $html);
    $html = preg_replace('/(<\/ul>)\s*<\/p>/', '$1', $html);
    $html = preg_replace('/<p>\s*(<pre>)/', '$1', $html);
    $html = preg_replace('/(<\/pre>)\s*<\/p>/', '$1', $html);
    
    return $html;
  }

}
