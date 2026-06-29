# AmISafe Module - Drupal Implementation Guide

## Overview
This guide provides step-by-step instructions for implementing the AmISafe crime map dashboard within the existing Drupal module structure on the Theory of Conspiracies website.

## Current Module Structure
```
sites/theoryofconspiracies/web/modules/custom/amisafe/
├── amisafe.info.yml (existing)
├── amisafe.routing.yml (existing) 
├── amisafe.module (existing)
├── amisafe.libraries.yml (existing)
├── src/ (existing)
├── templates/ (existing)
├── css/ (existing)
└── data/ (existing)
```

## Implementation Steps

### Step 1: Extend Module Configuration

#### 1.1 Update amisafe.info.yml
```yaml
name: Am I Safe
type: module  
description: Dashboard module for safety monitoring in Philadelphia 2085 with interactive crime mapping
core_version_requirement: ^9 || ^10 || ^11
package: Theory of Conspiracies
dependencies:
  - drupal:system
  - drupal:user
  - drupal:block
  - drupal:views
configure: amisafe.admin_form
```

#### 1.2 Enhanced amisafe.routing.yml
```yaml
# Existing dashboard route
amisafe.dashboard:
  path: '/amisafe'
  defaults:
    _controller: '\Drupal\amisafe\Controller\AmISafeController::dashboard'
    _title: 'Am I Safe?'
  requirements:
    _permission: 'access content'

# New crime map route
amisafe.crime_map:
  path: '/amisafe/crime-map'
  defaults:
    _controller: '\Drupal\amisafe\Controller\CrimeMapController::map'
    _title: 'Philadelphia Crime Map - 2085'
  requirements:
    _permission: 'access content'

# Analytics route
amisafe.analytics:
  path: '/amisafe/analytics'
  defaults:
    _controller: '\Drupal\amisafe\Controller\AnalyticsController::dashboard'
    _title: 'Crime Analytics'
  requirements:
    _permission: 'access content'

# API routes for AJAX data access
amisafe.api.incidents:
  path: '/api/amisafe/incidents'
  defaults:
    _controller: '\Drupal\amisafe\Controller\ApiController::incidents'
    _format: 'json'
  requirements:
    _permission: 'access content'
    _method: 'GET|POST'

amisafe.api.aggregated:
  path: '/api/amisafe/aggregated'
  defaults:
    _controller: '\Drupal\amisafe\Controller\ApiController::aggregated'
    _format: 'json'
  requirements:
    _permission: 'access content'
    _method: 'GET|POST'

amisafe.api.hotspots:
  path: '/api/amisafe/hotspots'
  defaults:
    _controller: '\Drupal\amisafe\Controller\ApiController::hotspots'
    _format: 'json'
  requirements:
    _permission: 'access content'
    _method: 'GET|POST'

amisafe.api.districts:
  path: '/api/amisafe/districts'
  defaults:
    _controller: '\Drupal\amisafe\Controller\ApiController::districts'
    _format: 'json'
  requirements:
    _permission: 'access content'
    _method: 'GET'

# Admin configuration route
amisafe.admin_form:
  path: '/admin/config/amisafe'
  defaults:
    _form: '\Drupal\amisafe\Form\AmISafeSettingsForm'
    _title: 'AmISafe Configuration'
  requirements:
    _permission: 'administer site configuration'
```

#### 1.3 Enhanced amisafe.libraries.yml
```yaml
# Core crime map library
crime-map:
  version: 1.x
  css:
    theme:
      css/crime-map.css: {}
      css/h3-hexagons.css: {}
      css/cyberpunk-theme.css: {}
  js:
    js/crime-map.js: {}
    js/h3-renderer.js: {}
    js/leaflet-integration.js: {}
    js/api-client.js: {}
  dependencies:
    - core/drupal
    - core/jquery
    - core/drupalSettings
    - amisafe/external-libraries

# External libraries
external-libraries:
  version: 1.x
  css:
    theme:
      https://unpkg.com/leaflet@1.9.4/dist/leaflet.css: { type: external }
  js:
    https://unpkg.com/leaflet@1.9.4/dist/leaflet.js: { type: external }
    https://unpkg.com/h3-js@4.1.0/dist/h3-js.umd.js: { type: external }
    https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js: { type: external }
    https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js: { type: external }

# Analytics dashboard
analytics:
  version: 1.x
  css:
    theme:
      css/analytics.css: {}
  js:
    js/analytics-dashboard.js: {}
    js/chart-configurations.js: {}
  dependencies:
    - amisafe/external-libraries
    - core/drupal

# Admin interface
admin:
  version: 1.x
  css:
    theme:
      css/admin.css: {}
  js:
    js/admin-interface.js: {}
  dependencies:
    - core/drupal
    - core/drupalSettings
```

#### 1.4 Create amisafe.services.yml
```yaml
services:
  amisafe.crime_data:
    class: Drupal\amisafe\Service\CrimeDataService
    arguments: ['@database.amisafe', '@cache.default', '@logger.factory']

  amisafe.h3_aggregator:
    class: Drupal\amisafe\Service\H3AggregatorService
    arguments: ['@amisafe.crime_data', '@config.factory']

  amisafe.spatial_analyzer:
    class: Drupal\amisafe\Service\SpatialAnalyzerService
    arguments: ['@amisafe.crime_data', '@amisafe.h3_aggregator']

  amisafe.api_manager:
    class: Drupal\amisafe\Service\ApiManagerService
    arguments: ['@amisafe.crime_data', '@request_stack', '@current_user']

  database.amisafe:
    class: Drupal\Core\Database\Connection
    factory: Drupal\Core\Database\Database::getConnection
    arguments: ['default', 'amisafe']
```

### Step 2: Create Controller Classes

#### 2.1 Create src/Controller/CrimeMapController.php
```php
<?php

namespace Drupal\amisafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\amisafe\Service\CrimeDataService;
use Drupal\amisafe\Service\H3AggregatorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the interactive crime map.
 */
class CrimeMapController extends ControllerBase {

  /**
   * The crime data service.
   *
   * @var \Drupal\amisafe\Service\CrimeDataService
   */
  protected $crimeDataService;

  /**
   * The H3 aggregator service.
   *
   * @var \Drupal\amisafe\Service\H3AggregatorService
   */
  protected $h3AggregatorService;

  /**
   * Constructs a CrimeMapController object.
   */
  public function __construct(CrimeDataService $crime_data_service, H3AggregatorService $h3_aggregator_service) {
    $this->crimeDataService = $crime_data_service;
    $this->h3AggregatorService = $h3_aggregator_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('amisafe.crime_data'),
      $container->get('amisafe.h3_aggregator')
    );
  }

  /**
   * Displays the interactive crime map.
   */
  public function map(Request $request) {
    // Get initial configuration
    $config = $this->config('amisafe.settings');
    $default_zoom = $config->get('map.default_zoom') ?: 11;
    $default_center = $config->get('map.default_center') ?: [39.9526, -75.1652];

    // Get available crime types for filters
    $crime_types = $this->crimeDataService->getCrimeTypes();
    
    // Get police districts for boundaries
    $districts = $this->crimeDataService->getDistricts();

    // Get date range for available data
    $date_range = $this->crimeDataService->getDateRange();

    $build = [
      '#theme' => 'amisafe_crime_map',
      '#map_config' => [
        'zoom' => $default_zoom,
        'center' => $default_center,
        'api_endpoints' => [
          'incidents' => '/api/amisafe/incidents',
          'aggregated' => '/api/amisafe/aggregated',
          'hotspots' => '/api/amisafe/hotspots',
          'districts' => '/api/amisafe/districts',
        ],
      ],
      '#crime_types' => $crime_types,
      '#districts' => $districts,
      '#date_range' => $date_range,
      '#attached' => [
        'library' => ['amisafe/crime-map'],
        'drupalSettings' => [
          'amisafe' => [
            'mapConfig' => [
              'zoom' => $default_zoom,
              'center' => $default_center,
            ],
            'apiEndpoints' => [
              'incidents' => '/api/amisafe/incidents',
              'aggregated' => '/api/amisafe/aggregated',
              'hotspots' => '/api/amisafe/hotspots',
              'districts' => '/api/amisafe/districts',
            ],
            'crimeTypes' => $crime_types,
            'districts' => $districts,
            'dateRange' => $date_range,
          ],
        ],
      ],
    ];

    return $build;
  }

}
```

#### 2.2 Create src/Controller/ApiController.php
```php
<?php

namespace Drupal\amisafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\amisafe\Service\CrimeDataService;
use Drupal\amisafe\Service\H3AggregatorService;
use Drupal\amisafe\Service\SpatialAnalyzerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for crime data endpoints.
 */
class ApiController extends ControllerBase {

  protected $crimeDataService;
  protected $h3AggregatorService;
  protected $spatialAnalyzerService;

  public function __construct(CrimeDataService $crime_data_service, H3AggregatorService $h3_aggregator_service, SpatialAnalyzerService $spatial_analyzer_service) {
    $this->crimeDataService = $crime_data_service;
    $this->h3AggregatorService = $h3_aggregator_service;
    $this->spatialAnalyzerService = $spatial_analyzer_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('amisafe.crime_data'),
      $container->get('amisafe.h3_aggregator'),
      $container->get('amisafe.spatial_analyzer')
    );
  }

  /**
   * Returns filtered incident data.
   */
  public function incidents(Request $request) {
    $filters = $this->parseFilters($request);
    $page = $request->query->get('page', 0);
    $limit = min($request->query->get('limit', 1000), 5000); // Max 5000 records

    try {
      $incidents = $this->crimeDataService->getIncidents($filters, $page, $limit);
      $total = $this->crimeDataService->getIncidentCount($filters);

      return new JsonResponse([
        'incidents' => $incidents,
        'meta' => [
          'total' => $total,
          'page' => $page,
          'limit' => $limit,
          'filters' => $filters,
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API incidents error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch incident data',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Returns H3 aggregated data.
   */
  public function aggregated(Request $request) {
    $filters = $this->parseFilters($request);
    $resolution = $request->query->get('resolution', 9);
    $bounds = $this->parseBounds($request);

    try {
      $aggregated_data = $this->h3AggregatorService->getAggregatedData($filters, $resolution, $bounds);

      return new JsonResponse([
        'hexagons' => $aggregated_data,
        'meta' => [
          'resolution' => $resolution,
          'bounds' => $bounds,
          'filters' => $filters,
          'count' => count($aggregated_data),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API aggregated error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch aggregated data',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Returns crime hotspot analysis.
   */
  public function hotspots(Request $request) {
    $filters = $this->parseFilters($request);
    $resolution = $request->query->get('resolution', 9);
    $threshold = $request->query->get('threshold', 10);

    try {
      $hotspots = $this->spatialAnalyzerService->getHotspots($filters, $resolution, $threshold);

      return new JsonResponse([
        'hotspots' => $hotspots,
        'meta' => [
          'resolution' => $resolution,
          'threshold' => $threshold,
          'filters' => $filters,
          'count' => count($hotspots),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API hotspots error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch hotspot data',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Returns police district boundaries.
   */
  public function districts(Request $request) {
    try {
      $districts = $this->crimeDataService->getDistrictBoundaries();

      return new JsonResponse([
        'districts' => $districts,
        'meta' => [
          'count' => count($districts),
        ],
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('API districts error: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'error' => 'Failed to fetch district data',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Parse filters from request.
   */
  private function parseFilters(Request $request) {
    $filters = [];

    // Date range
    if ($request->query->has('start_date')) {
      $filters['start_date'] = $request->query->get('start_date');
    }
    if ($request->query->has('end_date')) {
      $filters['end_date'] = $request->query->get('end_date');
    }

    // Crime types
    if ($request->query->has('crime_types')) {
      $filters['crime_types'] = explode(',', $request->query->get('crime_types'));
    }

    // Districts
    if ($request->query->has('districts')) {
      $filters['districts'] = explode(',', $request->query->get('districts'));
    }

    // Time of day
    if ($request->query->has('hour_start')) {
      $filters['hour_start'] = $request->query->get('hour_start');
    }
    if ($request->query->has('hour_end')) {
      $filters['hour_end'] = $request->query->get('hour_end');
    }

    // Severity
    if ($request->query->has('severity_min')) {
      $filters['severity_min'] = $request->query->get('severity_min');
    }
    if ($request->query->has('severity_max')) {
      $filters['severity_max'] = $request->query->get('severity_max');
    }

    return $filters;
  }

  /**
   * Parse map bounds from request.
   */
  private function parseBounds(Request $request) {
    if (!$request->query->has('bounds')) {
      return null;
    }

    $bounds_string = $request->query->get('bounds');
    $bounds_array = explode(',', $bounds_string);
    
    if (count($bounds_array) === 4) {
      return [
        'north' => floatval($bounds_array[0]),
        'east' => floatval($bounds_array[1]),
        'south' => floatval($bounds_array[2]),
        'west' => floatval($bounds_array[3]),
      ];
    }

    return null;
  }

}
```

### Step 3: Create Service Classes

#### 3.1 Create src/Service/CrimeDataService.php
```php
<?php

namespace Drupal\amisafe\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Service for accessing crime data from the amisafe database.
 */
class CrimeDataService {

  /**
   * The amisafe database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, CacheBackendInterface $cache, LoggerChannelFactory $logger_factory) {
    $this->database = $database;
    $this->cache = $cache;
    $this->logger = $logger_factory->get('amisafe');
  }

  /**
   * Get filtered incident data.
   */
  public function getIncidents($filters = [], $page = 0, $limit = 1000) {
    $cache_key = 'amisafe:incidents:' . md5(serialize($filters) . $page . $limit);
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    $query = $this->database->select('raw_incidents', 'ri')
      ->fields('ri')
      ->range($page * $limit, $limit)
      ->orderBy('dispatch_date_time', 'DESC');

    $this->applyFilters($query, $filters);

    try {
      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      // Process results for frontend consumption
      $processed_results = array_map([$this, 'processIncident'], $results);
      
      // Cache for 5 minutes
      $this->cache->set($cache_key, $processed_results, time() + 300);
      
      return $processed_results;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching incidents: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Get count of incidents matching filters.
   */
  public function getIncidentCount($filters = []) {
    $cache_key = 'amisafe:incident_count:' . md5(serialize($filters));
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    $query = $this->database->select('raw_incidents', 'ri')
      ->addExpression('COUNT(*)', 'count');

    $this->applyFilters($query, $filters);

    try {
      $result = $query->execute()->fetchField();
      
      // Cache for 10 minutes
      $this->cache->set($cache_key, $result, time() + 600);
      
      return $result;
    } catch (\Exception $e) {
      $this->logger->error('Error counting incidents: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Get available crime types.
   */
  public function getCrimeTypes() {
    $cache_key = 'amisafe:crime_types';
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $query = $this->database->select('raw_incidents', 'ri')
        ->fields('ri', ['ucr_general', 'text_general_code'])
        ->groupBy('ucr_general')
        ->groupBy('text_general_code')
        ->orderBy('ucr_general');

      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      $crime_types = [];
      foreach ($results as $result) {
        if (!empty($result['ucr_general']) && !empty($result['text_general_code'])) {
          $crime_types[] = [
            'code' => $result['ucr_general'],
            'name' => $result['text_general_code'],
            'severity' => $this->getCrimeSeverity($result['ucr_general']),
            'color' => $this->getCrimeColor($result['ucr_general']),
          ];
        }
      }
      
      // Cache for 1 hour
      $this->cache->set($cache_key, $crime_types, time() + 3600);
      
      return $crime_types;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching crime types: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Get police districts.
   */
  public function getDistricts() {
    $cache_key = 'amisafe:districts';
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $query = $this->database->select('raw_incidents', 'ri')
        ->fields('ri', ['dc_dist'])
        ->groupBy('dc_dist')
        ->orderBy('dc_dist');

      $results = $query->execute()->fetchCol();
      
      $districts = array_filter($results, function($district) {
        return !empty($district) && is_numeric($district);
      });
      
      // Cache for 1 hour
      $this->cache->set($cache_key, $districts, time() + 3600);
      
      return $districts;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching districts: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Get date range of available data.
   */
  public function getDateRange() {
    $cache_key = 'amisafe:date_range';
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $query = $this->database->select('raw_incidents', 'ri')
        ->addExpression('MIN(dispatch_date_time)', 'min_date')
        ->addExpression('MAX(dispatch_date_time)', 'max_date');

      $result = $query->execute()->fetchAssoc();
      
      $date_range = [
        'min' => $result['min_date'],
        'max' => $result['max_date'],
      ];
      
      // Cache for 1 hour
      $this->cache->set($cache_key, $date_range, time() + 3600);
      
      return $date_range;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching date range: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Apply filters to a query.
   */
  private function applyFilters($query, $filters) {
    if (!empty($filters['start_date'])) {
      $query->condition('dispatch_date_time', $filters['start_date'], '>=');
    }
    
    if (!empty($filters['end_date'])) {
      $query->condition('dispatch_date_time', $filters['end_date'], '<=');
    }
    
    if (!empty($filters['crime_types'])) {
      $query->condition('ucr_general', $filters['crime_types'], 'IN');
    }
    
    if (!empty($filters['districts'])) {
      $query->condition('dc_dist', $filters['districts'], 'IN');
    }
    
    if (isset($filters['hour_start']) && isset($filters['hour_end'])) {
      $query->condition('hour', $filters['hour_start'], '>=');
      $query->condition('hour', $filters['hour_end'], '<=');
    }
  }

  /**
   * Process incident data for frontend consumption.
   */
  private function processIncident($incident) {
    return [
      'id' => $incident['id'],
      'h3_index' => $incident['h3_index'],
      'lat' => floatval($incident['lat']),
      'lng' => floatval($incident['lng']),
      'crime_type' => $incident['ucr_general'],
      'description' => $incident['text_general_code'],
      'datetime' => $incident['dispatch_date_time'],
      'district' => $incident['dc_dist'],
      'block' => $incident['location_block'],
      'hour' => $incident['hour'],
      'severity' => $this->getCrimeSeverity($incident['ucr_general']),
    ];
  }

  /**
   * Get crime severity score.
   */
  private function getCrimeSeverity($ucr_code) {
    $severity_map = [
      '100' => 5, // Homicide
      '200' => 4, // Robbery
      '300' => 4, // Aggravated Assault
      '400' => 3, // Burglary
      '500' => 2, // Theft
      '600' => 3, // Auto Theft
      '700' => 1, // Other
    ];
    
    $code_prefix = substr($ucr_code, 0, 1) . '00';
    return $severity_map[$code_prefix] ?? 2;
  }

  /**
   * Get crime color for visualization.
   */
  private function getCrimeColor($ucr_code) {
    $color_map = [
      '100' => '#ff0000', // Red - Homicide
      '200' => '#ff8800', // Orange - Robbery
      '300' => '#ffff00', // Yellow - Assault
      '400' => '#00ff00', // Green - Burglary
      '500' => '#00ffff', // Cyan - Theft
      '600' => '#0088ff', // Blue - Auto Theft
      '700' => '#888888', // Gray - Other
    ];
    
    $code_prefix = substr($ucr_code, 0, 1) . '00';
    return $color_map[$code_prefix] ?? '#888888';
  }

}
```

### Step 4: Create Template Files

#### 4.1 Create templates/amisafe-crime-map.html.twig
```twig
{#
/**
 * @file
 * AmISafe Crime Map template.
 *
 * Available variables:
 * - map_config: Configuration for the map
 * - crime_types: Available crime types for filtering
 * - districts: Police districts
 * - date_range: Available date range
 */
#}

<div class="amisafe-crime-map-page">
  <div class="page-header cyberpunk-header">
    <h1 class="page-title terminal-text">
      <span class="glitch-text">PHILADELPHIA CRIME MAP - 2085</span>
    </h1>
    <div class="subtitle terminal-text">
      Real-time crime monitoring and spatial analysis
    </div>
  </div>

  <div class="crime-map-layout">
    <div class="control-panel-container">
      <div class="control-panel">
        <h3 class="panel-title">SECTOR FILTERS</h3>
        
        <!-- Date Range Filter -->
        <div class="filter-section">
          <label class="filter-label">TIME RANGE</label>
          <input type="text" id="date-range-picker" class="cyber-input" />
        </div>
        
        <!-- Crime Type Filter -->
        <div class="filter-section">
          <label class="filter-label">THREAT TYPES</label>
          <div id="crime-type-filters" class="filter-checkboxes">
            {% for crime_type in crime_types %}
              <div class="crime-type-option">
                <input type="checkbox" id="crime-{{ crime_type.code }}" value="{{ crime_type.code }}">
                <label for="crime-{{ crime_type.code }}" class="cyber-label">
                  <span class="crime-indicator" style="background-color: {{ crime_type.color }}"></span>
                  {{ crime_type.name }}
                </label>
              </div>
            {% endfor %}
          </div>
        </div>
        
        <!-- District Filter -->
        <div class="filter-section">
          <label class="filter-label">DISTRICTS</label>
          <select id="district-filter" class="cyber-select" multiple>
            {% for district in districts %}
              <option value="{{ district }}">DISTRICT {{ district }}</option>
            {% endfor %}
          </select>
        </div>
        
        <!-- Time of Day Filter -->
        <div class="filter-section">
          <label class="filter-label">TIME OF DAY</label>
          <div id="time-slider"></div>
          <div id="time-display" class="time-display">00:00 - 23:59</div>
        </div>
        
        <!-- View Options -->
        <div class="filter-section">
          <label class="filter-label">DISPLAY MODE</label>
          <div class="view-options">
            <button id="hexagon-view" class="cyber-button active">HEXAGON</button>
            <button id="heatmap-view" class="cyber-button">HEATMAP</button>
            <button id="points-view" class="cyber-button">POINTS</button>
          </div>
        </div>
        
        <!-- Stats Summary -->
        <div class="stats-summary">
          <h4 class="stats-title">SECTOR STATUS</h4>
          <div id="current-stats" class="current-stats">
            <div class="stat-item">
              <span class="stat-label">TOTAL INCIDENTS:</span>
              <span id="total-incidents" class="stat-value neon-green">--</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">THREAT LEVEL:</span>
              <span id="threat-level" class="stat-value neon-orange">CALCULATING</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">ACTIVE SECTORS:</span>
              <span id="active-sectors" class="stat-value neon-blue">--</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="map-container">
      <div id="crime-map-container" class="crime-map-container">
        <div id="loading-overlay" class="loading-overlay">
          <div class="loading-indicator">
            <div class="terminal-text">INITIALIZING NEURAL MAP</div>
            <div class="loading-dots">LOADING</div>
          </div>
        </div>
      </div>
      
      <!-- Map Controls -->
      <div class="map-controls">
        <button id="fullscreen-btn" class="control-btn" title="Fullscreen">
          <i class="fas fa-expand"></i>
        </button>
        <button id="reset-view-btn" class="control-btn" title="Reset View">
          <i class="fas fa-home"></i>
        </button>
        <button id="screenshot-btn" class="control-btn" title="Screenshot">
          <i class="fas fa-camera"></i>
        </button>
      </div>
    </div>
  </div>
  
  <!-- Expandable Analytics Dashboard -->
  <div class="analytics-section">
    <button id="toggle-analytics" class="section-toggle cyber-button">
      <span class="toggle-text">SHOW ANALYTICS</span>
      <i class="fas fa-chevron-down toggle-icon"></i>
    </button>
    
    <div id="analytics-dashboard" class="analytics-dashboard collapsed">
      <div class="analytics-grid">
        <div class="chart-container">
          <h4 class="chart-title">TEMPORAL ANALYSIS</h4>
          <canvas id="crime-trend-chart"></canvas>
        </div>
        
        <div class="chart-container">
          <h4 class="chart-title">PATTERN MATRIX</h4>
          <div id="time-pattern-heatmap"></div>
        </div>
        
        <div class="chart-container">
          <h4 class="chart-title">DISTRICT RANKINGS</h4>
          <div id="district-rankings-table"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Incident Details Modal -->
<div id="incident-modal" class="modal cyberpunk-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title terminal-text">INCIDENT ANALYSIS</h3>
      <button class="modal-close cyber-button">&times;</button>
    </div>
    <div class="modal-body">
      <div id="incident-details"></div>
    </div>
  </div>
</div>
```

### Step 5: Database Configuration

#### 5.1 Add to settings.php
Add the following to `sites/theoryofconspiracies/web/sites/default/settings.php`:

```php
// AmISafe crime data database connection
$databases['amisafe'] = [
  'default' => [
    'database' => 'amisafe',
    'username' => 'h3_user',
    'password' => 'secure_h3_password',
    'prefix' => '',
    'host' => 'localhost',
    'port' => '3306',
    'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
    'driver' => 'mysql',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_general_ci',
  ],
];

// AmISafe module configuration
$config['amisafe.settings'] = [
  'map' => [
    'default_zoom' => 11,
    'default_center' => [39.9526, -75.1652],
    'min_zoom' => 9,
    'max_zoom' => 16,
  ],
  'cache' => [
    'incidents_ttl' => 300,     // 5 minutes
    'aggregated_ttl' => 600,    // 10 minutes
    'static_data_ttl' => 3600,  // 1 hour
  ],
  'api' => [
    'max_incidents_per_request' => 5000,
    'rate_limit' => 100, // requests per minute
  ],
];
```

### Step 6: Navigation Integration

#### 6.1 Update Module's .module File
Add navigation integration to the existing `amisafe.module` file:

```php
<?php

/**
 * @file
 * AmISafe module with crime mapping functionality.
 */

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Implements hook_help().
 */
function amisafe_help($route_name, RouteMatchInterface $route_match) {
  switch ($route_name) {
    case 'help.page.amisafe':
      return '<p>' . t('AmISafe provides interactive crime mapping and safety analytics for Philadelphia 2085.') . '</p>';
  }
}

/**
 * Implements hook_theme().
 */
function amisafe_theme() {
  return [
    'amisafe_crime_map' => [
      'variables' => [
        'map_config' => NULL,
        'crime_types' => [],
        'districts' => [],
        'date_range' => NULL,
      ],
      'template' => 'amisafe-crime-map',
    ],
    'amisafe_analytics' => [
      'variables' => [
        'analytics_data' => NULL,
      ],
      'template' => 'amisafe-analytics',
    ],
  ];
}

/**
 * Implements hook_page_attachments().
 */
function amisafe_page_attachments(array &$page) {
  $route_match = \Drupal::routeMatch();
  
  // Add global AmISafe styles and scripts on module pages
  if (strpos($route_match->getRouteName(), 'amisafe.') === 0) {
    $page['#attached']['library'][] = 'amisafe/external-libraries';
  }
}

/**
 * Implements hook_menu_local_tasks_alter().
 */
function amisafe_menu_local_tasks_alter(&$data, $route_name) {
  // Add local tasks for AmISafe navigation
  if (strpos($route_name, 'amisafe.') === 0) {
    $data['tabs'][0]['amisafe.dashboard'] = [
      '#theme' => 'menu_local_task',
      '#link' => [
        'title' => t('Dashboard'),
        'url' => \Drupal\Core\Url::fromRoute('amisafe.dashboard'),
      ],
      '#active' => ($route_name === 'amisafe.dashboard'),
    ];
    
    $data['tabs'][0]['amisafe.crime_map'] = [
      '#theme' => 'menu_local_task',
      '#link' => [
        'title' => t('Crime Map'),
        'url' => \Drupal\Core\Url::fromRoute('amisafe.crime_map'),
      ],
      '#active' => ($route_name === 'amisafe.crime_map'),
    ];
    
    $data['tabs'][0]['amisafe.analytics'] = [
      '#theme' => 'menu_local_task',
      '#link' => [
        'title' => t('Analytics'),
        'url' => \Drupal\Core\Url::fromRoute('amisafe.analytics'),
      ],
      '#active' => ($route_name === 'amisafe.analytics'),
    ];
  }
}
```

## Testing the Implementation

### 1. Clear Drupal Cache
```bash
cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies
drush cr
```

### 2. Test Database Connection
```bash
drush php-eval "
  \$db = \Drupal\Core\Database\Database::getConnection('default', 'amisafe');
  \$result = \$db->query('SELECT COUNT(*) FROM raw_incidents')->fetchField();
  echo 'AmISafe database has ' . \$result . ' incidents.\n';
"
```

### 3. Test API Endpoints
- Visit `/api/amisafe/incidents` to test incident data
- Visit `/api/amisafe/aggregated?resolution=9` to test H3 aggregation
- Visit `/api/amisafe/districts` to test district data

### 4. Test Crime Map Page
- Visit `/amisafe/crime-map` to view the interactive crime map
- Test filtering functionality
- Verify H3 hexagon rendering
- Check responsive behavior on mobile devices

## Next Steps

1. **Complete Frontend JavaScript Implementation**: Create the actual JavaScript files referenced in the libraries
2. **Add CSS Styling**: Implement the cyberpunk visual theme
3. **Performance Testing**: Test with full dataset and optimize queries
4. **Security Review**: Implement proper input validation and rate limiting
5. **User Testing**: Gather feedback on interface and functionality

This implementation guide provides the complete backend foundation for the AmISafe crime map dashboard within the existing Drupal module structure.