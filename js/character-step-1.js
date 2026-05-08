/**
 * @file
 * Character Creation Step 1 - Name & Concept
 *
 * Provides submit-button loading state for the Drupal Form API form.
 * Name validation (required, minLength 2) is handled server-side in
 * CharacterCreationStepForm::validateForm().
 *
 * @see CharacterCreationStepForm::buildStep1Fields()
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep1 = {
    attach: function (context) {
      once('step1-init', 'form.character-creation-form', context).forEach(function (element) {
        var $form = $(element);
        var $submit = $form.find('[type="submit"]');

        $form.on('submit', function () {
          $submit.prop('disabled', true).text('Saving\u2026');
        });
      });
    },
  };

})(jQuery, Drupal, once);
