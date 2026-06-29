# Frontend Specification - amIsafe Crime Dashboard
## Interactive Web Application Design

### Overview
The amIsafe Crime Dashboard frontend is a responsive, interactive web application built on modern web technologies. It integrates seamlessly with the Theory of Conspiracies Drupal website while providing advanced crime data visualization and analysis capabilities.

### Technology Stack

#### Core Framework
- **Drupal 11.2.5**: Content management and user authentication
- **Theme Integration**: Theory of Conspiracies custom theme
- **Module Architecture**: Custom `amisafe` Drupal module
- **Responsive Design**: Bootstrap 5 with mobile-first approach

#### JavaScript Libraries
```javascript
// Core mapping and visualization
"leaflet": "^1.9.4",                    // Interactive maps
"h3-js": "^4.1.0",                      // H3 hexagon rendering
"leaflet.heat": "^0.2.0",               // Heatmap overlays
"chart.js": "^4.4.0",                   // Statistical charts
"d3": "^7.8.5",                         // Custom visualizations

// UI and interaction
"bootstrap": "^5.3.2",                  // UI framework
"jquery": "^3.7.1",                     // DOM manipulation
"moment.js": "^2.29.4",                 // Date/time handling
"lodash": "^4.17.21",                   // Utility functions

// Data handling
"axios": "^1.6.2",                      // HTTP requests
"papaparse": "^5.4.1",                  // CSV parsing (if needed)

// Performance
"intersection-observer": "^0.12.2",     // Lazy loading
"web-workers": "^1.3.0"                 // Background processing
```

### Application Architecture

#### Component Structure
```
amisafe-dashboard/
├── components/
│   ├── map/
│   │   ├── CrimeMap.js              # Main map component
│   │   ├── H3Layer.js               # H3 hexagon renderer
│   │   ├── HeatmapLayer.js          # Crime density heatmap
│   │   ├── DistrictLayer.js         # Police district boundaries
│   │   └── MarkerCluster.js         # Point clustering
│   ├── charts/
│   │   ├── TimeSeriesChart.js       # Temporal trends
│   │   ├── CategoryChart.js         # Crime type breakdown
│   │   ├── HeatmapChart.js          # Time-based heatmap
│   │   └── ComparisonChart.js       # District comparisons
│   ├── filters/
│   │   ├── DateRangeFilter.js       # Date selection
│   │   ├── CrimeTypeFilter.js       # Category filtering
│   │   ├── DistrictFilter.js        # Geographic filtering
│   │   └── SeverityFilter.js        # Risk level filtering
│   ├── ui/
│   │   ├── LoadingSpinner.js        # Loading indicators
│   │   ├── InfoPanel.js             # Detailed information
│   │   ├── LegendControl.js         # Map legend
│   │   └── SearchBox.js             # Location search
│   └── data/
│       ├── APIClient.js             # API communication
│       ├── DataProcessor.js         # Data transformation
│       └── CacheManager.js          # Client-side caching
```

### User Interface Design

#### Main Dashboard Layout
```html
<!DOCTYPE html>
<html>
<head>
    <title>amIsafe Philadelphia Crime Dashboard - Theory of Conspiracies</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Drupal head integration -->
    <?php print $head; ?>
    
    <!-- CSS Framework -->
    <link href="/themes/theoryofconspiracies/css/bootstrap.min.css" rel="stylesheet">
    <link href="/modules/amisafe/css/crime-dashboard.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
</head>
<body>
    <!-- Theory of Conspiracies Header -->
    <header class="site-header">
        <?php print render($page['header']); ?>
    </header>
    
    <!-- amIsafe Navigation -->
    <nav class="amisafe-nav">
        <div class="container">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#dashboard">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#analytics">Analytics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#reports">Reports</a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Main Dashboard Content -->
    <main class="dashboard-main">
        <div class="container-fluid">
            <div class="row">
                <!-- Filter Sidebar -->
                <aside class="col-md-3 filter-sidebar">
                    <div class="filter-panel">
                        <h3>Filters</h3>
                        
                        <!-- Date Range Filter -->
                        <div class="filter-group">
                            <label>Date Range</label>
                            <div class="date-range-picker">
                                <input type="date" id="start-date" class="form-control">
                                <input type="date" id="end-date" class="form-control">
                            </div>
                            <div class="quick-ranges">
                                <button class="btn btn-sm btn-outline-secondary" data-range="7d">Last 7 Days</button>
                                <button class="btn btn-sm btn-outline-secondary" data-range="30d">Last 30 Days</button>
                                <button class="btn btn-sm btn-outline-secondary" data-range="90d">Last 90 Days</button>
                            </div>
                        </div>
                        
                        <!-- Crime Type Filter -->
                        <div class="filter-group">
                            <label>Crime Types</label>
                            <div class="crime-type-checkboxes">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="600" id="theft">
                                    <label class="form-check-label" for="theft">
                                        <span class="crime-color" style="background-color: #FF6B35;"></span>
                                        Theft from Vehicle
                                    </label>
                                </div>
                                <!-- More crime types... -->
                            </div>
                        </div>
                        
                        <!-- District Filter -->
                        <div class="filter-group">
                            <label>Police Districts</label>
                            <select multiple class="form-select" id="district-filter">
                                <option value="all">All Districts</option>
                                <option value="12">District 12 - Southwest</option>
                                <option value="14">District 14 - Northwest</option>
                                <!-- More districts... -->
                            </select>
                        </div>
                        
                        <!-- Time of Day Filter -->
                        <div class="filter-group">
                            <label>Time of Day</label>
                            <div class="time-range-slider">
                                <input type="range" min="0" max="23" value="0" id="hour-start">
                                <input type="range" min="0" max="23" value="23" id="hour-end">
                                <div class="time-labels">
                                    <span id="time-start-label">12:00 AM</span>
                                    <span id="time-end-label">11:00 PM</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Filters Display -->
                        <div class="active-filters">
                            <h4>Active Filters</h4>
                            <div class="filter-tags" id="active-filter-tags">
                                <!-- Dynamic filter tags -->
                            </div>
                            <button class="btn btn-link btn-sm" onclick="clearAllFilters()">Clear All</button>
                        </div>
                    </div>
                </aside>
                
                <!-- Main Map Area -->
                <section class="col-md-9 map-section">
                    <div class="map-container">
                        <!-- Map Controls -->
                        <div class="map-controls">
                            <div class="control-group">
                                <label>H3 Resolution:</label>
                                <select id="h3-resolution" class="form-select form-select-sm">
                                    <option value="6">District Level (Res 6)</option>
                                    <option value="7">Area Level (Res 7)</option>
                                    <option value="8" selected>Neighborhood Level (Res 8)</option>
                                    <option value="9">Block Level (Res 9)</option>
                                    <option value="10">Precise (Res 10)</option>
                                </select>
                            </div>
                            
                            <div class="control-group">
                                <label>Visualization:</label>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="viz-type" id="hexagons" checked>
                                    <label class="btn btn-outline-primary" for="hexagons">Hexagons</label>
                                    
                                    <input type="radio" class="btn-check" name="viz-type" id="heatmap">
                                    <label class="btn btn-outline-primary" for="heatmap">Heatmap</label>
                                    
                                    <input type="radio" class="btn-check" name="viz-type" id="points">
                                    <label class="btn btn-outline-primary" for="points">Points</label>
                                </div>
                            </div>
                            
                            <div class="control-group">
                                <button class="btn btn-primary" onclick="refreshData()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <!-- Interactive Map -->
                        <div id="crime-map" class="leaflet-map"></div>
                        
                        <!-- Map Legend -->
                        <div class="map-legend">
                            <h4>Crime Density</h4>
                            <div class="legend-scale">
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #ffffcc;"></span>
                                    <span>Low (1-5)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #ffeda0;"></span>
                                    <span>Medium (6-15)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #fed976;"></span>
                                    <span>High (16-30)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #fd8d3c;"></span>
                                    <span>Very High (31-50)</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background: #e31a1c;"></span>
                                    <span>Critical (50+)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            
            <!-- Analytics Dashboard -->
            <div class="row analytics-section">
                <!-- Temporal Trends Chart -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Crime Trends</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="trends-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Time Pattern Heatmap -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Time Patterns</h5>
                        </div>
                        <div class="card-body">
                            <div id="time-heatmap"></div>
                        </div>
                    </div>
                </div>
                
                <!-- District Statistics -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>District Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="district-stats" id="district-stats-table">
                                <!-- Dynamic district statistics -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="site-footer">
        <?php print render($page['footer']); ?>
    </footer>
    
    <!-- JavaScript -->
    <script src="/modules/amisafe/js/dashboard.js"></script>
</body>
</html>
```

### JavaScript Implementation

#### Main Dashboard Controller
```javascript
// dashboard.js - Main application controller
class CrimeDashboard {
    constructor() {
        this.map = null;
        this.apiClient = new APIClient();
        this.filters = new FilterManager();
        this.charts = new ChartManager();
        this.currentData = null;
        
        this.init();
    }
    
    async init() {
        // Initialize map
        this.initializeMap();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Load initial data
        await this.loadInitialData();
        
        // Initialize charts
        this.initializeCharts();
    }
    
    initializeMap() {
        // Create Leaflet map
        this.map = L.map('crime-map').setView([39.9526, -75.1652], 11);
        
        // Add base tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);
        
        // Initialize H3 layer
        this.h3Layer = new H3Layer(this.map);
        
        // Initialize district boundaries
        this.districtLayer = new DistrictLayer(this.map);
        
        // Set up map event handlers
        this.setupMapEvents();
    }
    
    setupMapEvents() {
        // Handle H3 cell clicks
        this.map.on('h3cellclick', (e) => {
            this.showCellDetails(e.h3Index);
        });
        
        // Handle zoom changes
        this.map.on('zoomend', () => {
            this.adjustVisualizationDetail();
        });
        
        // Handle bounds changes
        this.map.on('moveend', () => {
            this.updateVisibleData();
        });
    }
    
    async loadInitialData() {
        try {
            // Show loading indicator
            this.showLoading();
            
            // Load crime categories
            const categories = await this.apiClient.getCrimeCategories();
            this.updateCrimeTypeFilter(categories);
            
            // Load police districts
            const districts = await this.apiClient.getDistricts();
            this.updateDistrictFilter(districts);
            this.districtLayer.addDistricts(districts);
            
            // Load initial incident data
            const filters = this.filters.getCurrentFilters();
            const incidents = await this.apiClient.getIncidents(filters);
            this.updateMapData(incidents);
            
            // Hide loading indicator
            this.hideLoading();
            
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showError('Failed to load dashboard data');
        }
    }
    
    async updateMapData(filters = null) {
        try {
            this.showLoading();
            
            // Get current filters if none provided
            if (!filters) {
                filters = this.filters.getCurrentFilters();
            }
            
            // Add map bounds to filters
            const bounds = this.map.getBounds();
            filters.bounds = {
                lat_min: bounds.getSouth(),
                lat_max: bounds.getNorth(),
                lng_min: bounds.getWest(),
                lng_max: bounds.getEast()
            };
            
            // Load aggregated data for current zoom level
            const resolution = this.getOptimalH3Resolution();
            const aggregatedData = await this.apiClient.getAggregatedData({
                ...filters,
                h3_resolution: resolution
            });
            
            // Update H3 visualization
            this.h3Layer.updateHexagons(aggregatedData);
            
            // Update charts
            this.charts.updateAll(aggregatedData);
            
            // Update statistics
            this.updateStatistics(aggregatedData);
            
            this.hideLoading();
            
        } catch (error) {
            console.error('Error updating map data:', error);
            this.showError('Failed to update map data');
        }
    }
    
    getOptimalH3Resolution() {
        const zoom = this.map.getZoom();
        if (zoom <= 10) return 6;
        if (zoom <= 12) return 7;
        if (zoom <= 14) return 8;
        if (zoom <= 16) return 9;
        return 10;
    }
}

// H3 Layer Management
class H3Layer {
    constructor(map) {
        this.map = map;
        this.hexagons = new Map();
        this.layerGroup = L.layerGroup().addTo(map);
    }
    
    updateHexagons(data) {
        // Clear existing hexagons
        this.layerGroup.clearLayers();
        this.hexagons.clear();
        
        // Add new hexagons
        data.forEach(cell => {
            const hexagon = this.createHexagon(cell);
            this.hexagons.set(cell.h3_index, hexagon);
            this.layerGroup.addLayer(hexagon);
        });
    }
    
    createHexagon(cellData) {
        // Get H3 cell boundary
        const boundary = h3.h3ToGeoBoundary(cellData.h3_index, true);
        
        // Create Leaflet polygon
        const polygon = L.polygon(boundary, {
            fillColor: this.getColorForDensity(cellData.total_incidents),
            fillOpacity: 0.7,
            color: '#ffffff',
            weight: 1,
            opacity: 0.8
        });
        
        // Add popup with cell information
        polygon.bindPopup(this.createPopupContent(cellData));
        
        // Add click handler
        polygon.on('click', (e) => {
            this.map.fire('h3cellclick', {
                h3Index: cellData.h3_index,
                data: cellData
            });
        });
        
        return polygon;
    }
    
    getColorForDensity(incidents) {
        if (incidents >= 50) return '#e31a1c';
        if (incidents >= 31) return '#fd8d3c';
        if (incidents >= 16) return '#fed976';
        if (incidents >= 6) return '#ffeda0';
        return '#ffffcc';
    }
    
    createPopupContent(cellData) {
        return `
            <div class="h3-popup">
                <h4>Crime Data</h4>
                <p><strong>Total Incidents:</strong> ${cellData.total_incidents}</p>
                <p><strong>H3 Index:</strong> ${cellData.h3_index}</p>
                <p><strong>Most Common:</strong> ${cellData.primary_crime_type}</p>
                <p><strong>Incidents/Day:</strong> ${cellData.incidents_per_day.toFixed(1)}</p>
                <button class="btn btn-primary btn-sm" onclick="dashboard.showDetailedView('${cellData.h3_index}')">
                    View Details
                </button>
            </div>
        `;
    }
}

// Filter Management
class FilterManager extends EventTarget {
    constructor() {
        super();
        this.filters = {
            dateRange: {
                start: null,
                end: null
            },
            crimeTypes: [],
            districts: [],
            timeOfDay: {
                start: 0,
                end: 23
            },
            severity: {
                min: 1,
                max: 5
            }
        };
        
        this.setupFilterControls();
    }
    
    setupFilterControls() {
        // Date range controls
        $('#start-date, #end-date').on('change', () => {
            this.updateDateRange();
        });
        
        // Quick date range buttons
        $('.quick-ranges button').on('click', (e) => {
            const range = $(e.target).data('range');
            this.setQuickDateRange(range);
        });
        
        // Crime type checkboxes
        $('.crime-type-checkboxes input').on('change', () => {
            this.updateCrimeTypes();
        });
        
        // District select
        $('#district-filter').on('change', () => {
            this.updateDistricts();
        });
        
        // Time range sliders
        $('#hour-start, #hour-end').on('input', () => {
            this.updateTimeRange();
        });
    }
    
    getCurrentFilters() {
        return {
            ...this.filters,
            // Add any computed filters
            bounds: this.getCurrentBounds()
        };
    }
    
    updateDateRange() {
        const start = $('#start-date').val();
        const end = $('#end-date').val();
        
        this.filters.dateRange.start = start ? new Date(start) : null;
        this.filters.dateRange.end = end ? new Date(end) : null;
        
        this.dispatchEvent(new CustomEvent('filtersChanged'));
    }
    
    setQuickDateRange(range) {
        const end = new Date();
        const start = new Date();
        
        switch(range) {
            case '7d':
                start.setDate(start.getDate() - 7);
                break;
            case '30d':
                start.setDate(start.getDate() - 30);
                break;
            case '90d':
                start.setDate(start.getDate() - 90);
                break;
        }
        
        $('#start-date').val(start.toISOString().split('T')[0]);
        $('#end-date').val(end.toISOString().split('T')[0]);
        
        this.updateDateRange();
    }
}

// Chart Management
class ChartManager {
    constructor() {
        this.charts = new Map();
        this.initializeCharts();
    }
    
    initializeCharts() {
        // Crime trends line chart
        this.charts.set('trends', new Chart(document.getElementById('trends-chart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Daily Incidents',
                    data: [],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        }));
        
        // Time pattern heatmap (using D3)
        this.initializeTimeHeatmap();
    }
    
    initializeTimeHeatmap() {
        const container = d3.select('#time-heatmap');
        const margin = {top: 20, right: 20, bottom: 30, left: 40};
        const width = 400 - margin.left - margin.right;
        const height = 200 - margin.top - margin.bottom;
        
        const svg = container.append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);
        
        // Store reference for updates
        this.timeHeatmapSvg = svg;
    }
    
    updateAll(data) {
        this.updateTrendsChart(data);
        this.updateTimeHeatmap(data);
        this.updateDistrictStats(data);
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.dashboard = new CrimeDashboard();
});
```

### CSS Styling

#### Main Stylesheet
```css
/* crime-dashboard.css */
.dashboard-main {
    padding: 20px 0;
}

.filter-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    padding: 20px;
    height: calc(100vh - 200px);
    overflow-y: auto;
}

.filter-panel h3 {
    color: #495057;
    margin-bottom: 20px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.filter-group {
    margin-bottom: 25px;
}

.filter-group label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

.date-range-picker input {
    margin-bottom: 8px;
}

.quick-ranges {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.quick-ranges button {
    font-size: 0.8em;
}

.crime-type-checkboxes {
    max-height: 200px;
    overflow-y: auto;
}

.crime-color {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.time-range-slider {
    position: relative;
    padding: 20px 0;
}

.time-range-slider input[type="range"] {
    width: 100%;
    margin: 5px 0;
}

.time-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.9em;
    color: #6c757d;
}

.active-filters {
    border-top: 1px solid #dee2e6;
    padding-top: 20px;
    margin-top: 20px;
}

.filter-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 10px;
}

.filter-tag {
    background: #007bff;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    display: flex;
    align-items: center;
}

.filter-tag .remove {
    margin-left: 5px;
    cursor: pointer;
}

.map-section {
    padding: 0;
}

.map-container {
    position: relative;
    height: 600px;
}

.leaflet-map {
    height: 100%;
    border-radius: 8px;
}

.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    align-items: center;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.control-group label {
    font-size: 0.9em;
    font-weight: 600;
    color: #495057;
}

.map-legend {
    position: absolute;
    bottom: 10px;
    left: 10px;
    z-index: 1000;
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-width: 150px;
}

.map-legend h4 {
    margin: 0 0 10px 0;
    font-size: 1em;
    color: #495057;
}

.legend-scale {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
}

.legend-color {
    width: 20px;
    height: 12px;
    border-radius: 2px;
    border: 1px solid #ccc;
}

.analytics-section {
    margin-top: 30px;
}

.analytics-section .card {
    height: 400px;
}

.analytics-section .card-body {
    padding: 15px;
}

.h3-popup {
    min-width: 200px;
}

.h3-popup h4 {
    margin: 0 0 10px 0;
    color: #495057;
}

.h3-popup p {
    margin: 5px 0;
    font-size: 0.9em;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Mobile responsive styles */
@media (max-width: 768px) {
    .filter-sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        width: 80%;
        height: 100vh;
        z-index: 9999;
        transition: left 0.3s ease;
    }
    
    .filter-sidebar.show {
        left: 0;
    }
    
    .map-controls {
        position: relative;
        top: auto;
        right: auto;
        margin-bottom: 10px;
        flex-direction: column;
        align-items: stretch;
    }
    
    .analytics-section .col-md-4 {
        margin-bottom: 20px;
    }
    
    .map-container {
        height: 400px;
    }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .filter-sidebar {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    .map-controls,
    .map-legend {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    .leaflet-popup-content-wrapper {
        background: #2d3748;
        color: #e2e8f0;
    }
}
```

### Progressive Web App Features

#### Service Worker Registration
```javascript
// Register service worker for offline capabilities
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/modules/amisafe/sw.js')
        .then(registration => {
            console.log('SW registered:', registration);
        })
        .catch(error => {
            console.log('SW registration failed:', error);
        });
}
```

#### Web App Manifest
```json
{
    "name": "amIsafe Philadelphia Crime Dashboard",
    "short_name": "amIsafe",
    "description": "Interactive crime data visualization for Philadelphia",
    "start_url": "/amisafe/dashboard",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#007bff",
    "icons": [
        {
            "src": "/modules/amisafe/icons/icon-192.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/modules/amisafe/icons/icon-512.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
}
```

### Accessibility Features

#### ARIA Labels and Semantic HTML
- Proper heading hierarchy
- Screen reader compatible controls
- Keyboard navigation support
- High contrast color options
- Alternative text for visualizations

#### WCAG 2.1 Compliance
- Color contrast ratios meet AA standards
- Focus indicators visible
- Text alternatives for non-text content
- Consistent navigation patterns

This comprehensive frontend specification provides the foundation for building a modern, accessible, and performant crime data visualization interface that integrates seamlessly with the Theory of Conspiracies website architecture.