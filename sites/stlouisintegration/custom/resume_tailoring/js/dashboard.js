(function ($, Drupal) {
  'use strict';

  /**
   * Resume Tailoring Dashboard behavior.
   */
  Drupal.behaviors.resumeTailoringDashboard = {
    attach: function (context, settings) {
      // Smooth scrolling for anchor links
      $('a[href^="#"]', context).once('resume-tailoring-scroll').on('click', function (e) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
          e.preventDefault();
          $('html, body').animate({
            scrollTop: target.offset().top - 20
          }, 500);
        }
      });

      // Add loading state for generate buttons
      $('.btn[href*="/resume-tailoring/generate/"]', context).once('resume-tailoring-generate').on('click', function () {
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.text('Generating...').prop('disabled', true);
        
        // Re-enable after 3 seconds (in case of page navigation failure)
        setTimeout(function () {
          $btn.text(originalText).prop('disabled', false);
        }, 3000);
      });
    }
  };

})(jQuery, Drupal);