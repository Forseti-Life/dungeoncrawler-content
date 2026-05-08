/**
 * @file
 * Character Creation Step 5 - Free Ability Boosts
 *
 * Interactive ability boost selection is handled entirely by
 * ability-boost-selector.js (attached as a separate library on this step).
 * This file provides only the submit-button loading state.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep5 = {
    attach: function (context) {
      once('step5-init', 'form.character-creation-form', context).forEach(function (element) {
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
