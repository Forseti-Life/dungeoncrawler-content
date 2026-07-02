/**
 * @file
 * JavaScript for sequence display pages.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Sequence page enhancements.
   */
  Drupal.behaviors.sequenceDisplay = {
    attach: function (context, settings) {
      // Add glitch effect to sequence numbers
      $('.sequence-number', context).once('sequence-glitch').each(function() {
        const $this = $(this);
        const originalText = $this.text();
        
        // Random glitch effect on hover
        $this.hover(
          function() {
            let glitchInterval = setInterval(() => {
              const glitchChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';
              let glitchText = '';
              for(let i = 0; i < originalText.length; i++) {
                if(Math.random() < 0.3) {
                  glitchText += glitchChars[Math.floor(Math.random() * glitchChars.length)];
                } else {
                  glitchText += originalText[i];
                }
              }
              $this.text(glitchText);
            }, 50);
            
            $this.data('glitch-interval', glitchInterval);
            
            // Stop glitch after 200ms
            setTimeout(() => {
              clearInterval(glitchInterval);
              $this.text(originalText);
            }, 200);
          },
          function() {
            clearInterval($this.data('glitch-interval'));
            $this.text(originalText);
          }
        );
      });

      // Add typing effect to sequence titles
      $('.sequence-title', context).once('sequence-typing').each(function() {
        const $this = $(this);
        const text = $this.text();
        $this.text('');
        
        let i = 0;
        const typeInterval = setInterval(() => {
          $this.text(text.slice(0, i));
          i++;
          if(i > text.length) {
            clearInterval(typeInterval);
          }
        }, 50);
      });

      // Add hover effects to character roles
      $('.character-role', context).once('character-hover').hover(
        function() {
          $(this).css({
            'transform': 'scale(1.02)',
            'box-shadow': '0 0 15px rgba(255, 107, 107, 0.5)',
            'transition': 'all 0.3s ease'
          });
        },
        function() {
          $(this).css({
            'transform': 'scale(1)',
            'box-shadow': 'none'
          });
        }
      );

      // Add matrix rain effect to background (subtle)
      if ($('.sequence-page', context).length > 0) {
        createMatrixRain();
      }

      // Smooth scrolling for navigation links
      $('.nav-links a', context).once('smooth-scroll').click(function(e) {
        const href = $(this).attr('href');
        if (href.indexOf('#') === 0) {
          e.preventDefault();
          $('html, body').animate({
            scrollTop: $(href).offset().top - 100
          }, 500);
        }
      });
    }
  };

  /**
   * Create subtle matrix rain effect.
   */
  function createMatrixRain() {
    const canvas = $('<canvas id="matrix-rain"></canvas>');
    canvas.css({
      'position': 'fixed',
      'top': 0,
      'left': 0,
      'width': '100%',
      'height': '100%',
      'pointer-events': 'none',
      'z-index': -1,
      'opacity': 0.03
    });
    
    $('body').append(canvas);
    
    const c = canvas[0];
    const ctx = c.getContext('2d');
    
    c.width = window.innerWidth;
    c.height = window.innerHeight;
    
    const chars = "01";
    const charArray = chars.split("");
    const fontSize = 14;
    const columns = c.width / fontSize;
    
    const drops = [];
    for (let x = 0; x < columns; x++) {
      drops[x] = 1;
    }
    
    function draw() {
      ctx.fillStyle = 'rgba(10, 10, 10, 0.04)';
      ctx.fillRect(0, 0, c.width, c.height);
      
      ctx.fillStyle = '#00ff41';
      ctx.font = fontSize + 'px monospace';
      
      for (let i = 0; i < drops.length; i++) {
        const text = charArray[Math.floor(Math.random() * charArray.length)];
        ctx.fillText(text, i * fontSize, drops[i] * fontSize);
        
        if (drops[i] * fontSize > c.height && Math.random() > 0.975) {
          drops[i] = 0;
        }
        drops[i]++;
      }
    }
    
    setInterval(draw, 100);
    
    // Resize handler
    $(window).resize(function() {
      c.width = window.innerWidth;
      c.height = window.innerHeight;
    });
  }

})(jQuery, Drupal);