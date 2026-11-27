<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionFormMapper;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
use Drupal\dungeoncrawler_content\Render\DefinitionEditorPage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One schema-driven editor for all six canonical definition families.
 *
 * Normative specification:
 * copilot-hq 20260904-dc-canonical-dungeon-editor-architecture/
 *   19-definition-editor-spec.md
 *
 * The form holds no field knowledge. Every control is produced by
 * DefinitionFormMapper from the family JSON Schema; every save is validated by
 * CanonicalDefinitionService::saveDefinition(). Adding a field to a schema
 * adds it here. There is no raw JSON control anywhere in this form.
 *
 * The working payload lives in form state so add/remove row buttons can
 * rebuild the form without AJAX and without losing edits.
 *
 * Stored definitions that do not conform to their schema are shown with their
 * findings. Properties the schema does not define cannot be rendered and
 * would be dropped on save; the author must explicitly confirm that discard
 * before the form will write.
 */
final class SchemaDrivenDefinitionForm extends FormBase {

  public function __construct(
    private readonly CanonicalDefinitionService $definitions,
    private readonly DefinitionFormMapper $mapper,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.canonical_definitions'),
      $container->get('dungeoncrawler_content.definition_form_mapper'),
      $container->get('csrf_token'),
    );
  }

  public function getFormId(): string {
    return 'dungeoncrawler_content_schema_driven_definition_form';
  }

  /**
   * Page title callback shared by the create and edit routes.
   */
  public function title(string $family, ?string $definition_id = NULL): string {
    return $definition_id === NULL
      ? 'New ' . $family . ' definition'
      : ucfirst($family) . ' › ' . $definition_id;
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $family = NULL, ?string $definition_id = NULL): array {
    $family = (string) $family;
    $schema = $this->definitions->schemaForFamily($family);

    if (!$form_state->has('payload')) {
      $form_state->set('family', $family);
      $form_state->set('definition_id', $definition_id);
      try {
        $payload = $definition_id === NULL ? [] : $this->definitions->definitionPayload($family, $definition_id);
      }
      catch (\OutOfBoundsException $exception) {
        throw new NotFoundHttpException($exception->getMessage(), $exception);
      }
      $form_state->set('payload', $payload);
      $form_state->set('expected_version', $definition_id === NULL ? NULL : $this->definitions->currentVersion($family, $definition_id));
      $form_state->set('stored_findings', $definition_id === NULL ? [] : $this->definitions->validateDefinition($family, $payload));
    }
    $payload = $form_state->get('payload');
    $stored_findings = $form_state->get('stored_findings');
    $affected = $definition_id === NULL ? [] : $this->definitions->publishedRoomsReferencing($family, $definition_id);
    $current_version = $form_state->get('expected_version');

    $scope = ['family' => $family];
    if ($definition_id !== NULL) {
      $scope['definition_id'] = $definition_id;
    }
    $form['#attached']['library'][] = 'dungeoncrawler_content/definition-editor';
    $form['#attached']['drupalSettings']['dungeoncrawlerContent']['definitionEditor'] = [
      'gmUrl' => Url::fromRoute('dungeoncrawler_content.definition_editor_gm_describe')->toString(),
      'csrfToken' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
      'scope' => $scope,
    ];
    $label = $definition_id === NULL
      ? (string) $this->t('New @family', ['@family' => $family])
      : (string) (($payload['name'] ?? $payload['display_name'] ?? $payload['label'] ?? '') ?: $definition_id);
    $page = DefinitionEditorPage::wrap(
      $this->definitions,
      $label,
      $family,
      [
        ['label' => $this->t('Family'), 'value' => ucfirst($family)],
        ['label' => $this->t('Version'), 'value' => $current_version === NULL ? (string) $this->t('not saved yet') : (string) $current_version],
        ['label' => $this->t('Schema'), 'value' => $definition_id === NULL ? '—' : ($stored_findings === [] ? (string) $this->t('conforms') : (string) $this->formatPlural(count($stored_findings), '1 finding', '@count findings'))],
        ['label' => $this->t('Used by published rooms'), 'value' => (string) count($affected)],
      ],
      NULL,
      array_slice(array_map(static fn(array $f): string => $f['pointer'] . ': ' . $f['message'], $stored_findings), 0, 5),
      [['label' => $this->t('Back to @family list', ['@family' => $family]), 'url' => Url::fromRoute('dungeoncrawler_content.definition_family', ['family' => $family])->toString()]],
    );
    // Markup, not a string: the page holds the agent's form controls.
    $form['#prefix'] = Markup::create($page['prefix']);
    $form['#suffix'] = Markup::create($page['suffix']);

    $form['#attributes']['class'][] = 'dc-definition-form';
    $form['#attributes']['class'][] = 'dc-sheet';
    $form['meta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dc-definition-form__meta', 'dc-sheet__section']],
      'family' => ['#markup' => '<p><strong>' . $this->t('Family') . ':</strong> ' . $family . '</p>'],
      'schema' => ['#markup' => '<p><strong>' . $this->t('Schema') . ':</strong> ' . CanonicalDefinitionService::SCHEMA_FILES[$family] . '</p>'],
      'version' => $current_version === NULL ? [] : ['#markup' => '<p><strong>' . $this->t('Version') . ':</strong> ' . $current_version . '</p>'],
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to @family list', ['@family' => $family]),
        '#url' => Url::fromRoute('dungeoncrawler_content.definition_family', ['family' => $family]),
        '#attributes' => ['class' => ['dc-btn', 'dc-btn-secondary', 'dc-btn--sm']],
      ],
    ];

    $discard_pointers = array_values(array_unique(array_column(
      array_filter($stored_findings, static fn(array $f): bool => $f['code'] === 'additional_property'),
      'pointer'
    )));
    if ($stored_findings !== []) {
      $items = array_map(static fn(array $f): string => $f['pointer'] . ' — ' . $f['message'], $stored_findings);
      $form['stored_findings'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->formatPlural(count($stored_findings), 'Stored definition has 1 schema finding', 'Stored definition has @count schema findings'),
        '#attributes' => ['class' => ['messages', 'messages--warning', 'dc-sheet__section', 'dc-definition-form__section']],
        'list' => ['#theme' => 'item_list', '#items' => $items],
      ];
      if ($discard_pointers !== []) {
        $form['stored_findings']['discard_confirmed'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Discard the @count properties above that the schema does not define. They cannot be edited here and will be removed on save.', ['@count' => count($discard_pointers)]),
          '#default_value' => FALSE,
        ];
      }
    }
    $form_state->set('discard_pointers', $discard_pointers);

    if ($affected !== []) {
      $rows = array_map(static fn(array $r): array => [$r['room_id'], $r['version'], $r['pinned_definition_version'], (string) $r['placement_count']], $affected);
      $form['blast_radius'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->formatPlural(count($affected), 'Referenced by 1 published room version', 'Referenced by @count published room versions'),
        '#attributes' => ['class' => ['dc-sheet__section', 'dc-definition-form__section']],
        'note' => [
          '#markup' => '<p>' . $this->t('Saving increments this definition from @from to @to. Published rooms keep their pinned version and are not altered.', [
            '@from' => $this->definitions->normalizeSemanticVersion($current_version),
            '@to' => $this->definitions->incrementPatch($this->definitions->normalizeSemanticVersion($current_version)),
          ]) . '</p>',
        ],
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Room'), $this->t('Room version'), $this->t('Pinned definition version'), $this->t('Placements')],
          '#rows' => $rows,
        ],
      ];
    }

    $form['definition'] = $this->mapper->build($schema, $payload === [] ? NULL : $payload, 'definition');
    $form['definition']['#title'] = (string) ($schema['title'] ?? ucfirst($family));
    $form['definition']['#attributes']['class'][] = 'dc-sheet__section';
    $form['definition']['#attributes']['class'][] = 'dc-definition-form__section';

    if ($definition_id !== NULL) {
      $form['object_image'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['dc-sheet__section', 'object-image-panel'],
          'data-object-image-panel' => '',
          'data-kind' => $family,
          'data-object-id' => $definition_id,
          'data-endpoint' => Url::fromRoute('dungeoncrawler_content.object_image', [
            'kind' => $family,
            'object_id' => $definition_id,
          ])->toString(),
          'data-csrf-token' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
        ],
        'title' => ['#markup' => '<h2>' . $this->t('Definition image') . '</h2>'],
        'preview' => [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'data-object-image-preview' => '',
            'class' => ['object-image-panel__preview'],
            'alt' => $this->t('@label image', ['@label' => $label]),
            'hidden' => TRUE,
          ],
        ],
        'placeholder' => [
          '#markup' => '<p data-object-image-placeholder>' . $this->t('No generated image exists for this definition yet.') . '</p>',
        ],
        'status' => [
          '#markup' => '<p data-object-image-status aria-live="polite">' . $this->t('Loading image history…') . '</p>',
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['object-image-panel__actions']],
          'prompt' => [
            '#type' => 'textarea',
            '#title' => $this->t('Generation prompt override'),
            '#attributes' => ['data-object-image-prompt' => ''],
            '#description' => $this->t('Leave blank to generate from the canonical definition.'),
            '#rows' => 3,
          ],
          'version' => [
            '#type' => 'select',
            '#title' => $this->t('Image version'),
            '#attributes' => ['data-object-image-version' => ''],
          ],
          'generate' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => $this->t('Generate / Regenerate'),
            '#attributes' => [
              'data-object-image-generate' => '',
              'class' => ['dc-btn', 'dc-btn-primary'],
              'type' => 'button',
            ],
          ],
          'promote' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => $this->t('Use selected as current'),
            '#attributes' => [
              'data-object-image-promote' => '',
              'class' => ['dc-btn', 'dc-btn-secondary'],
              'type' => 'button',
            ],
          ],
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['dc-sheet__section', 'dc-definition-form__actions']],
      'save' => [
        '#type' => 'submit',
        '#value' => $definition_id === NULL ? $this->t('Create') : $this->t('Save'),
        '#button_type' => 'primary',
        '#name' => 'dc_def_save',
      ],
    ];

    $this->attachRowButtonHandlers($form);

    return $form;
  }

  /**
   * Points every add/remove button at the mutate handler.
   */
  private function attachRowButtonHandlers(array &$element): void {
    foreach ($element as $key => &$child) {
      if (!is_array($child) || (is_string($key) && $key[0] === '#')) {
        continue;
      }
      if (($child['#type'] ?? NULL) === 'submit' && DefinitionFormMapper::parseButton((string) ($child['#name'] ?? '')) !== NULL) {
        $child['#submit'] = ['::mutateRows'];
        $child['#validate'] = [];
      }
      $this->attachRowButtonHandlers($child);
    }
  }

  /**
   * Add/remove row submit handler: fold input into the payload and rebuild.
   */
  public function mutateRows(array &$form, FormStateInterface $form_state): void {
    $button = DefinitionFormMapper::parseButton((string) ($form_state->getTriggeringElement()['#name'] ?? ''));
    if ($button === NULL) {
      return;
    }
    $schema = $this->definitions->schemaForFamily($form_state->get('family'));
    $payload = $this->mapper->extract($schema, $form_state->getValue('definition'));
    $payload = $this->mapper->mutate($schema, is_array($payload) ? $payload : [], $button['op'], $button['pointer']);
    $form_state->set('payload', $payload);
    // Drop the raw input so re-indexed rows render from the payload rather
    // than from the stale input of the row that was removed.
    $input = $form_state->getUserInput();
    unset($input['definition']);
    $form_state->setUserInput($input);
    $form_state->setRebuild();
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#name'] ?? '') !== 'dc_def_save') {
      return;
    }
    $family = $form_state->get('family');
    $schema = $this->definitions->schemaForFamily($family);
    $payload = $this->mapper->extract($schema, $form_state->getValue('definition'));
    $payload = is_array($payload) ? $payload : [];
    $definition_id = $form_state->get('definition_id');
    $id_property = $this->definitions->idProperty($family);
    if ($definition_id === NULL && trim((string) ($payload[$id_property] ?? '')) === '') {
      $generated_id = $this->generateDefinitionIdFromPayload($family, $payload);
      if ($generated_id !== '') {
        $payload[$id_property] = $generated_id;
      }
    }
    $form_state->set('payload', $payload);

    if ($form_state->get('discard_pointers') !== [] && !$form_state->getValue(['stored_findings', 'discard_confirmed'])) {
      $form_state->setErrorByName('stored_findings][discard_confirmed', $this->t('Confirm the discard of non-schema properties, or cancel.'));
    }

    foreach ($this->definitions->validateDefinition($family, $payload) as $finding) {
      $name = $this->mapper->elementName($finding['pointer']) ?? 'definition';
      $form_state->setErrorByName($name, $this->t('@pointer: @message', ['@pointer' => $finding['pointer'], '@message' => $finding['message']]));
    }

    $payload_id = (string) ($payload[$id_property] ?? '');
    if ($definition_id !== NULL && $payload_id !== $definition_id) {
      $form_state->setErrorByName($this->mapper->elementName('/' . $id_property) ?? 'definition', $this->t('definition_id_mismatch: @prop must stay @id.', ['@prop' => $id_property, '@id' => $definition_id]));
    }
    if ($definition_id === NULL && $payload_id !== '' && $this->definitions->currentVersion($family, $payload_id) !== NULL) {
      $form_state->setErrorByName($this->mapper->elementName('/' . $id_property) ?? 'definition', $this->t('definition_exists: a @family with id @id already exists.', ['@family' => $family, '@id' => $payload_id]));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $family = $form_state->get('family');
    $definition_id = $form_state->get('definition_id');
    try {
      $result = $this->definitions->saveDefinition($family, $definition_id, $form_state->get('payload'), $form_state->get('expected_version'));
    }
    catch (DefinitionValidationException $exception) {
      foreach ($exception->findings as $finding) {
        $this->messenger()->addError($finding['pointer'] . ': ' . $finding['message']);
      }
      $form_state->setRebuild();
      return;
    }
    catch (\RuntimeException $exception) {
      if ($exception->getMessage() === 'definition_version_conflict') {
        $this->messenger()->addError($this->t('definition_version_conflict: this definition was changed by someone else since you opened it. Reload and reapply your edit.'));
        $form_state->setRebuild();
        return;
      }
      throw $exception;
    }

    $this->messenger()->addStatus($result['created']
      ? $this->t('Created @family @id at version @version.', ['@family' => $family, '@id' => $result['definition_id'], '@version' => $result['version']])
      : $this->t('Saved @family @id at version @version.', ['@family' => $family, '@id' => $result['definition_id'], '@version' => $result['version']]));
    if ($result['affected_rooms'] !== []) {
      $this->messenger()->addWarning($this->formatPlural(
        count($result['affected_rooms']),
        'Version incremented from @from: 1 published room version still pins the prior definition.',
        'Version incremented from @from: @count published room versions still pin the prior definition.',
        ['@from' => (string) $result['previous_version']]
      ));
    }
    $form_state->setRedirect('dungeoncrawler_content.definition_edit', ['family' => $family, 'definition_id' => $result['definition_id']]);
  }

  /**
   * Generate a stable definition id candidate from the payload name.
   */
  private function generateDefinitionIdFromPayload(string $family, array $payload): string {
    $name_property = $this->definitions->nameProperty($family);
    $base = $this->sanitizeIdentifier((string) ($payload[$name_property] ?? ''));
    if ($base === '') {
      return '';
    }
    $candidate = $base;
    $suffix = 2;
    while ($this->definitions->currentVersion($family, $candidate) !== NULL) {
      $candidate = $base . '-' . $suffix;
      $suffix += 1;
    }
    return $candidate;
  }

  /**
   * Normalize text to the canonical slug-like identifier pattern.
   */
  private function sanitizeIdentifier(string $value): string {
    $value = trim(mb_strtolower($value));
    if ($value === '') {
      return '';
    }
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (!is_string($normalized) || $normalized === '') {
      $normalized = $value;
    }
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');
    $normalized = preg_replace('/-+/', '-', $normalized) ?? '';
    return $normalized;
  }

}
