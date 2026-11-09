<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormValidator;

/**
 * Runs native Form API validation without publishing session error messages.
 *
 * Findings are returned in the GM validation report, not hidden or discarded.
 */
class CharacterDraftFormValidator extends FormValidator {

  protected function finalizeValidation(&$form, FormStateInterface &$form_state, $form_id) {
    $form_state->setValidationComplete();
  }

}
