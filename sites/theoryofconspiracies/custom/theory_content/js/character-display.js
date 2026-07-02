/**
 * Character Display JavaScript
 * Interactive elements for character profiles
 */

(function (Drupal, $, once) {
  'use strict';

  /**
   * Character Profile Enhancements
   */
  Drupal.behaviors.characterDisplay = {
    attach: function (context, settings) {
      
      // Add glitch effect to character names
      once('character-glitch', '.character-name', context).forEach(function (element) {
        const text = element.textContent;
        element.setAttribute('data-text', text);
        
        // Random glitch effect
        setInterval(function() {
          if (Math.random() > 0.98) {
            element.classList.add('cyber-glitch');
            setTimeout(function() {
              element.classList.remove('cyber-glitch');
            }, 150);
          }
        }, 2000);
      });

      // Trust level animations
      once('trust-animations', '.trust-connection', context).forEach(function (element) {
        const trustLevel = element.dataset.trustLevel || 50;
        const bar = element.querySelector('.trust-bar');
        
        if (bar) {
          // Animate trust bar fill
          setTimeout(function() {
            bar.style.width = trustLevel + '%';
          }, 500);
        }
        
        // Add hover effects with sound (if audio context available)
        element.addEventListener('mouseenter', function() {
          if (window.AudioContext || window.webkitAudioContext) {
            playTrustLevelSound(trustLevel);
          }
        });
      });

      // Character relationship network visualization
      once('character-network', '.trust-network', context).forEach(function (element) {
        if (typeof d3 !== 'undefined') {
          initTrustNetworkVisualization(element);
        }
      });

      // Typewriter effect for character descriptions
      once('character-typewriter', '.character-description.typewriter', context).forEach(function (element) {
        typewriterEffect(element, 30);
      });
    }
  };

  /**
   * Play trust level sound effect
   */
  function playTrustLevelSound(trustLevel) {
    try {
      const audioContext = new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();
      
      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);
      
      // Frequency based on trust level
      const frequency = 200 + (trustLevel * 4);
      oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
      
      gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
      
      oscillator.start(audioContext.currentTime);
      oscillator.stop(audioContext.currentTime + 0.1);
    } catch (e) {
      // Silently fail if Web Audio API not available
    }
  }

  /**
   * Initialize trust network visualization with D3
   */
  function initTrustNetworkVisualization(container) {
    const width = container.offsetWidth;
    const height = 400;
    
    // Sample data - would come from Drupal in real implementation
    const nodes = [
      {id: 'current', name: 'Current Character', group: 1},
      {id: 'sal', name: 'Sal Mueller', group: 2},
      {id: 'tiger', name: 'Tiger Mueller', group: 2},
      {id: 'estella', name: 'Estella Mueller', group: 3},
      {id: 'keith', name: 'Keith AI', group: 4}
    ];
    
    const links = [
      {source: 'current', target: 'sal', value: 70},
      {source: 'current', target: 'tiger', value: 30},
      {source: 'current', target: 'estella', value: 85},
      {source: 'sal', target: 'tiger', value: 65},
      {source: 'sal', target: 'keith', value: 40}
    ];
    
    const svg = d3.select(container)
      .append('svg')
      .attr('width', width)
      .attr('height', height)
      .style('background', 'rgba(0, 0, 0, 0.3)')
      .style('border', '1px solid rgba(0, 255, 255, 0.3)');
    
    const simulation = d3.forceSimulation(nodes)
      .force('link', d3.forceLink(links).id(d => d.id).distance(100))
      .force('charge', d3.forceManyBody().strength(-300))
      .force('center', d3.forceCenter(width / 2, height / 2));
    
    // Add links
    const link = svg.append('g')
      .selectAll('line')
      .data(links)
      .enter().append('line')
      .attr('stroke', d => {
        if (d.value > 70) return '#00ff41';
        if (d.value > 40) return '#ffff00';
        return '#ff0040';
      })
      .attr('stroke-width', d => Math.sqrt(d.value) / 5);
    
    // Add nodes
    const node = svg.append('g')
      .selectAll('circle')
      .data(nodes)
      .enter().append('circle')
      .attr('r', 8)
      .attr('fill', d => {
        const colors = ['#00ffff', '#ff00ff', '#00ff41', '#0080ff'];
        return colors[d.group - 1];
      })
      .attr('stroke', '#ffffff')
      .attr('stroke-width', 2);
    
    // Add labels
    const text = svg.append('g')
      .selectAll('text')
      .data(nodes)
      .enter().append('text')
      .text(d => d.name)
      .attr('font-family', 'Rajdhani, sans-serif')
      .attr('font-size', '12px')
      .attr('fill', '#ffffff')
      .attr('dx', 12)
      .attr('dy', 4);
    
    simulation.on('tick', () => {
      link
        .attr('x1', d => d.source.x)
        .attr('y1', d => d.source.y)
        .attr('x2', d => d.target.x)
        .attr('y2', d => d.target.y);
      
      node
        .attr('cx', d => d.x)
        .attr('cy', d => d.y);
      
      text
        .attr('x', d => d.x)
        .attr('y', d => d.y);
    });
  }

  /**
   * Typewriter effect for text elements
   */
  function typewriterEffect(element, speed) {
    const text = element.textContent;
    element.textContent = '';
    element.style.borderRight = '2px solid #00ffff';
    
    let i = 0;
    const timer = setInterval(function() {
      if (i < text.length) {
        element.textContent += text.charAt(i);
        i++;
      } else {
        clearInterval(timer);
        // Blinking cursor
        setInterval(function() {
          element.style.borderRightColor = 
            element.style.borderRightColor === 'transparent' ? '#00ffff' : 'transparent';
        }, 500);
      }
    }, speed);
  }

})(Drupal, jQuery, once);