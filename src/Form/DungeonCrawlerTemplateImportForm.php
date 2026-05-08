<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dungeoncrawler_content\Service\TemplateImportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing template examples into template tables.
 */
class DungeonCrawlerTemplateImportForm extends FormBase {

  /**
   * Template importer service.
   */
  protected TemplateImportService $templateImportService;

  /**
   * Constructs the form.
   */
  public function __construct(TemplateImportService $template_import_service) {
    $this->templateImportService = $template_import_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.template_importer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_template_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Import template examples from table-organized files in <code>@path</code>.', [
        '@path' => $this->templateImportService->getTemplatesRootPath(),
      ]),
      '#attributes' => ['class' => ['mb-2']],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import templates'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $summary = $this->templateImportService->importTemplates();

    $this->messenger()->addStatus($this->t(
      'Template import complete. Table rows processed: @processed (inserted: @inserted, updated: @updated, skipped: @skipped).',
      [
        '@processed' => $summary['table_rows_processed'],
        '@inserted' => $summary['table_rows_inserted'],
        '@updated' => $summary['table_rows_updated'],
        '@skipped' => $summary['table_rows_skipped'],
      ],
    ));

    if (!empty($summary['missing_template_pairs'])) {
      $missing_descriptions = array_map(static function (array $pair): string {
        return $pair['campaign_table'] . ' → ' . $pair['expected_template_table'];
      }, $summary['missing_template_pairs']);

      $this->messenger()->addWarning($this->t('Campaign template table pairs missing: @pairs', [
        '@pairs' => implode('; ', $missing_descriptions),
      ]));
    }

    if (!empty($summary['errors'])) {
      $preview = array_slice($summary['errors'], 0, 5);
      foreach ($preview as $error) {
        $this->messenger()->addWarning($error);
      }

      if (count($summary['errors']) > count($preview)) {
        $this->messenger()->addWarning($this->t('Additional import warnings: @count', [
          '@count' => count($summary['errors']) - count($preview),
        ]));
      }
    }

    $form_state->setRedirect('dungeoncrawler_content.game_objects');
  }

}
