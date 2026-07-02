<?php

namespace Drupal\amisafe\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Service for accessing crime data from the amisafe database.
 */
class CrimeDataService {

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
  public function __construct(CacheBackendInterface $cache, LoggerChannelFactory $logger_factory) {
    $this->cache = $cache;
    $this->logger = $logger_factory->get('amisafe');
  }

  /**
   * Get the database connection (use default Drupal database).
   */
  protected function getDatabase() {
    try {
      return \Drupal\Core\Database\Database::getConnection('default');
    } catch (\Exception $e) {
      $this->logger->error('Failed to connect to database: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw new \Exception('Database connection failed: ' . $e->getMessage());
    }
  }

  /**
   * Get pre-aggregated H3 hexagon data from Gold layer (final aggregations).
   * Resolution 4-13: From metro-wide coverage to ultra-precision analytics.
   */
  public function getH3Aggregations($resolution = 9, $filters = [], $page = 0, $limit = 1000) {
    // Validate resolution parameter (now supports Resolution 4-13)
    if (empty($resolution) || !is_numeric($resolution) || $resolution < 4 || $resolution > 13) {
      $resolution = 9; // Default fallback
    }
    
    $cache_key = 'amisafe:h3_aggregations:' . md5($resolution . serialize($filters) . $page . $limit);
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      // Use Gold layer (amisafe_h3_aggregated) with ultra-precision analytics
      $database = \Drupal\Core\Database\Database::getConnection();
      $query = $database->select('amisafe_h3_aggregated', 'h3a')
        ->fields('h3a', [
          'id', 'h3_index', 'h3_resolution', 'incident_count', 'unique_incident_types',
          'earliest_incident', 'latest_incident', 'incidents_last_30_days', 'incidents_last_year',
          'center_latitude', 'center_longitude', 'coverage_area_km2', 'incident_type_counts',
          'district_counts', 'avg_data_quality_score', 'total_valid_records', 'last_aggregation'
        ]);

      // Apply H3 filters first
      $this->applyH3Filters($query, $filters);
      
      // Only apply resolution filter if no specific h3_index is requested
      if (empty($filters['h3_index'])) {
        $query->condition('h3_resolution', $resolution);
      }
      
      $query->range($page * $limit, $limit)
        ->orderBy('incident_count', 'DESC');

      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      // Process results for frontend consumption with precision metadata
      $processed_results = array_map(function($row) use ($resolution) {
        return $this->processH3Aggregation($row, $resolution);
      }, $results);
      
      // Cache for 30 minutes (longer cache for aggregated data)
      $this->cache->set($cache_key, $processed_results, time() + 1800);
      
      return $processed_results;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching Gold layer H3 aggregations: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get filtered incident data from Silver layer (transform data).
   * Resolution 13 Ultra-Precision: Access to 3.4M+ H3-indexed records.
   */
  public function getIncidents($filters = [], $page = 0, $limit = 1000) {
    $cache_key = 'amisafe:incidents:' . md5(serialize($filters) . $page . $limit);
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      // Use Silver layer (amisafe_clean_incidents) with full H3 indexing
      $database = \Drupal\Core\Database\Database::getConnection();
      $query = $database->select('amisafe_clean_incidents', 'ci')
        ->fields('ci', [
          'id', 'incident_datetime', 'dc_dist', 'ucr_general', 'lat', 'lng',
          'h3_res_6', 'h3_res_7', 'h3_res_8', 'h3_res_9', 'h3_res_10',
          'h3_res_11', 'h3_res_12', 'h3_res_13', 'data_quality_score'
        ])
        ->range($page * $limit, $limit)
        ->orderBy('incident_datetime', 'DESC');

      $this->applyFilters($query, $filters);

      $results = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      
      // Process results for frontend consumption with H3 data
      $processed_results = array_map([$this, 'processTransformIncident'], $results);
      
      // Cache for 10 minutes (longer cache for processed data)
      $this->cache->set($cache_key, $processed_results, time() + 600);
      
      return $processed_results;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching Silver layer incidents: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get count of incidents matching filters.
   * For citywide stats, uses Resolution 5 single hexagon (1.48M incidents, 251 km²).
   * For filtered queries, uses Silver layer for precision.
   */
  public function getIncidentCount($filters = []) {
    $cache_key = 'amisafe:incident_count:' . md5(serialize($filters));
    
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    try {
      $database = \Drupal\Core\Database\Database::getConnection();
      
      // If no filters, use Resolution 5 citywide hexagon (most efficient)
      if (empty($filters)) {
        $query = $database->select('amisafe_h3_aggregated', 'h3a');
        $query->addField('h3a', 'incident_count');
        $query->condition('h3_resolution', 5);
        $query->condition('h3_index', '852a134bfffffff'); // Philadelphia citywide hexagon
        
        $result = $query->execute()->fetchField();
        
        if ($result) {
          $this->cache->set($cache_key, $result, time() + 3600); // Cache for 1 hour
          return $result;
        }
      }
      
      // For filtered queries, use Silver layer for accuracy
      $query = $database->select('amisafe_clean_incidents', 'ci');
      $query->addExpression('COUNT(*)', 'total_incidents');
      
      // Apply filters if provided
      if (!empty($filters['district'])) {
        $query->condition('dc_dist', $filters['district']);
      }
      if (!empty($filters['date_from'])) {
        $query->condition('incident_date', $filters['date_from'], '>=');
      }
      if (!empty($filters['date_to'])) {
        $query->condition('incident_date', $filters['date_to'], '<=');
      }
      if (!empty($filters['crime_type'])) {
        $query->condition('ucr_general', $filters['crime_type']);
      }

      $result = $query->execute()->fetchField();
      
      // Cache for 10 minutes
      $this->cache->set($cache_key, $result, time() + 600);
      
      return $result;
    } catch (\Exception $e) {
      $this->logger->error('Error counting incidents: @message', [
        '@message' => $e->getMessage(),
      ]);
      return 0;
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
      $database = \Drupal\Core\Database\Database::getConnection();
      $query = $database->select('amisafe_raw_incidents', 'ri')
        ->fields('ri', ['dc_dist'])
        ->groupBy('dc_dist')
        ->orderBy('dc_dist');

      $results = $query->execute()->fetchCol();
      
      $districts = array_filter($results, function($district) {
        return !empty($district) && is_numeric($district);
      });
      
      sort($districts, SORT_NUMERIC);
      
      // Cache for 1 hour
      $this->cache->set($cache_key, array_values($districts), time() + 3600);
      
      return array_values($districts);
    } catch (\Exception $e) {
      $this->logger->error('Error fetching districts: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return fallback data
      return ['1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', '17', '18', '19', '22', '24', '25', '26', '35', '39'];
    }
  }

  /**
   * Get date range of available data.
   */
  public function getDateRange() {
    try {
      $database = \Drupal\Core\Database\Database::getConnection();
      $query = $database->select('amisafe_raw_incidents', 'ri');
      $query->addExpression('MIN(incident_date)', 'min_date');
      $query->addExpression('MAX(incident_date)', 'max_date');
      $result = $query->execute()->fetchAssoc();
      
      return [
        'min' => $result['min_date'],
        'max' => $result['max_date'],
      ];
    } catch (\Exception $e) {
      $this->logger->error('Error fetching date range: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'min' => '2025-01-01',
        'max' => '2025-12-31',
      ];
    }
  }

  /**
   * Get available crime types for filtering.
   */
  public function getCrimeTypes() {
    try {
      $database = \Drupal\Core\Database\Database::getConnection();
      $result = $database->query('SELECT DISTINCT ucr_code, ucr_description FROM amisafe_raw_incidents WHERE ucr_code IS NOT NULL AND ucr_description IS NOT NULL ORDER BY ucr_code');
      
      $crime_types = [];
      foreach ($result as $row) {
        $crime_types[$row->ucr_code] = $row->ucr_description;
      }
      
      return $crime_types;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching crime types: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Return common Philadelphia crime types as fallback
      return [
        '100' => 'Murder',
        '200' => 'Rape',
        '300' => 'Robbery - Total',
        '400' => 'Aggravated Assault - Total',
        '500' => 'Burglary - Total',
        '600' => 'Theft from Vehicle',
        '700' => 'All Other Larceny',
        '800' => 'Vandalism',
        '900' => 'Fraud',
        '1000' => 'Embezzlement',
        '1100' => 'Narcotic Drug Law Violations',
        '1200' => 'Weapons Violations',
        '1300' => 'Prostitution',
        '1400' => 'Other Assaults',
        '1500' => 'Arson',
        '1600' => 'Stolen Property',
        '1700' => 'DUI',
        '1800' => 'Liquor Laws',
        '2000' => 'Public Drunkenness',
        '2100' => 'Disorderly Conduct',
        '2600' => 'Theft from Person',
      ];
    }
  }

  /**
   * Get district boundaries (placeholder for future implementation).
   */
  public function getDistrictBoundaries() {
    // For now, return empty array - this would normally query a districts table
    return [];
  }

  /**
   * Apply filters to a query.
   */
  private function applyFilters($query, $filters) {
    // Date range filters
    if (!empty($filters['start_date'])) {
      $query->condition('incident_date', $filters['start_date'], '>=');
    }
    
    if (!empty($filters['end_date'])) {
      $query->condition('incident_date', $filters['end_date'], '<=');
    }
    
    // Crime type filters
    if (!empty($filters['crime_types'])) {
      $query->condition('ucr_general', $filters['crime_types'], 'IN');
    }
    
    // District filters
    if (!empty($filters['districts'])) {
      $query->condition('dc_dist', $filters['districts'], 'IN');
    }
    
    // Severity filters (new selector-based)
    if (!empty($filters['severities'])) {
      // Apply severity filtering based on UCR codes
      $severity_conditions = $query->orConditionGroup();
      foreach ($filters['severities'] as $severity_level) {
        $ucr_codes = $this->getUcrCodesBySeverity($severity_level);
        if (!empty($ucr_codes)) {
          $severity_conditions->condition('ucr_general', $ucr_codes, 'IN');
        }
      }
      if ($severity_conditions->count() > 0) {
        $query->condition($severity_conditions);
      }
    }
    
    // Time period filters (new selector-based)
    if (!empty($filters['time_periods'])) {
      $time_conditions = $query->orConditionGroup();
      foreach ($filters['time_periods'] as $time_period) {
        switch ($time_period) {
          case 'early-morning':
            $period_condition = $query->andConditionGroup();
            $period_condition->condition('incident_hour', 0, '>=');
            $period_condition->condition('incident_hour', 5, '<=');
            $time_conditions->condition($period_condition);
            break;
          case 'morning':
            $period_condition = $query->andConditionGroup();
            $period_condition->condition('incident_hour', 6, '>=');
            $period_condition->condition('incident_hour', 11, '<=');
            $time_conditions->condition($period_condition);
            break;
          case 'afternoon':
            $period_condition = $query->andConditionGroup();
            $period_condition->condition('incident_hour', 12, '>=');
            $period_condition->condition('incident_hour', 17, '<=');
            $time_conditions->condition($period_condition);
            break;
          case 'evening':
            $period_condition = $query->andConditionGroup();
            $period_condition->condition('incident_hour', 18, '>=');
            $period_condition->condition('incident_hour', 23, '<=');
            $time_conditions->condition($period_condition);
            break;
        }
      }
      if ($time_conditions->count() > 0) {
        $query->condition($time_conditions);
      }
    }
    
    // Legacy time filters (backwards compatibility)
    if (isset($filters['hour_start']) && isset($filters['hour_end'])) {
      $query->condition('incident_hour', $filters['hour_start'], '>=');
      $query->condition('incident_hour', $filters['hour_end'], '<=');
    }
    
    // Legacy severity filters (backwards compatibility)
    if (isset($filters['severity_min']) || isset($filters['severity_max'])) {
      if (isset($filters['severity_min'])) {
        $min_ucr_codes = $this->getUcrCodesBySeverity($filters['severity_min'], 'min');
        if (!empty($min_ucr_codes)) {
          $query->condition('ucr_general', $min_ucr_codes, 'IN');
        }
      }
      if (isset($filters['severity_max'])) {
        $max_ucr_codes = $this->getUcrCodesBySeverity($filters['severity_max'], 'max');
        if (!empty($max_ucr_codes)) {
          $query->condition('ucr_general', $max_ucr_codes, 'IN');
        }
      }
    }
  }

  /**
   * Process incident data for frontend consumption (legacy raw data).
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
   * Process Silver layer (transform) incident data with Resolution 13 H3 support.
   * Provides all H3 indices from resolutions 6-13 for multi-scale analysis.
   */
  private function processTransformIncident($incident) {
    return [
      'id' => $incident['id'],
      'lat' => floatval($incident['lat']),
      'lng' => floatval($incident['lng']),
      'crime_type' => $incident['ucr_general'],
      'datetime' => $incident['incident_datetime'],
      'district' => $incident['dc_dist'],
      'data_quality_score' => floatval($incident['data_quality_score']),
      'severity' => $this->getCrimeSeverity($incident['ucr_general']),
      // Multi-resolution H3 indices (Resolutions 6-13)
      'h3_indices' => [
        'res_6' => $incident['h3_res_6'],    // City-wide (36.1 km²)
        'res_7' => $incident['h3_res_7'],    // District (5.2 km²)
        'res_8' => $incident['h3_res_8'],    // Block (737 m²)
        'res_9' => $incident['h3_res_9'],    // Street (105 m²)
        'res_10' => $incident['h3_res_10'],  // Building cluster (15K m²)
        'res_11' => $incident['h3_res_11'],  // Building (2.1K m²)
        'res_12' => $incident['h3_res_12'],  // Floor (307 m²)
        'res_13' => $incident['h3_res_13'],  // Ultra-fine room (44 m²)
      ],
    ];
  }

  /**
   * Process Gold layer (final) H3 aggregation data with ultra-precision metadata.
   * Provides comprehensive analytics for Resolution 13 hexagon data.
   */
  private function processH3Aggregation($aggregation, $resolution) {
    // Decode JSON fields
    $incident_types = json_decode($aggregation['incident_type_counts'], true) ?: [];
    $districts = json_decode($aggregation['district_counts'], true) ?: [];
    
    return [
      'id' => $aggregation['id'],
      'h3_index' => $aggregation['h3_index'],
      'resolution' => $aggregation['h3_resolution'],
      'incident_count' => intval($aggregation['incident_count']),
      'unique_types' => intval($aggregation['unique_incident_types']),
      'center' => [
        'lat' => floatval($aggregation['center_latitude']),
        'lng' => floatval($aggregation['center_longitude']),
      ],
      'temporal' => [
        'earliest' => $aggregation['earliest_incident'],
        'latest' => $aggregation['latest_incident'],
        'last_30_days' => intval($aggregation['incidents_last_30_days']),
        'last_year' => intval($aggregation['incidents_last_year']),
      ],
      'quality' => [
        'avg_score' => floatval($aggregation['avg_data_quality_score']),
        'valid_records' => intval($aggregation['total_valid_records']),
      ],
      'geography' => [
        'coverage_km2' => floatval($aggregation['coverage_area_km2']),
        'precision_level' => $this->getPrecisionLevel($resolution),
        'hex_size_m2' => $this->getHexagonSizeM2($resolution),
      ],
      'analytics' => [
        'crime_types' => $incident_types,
        'districts' => $districts,
        'density' => $this->calculateDensity($aggregation['incident_count'], $resolution),
        'risk_level' => $this->calculateRiskLevel($aggregation['incident_count'], $resolution),
      ],
      'metadata' => [
        'last_updated' => $aggregation['last_aggregation'],
        'source_records' => intval($aggregation['source_record_count'] ?? $aggregation['total_valid_records']),
        'aggregation_type' => 'gold_layer_ultra_precision',
      ],
    ];
  }

  /**
   * Get crime severity score.
   */
  private function getCrimeSeverity($ucr_code) {
    $severity_map = [
      '100' => 5, // Murder
      '200' => 4, // Rape
      '300' => 4, // Robbery
      '400' => 4, // Aggravated Assault
      '500' => 3, // Burglary
      '600' => 2, // Theft from Vehicle
      '700' => 2, // All Other Larceny
      '800' => 2, // Vandalism
      '900' => 2, // Fraud
      '1000' => 2, // Embezzlement
      '1100' => 3, // Narcotic Drug Law Violations
      '1200' => 3, // Weapons Violations
      '1300' => 1, // Prostitution
      '1400' => 2, // Other Assaults
      '1500' => 4, // Arson
      '1600' => 2, // Stolen Property
      '1700' => 2, // DUI
      '1800' => 1, // Liquor Laws
      '2000' => 1, // Public Drunkenness
      '2100' => 1, // Disorderly Conduct
      '2600' => 3, // Theft from Person
    ];
    
    return $severity_map[$ucr_code] ?? 2;
  }

  /**
   * Get UCR codes by severity level.
   */
  private function getUcrCodesBySeverity($severity_level, $mode = 'exact') {
    $severity_to_codes = [
      1 => ['1300', '1800', '2000', '2100'], // Low severity
      2 => ['600', '700', '800', '900', '1000', '1400', '1600', '1700'], // Moderate severity
      3 => ['500', '1100', '1200', '2600'], // High severity
      4 => ['200', '300', '400', '1500'], // Critical severity
      5 => ['100'], // Extreme severity
    ];
    
    switch ($mode) {
      case 'min':
        // Get codes with severity >= specified level
        $codes = [];
        for ($level = $severity_level; $level <= 5; $level++) {
          if (isset($severity_to_codes[$level])) {
            $codes = array_merge($codes, $severity_to_codes[$level]);
          }
        }
        return $codes;
        
      case 'max':
        // Get codes with severity <= specified level
        $codes = [];
        for ($level = 1; $level <= $severity_level; $level++) {
          if (isset($severity_to_codes[$level])) {
            $codes = array_merge($codes, $severity_to_codes[$level]);
          }
        }
        return $codes;
        
      default:
        // Get codes with exact severity level
        return $severity_to_codes[$severity_level] ?? [];
    }
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

  /**
   * Get detailed information for a specific hexagon.
   */
  public function getHexagonDetails($h3_index, array $filters = []) {
    
    try {
      // Get database connection like other methods
      $database = \Drupal\Core\Database\Database::getConnection();
      
      $query = $database->select('amisafe_raw_incidents', 'ri');
      $query->fields('ri');
      $query->condition('ri.h3_index', $h3_index);

      // Apply filters
      if (!empty($filters['crime_types'])) {
        $query->condition('ri.ucr_general', $filters['crime_types'], 'IN');
      }

      if (!empty($filters['start_date'])) {
        $query->condition('ri.dispatch_date', $filters['start_date'], '>=');
      }

      if (!empty($filters['end_date'])) {
        $query->condition('ri.dispatch_date', $filters['end_date'], '<=');
      }

      if (!empty($filters['districts'])) {
        $query->condition('ri.dc_dist', $filters['districts'], 'IN');
      }

      $results = $query->execute()->fetchAll();

      if (empty($results)) {
        return [
          'h3_index' => $h3_index,
          'incidents' => [],
          'summary' => [
            'total_incidents' => 0,
            'crime_types' => [],
            'severity_avg' => 0,
            'threat_level' => 'MINIMAL',
            'last_incident' => null,
          ]
        ];
      }

      // Process incidents
      $incidents = [];
      $crime_types = [];
      $severities = [];
      $latest_date = null;

      foreach ($results as $row) {
        $ucr_code = $row->ucr_general;
        $severity = $this->calculateSeverityScore($ucr_code);
        $severities[] = $severity;
        
        if (!isset($crime_types[$ucr_code])) {
          $crime_types[$ucr_code] = 0;
        }
        $crime_types[$ucr_code]++;

        if (!$latest_date || $row->dispatch_date > $latest_date) {
          $latest_date = $row->dispatch_date;
        }

        $incidents[] = [
          'incident_id' => $row->id,
          'date' => $row->dispatch_date,
          'time' => $row->dispatch_time ?? '00:00:00',
          'ucr_code' => $ucr_code,
          'crime_type' => $row->text_general_code ?? 'Unknown',
          'address' => $row->location_block ?? 'Address unavailable',
          'district' => $row->dc_dist ?? 'Unknown',
          'severity' => $severity,
          'lat' => $row->lat,
          'lng' => $row->lng,
        ];
      }

      $avg_severity = !empty($severities) ? round(array_sum($severities) / count($severities), 1) : 0;
      
      // Determine threat level
      $threat_level = 'MINIMAL';
      if ($avg_severity >= 4.5) {
        $threat_level = 'EXTREME';
      } elseif ($avg_severity >= 3.5) {
        $threat_level = 'HIGH';
      } elseif ($avg_severity >= 2.5) {
        $threat_level = 'MODERATE';
      } elseif ($avg_severity >= 1.5) {
        $threat_level = 'LOW';
      }

      return [
        'h3_index' => $h3_index,
        'incidents' => $incidents,
        'summary' => [
          'total_incidents' => count($incidents),
          'crime_types' => $crime_types,
          'severity_avg' => $avg_severity,
          'threat_level' => $threat_level,
          'last_incident' => $latest_date,
        ]
      ];

    } catch (\Exception $e) {
      \Drupal::logger('amisafe')->error('Error getting hexagon details: @message', ['@message' => $e->getMessage()]);
      return [
        'error' => 'Failed to fetch hexagon details',
        'h3_index' => $h3_index,
        'incidents' => [],
        'summary' => [
          'total_incidents' => 0,
          'crime_types' => [],
          'severity_avg' => 0,
          'threat_level' => 'UNKNOWN',
          'last_incident' => null,
        ]
      ];
    }
  }

  /**
   * Calculate severity score for a UCR code.
   */
  private function calculateSeverityScore($ucr_code) {
    $severity_map = [
      '100' => 5, // Murder
      '200' => 4, // Rape  
      '300' => 4, // Robbery
      '400' => 3, // Aggravated Assault
      '500' => 2, // Burglary
      '600' => 3, // Theft from Vehicle
      '700' => 1, // All Other Larceny
      '800' => 2, // Vandalism
      '900' => 2, // Fraud
      '1000' => 2, // Embezzlement
      '1100' => 3, // Drug Violations
      '1200' => 4, // Weapons
      '1300' => 2, // Prostitution
      '1400' => 3, // Other Assaults
      '1500' => 4, // Arson
      '1600' => 2, // Stolen Property
      '1700' => 2, // DUI
      '1800' => 1, // Liquor Laws
      '2000' => 1, // Public Drunkenness
      '2100' => 1, // Disorderly Conduct
      '2600' => 3, // Theft from Person
    ];
    
    return $severity_map[$ucr_code] ?? 2;
  }

  /**
   * Apply filters to H3 aggregation queries.
   * Now supports h3_index filtering for Resolution 5 citywide hexagon lookup.
   */
  private function applyH3Filters($query, $filters) {
    // Filter by specific H3 index (for Resolution 5 citywide hexagon)
    if (!empty($filters['h3_index'])) {
      $query->condition('h3_index', $filters['h3_index']);
    }

    // Log filters for debugging
    \Drupal::logger('amisafe')->info('H3 Filters received: @filters', ['@filters' => print_r($filters, TRUE)]);

    // Apply incident count filters
    if (!empty($filters['min_incidents'])) {
      $query->condition('incident_count', $filters['min_incidents'], '>=');
    }

    if (!empty($filters['max_incidents'])) {
      $query->condition('incident_count', $filters['max_incidents'], '<=');
    }

    // Handle districts filter - using simple LIKE approach for JSON fields
    if (!empty($filters['districts'])) {
      $districts = is_array($filters['districts']) ? $filters['districts'] : [$filters['districts']];
      $district_conditions = $query->orConditionGroup();
      foreach ($districts as $district) {
        // Use LIKE to search for district in JSON - simpler than JSON_SEARCH
        $district_conditions->condition('district_counts', '%"' . $district . '"%', 'LIKE');
      }
      $query->condition($district_conditions);
      \Drupal::logger('amisafe')->info('Applied districts filter: @districts', ['@districts' => implode(',', $districts)]);
    }

    // Handle crime_types filter - using simple LIKE approach for JSON fields
    if (!empty($filters['crime_types'])) {
      $crime_types = is_array($filters['crime_types']) ? $filters['crime_types'] : [$filters['crime_types']];
      $crime_conditions = $query->orConditionGroup();
      foreach ($crime_types as $crime_type) {
        // Use LIKE to search for crime type in JSON - simpler than JSON_SEARCH
        $crime_conditions->condition('incident_type_counts', '%"' . $crime_type . '"%', 'LIKE');
      }
      $query->condition($crime_conditions);
      \Drupal::logger('amisafe')->info('Applied crime_types filter: @types', ['@types' => implode(',', $crime_types)]);
    }


  }

  /**
   * Get precision level description for resolution.
   */
  private function getPrecisionLevel($resolution) {
    $levels = [
      6 => 'Metropolitan',
      7 => 'District-wide', 
      8 => 'Block-level',
      9 => 'Street-level',
      10 => 'Property clusters',
      11 => 'Building-level',
      12 => 'Floor-level',
      13 => 'Ultra-fine room-level'
    ];
    return $levels[$resolution] ?? 'Unknown';
  }

  /**
   * Get hexagon size in square meters for resolution.
   */
  private function getHexagonSizeM2($resolution) {
    $sizes = [
      6 => 36129000,  // 36.1 km²
      7 => 5161000,   // 5.2 km²
      8 => 737000,    // 737 m²
      9 => 105000,    // 105 m²
      10 => 15048,    // 15K m²
      11 => 2150,     // 2.1K m²
      12 => 307,      // 307 m²
      13 => 44        // 44 m² (Ultra-fine)
    ];
    return $sizes[$resolution] ?? 0;
  }

  /**
   * Calculate incident density per square meter.
   */
  private function calculateDensity($incident_count, $resolution) {
    $hex_size = $this->getHexagonSizeM2($resolution);
    return $hex_size > 0 ? round($incident_count / $hex_size * 10000, 6) : 0; // Per 10K m²
  }

  /**
   * Calculate risk level based on incident count and resolution.
   */
  private function calculateRiskLevel($incident_count, $resolution) {
    // Adjusted thresholds based on hexagon size
    $thresholds = [
      6 => [1000, 5000, 10000],   // City-wide
      7 => [500, 2000, 5000],     // District
      8 => [100, 500, 1000],      // Block
      9 => [50, 200, 500],        // Street
      10 => [20, 100, 300],       // Property
      11 => [10, 50, 150],        // Building
      12 => [5, 25, 75],          // Floor
      13 => [2, 10, 30]           // Ultra-fine
    ];

    $levels = $thresholds[$resolution] ?? [10, 50, 150];
    
    if ($incident_count >= $levels[2]) return 'EXTREME';
    if ($incident_count >= $levels[1]) return 'HIGH';
    if ($incident_count >= $levels[0]) return 'MODERATE';
    if ($incident_count > 0) return 'LOW';
    return 'MINIMAL';
  }

}