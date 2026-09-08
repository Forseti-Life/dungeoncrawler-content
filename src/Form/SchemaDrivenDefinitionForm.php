<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionFormMapper;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
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
    $form['#prefix'] = '<div class="room-editor definition-editor definition-editor__form-prefix" data-definition-editor><div class="definition-editor__body"><main class="definition-editor__content">';
    $form['#suffix'] = '</main>' . $this->gmPanelMarkup() . '</div></div>';

    $form['#attributes']['class'][] = 'dc-definition-form';
    $form['meta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dc-definition-form__meta']],
      'family' => ['#markup' => '<p><strong>' . $this->t('Family') . ':</strong> ' . $family . '</p>'],
      'schema' => ['#markup' => '<p><strong>' . $this->t('Schema') . ':</strong> ' . CanonicalDefinitionService::SCHEMA_FILES[$family] . '</p>'],
      'version' => $current_version === NULL ? [] : ['#markup' => '<p><strong>' . $this->t('Version') . ':</strong> ' . $current_version . '</p>'],
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to @family list', ['@family' => $family]),
        '#url' => Url::fromRoute('dungeoncrawler_content.definition_family', ['family' => $family]),
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
        '#attributes' => ['class' => ['messages', 'messages--warning']],
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

    $form['actions'] = [
      '#type' => 'actions',
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
   * Shared GM panel structure consumed by DefinitionEditorShell.js.
   */
  private function gmPanelMarkup(): string {
    return <<<'HTML'
<aside class="room-editor__gm-panel definition-editor__gm-panel" data-definition-editor-gm-panel aria-label="GM assistant">
  <header class="room-editor__gm-header">
    <p class="room-editor__eyebrow">GM Assistant</p>
    <span class="room-editor__gm-state" data-definition-editor-gm-state role="status" aria-live="polite">Idle</span>
  </header>
  <section class="room-editor__gm-disclosure-group" aria-label="Grounded definition context">
    <button type="button" class="room-editor__gm-disclosure" data-definition-editor-action="gm-toggle-context" aria-expanded="false" aria-controls="definition-editor-gm-context-body">Grounded context</button>
    <div id="definition-editor-gm-context-body" class="room-editor__gm-context-body" data-definition-editor-gm-context hidden></div>
  </section>
  <section class="room-editor__gm-disclosure-group" aria-label="Assistant toolset">
    <button type="button" class="room-editor__gm-disclosure" data-definition-editor-action="gm-toggle-tools" aria-expanded="false" aria-controls="definition-editor-gm-tools-body">Toolset</button>
    <div id="definition-editor-gm-tools-body" class="room-editor__gm-tools-body" data-definition-editor-gm-tools hidden></div>
  </section>
  <section class="room-editor__drawer-section definition-editor__generation" data-definition-editor-generation aria-label="Generate definition">
    <p class="room-editor__eyebrow">Generate…</p>
    <label class="room-editor__field">Family
      <select data-definition-editor-generation-family>
        <option value="item">Item</option>
        <option value="creature">Creature</option>
        <option value="actor">NPC</option>
      </select>
    </label>
    <label class="room-editor__field">Prompt
      <textarea data-definition-editor-generation-prompt rows="3" maxlength="2000" placeholder="A rusted but enchanted sewer-worker's lantern"></textarea>
    </label>
    <label class="room-editor__field">Level
      <input type="number" data-definition-editor-generation-level min="-1" max="30" step="1" value="1">
    </label>
    <label class="room-editor__field" data-definition-editor-generation-rarity-field>Rarity
      <select data-definition-editor-generation-rarity>
        <option value="common">Common</option>
        <option value="uncommon">Uncommon</option>
        <option value="rare">Rare</option>
        <option value="epic">Epic</option>
        <option value="legendary">Legendary</option>
      </select>
    </label>
    <label class="room-editor__field" data-definition-editor-generation-role-field>Role
      <input type="text" data-definition-editor-generation-role maxlength="100" placeholder="informant, guardian, skirmisher">
    </label>
    <label class="room-editor__field" data-definition-editor-generation-attitude-field>Attitude
      <select data-definition-editor-generation-attitude>
        <option value="">Default</option>
        <option value="hostile">Hostile</option>
        <option value="unfriendly">Unfriendly</option>
        <option value="indifferent">Indifferent</option>
        <option value="friendly">Friendly</option>
        <option value="helpful">Helpful</option>
      </select>
    </label>
    <label class="room-editor__field">Seed (optional)
      <input type="number" data-definition-editor-generation-seed min="0" max="2147483647" step="1">
    </label>
    <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="generate-definition">Generate definition</button>
    <p class="room-editor__hint" data-definition-editor-generation-unavailable hidden>Generation is available only for item, creature, and actor/NPC scopes.</p>
  </section>
  <ol class="room-editor__gm-transcript" data-definition-editor-gm-transcript aria-label="Assistant transcript" aria-live="polite"></ol>
  <div class="room-editor__gm-plan" data-definition-editor-gm-plan hidden>
    <p class="room-editor__eyebrow">Proposed definition update</p>
    <ol class="room-editor__gm-plan-list" data-definition-editor-gm-plan-list></ol>
    <div class="room-editor__gm-plan-actions">
      <button type="button" class="room-editor__button" data-definition-editor-action="gm-preview-definition">Preview</button>
      <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="gm-apply-definition">Apply</button>
      <button type="button" class="room-editor__button" data-definition-editor-action="gm-discard-definition">Discard</button>
    </div>
  </div>
  <div class="room-editor__gm-composer" data-definition-editor-gm-form>
    <label class="visually-hidden" for="definition-editor-gm-input">Message the GM assistant</label>
    <textarea id="definition-editor-gm-input" data-definition-editor-gm-input rows="3" placeholder="Ask the assistant, or run a tool: describe_definition_schema {}"></textarea>
    <div class="room-editor__gm-composer-actions">
      <button type="button" class="room-editor__button room-editor__button--primary" data-definition-editor-action="gm-send">Send</button>
    </div>
  </div>
</aside>
HTML;
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
    $form_state->set('payload', $payload);

    if ($form_state->get('discard_pointers') !== [] && !$form_state->getValue(['stored_findings', 'discard_confirmed'])) {
      $form_state->setErrorByName('stored_findings][discard_confirmed', $this->t('Confirm the discard of non-schema properties, or cancel.'));
    }

    foreach ($this->definitions->validateDefinition($family, $payload) as $finding) {
      $name = $this->mapper->elementName($finding['pointer']) ?? 'definition';
      $form_state->setErrorByName($name, $this->t('@pointer: @message', ['@pointer' => $finding['pointer'], '@message' => $finding['message']]));
    }

    $definition_id = $form_state->get('definition_id');
    $id_property = $this->definitions->idProperty($family);
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

}
