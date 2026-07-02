/**
 * @file
 * AmISafe Crime Map - Refactored Core JavaScript
 * 
 * Clean, organized implementation of the interactive crime map
 * Features: H3 hexagon visualization, zoom-based resolution switching, minimal mode
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * AmISafe Crime Map Drupal Behavior
   */
  Drupal.behaviors.amisafeCrimeMap = {
    attach: function (context, settings) {
      if (!settings.amisafe) {
        console.error('AmISafe settings not found');
        return;
      }

      $(context).find('#crime-map-container').addBack('#crime-map-container').each(function () {
        if (!this.hasAttribute('data-amisafe-initialized')) {
          this.setAttribute('data-amisafe-initialized', 'true');
          var crimeMap = new AmISafeCrimeMap(this, settings.amisafe);
          crimeMap.initialize();
        }
      });
    }
  };

  /**
   * AmISafe Crime Map Class
   */
  function AmISafeCrimeMap(container, settings) {
    // Core properties
    this.container = container;
    this.settings = settings;
    this.map = null;
    this.hexagonLayer = null;
    this.incidentLayer = null; // For individual incident points at high zoom
    this.currentFilters = {};
    this.debugMode = false;
    
    // Minimal mode configuration
    this.minimalMode = true; // Clean data visualization
    
    // Performance optimization
    this.dataCache = new Map();
    this.currentRequest = null;
    this.apiCallCount = 0;
    
    // Timing controls
    this.loadTimeout = null;
    this.filterTimeout = null;
    this.zoomTimeout = null;
    
    // Auto-fit control - prevent constant re-centering
    this.shouldAutoFit = true; // Only auto-fit on initial load and zoom changes
    this.isInitialLoad = true; // Track if this is the first data load
  }

  /**
   * Class Methods
   */
  AmISafeCrimeMap.prototype = {
    
    /**
     * Initialize the crime map
     */
    initialize: function() {
      console.log('🚀 Initializing AmISafe Crime Map...');
      
      // Template elements verified working - debug removed for cleaner output
      
      // Apply minimal mode styling
      if (this.minimalMode) {
        this.enableMinimalMode();
      }
      
      // Initialize map components
      this.createMap();
      this.setupEventListeners();
      this.initializeControls();
      this.initializeFilters();
      
      // Load initial data
      setTimeout(() => {
        this.loadInitialData();
      }, 500);
    },

    /**
     * Enable minimal mode for clean data visualization
     */
    enableMinimalMode: function() {
      // Apply CSS class after a delay to avoid interfering with map initialization
      setTimeout(() => {
        document.body.classList.add('minimal-mode');
        console.log('🔇 Minimal mode activated');
      }, 1000);
    },

    /**
     * Create and configure the Leaflet map
     */
    createMap: function() {
      if (!window.L) {
        console.error('Leaflet library not loaded');
        return;
      }

      // Create map with Philadelphia center
      const mapConfig = this.settings.mapConfig;
      this.map = L.map('crime-map-container', {
        center: mapConfig.center,
        zoom: mapConfig.zoom,
        minZoom: 5,  // Allow zoom out to Resolution 5 (Philadelphia metro area)
        maxZoom: 20,
        zoomControl: true,
        attributionControl: false
      });

      // Add dark tile layer
      L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '',
        subdomains: 'abcd',
        maxZoom: 20,
        className: 'dark-tiles'
      }).addTo(this.map);

      // Initialize layers
      this.hexagonLayer = L.layerGroup().addTo(this.map);
      this.incidentLayer = L.layerGroup().addTo(this.map); // For individual incident points
      
      console.log('🗺️ Map created successfully');
      
      // Check H3 library availability
      if (window.h3) {
        console.log('✅ H3 library available, version:', typeof h3.cellToBoundary === 'function' ? 'v4+' : 'unknown');
      } else {
        console.log('⚠️ H3 library not found - will use fallback circles');
      }
    },

    /**
     * Setup map event listeners
     */
    setupEventListeners: function() {
      this.map.on('zoomend', () => this.handleZoomChange());
      this.map.on('moveend', () => this.handleMapMove());
      
      // Force map resize after initialization
      setTimeout(() => {
        this.map.invalidateSize();
        this.updateZoomIndicator(); // Initial zoom indicator update
      }, 1000);
    },

    /**
     * Initialize map controls
     */
    initializeControls: function() {
      const self = this;
      
      // Manual zoom refresh for debugging
      $('#refresh-zoom').on('click', function() {
        self.updateZoomIndicator();
      });
      
      // Re-center map to fit hexagons
      $('#fit-hexagons-btn').on('click', function() {
        self.fitMapToHexagons();
      });
    },

    /**
     * Initialize filter controls
     */
    initializeFilters: function() {
      const self = this;
      
      // Initialize default filter state
      this.currentFilters = {
        crimeTypes: [],
        districts: [],
        startMonth: '01',
        endMonth: '12',
        timePeriods: ['early-morning', 'morning', 'afternoon', 'evening'],
        viewMode: 'hexagon'
      };
      
      // Load filter options from API
      this.loadFilterOptions();
      
      // Filter action buttons
      $('#apply-filters').on('click', function() {
        self.applyFilters();
      });
      
      $('#clear-filters').on('click', function() {
        self.clearAllFilters();
      });

      // Manual filtering only - no auto-apply to prevent data loss
      $('#crime-type-selector, #district-selector, #time-period-selector').on('change', function() {
        console.log('🔄 Filter dropdown changed - use Apply Filters button to apply');
        // No auto-apply - user must click Apply Filters button
      });

      // Manual filtering only - no auto-apply to prevent data loss  
      $('#start-month, #end-month').on('change', function() {
        console.log('🔄 Date range changed - use Apply Filters button to apply');
        // No auto-apply - user must click Apply Filters button
      });
      
      // View mode buttons
      $('#hexagon-view').on('click', function() {
        self.switchViewMode('hexagon');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });
      
      $('#heatmap-view').on('click', function() {
        self.switchViewMode('heatmap');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });
      
      $('#points-view').on('click', function() {
        self.switchViewMode('points');
        $('.view-options .cyber-button').removeClass('active');
        $(this).addClass('active');
      });
      
      // Quick preset buttons
      $('.preset-btn').on('click', function() {
        const preset = $(this).data('preset');
        self.applyPreset(preset);
        $('.preset-btn').removeClass('active');
        $(this).addClass('active');
      });
      
      // Map control buttons - fit-hexagons-btn is already handled in initializeControls()
      
      console.log('🔧 Filters initialized with full functionality');
    },

    /**
     * Handle zoom changes with debouncing
     */
    handleZoomChange: function() {
      // Clear existing timeout
      if (this.zoomTimeout) {
        clearTimeout(this.zoomTimeout);
      }
      
      // Update zoom indicator immediately
      this.updateZoomIndicator();
      
      // Allow auto-fit on zoom changes (user wants new data at different resolution)
      this.shouldAutoFit = true;
      
      // Debounce data loading
      this.zoomTimeout = setTimeout(() => {
        this.loadHexagonData();
      }, 300);
    },

    /**
     * Handle map movement
     */
    handleMapMove: function() {
      // Debounce map move events
      if (this.loadTimeout) {
        clearTimeout(this.loadTimeout);
      }
      
      // Prevent auto-fit on map moves (user is panning around)
      this.shouldAutoFit = false;
      
      this.loadTimeout = setTimeout(() => {
        this.loadHexagonData();
      }, 500);
    },

    /**
     * Update zoom level indicator with robust element waiting
     */
    updateZoomIndicator: function(zoom, resolution) {
      // Get current values if not provided
      if (zoom === undefined) zoom = this.map.getZoom();
      if (resolution === undefined) resolution = this.getOptimalResolution(zoom);
      
      const scaleDescription = this.getResolutionDescription(resolution);
      const roundedZoom = Math.round(zoom * 10) / 10;
      
      console.log(`📊 Updating zoom indicator: zoom=${roundedZoom}, resolution=${resolution}`);
      
      // Use robust element waiting with multiple attempts
      this.waitForZoomElements(roundedZoom, resolution, scaleDescription, 0);
    },

    /**
     * Wait for zoom indicator elements with progressive retry
     */
    waitForZoomElements: function(zoom, resolution, scaleDesc, attempt) {
      const maxAttempts = 15; // More attempts
      const baseDelay = 50; // Start with shorter delay
      const delay = Math.min(baseDelay * Math.pow(1.2, attempt), 3000); // Progressive but capped
      
      // Try multiple selection methods
      const methods = [
        () => ({
          zoom: document.getElementById('zoom-level'),
          resolution: document.getElementById('h3-resolution'),
          scale: document.querySelector('.scale-label')
        }),
        () => ({
          zoom: document.querySelector('#zoom-level'),
          resolution: document.querySelector('#h3-resolution'),
          scale: document.getElementsByClassName('scale-label')[0]
        }),
        () => ({
          zoom: document.querySelector('#zoom-indicator #zoom-level'),
          resolution: document.querySelector('#zoom-indicator #h3-resolution'),
          scale: document.querySelector('#zoom-indicator .scale-label')
        })
      ];
      
      let elements = null;
      for (let method of methods) {
        elements = method();
        if (elements.zoom && elements.resolution && elements.scale) {
          // Success! Update elements
          elements.zoom.textContent = zoom;
          elements.resolution.textContent = resolution;
          elements.scale.textContent = scaleDesc;
          console.log(`✅ Zoom indicator updated (attempt ${attempt + 1}, method ${methods.indexOf(method) + 1}): ZOOM=${zoom} H3=${resolution} ${scaleDesc}`);
          return;
        }
      }
      
      // None of the methods worked
      if (attempt < maxAttempts) {
        console.log(`⏳ Zoom elements not ready (attempt ${attempt + 1}/${maxAttempts}), retrying in ${delay}ms...`);
        setTimeout(() => {
          this.waitForZoomElements(zoom, resolution, scaleDesc, attempt + 1);
        }, delay);
      } else {
        // Final failure - comprehensive debug
        console.log(`❌ Zoom indicator elements not found after ${maxAttempts} attempts`);
        console.log('🔍 Final DOM state:');
        console.log('  - zoom-indicator container:', !!document.getElementById('zoom-indicator'));
        console.log('  - crime-map-container:', !!document.getElementById('crime-map-container'));
        console.log('  - zoom-level element:', !!document.getElementById('zoom-level'));
        console.log('  - h3-resolution element:', !!document.getElementById('h3-resolution'));
        console.log('  - scale-label elements:', document.querySelectorAll('.scale-label').length);
        console.log('  - all zoom-level elements:', document.querySelectorAll('#zoom-level').length);
        
        // DEBUG: Show what's actually inside the zoom-indicator container
        const container = document.getElementById('zoom-indicator');
        if (container) {
          console.log('🔍 zoom-indicator innerHTML:', container.innerHTML);
          console.log('🔍 zoom-indicator children:', container.children.length);
          for (let i = 0; i < container.children.length; i++) {
            const child = container.children[i];
            console.log(`  child ${i}:`, child.tagName, child.id || 'no-id', child.className || 'no-class', child.textContent.substring(0, 50));
          }
        }
        
        // Store values for manual display
        console.log(`📊 Current values: ZOOM=${zoom} H3=${resolution} ${scaleDesc}`);
        
        // FALLBACK: Create missing elements if container exists but children don't
        if (container && container.children.length === 0) {
          console.log('🔧 Creating missing zoom indicator elements...');
          container.innerHTML = `
            <span class="zoom-label">ZOOM:</span>
            <span id="zoom-level">${zoom}</span>
            <span class="resolution-label">H3:</span>
            <span id="h3-resolution">${resolution}</span>
            <span class="scale-label">${scaleDesc}</span>
            <button id="refresh-zoom" style="margin-left: 10px; font-size: 10px;">REFRESH</button>
          `;
          console.log(`✅ Zoom indicator elements created and updated: ZOOM=${zoom} H3=${resolution} ${scaleDesc}`);
        }
      }
    },

    /**
     * Get optimal H3 resolution based on zoom level
     */
    getOptimalResolution: function(zoomLevel) {
      if (zoomLevel <= 9) return 4;        // ~1,770 km² - Complete metro area coverage
      else if (zoomLevel <= 10) return 5;  // ~251 km² - Metro districts
      else if (zoomLevel <= 11) return 6;  // ~36 km² - City areas
      else if (zoomLevel <= 12) return 7;  // ~5.2 km² - Neighborhoods
      else if (zoomLevel <= 13) return 8;  // ~0.7 km² - Block groups  
      else if (zoomLevel <= 14) return 9;  // ~0.1 km² - Street blocks
      else if (zoomLevel <= 15) return 10; // ~15,047 m² - Building groups
      else if (zoomLevel <= 16) return 11; // ~2,150 m² - Individual buildings
      else if (zoomLevel <= 17) return 12; // ~307 m² - Room-level precision
      else return 13;                      // ~44 m² - Ultra-precision
    },

    /**
     * Get human-readable description of H3 resolution
     */
    getResolutionDescription: function(resolution) {
      const descriptions = {
        4: '~1,770km² metro-wide',
        5: '~251km² districts',
        6: '~36km² city areas',
        7: '~5.2km² neighborhoods',
        8: '~0.7km² block groups', 
        9: '~0.1km² street blocks',
        10: '~15,047m² building groups',
        11: '~2,150m² buildings',
        12: '~307m² rooms',
        13: '~44m² ultra-precision'
      };
      return descriptions[resolution] || 'unknown';
    },

    /**
     * Load initial crime data
     */
    loadInitialData: function() {
      this.showLoading('LOADING CRIME DATA...');
      this.shouldAutoFit = true; // Allow auto-fit for initial load
      this.isInitialLoad = true; // Flag to skip filters on initial load
      this.loadHexagonData();
      
      // Load citywide stats
      setTimeout(() => {
        this.loadCitywideStats();
      }, 1000);
    },

    /**
     * Load hexagon crime data based on current view
     */
    loadHexagonData: function() {
      const zoom = this.map.getZoom();
      const resolution = this.getOptimalResolution(zoom);
      let bounds = this.map.getBounds();
      // Only apply filters when explicitly requested (not on initial load or zoom changes)
      let filters = {}; // Default to no filters to show all data
      
      console.log(`📊 Loading H3 Resolution ${resolution} data...`);
      
      // DEBUG: For H3:5, try a much broader bounds to see if data exists
      if (resolution === 5) {
        console.log('🔍 H3:5 requested - using broader bounds for metro area coverage');
        // Expand bounds significantly for H3:5 since these are large hexagons (~251km² each)
        // Need to cover multiple H3:5 hexagons to encompass entire Philadelphia metro area
        const center = bounds.getCenter();
        const expandedBounds = L.latLngBounds(
          [center.lat - 2, center.lng - 2],  // Much larger area for multiple H3:5 hexagons
          [center.lat + 2, center.lng + 2]
        );
        console.log('🔍 Original bounds:', bounds.toString());
        console.log('🔍 Expanded bounds for H3:5 (metro area):', expandedBounds.toString());
        bounds = expandedBounds;
        
        // TEMPORARILY: Clear filters for H3:5 to test if filters are blocking the hexagon
        console.log('🧪 Temporarily clearing filters for H3:5 debugging...');
        filters = {
          crimeTypes: [],
          districts: [],
          startMonth: '01',
          endMonth: '12',
          timePeriods: [],
          viewMode: 'hexagon'
        };
      }
      
      // Cancel previous request
      if (this.currentRequest) {
        this.currentRequest.abort();
      }
      
      // Build API URL
      const apiUrl = this.buildApiUrl(resolution, bounds, filters);
      
      // Make API request
      this.currentRequest = $.ajax({
        url: apiUrl,
        method: 'GET',
        timeout: 30000
      })
      .done((data) => {
        console.log('📊 Received filtered data:', {
          hexagons: data.hexagons ? data.hexagons.length : 0,
          resolution: data.meta ? data.meta.resolution : 'unknown',
          filters: data.meta ? data.meta.filters : 'none'
        });
        
        // Debug: Check for H3:5 hexagons in received data
        if (data.hexagons && data.hexagons.length > 0) {
          const h3_5_hexagons = data.hexagons.filter(hex => {
            if (window.h3 && hex.h3_index) {
              return h3.getResolution(hex.h3_index) === 5;
            }
            return false;
          });
          if (h3_5_hexagons.length > 0) {
            console.log('🔍 Found H3:5 hexagons in API data:', h3_5_hexagons.length);
          } else if (resolution === 5) {
            console.warn('⚠️ Expected H3:5 hexagons but none found in API response');
          }
        } else if (resolution === 5) {
          // Test if H3:5 data exists at all with minimal filters
          console.log('🧪 Testing if H3:5 data exists anywhere...');
          const testUrl = '/api/amisafe/aggregated?resolution=5&limit=10';
          fetch(testUrl)
            .then(response => response.json())
            .then(testData => {
              if (testData.hexagons && testData.hexagons.length > 0) {
                console.log('✅ H3:5 data EXISTS in database:', testData.hexagons.length, 'hexagons');
                console.log('🔍 Sample H3:5 hexagon:', testData.hexagons[0]);
              } else {
                console.error('❌ NO H3:5 data found in database - backend aggregation issue');
              }
            })
            .catch(error => {
              console.error('❌ Error testing H3:5 data:', error);
            });
        }
        
        this.renderHexagons(data);
        this.hideLoading();
        
        // Reset initial load flag after first successful data load
        if (this.isInitialLoad) {
          this.isInitialLoad = false;
          console.log('🔄 Initial load complete, filters now active');
        }
      })
      .fail((xhr, status, error) => {
        if (status !== 'abort') {
          console.error('API request failed:', error);
          this.hideLoading();
        }
      });
    },

    /**
     * Load hexagon data WITH filters applied (only called from applyFilters)
     */
    loadHexagonDataWithFilters: function() {
      const zoom = this.map.getZoom();
      const resolution = this.getOptimalResolution(zoom);
      let bounds = this.map.getBounds();
      let filters = this.getCurrentFilters(); // Apply current filters
      
      console.log(`📊 Loading H3 Resolution ${resolution} data WITH FILTERS...`);
      
      // Cancel any ongoing request
      if (this.currentRequest) {
        this.currentRequest.abort();
      }
      
      const apiUrl = this.buildApiUrl(resolution, bounds, filters);
      
      this.currentRequest = $.get(apiUrl)
      .done((data) => {
        console.log('📊 Received filtered data:', data);
        
        if (!data || !data.hexagons) {
          console.warn('⚠️ No hexagon data in API response');
          this.hideLoading();
          return;
        }
        
        if (data.hexagons.length === 0) {
          console.log('📊 No hexagons match current filters');
        }
        
        this.renderHexagons(data);
        this.hideLoading();
      })
      .fail((xhr, status, error) => {
        if (status !== 'abort') {
          console.error('Filtered API request failed:', error);
          this.hideLoading();
        }
      });
    },

    /**
     * Build API URL for hexagon data
     */
    buildApiUrl: function(resolution, bounds, filters) {
      const baseUrl = '/api/amisafe/aggregated';
      const params = new URLSearchParams();
      
      // Add basic parameters
      params.append('resolution', resolution);
      params.append('bounds', bounds.getNorth() + ',' + bounds.getEast() + ',' + bounds.getSouth() + ',' + bounds.getWest());
      params.append('limit', 1000);
      
      // Add filter parameters if they exist
      if (filters.crimeTypes && filters.crimeTypes.length > 0) {
        params.append('crime_types', filters.crimeTypes.join(','));
      }
      if (filters.districts && filters.districts.length > 0) {
        params.append('districts', filters.districts.join(','));
      }
      if (filters.startMonth) {
        params.append('start_month', filters.startMonth);
      }
      if (filters.endMonth) {
        params.append('end_month', filters.endMonth);
      }
      
      const finalUrl = `${baseUrl}?${params.toString()}`;
      console.log('🔗 API URL:', finalUrl);
      return finalUrl;
    },

    /**
     * Render hexagons on the map
     */
    renderHexagons: function(data) {
      // Clear existing layers
      this.hexagonLayer.clearLayers();
      this.incidentLayer.clearLayers();
      
      if (!data.hexagons || data.hexagons.length === 0) {
        console.log('📊 No hexagon data received');
        return;
      }
      
      // Check if we should render individual incidents (Resolution 10+)
      const currentResolution = this.getOptimalResolution(this.map.getZoom());
      const shouldShowIncidents = currentResolution >= 10;
      
      // Track hexagon bounds to fit map view
      const allBounds = L.latLngBounds();
      let successfulHexagons = 0;
      
      // Render each hexagon
      data.hexagons.forEach(hexagon => {
        const result = this.renderSingleHexagon(hexagon);
        if (result && result.getBounds) {
          allBounds.extend(result.getBounds());
          successfulHexagons++;
        }
      });
      
      console.log(`📊 Rendered ${data.hexagons.length} hexagons (${successfulHexagons} successful)`);
      
      // Load individual incidents for high-resolution views
      if (shouldShowIncidents && successfulHexagons > 0) {
        console.log(`🔍 Loading individual incidents for Resolution ${currentResolution}`);
        this.loadIncidentPoints(data.hexagons);
      }
      
      // Fit map to show all hexagons only when appropriate (not during user panning)
      if (successfulHexagons > 0 && allBounds.isValid() && this.shouldAutoFit) {
        setTimeout(() => {
          this.map.fitBounds(allBounds, {
            padding: [20, 20],
            maxZoom: 15
          });
          console.log(`📍 Map fitted to show ${successfulHexagons} hexagons`);
          
          // Disable auto-fit after initial load to prevent constant re-centering
          if (this.isInitialLoad) {
            this.isInitialLoad = false;
            console.log('🎯 Initial load complete - auto-fit disabled for user interaction');
          }
        }, 500);
      } else if (successfulHexagons > 0) {
        console.log(`📊 ${successfulHexagons} hexagons rendered without auto-fit (user is panning)`);
      }
    },

    /**
     * Load and render individual incident points for high-resolution views
     */
    loadIncidentPoints: function(hexagons) {
      // Get the current map bounds to limit incident queries
      const bounds = this.map.getBounds();
      const params = new URLSearchParams({
        minLat: bounds.getSouth(),
        maxLat: bounds.getNorth(),
        minLng: bounds.getWest(),
        maxLng: bounds.getEast(),
        limit: 500, // Limit to prevent overwhelming the map
        format: 'json'
      });
      
      // Add current filters
      Object.keys(this.currentFilters).forEach(key => {
        if (this.currentFilters[key]) {
          params.append(key, this.currentFilters[key]);
        }
      });
      
      const apiUrl = `${this.settings.apiEndpoints.incidents}?${params.toString()}`;
      
      fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
          if (data.incidents && data.incidents.length > 0) {
            this.renderIncidentPoints(data.incidents);
            console.log(`📍 Rendered ${data.incidents.length} individual incident points`);
          } else {
            console.log('📍 No individual incidents found for current view');
          }
        })
        .catch(error => {
          console.error('Error loading incident points:', error);
        });
    },

    /**
     * Render individual incident points on the map
     */
    renderIncidentPoints: function(incidents) {
      incidents.forEach(incident => {
        if (incident.lat && incident.lng) {
          // Create incident marker
          const marker = L.circleMarker([incident.lat, incident.lng], {
            radius: 3,
            fillColor: this.getIncidentColor(incident.incident_type),
            color: '#ffffff',
            weight: 1,
            opacity: 0.8,
            fillOpacity: 0.6
          });
          
          // Add popup with incident details
          if (!this.minimalMode) {
            marker.bindPopup(this.createIncidentPopup(incident));
          }
          
          marker.addTo(this.incidentLayer);
        }
      });
    },

    /**
     * Get color for incident type
     */
    getIncidentColor: function(incidentType) {
      const colors = {
        'violent': '#ff4444',     // Red for violent crimes
        'property': '#ff8800',    // Orange for property crimes  
        'drug': '#8844ff',        // Purple for drug crimes
        'traffic': '#44ff44',     // Green for traffic incidents
        'other': '#44ffff'        // Cyan for other incidents
      };
      
      // Map incident codes to categories (simplified)
      if (!incidentType) return colors.other;
      
      const code = incidentType.toString();
      if (code.startsWith('1') || code.startsWith('2')) return colors.violent;
      if (code.startsWith('3') || code.startsWith('5')) return colors.property;
      if (code.startsWith('4')) return colors.drug;
      if (code.startsWith('7')) return colors.traffic;
      
      return colors.other;
    },

    /**
     * Create popup content for individual incidents
     */
    createIncidentPopup: function(incident) {
      const date = incident.incident_date ? new Date(incident.incident_date).toLocaleDateString() : 'Unknown';
      const time = incident.incident_time || 'Unknown';
      const type = incident.incident_type || 'Unknown';
      const location = incident.location_block || 'Unknown location';
      
      return `
        <div class="incident-popup">
          <strong>Crime Incident</strong><br>
          <strong>Type:</strong> ${type}<br>
          <strong>Date:</strong> ${date}<br>
          <strong>Time:</strong> ${time}<br>
          <strong>Location:</strong> ${location}
        </div>
      `;
    },

    /**
     * Render a single hexagon
     */
    renderSingleHexagon: function(hexagon) {
      const incidentCount = hexagon.incident_count || hexagon.incidentCount || 0;
      const h3Index = hexagon.h3_index;
      
      if (!h3Index) {
        console.warn('No H3 index found for hexagon:', hexagon);
        return;
      }
      
      // Debug logging for H3:5 hexagons
      const h3Resolution = window.h3 ? h3.getResolution(h3Index) : 'unknown';
      if (h3Resolution === 5) {
        console.log('🔍 Rendering H3:5 hexagon:', {
          h3Index: h3Index,
          incidentCount: incidentCount,
          resolution: h3Resolution
        });
      }
      
      // Use H3 library to get boundary if available
      if (window.h3 && h3.cellToBoundary) {
        try {
          // Get H3 boundary coordinates
          const boundary = h3.cellToBoundary(h3Index, true);
          
          // Convert from H3 [lng, lat] to Leaflet [lat, lng] format
          const leafletCoords = boundary.map(coord => [coord[1], coord[0]]);
          
          // Debug logging for large hexagons
          if (h3Resolution === 5) {
            console.log('🔍 H3:5 boundary coordinates:', leafletCoords);
          }
          
          // Calculate styling based on incident count
          const style = this.calculateHexagonStyle(incidentCount);
          
          // Create and add polygon to map
          const polygon = L.polygon(leafletCoords, style);
          
          // Add popup and event handlers if not in minimal mode
          if (!this.minimalMode) {
            polygon.bindPopup(this.createHexagonPopup(hexagon));
            polygon.on('mouseover', function(e) {
              e.target.setStyle({ weight: 2, fillOpacity: 0.9 });
            });
            polygon.on('mouseout', function(e) {
              e.target.setStyle({ weight: 1, fillOpacity: style.fillOpacity });
            });
          }
          
          polygon.addTo(this.hexagonLayer);
          
          // Success logging for H3:5
          if (h3Resolution === 5) {
            console.log('✅ H3:5 hexagon successfully rendered');
          }
          
          return polygon; // Return polygon for bounds tracking
          
        } catch (error) {
          console.warn('Failed to render hexagon', h3Index, ':', error);
          if (h3Resolution === 5) {
            console.error('❌ H3:5 hexagon rendering failed:', error);
          }
          return this.createFallbackCircle(hexagon);
        }
      } else {
        // Fallback to circle if H3 library not available
        console.warn('H3 library not available, using fallback circle for:', h3Index);
        return this.createFallbackCircle(hexagon);
      }
    },

    /**
     * Create fallback circle when H3 is not available
     */
    createFallbackCircle: function(hexagon) {
      const incidentCount = hexagon.incident_count || hexagon.incidentCount || 0;
      const lat = hexagon.lat || 39.9526;
      const lng = hexagon.lng || -75.1652;
      const radius = Math.max(50, incidentCount * 5);
      
      const style = this.calculateHexagonStyle(incidentCount);
      const circle = L.circle([lat, lng], {
        radius: radius,
        ...style
      });
      
      if (!this.minimalMode) {
        circle.bindPopup(this.createHexagonPopup(hexagon));
      }
      
      circle.addTo(this.hexagonLayer);
      return circle; // Return circle for bounds tracking
    },

    /**
     * Calculate hexagon styling based on incident count
     */
    calculateHexagonStyle: function(incidentCount) {
      // Color intensity based on incident count
      const maxIntensity = 100; // Adjust based on your data
      const intensity = Math.min(incidentCount / maxIntensity, 1);
      
      return {
        fillColor: this.minimalMode ? '#00ff41' : '#ff0040',
        weight: 1,
        opacity: 0.8,
        color: this.minimalMode ? '#00ff41' : '#00bfff',
        fillOpacity: 0.3 + (intensity * 0.4)
      };
    },

    /**
     * Create popup content for hexagon
     */
    createHexagonPopup: function(hexagon) {
      const incidentCount = hexagon.incident_count || hexagon.incidentCount || 0;
      const h3Index = hexagon.h3_index || hexagon.h3Index || 'Unknown';
      
      return `
        <div class="hexagon-popup terminal-text">
          <h4>SECTOR ${h3Index.substring(0, 8).toUpperCase()}</h4>
          <div class="stat-line">INCIDENTS: <span class="neon-green">${incidentCount}</span></div>
          <div class="stat-line">H3 ID: <span class="neon-yellow">${h3Index}</span></div>
        </div>
      `;
    },

    /**
     * Get current filter values
     */
    getCurrentFilters: function() {
      // Return current filter settings
      return this.currentFilters;
    },

    /**
     * Load citywide statistics
     */
    loadCitywideStats: function() {
      const self = this;
      console.log('📈 Loading citywide statistics...');
      
      $.ajax({
        url: '/api/amisafe/citywide-stats',
        method: 'GET',
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
          if (response && response.citywide_stats) {
            const stats = response.citywide_stats;
            $('#citywide-total').text((stats.total_incidents || 0).toLocaleString());
            $('#citywide-districts').text(stats.active_districts || '--');
            $('#citywide-threat').text(stats.threat_level || 'UNKNOWN');
            $('#citywide-coverage').text((stats.coverage_percent || 0) + '%');
          }
        },
        error: function() {
          // Use mock data if API fails
          $('#citywide-total').text('3,406,192');
          $('#citywide-districts').text('25');
          $('#citywide-threat').text('MODERATE');
          $('#citywide-coverage').text('98.7%');
        }
      });
    },

    /**
     * Show loading overlay
     */
    showLoading: function(message) {
      const overlay = $('#loading-overlay');
      overlay.find('.terminal-text').text(message || 'LOADING...');
      overlay.show();
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function() {
      $('#loading-overlay').fadeOut(300);
    },

    /**
     * Reset map view to initial position
     */
    resetView: function() {
      const mapConfig = this.settings.mapConfig;
      this.map.setView(mapConfig.center, mapConfig.zoom);
      console.log('🏠 View reset to initial position');
    },

    /**
     * Manually fit map to show all hexagons
     */
    fitMapToHexagons: function() {
      if (this.hexagonLayer && this.hexagonLayer.getLayers().length > 0) {
        const allBounds = L.latLngBounds();
        let hexagonCount = 0;
        
        this.hexagonLayer.eachLayer(function(layer) {
          if (layer.getBounds) {
            allBounds.extend(layer.getBounds());
            hexagonCount++;
          }
        });
        
        if (hexagonCount > 0 && allBounds.isValid()) {
          this.map.fitBounds(allBounds, {
            padding: [20, 20],
            maxZoom: 15
          });
          console.log(`📍 Manually fitted map to show ${hexagonCount} hexagons`);
        } else {
          console.log('⚠️ No hexagons available to fit map view');
        }
      } else {
        console.log('⚠️ No hexagon layer available for fitting');
      }
    },

    /**
     * Load filter options from API
     */
    loadFilterOptions: function() {
      const self = this;
      
      // Load crime types
      $.ajax({
        url: '/api/amisafe/crime-types',
        method: 'GET',
        success: function(data) {
          // Handle API response format - extract crime_types array from response
          const crimeTypes = data.crime_types || data || [];
          console.log('📊 Crime types data received:', crimeTypes);
          self.populateCrimeTypes(crimeTypes);
        },
        error: function() {
          console.warn('Failed to load crime types, using defaults');
          self.populateCrimeTypes(self.getDefaultCrimeTypes());
        }
      });
      
      // Load districts
      $.ajax({
        url: '/api/amisafe/districts',
        method: 'GET',
        success: function(data) {
          // Handle API response format - extract districts array from response
          const districts = data.districts || data || [];
          console.log('🏘️ Districts data received:', districts);
          self.populateDistricts(districts);
        },
        error: function() {
          console.warn('Failed to load districts, using defaults');
          self.populateDistricts(self.getDefaultDistricts());
        }
      });
    },

    /**
     * Get default crime types if API fails
     */
    getDefaultCrimeTypes: function() {
      return [
        { value: '100', label: 'HOMICIDE' },
        { value: '200', label: 'ASSAULT' },
        { value: '300', label: 'ROBBERY' },
        { value: '400', label: 'BURGLARY' },
        { value: '500', label: 'THEFT' },
        { value: '600', label: 'AUTO THEFT' },
        { value: '700', label: 'VANDALISM' },
        { value: '800', label: 'DRUGS' },
        { value: '900', label: 'WEAPON OFFENSE' },
        { value: '1000', label: 'FRAUD' }
      ];
    },

    /**
     * Get default districts if API fails
     */
    getDefaultDistricts: function() {
      return [
        { value: '1', label: 'DISTRICT 1 - CENTER CITY' },
        { value: '2', label: 'DISTRICT 2 - SOUTH' },
        { value: '3', label: 'DISTRICT 3 - WEST' },
        { value: '4', label: 'DISTRICT 4 - NORTH' },
        { value: '5', label: 'DISTRICT 5 - NORTHEAST' },
        { value: '6', label: 'DISTRICT 6 - NORTHWEST' }
      ];
    },

    /**
     * Populate crime type selector
     */
    populateCrimeTypes: function(crimeTypes) {
      const selector = $('#crime-type-selector');
      selector.empty();
      
      let processedTypes = [];
      
      // Handle different data formats
      if (Array.isArray(crimeTypes)) {
        // Already an array of objects with value/label
        processedTypes = crimeTypes;
      } else if (typeof crimeTypes === 'object' && crimeTypes !== null) {
        // Object format: {100: 'Murder', 200: 'Rape', ...}
        processedTypes = Object.entries(crimeTypes).map(([key, value]) => ({
          value: key,
          label: value
        }));
      } else {
        console.warn('Crime types data format not recognized:', crimeTypes);
        processedTypes = this.getDefaultCrimeTypes();
      }
      
      processedTypes.forEach(type => {
        selector.append(`<option value="${type.value}" selected>${type.label}</option>`);
      });
      
      // Update current filters
      this.currentFilters.crimeTypes = processedTypes.map(t => t.value);
      console.log('✅ Crime types populated:', processedTypes.length, 'types');
    },

    /**
     * Populate district selector
     */
    populateDistricts: function(districts) {
      const selector = $('#district-selector');
      selector.empty();
      
      let processedDistricts = [];
      
      // Handle different data formats
      if (Array.isArray(districts)) {
        // Check if first element has value/label structure
        if (districts.length > 0 && typeof districts[0] === 'object' && districts[0].value) {
          // Already an array of objects with value/label
          processedDistricts = districts;
        } else {
          // Simple array of strings/numbers: ['1', '2', '3', ...]
          processedDistricts = districts.map(district => ({
            value: district,
            label: `DISTRICT ${district}`
          }));
        }
      } else {
        console.warn('Districts data format not recognized:', districts);
        processedDistricts = this.getDefaultDistricts();
      }
      
      processedDistricts.forEach(district => {
        selector.append(`<option value="${district.value}" selected>${district.label}</option>`);
      });
      
      // Update current filters
      this.currentFilters.districts = processedDistricts.map(d => d.value);
      console.log('✅ Districts populated:', processedDistricts.length, 'districts');
    },

    /**
     * Apply current filters
     */
    applyFilters: function() {
      // Collect filter values
      this.currentFilters.crimeTypes = $('#crime-type-selector').val() || [];
      this.currentFilters.districts = $('#district-selector').val() || [];
      this.currentFilters.startMonth = $('#start-month').val();
      this.currentFilters.endMonth = $('#end-month').val();
      this.currentFilters.timePeriods = $('#time-period-selector').val() || [];
      
      console.log('🔍 Applying filters:', this.currentFilters);
      console.log('🎯 Filter summary:', {
        crimeTypes: this.currentFilters.crimeTypes.length,
        districts: this.currentFilters.districts.length,
        dateRange: `${this.currentFilters.startMonth}-${this.currentFilters.endMonth}`,
        timePeriods: this.currentFilters.timePeriods.length
      });
      
      // Clear existing data to show filter changes
      if (this.hexagonLayer) {
        this.hexagonLayer.clearLayers();
      }
      
      // Show loading
      this.showLoading('APPLYING FILTERS...');
      
      // Reload data with filters - force fresh load
      this.loadHexagonDataWithFilters();
      
      // Update stats with filtered data
      this.updateStats();
    },

    /**
     * Clear all filters to default state
     */
    clearAllFilters: function() {
      // Reset all selectors to default (all selected)
      $('#crime-type-selector option').prop('selected', true);
      $('#district-selector option').prop('selected', true);
      $('#start-month').val('01');
      $('#end-month').val('12');
      $('#time-period-selector option').prop('selected', true);
      
      // Clear preset button states
      $('.preset-btn').removeClass('active');
      
      // Apply cleared filters
      this.applyFilters();
      
      console.log('🔄 All filters cleared to default state');
    },

    /**
     * Apply quick filter presets
     */
    applyPreset: function(preset) {
      console.log('⚡ Applying preset:', preset);
      
      // Clear current selections first
      $('#crime-type-selector option').prop('selected', false);
      
      switch (preset) {
        case 'violent':
          // Select violent crime types
          $('#crime-type-selector option[value="100"], #crime-type-selector option[value="200"], #crime-type-selector option[value="300"], #crime-type-selector option[value="900"]').prop('selected', true);
          break;
          
        case 'property':
          // Select property crime types
          $('#crime-type-selector option[value="400"], #crime-type-selector option[value="500"], #crime-type-selector option[value="600"], #crime-type-selector option[value="700"]').prop('selected', true);
          break;
          
        case 'recent':
          // Select all crime types but limit to recent months
          $('#crime-type-selector option').prop('selected', true);
          const currentMonth = new Date().getMonth() + 1;
          const recentMonth = currentMonth > 3 ? (currentMonth - 3).toString().padStart(2, '0') : '01';
          $('#start-month').val(recentMonth);
          $('#end-month').val(currentMonth.toString().padStart(2, '0'));
          break;
      }
      
      // Apply the preset filters
      this.applyFilters();
    },

    /**
     * Switch view mode
     */
    switchViewMode: function(mode) {
      console.log('🔄 Switching to view mode:', mode);
      this.currentFilters.viewMode = mode;
      
      // Hide all layers first
      if (this.hexagonLayer) this.map.removeLayer(this.hexagonLayer);
      if (this.heatmapLayer) this.map.removeLayer(this.heatmapLayer);
      if (this.incidentLayer) this.map.removeLayer(this.incidentLayer);
      
      // Show selected layer
      switch (mode) {
        case 'hexagon':
          if (this.hexagonLayer) this.map.addLayer(this.hexagonLayer);
          break;
        case 'heatmap':
          this.loadHeatmapData();
          break;
        case 'points':
          this.loadPointsData();
          break;
      }
    },

    /**
     * Load heatmap data
     */
    loadHeatmapData: function() {
      console.log('🔥 Loading heatmap data...');
      this.showLoading('GENERATING HEATMAP...');
      
      const self = this;
      const bounds = this.map.getBounds();
      
      // Prepare API parameters
      const apiData = {
        bounds: `${bounds.getNorth()},${bounds.getEast()},${bounds.getSouth()},${bounds.getWest()}`,
        limit: 2000,
        page: 0
      };

      // Add filters if they exist
      if (this.currentFilters.crimeTypes && this.currentFilters.crimeTypes.length > 0) {
        apiData.crime_types = this.currentFilters.crimeTypes.join(',');
      }
      if (this.currentFilters.districts && this.currentFilters.districts.length > 0) {
        apiData.districts = this.currentFilters.districts.join(',');
      }
      if (this.currentFilters.startMonth) {
        apiData.start_month = this.currentFilters.startMonth;
      }
      if (this.currentFilters.endMonth) {
        apiData.end_month = this.currentFilters.endMonth;
      }
      
      $.ajax({
        url: '/api/amisafe/incidents',
        method: 'GET',
        data: apiData,
        success: function(data) {
          const incidents = data.incidents || data || [];
          self.createHeatmapLayer(incidents);
          self.hideLoading();
          console.log('🔥 Heatmap data loaded:', incidents.length, 'points');
        },
        error: function(xhr, status, error) {
          self.hideLoading();
          console.warn('Failed to load heatmap data:', status, error);
          console.log('🔥 Using mock heatmap data instead');
          self.createMockHeatmap();
        }
      });
    },

    /**
     * Load points data
     */
    loadPointsData: function() {
      console.log('📍 Loading individual incident points...');
      this.showLoading('LOADING INCIDENT POINTS...');
      
      const self = this;
      const bounds = this.map.getBounds();
      const zoom = this.map.getZoom();
      
      // Only load individual points at high zoom levels
      if (zoom < 12) {
        this.hideLoading();
        console.log('📍 Zoom too low for individual points, showing aggregated data instead');
        this.switchViewMode('hexagon');
        return;
      }
      
      // Prepare API parameters
      const apiData = {
        bounds: `${bounds.getNorth()},${bounds.getEast()},${bounds.getSouth()},${bounds.getWest()}`,
        limit: 500,
        page: 0
      };

      // Add filters if they exist
      if (this.currentFilters.crimeTypes && this.currentFilters.crimeTypes.length > 0) {
        apiData.crime_types = this.currentFilters.crimeTypes.join(',');
      }
      if (this.currentFilters.districts && this.currentFilters.districts.length > 0) {
        apiData.districts = this.currentFilters.districts.join(',');
      }
      if (this.currentFilters.startMonth) {
        apiData.start_month = this.currentFilters.startMonth;
      }
      if (this.currentFilters.endMonth) {
        apiData.end_month = this.currentFilters.endMonth;
      }

      $.ajax({
        url: '/api/amisafe/incidents',
        method: 'GET',
        data: apiData,
        success: function(data) {
          const incidents = data.incidents || data || [];
          self.createPointsLayer(incidents);
          self.hideLoading();
          console.log('📍 Points data loaded:', incidents.length, 'incidents');
        },
        error: function(xhr, status, error) {
          self.hideLoading();
          console.warn('Failed to load points data:', status, error);
          console.log('📍 Using mock points data instead');
          self.createMockPoints();
        }
      });
    },





    /**
     * Update statistics display
     */
    updateStats: function() {
      // Update current view stats
      const totalIncidents = this.getCurrentIncidentCount();
      const threatLevel = this.calculateThreatLevel();
      const activeSectors = this.getActiveSectorCount();
      
      $('#total-incidents').text(totalIncidents.toLocaleString());
      $('#threat-level').text(threatLevel);
      $('#active-sectors').text(activeSectors);
      
      // Load citywide stats
      this.loadCitywideStats();
    },

    /**
     * Get current incident count
     */
    getCurrentIncidentCount: function() {
      // Calculate from current hexagon data
      let total = 0;
      if (this.hexagonLayer) {
        this.hexagonLayer.eachLayer(function(layer) {
          if (layer.options && layer.options.incidentCount) {
            total += layer.options.incidentCount;
          }
        });
      }
      return total;
    },

    /**
     * Calculate threat level
     */
    calculateThreatLevel: function() {
      const incidentCount = this.getCurrentIncidentCount();
      const sectorCount = this.getActiveSectorCount();
      
      if (sectorCount === 0) return 'MINIMAL';
      
      const avgIncidentsPerSector = incidentCount / sectorCount;
      
      if (avgIncidentsPerSector > 50) return 'EXTREME';
      if (avgIncidentsPerSector > 30) return 'CRITICAL';
      if (avgIncidentsPerSector > 15) return 'HIGH';
      if (avgIncidentsPerSector > 5) return 'MODERATE';
      return 'LOW';
    },

    /**
     * Get active sector count
     */
    getActiveSectorCount: function() {
      return this.hexagonLayer ? this.hexagonLayer.getLayers().length : 0;
    },

    /**
     * Create heatmap layer from incident data
     */
    createHeatmapLayer: function(incidents) {
      // Remove existing heatmap layer
      if (this.heatmapLayer) {
        this.map.removeLayer(this.heatmapLayer);
      }
      
      // Check if Leaflet heatmap plugin is available
      if (typeof L.heatLayer === 'undefined') {
        console.warn('Leaflet heatmap plugin not available, using fallback visualization');
        this.createHeatmapFallback(incidents);
        return;
      }
      
      // Convert incidents to heatmap points
      const heatPoints = incidents.map(incident => [
        parseFloat(incident.latitude),
        parseFloat(incident.longitude),
        parseFloat(incident.severity || 1)
      ]);
      
      // Create heatmap layer
      this.heatmapLayer = L.heatLayer(heatPoints, {
        radius: 25,
        blur: 15,
        maxZoom: 17,
        gradient: {
          0.0: '#0099ff',
          0.3: '#00ff66', 
          0.5: '#ffaa00',
          0.7: '#ff6600',
          1.0: '#ff0000'
        }
      });
      
      this.map.addLayer(this.heatmapLayer);
    },

    /**
     * Fallback heatmap using circle markers
     */
    createHeatmapFallback: function(incidents) {
      this.heatmapLayer = L.layerGroup();
      
      incidents.forEach(incident => {
        const severity = parseInt(incident.severity || 1);
        const color = this.getSeverityColor(severity);
        
        const circle = L.circle([incident.latitude, incident.longitude], {
          radius: 50 + (severity * 20),
          fillColor: color,
          color: color,
          weight: 1,
          opacity: 0.3,
          fillOpacity: 0.2
        });
        
        this.heatmapLayer.addLayer(circle);
      });
      
      this.map.addLayer(this.heatmapLayer);
    },

    /**
     * Create mock heatmap for testing
     */
    createMockHeatmap: function() {
      // Check if Leaflet heatmap plugin is available
      if (typeof L.heatLayer === 'undefined') {
        console.warn('Leaflet heatmap plugin not available, using mock fallback');
        this.createMockHeatmapFallback();
        return;
      }
      
      const mockPoints = [];
      const center = this.map.getCenter();
      
      // Generate random points around the center
      for (let i = 0; i < 100; i++) {
        mockPoints.push([
          center.lat + (Math.random() - 0.5) * 0.02,
          center.lng + (Math.random() - 0.5) * 0.02,
          Math.random() * 5
        ]);
      }
      
      this.heatmapLayer = L.heatLayer(mockPoints, {
        radius: 25,
        blur: 15,
        maxZoom: 17
      });
      
      this.map.addLayer(this.heatmapLayer);
    },

    /**
     * Mock heatmap fallback using circles
     */
    createMockHeatmapFallback: function() {
      this.heatmapLayer = L.layerGroup();
      const center = this.map.getCenter();
      
      for (let i = 0; i < 50; i++) {
        const severity = Math.floor(Math.random() * 5) + 1;
        
        const circle = L.circle([
          center.lat + (Math.random() - 0.5) * 0.02,
          center.lng + (Math.random() - 0.5) * 0.02
        ], {
          radius: 50 + (severity * 20),
          fillColor: this.getSeverityColor(severity),
          color: this.getSeverityColor(severity),
          weight: 1,
          opacity: 0.3,
          fillOpacity: 0.2
        });
        
        this.heatmapLayer.addLayer(circle);
      }
      
      this.map.addLayer(this.heatmapLayer);
    },

    /**
     * Create points layer from incident data
     */
    createPointsLayer: function(incidents) {
      // Remove existing points layer
      if (this.incidentLayer) {
        this.map.removeLayer(this.incidentLayer);
      }
      
      this.incidentLayer = L.layerGroup();
      
      incidents.forEach(incident => {
        const severity = parseInt(incident.severity || 1);
        const color = this.getSeverityColor(severity);
        
        const marker = L.circleMarker([incident.latitude, incident.longitude], {
          radius: 4 + severity,
          fillColor: color,
          color: '#fff',
          weight: 1,
          opacity: 0.8,
          fillOpacity: 0.7
        });
        
        // Add popup with incident details
        marker.bindPopup(`
          <div class="incident-popup">
            <h4>${incident.crime_type || 'Unknown Crime'}</h4>
            <p><strong>Date:</strong> ${incident.incident_date || 'Unknown'}</p>
            <p><strong>District:</strong> ${incident.district || 'Unknown'}</p>
            <p><strong>Severity:</strong> Level ${severity}</p>
          </div>
        `);
        
        this.incidentLayer.addLayer(marker);
      });
      
      this.map.addLayer(this.incidentLayer);
    },

    /**
     * Create mock points for testing
     */
    createMockPoints: function() {
      this.incidentLayer = L.layerGroup();
      const center = this.map.getCenter();
      
      const crimeTypes = ['THEFT', 'ASSAULT', 'BURGLARY', 'VANDALISM', 'ROBBERY'];
      
      for (let i = 0; i < 50; i++) {
        const severity = Math.floor(Math.random() * 5) + 1;
        const crimeType = crimeTypes[Math.floor(Math.random() * crimeTypes.length)];
        
        const marker = L.circleMarker([
          center.lat + (Math.random() - 0.5) * 0.01,
          center.lng + (Math.random() - 0.5) * 0.01
        ], {
          radius: 4 + severity,
          fillColor: this.getSeverityColor(severity),
          color: '#fff',
          weight: 1,
          opacity: 0.8,
          fillOpacity: 0.7
        });
        
        marker.bindPopup(`
          <div class="incident-popup">
            <h4>${crimeType}</h4>
            <p><strong>Severity:</strong> Level ${severity}</p>
            <p><strong>Status:</strong> Mock Data</p>
          </div>
        `);
        
        this.incidentLayer.addLayer(marker);
      }
      
      this.map.addLayer(this.incidentLayer);
    },

    /**
     * Get severity color mapping
     */
    getSeverityColor: function(severity) {
      const colors = {
        1: '#0099ff', // Low - Blue
        2: '#00ff66', // Moderate - Green  
        3: '#ffaa00', // Medium - Orange
        4: '#ff6600', // High - Red-Orange
        5: '#ff0000'  // Critical - Red
      };
      return colors[severity] || '#888888';
    },

    /**
     * Clear all visualization layers
     */
    clearVisualizationLayers: function() {
      // Clear hexagon layer
      if (this.hexagonLayer) {
        this.map.removeLayer(this.hexagonLayer);
        this.hexagonLayer = null;
      }
      
      // Clear heatmap layer
      if (this.heatmapLayer) {
        this.map.removeLayer(this.heatmapLayer);
        this.heatmapLayer = null;
      }
      
      // Clear incident layer
      if (this.incidentLayer) {
        this.map.removeLayer(this.incidentLayer);
        this.incidentLayer = null;
      }
    },

    /**
     * Update layer visibility based on current mode
     */
    updateLayerVisibility: function() {
      const mode = this.currentViewMode;
      
      // Show/hide layers based on current mode
      if (this.hexagonLayer) {
        if (mode === 'hexagon') {
          this.map.addLayer(this.hexagonLayer);
        } else {
          this.map.removeLayer(this.hexagonLayer);
        }
      }
      
      if (this.heatmapLayer) {
        if (mode === 'heatmap') {
          this.map.addLayer(this.heatmapLayer);
        } else {
          this.map.removeLayer(this.heatmapLayer);
        }
      }
      
      if (this.incidentLayer) {
        if (mode === 'points') {
          this.map.addLayer(this.incidentLayer);
        } else {
          this.map.removeLayer(this.incidentLayer);
        }
      }
    }

  };

})(jQuery, Drupal, drupalSettings);