/**
 * @file
 * Character Creation Step 3 - Background Selection
 *
 * Background is a Drupal Form API <select>; ability boosts are handled by
 * ability-boost-selector.js via the interactive ability widget. This file
 * provides only the submit-button disable/loading state.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep3 = {
    attach: function (context) {
      once('step3-init', 'form.character-creation-form', context).forEach(function (element) {
        const $form = $(element);
        const $submit = $form.find('[type="submit"]');

        $form.on('submit', function (e) {
          window.setTimeout(function () {
            if (e.isDefaultPrevented() || (element.checkValidity && !element.checkValidity())) {
              return;
            }
            $submit.prop('disabled', true).text('Saving...');
          }, 0);
        });
      });
    },
  };

})(jQuery, Drupal, once);
