/**
 * @file
 * Character Creation Step 8 - Finishing Touches
 *
 * All fields on this step are optional. Provides client-side maxlength
 * validation (textarea maxlength is advisory in most browsers) and
 * submit-button loading state.
 *
 * @see CharacterCreationStepForm::buildStep8Fields()
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Field validation rules keyed by Drupal Form API name attribute.
   * Only maxlength is enforced client-side; other constraints are in PHP.
   */
  var RULES = {
    appearance:      { max: 1000, label: 'Appearance & Presence' },
    personality:     { max: 1000, label: 'Personality & Table Voice' },
    backstory:       { max: 5000, label: 'Backstory & Legacy Goal' },
    portrait_prompt: { max: 500,  label: 'Portrait prompt' },
  };

  /**
   * Validate all textareas against their maxlength rules.
   *
   * @param {jQuery} $form
   * @return {{ valid: boolean, errors: string[] }}
   */
  function validateFields($form) {
    var errors = [];

    Object.keys(RULES).forEach(function (name) {
      var rule = RULES[name];
      var $field = $form.find('[name="' + name + '"]');
      if (!$field.length) {
        return;
      }

      var value = ($field.val() || '').trim();
      if (value.length > rule.max) {
        errors.push(rule.label + ' cannot exceed ' + rule.max + ' characters.');
        $field.addClass('is-invalid');
      }
      else {
        $field.removeClass('is-invalid');
      }
    });

    return { valid: errors.length === 0, errors: errors };
  }

  Drupal.behaviors.characterStep8 = {
    attach: function (context) {
      once('step8-init', 'form.character-creation-form', context).forEach(function (element) {
        var $form = $(element);
        var $submit = $form.find('[type="submit"]');

        $form.on('submit', function (e) {
          var result = validateFields($form);
          if (!result.valid) {
            e.preventDefault();
            alert(result.errors.join('\n'));
            return false;
          }

          $submit.prop('disabled', true).text('Creating Character\u2026');
        });
      });
    },
  };

})(jQuery, Drupal, once);
