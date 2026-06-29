"""
Example Scripts and Use Cases for H3 Framework

This module provides practical examples of how to use the H3 geolocation framework
for various real-world scenarios including urban planning, logistics, data analysis,
and spatial intelligence applications.
"""

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from h3_framework import H3GeolocationFramework
from geospatial_utils import GeospatialUtils
from data_processor import H3DataProcessor
from visualizer import H3Visualizer
import h3
import numpy as np
import random
from typing import List, Tuple, Dict
import json

class H3Examples:
    """Collection of practical H3 framework examples."""
    
    def __init__(self):
        self.framework = H3GeolocationFramework()
        self.utils = GeospatialUtils()
        self.processor = H3DataProcessor()
        self.visualizer = H3Visualizer()
    
    def example_1_urban_heat_island_analysis(self):
        """
        Example 1: Urban Heat Island Analysis
        Analyze temperature variations across a city using H3 hexagons.
        """
        print("=== Example 1: Urban Heat Island Analysis ===")
        
        # St. Louis city boundaries (approximate)
        st_louis_center = (38.6270, -90.1994)
        
        # Generate sample temperature data points
        temperature_data = []
        for i in range(100):
            # Random points around St. Louis
            lat_offset = random.uniform(-0.1, 0.1)
            lng_offset = random.uniform(-0.1, 0.1)
            lat = st_louis_center[0] + lat_offset
            lng = st_louis_center[1] + lng_offset
            
            # Simulate temperature with urban heat island effect
            # Higher temperatures near city center
            distance_from_center = ((lat - st_louis_center[0])**2 + (lng - st_louis_center[1])**2)**0.5
            base_temp = 75 + random.uniform(-5, 5)  # Base temperature
            heat_island_effect = max(0, 10 - distance_from_center * 100)  # Heat island effect
            temperature = base_temp + heat_island_effect
            
            temperature_data.append({
                'lat': lat,
                'lng': lng,
                'temperature': temperature,
                'location_type': 'urban' if distance_from_center < 0.05 else 'suburban'
            })
        
        # Convert to H3 and aggregate
        h3_data = []
        for point in temperature_data:
            h3_index = h3.latlng_to_cell(point['lat'], point['lng'], 9)
            h3_data.append({
                'h3_index': h3_index,
                'lat': point['lat'],
                'lng': point['lng'],
                'data': {'temperature': point['temperature'], 'type': point['location_type']}
            })
        
        # Aggregate by hexagon
        aggregated = self.processor.aggregate_by_h3(h3_data, {'temperature': 'mean'})
        
        # Create visualization
        hexagons = list(aggregated.keys())
        temperatures = [aggregated[h]['temperature_avg'] for h in hexagons]
        
        folium_map = self.visualizer.create_folium_map(st_louis_center, zoom=11)
        heat_map = self.visualizer.add_h3_hexagons_to_map(
            folium_map, hexagons, temperatures, color_palette='inferno'
        )
        
        # Save results
        heat_map.save('urban_heat_island_analysis.html')
        
        print(f"Processed {len(temperature_data)} temperature readings")
        print(f"Aggregated into {len(hexagons)} H3 hexagons")
        print(f"Temperature range: {min(temperatures):.1f}°F to {max(temperatures):.1f}°F")
        print("Heat island map saved as 'urban_heat_island_analysis.html'")
        
        return aggregated
    
    def example_2_delivery_route_optimization(self):
        """
        Example 2: Delivery Route Optimization
        Optimize delivery routes using H3 spatial indexing.
        """
        print("\n=== Example 2: Delivery Route Optimization ===")
        
        # Distribution center
        distribution_center = (38.6270, -90.1994)
        
        # Generate delivery locations
        delivery_points = []
        for i in range(50):
            # Random delivery points within 20km radius
            bearing = random.uniform(0, 360)
            distance = random.uniform(1000, 20000)  # 1-20km
            
            delivery_location = self.utils.destination_point(
                distribution_center, bearing, distance
            )
            
            delivery_points.append({
                'id': f'delivery_{i+1}',
                'lat': delivery_location[0],
                'lng': delivery_location[1],
                'priority': random.choice(['high', 'medium', 'low']),
                'package_count': random.randint(1, 5)
            })
        
        # Convert to H3 for spatial analysis
        h3_deliveries = []
        for point in delivery_points:
            h3_index = h3.latlng_to_cell(point['lat'], point['lng'], 8)
            h3_deliveries.append({
                'h3_index': h3_index,
                'lat': point['lat'],
                'lng': point['lng'],
                'data': point
            })
        
        # Group deliveries by H3 zones for route optimization
        zones = {}
        for delivery in h3_deliveries:
            zone = delivery['h3_index']
            if zone not in zones:
                zones[zone] = []
            zones[zone].append(delivery)
        
        # Create route visualization
        route_map = self.visualizer.create_folium_map(distribution_center, zoom=10)
        
        # Add distribution center
        import folium
        folium.Marker(
            distribution_center,
            popup='Distribution Center',
            icon=folium.Icon(color='red', icon='home')
        ).add_to(route_map)
        
        # Add delivery zones
        zone_colors = ['blue', 'green', 'purple', 'orange', 'darkred', 'lightred', 
                      'beige', 'darkblue', 'darkgreen', 'cadetblue']
        
        for i, (zone, deliveries) in enumerate(zones.items()):
            color = zone_colors[i % len(zone_colors)]
            
            # Add zone polygon
            boundary = h3.cell_to_boundary(zone)
            coordinates = [[lat, lng] for lat, lng in boundary]
            
            folium.Polygon(
                locations=coordinates,
                color=color,
                weight=2,
                opacity=0.7,
                fillColor=color,
                fillOpacity=0.3,
                popup=f'Zone {zone}<br>Deliveries: {len(deliveries)}'
            ).add_to(route_map)
            
            # Add delivery points
            for delivery in deliveries:
                folium.CircleMarker(
                    location=[delivery['lat'], delivery['lng']],
                    radius=5,
                    popup=f"ID: {delivery['data']['id']}<br>Priority: {delivery['data']['priority']}",
                    color=color,
                    fillColor=color,
                    fillOpacity=0.8
                ).add_to(route_map)
        
        route_map.save('delivery_route_optimization.html')
        
        print(f"Generated {len(delivery_points)} delivery locations")
        print(f"Organized into {len(zones)} delivery zones")
        print("Route optimization map saved as 'delivery_route_optimization.html'")
        
        # Calculate zone statistics
        zone_stats = {}
        for zone, deliveries in zones.items():
            zone_stats[zone] = {
                'delivery_count': len(deliveries),
                'high_priority': sum(1 for d in deliveries if d['data']['priority'] == 'high'),
                'total_packages': sum(d['data']['package_count'] for d in deliveries)
            }
        
        return zone_stats
    
    def example_3_demographic_analysis(self):
        """
        Example 3: Demographic Analysis
        Analyze population demographics using H3 spatial aggregation.
        """
        print("\n=== Example 3: Demographic Analysis ===")
        
        # Generate sample demographic data
        demographic_data = []
        
        # Different neighborhoods with different characteristics
        neighborhoods = [
            {'center': (38.6270, -90.1994), 'type': 'downtown', 'population_density': 'high'},
            {'center': (38.6500, -90.2200), 'type': 'residential', 'population_density': 'medium'},
            {'center': (38.6000, -90.1700), 'type': 'suburban', 'population_density': 'low'},
            {'center': (38.6400, -90.1800), 'type': 'commercial', 'population_density': 'medium'}
        ]
        
        for neighborhood in neighborhoods:
            center = neighborhood['center']
            for i in range(75):  # 75 data points per neighborhood
                # Random point within neighborhood
                lat_offset = random.uniform(-0.02, 0.02)
                lng_offset = random.uniform(-0.02, 0.02)
                lat = center[0] + lat_offset
                lng = center[1] + lng_offset
                
                # Generate demographic data based on neighborhood type
                if neighborhood['type'] == 'downtown':
                    age_avg = random.uniform(25, 45)
                    income_avg = random.uniform(40000, 80000)
                elif neighborhood['type'] == 'residential':
                    age_avg = random.uniform(30, 55)
                    income_avg = random.uniform(50000, 90000)
                elif neighborhood['type'] == 'suburban':
                    age_avg = random.uniform(35, 65)
                    income_avg = random.uniform(60000, 120000)
                else:  # commercial
                    age_avg = random.uniform(20, 40)
                    income_avg = random.uniform(35000, 70000)
                
                demographic_data.append({
                    'lat': lat,
                    'lng': lng,
                    'age': age_avg + random.uniform(-10, 10),
                    'income': income_avg + random.uniform(-15000, 15000),
                    'education': random.choice(['high_school', 'college', 'graduate']),
                    'neighborhood_type': neighborhood['type']
                })
        
        # Convert to H3
        h3_demographics = []
        for point in demographic_data:
            h3_index = h3.latlng_to_cell(point['lat'], point['lng'], 8)
            h3_demographics.append({
                'h3_index': h3_index,
                'lat': point['lat'],
                'lng': point['lng'],
                'data': point
            })
        
        # Aggregate demographics by H3 hexagon
        aggregated_demographics = self.processor.aggregate_by_h3(
            h3_demographics, 
            {'age': 'mean', 'income': 'mean'}
        )
        
        # Create visualizations
        hexagons = list(aggregated_demographics.keys())
        ages = [aggregated_demographics[h]['age_avg'] for h in hexagons]
        incomes = [aggregated_demographics[h]['income_avg'] for h in hexagons]
        
        # Age distribution map
        age_map = self.visualizer.create_folium_map((38.6270, -90.1994), zoom=11)
        age_map = self.visualizer.add_h3_hexagons_to_map(
            age_map, hexagons, ages, color_palette='viridis'
        )
        age_map.save('demographic_age_analysis.html')
        
        # Income distribution map
        income_map = self.visualizer.create_folium_map((38.6270, -90.1994), zoom=11)
        income_map = self.visualizer.add_h3_hexagons_to_map(
            income_map, hexagons, incomes, color_palette='plasma'
        )
        income_map.save('demographic_income_analysis.html')
        
        print(f"Analyzed {len(demographic_data)} demographic data points")
        print(f"Aggregated into {len(hexagons)} demographic zones")
        print(f"Average age range: {min(ages):.1f} to {max(ages):.1f} years")
        print(f"Average income range: ${min(incomes):,.0f} to ${max(incomes):,.0f}")
        print("Demographic maps saved as 'demographic_age_analysis.html' and 'demographic_income_analysis.html'")
        
        return aggregated_demographics
    
    def example_4_environmental_monitoring(self):
        """
        Example 4: Environmental Monitoring
        Monitor air quality and environmental factors using H3 spatial indexing.
        """
        print("\n=== Example 4: Environmental Monitoring ===")
        
        # Generate environmental monitoring stations
        monitoring_stations = []
        
        # Different areas with different pollution levels
        areas = [
            {'center': (38.6270, -90.1994), 'type': 'industrial', 'base_aqi': 80},
            {'center': (38.6500, -90.2200), 'type': 'residential', 'base_aqi': 45},
            {'center': (38.6000, -90.1700), 'type': 'park', 'base_aqi': 25},
            {'center': (38.6400, -90.1800), 'type': 'highway', 'base_aqi': 65}
        ]
        
        for area in areas:
            center = area['center']
            for i in range(25):  # 25 stations per area
                # Random station location
                lat_offset = random.uniform(-0.015, 0.015)
                lng_offset = random.uniform(-0.015, 0.015)
                lat = center[0] + lat_offset
                lng = center[1] + lng_offset
                
                # Generate environmental data
                base_aqi = area['base_aqi']
                aqi = max(0, base_aqi + random.uniform(-20, 20))
                
                # Other environmental factors
                pm25 = aqi * 0.4 + random.uniform(-5, 5)
                ozone = aqi * 0.3 + random.uniform(-3, 3)
                no2 = aqi * 0.25 + random.uniform(-2, 2)
                
                monitoring_stations.append({
                    'lat': lat,
                    'lng': lng,
                    'station_id': f'station_{len(monitoring_stations)+1}',
                    'aqi': aqi,
                    'pm25': max(0, pm25),
                    'ozone': max(0, ozone),
                    'no2': max(0, no2),
                    'area_type': area['type']
                })
        
        # Convert to H3
        h3_environmental = []
        for station in monitoring_stations:
            h3_index = h3.latlng_to_cell(station['lat'], station['lng'], 8)
            h3_environmental.append({
                'h3_index': h3_index,
                'lat': station['lat'],
                'lng': station['lng'],
                'data': station
            })
        
        # Aggregate environmental data
        aggregated_env = self.processor.aggregate_by_h3(
            h3_environmental,
            {'aqi': 'mean', 'pm25': 'mean', 'ozone': 'mean', 'no2': 'mean'}
        )
        
        # Create environmental visualization
        hexagons = list(aggregated_env.keys())
        aqi_values = [aggregated_env[h]['aqi_avg'] for h in hexagons]
        
        env_map = self.visualizer.create_folium_map((38.6270, -90.1994), zoom=10)
        env_map = self.visualizer.add_h3_hexagons_to_map(
            env_map, hexagons, aqi_values, color_palette='hot'
        )
        env_map.save('environmental_monitoring.html')
        
        # Create heatmap for PM2.5
        coordinates = [(station['lat'], station['lng']) for station in monitoring_stations]
        pm25_values = [station['pm25'] for station in monitoring_stations]
        
        pm25_heatmap = self.visualizer.create_heatmap(coordinates, pm25_values)
        pm25_heatmap.save('pm25_heatmap.html')
        
        print(f"Monitored {len(monitoring_stations)} environmental stations")
        print(f"Aggregated into {len(hexagons)} environmental zones")
        print(f"AQI range: {min(aqi_values):.1f} to {max(aqi_values):.1f}")
        print("Environmental maps saved as 'environmental_monitoring.html' and 'pm25_heatmap.html'")
        
        # Calculate pollution hotspots
        hotspots = [h for h in hexagons if aggregated_env[h]['aqi_avg'] > 70]
        print(f"Identified {len(hotspots)} pollution hotspots (AQI > 70)")
        
        return aggregated_env
    
    def example_5_retail_site_analysis(self):
        """
        Example 5: Retail Site Analysis
        Analyze potential retail locations using H3 spatial intelligence.
        """
        print("\n=== Example 5: Retail Site Analysis ===")
        
        # Define analysis area (St. Louis metropolitan area)
        analysis_center = (38.6270, -90.1994)
        
        # Generate potential retail sites
        potential_sites = []
        for i in range(30):
            # Random locations within analysis area
            bearing = random.uniform(0, 360)
            distance = random.uniform(2000, 15000)  # 2-15km radius
            
            site_location = self.utils.destination_point(analysis_center, bearing, distance)
            
            # Site characteristics
            foot_traffic = random.randint(100, 2000)  # Daily foot traffic
            competition_score = random.uniform(0, 10)  # Lower is better
            rent_cost = random.uniform(15, 50)  # Per sq ft per month
            accessibility = random.uniform(1, 10)  # Higher is better
            
            # Calculate site score (higher is better)
            site_score = (foot_traffic / 100) + (10 - competition_score) + accessibility - (rent_cost / 10)
            
            potential_sites.append({
                'lat': site_location[0],
                'lng': site_location[1],
                'site_id': f'site_{i+1}',
                'foot_traffic': foot_traffic,
                'competition_score': competition_score,
                'rent_cost': rent_cost,
                'accessibility': accessibility,
                'site_score': site_score
            })
        
        # Convert to H3
        h3_retail = []
        for site in potential_sites:
            h3_index = h3.latlng_to_cell(site['lat'], site['lng'], 8)
            h3_retail.append({
                'h3_index': h3_index,
                'lat': site['lat'],
                'lng': site['lng'],
                'data': site
            })
        
        # Create retail analysis visualization
        retail_map = self.visualizer.create_folium_map(analysis_center, zoom=10)
        
        # Add potential sites with color coding based on score
        import folium
        
        scores = [site['site_score'] for site in potential_sites]
        min_score, max_score = min(scores), max(scores)
        
        for site in potential_sites:
            # Normalize score for color mapping
            if max_score > min_score:
                score_norm = (site['site_score'] - min_score) / (max_score - min_score)
            else:
                score_norm = 0.5
            
            # Color based on score (green = good, red = poor)
            if score_norm > 0.7:
                color = 'green'
                icon = 'thumbs-up'
            elif score_norm > 0.4:
                color = 'orange'
                icon = 'minus'
            else:
                color = 'red'
                icon = 'thumbs-down'
            
            popup_text = f"""
            Site: {site['site_id']}<br>
            Score: {site['site_score']:.1f}<br>
            Foot Traffic: {site['foot_traffic']}/day<br>
            Competition: {site['competition_score']:.1f}/10<br>
            Rent: ${site['rent_cost']:.0f}/sq ft<br>
            Accessibility: {site['accessibility']:.1f}/10
            """
            
            folium.Marker(
                location=[site['lat'], site['lng']],
                popup=folium.Popup(popup_text, max_width=250),
                icon=folium.Icon(color=color, icon=icon)
            ).add_to(retail_map)
        
        retail_map.save('retail_site_analysis.html')
        
        # Find top sites
        top_sites = sorted(potential_sites, key=lambda x: x['site_score'], reverse=True)[:5]
        
        print(f"Analyzed {len(potential_sites)} potential retail sites")
        print(f"Site scores range: {min(scores):.1f} to {max(scores):.1f}")
        print("Retail analysis map saved as 'retail_site_analysis.html'")
        print("\nTop 5 recommended sites:")
        for i, site in enumerate(top_sites, 1):
            print(f"  {i}. {site['site_id']}: Score {site['site_score']:.1f}")
        
        return potential_sites
    
    def run_all_examples(self):
        """Run all example analyses."""
        print("Running H3 Geolocation Framework Examples\n")
        print("=" * 50)
        
        # Run all examples
        heat_data = self.example_1_urban_heat_island_analysis()
        delivery_data = self.example_2_delivery_route_optimization()
        demographic_data = self.example_3_demographic_analysis()
        environmental_data = self.example_4_environmental_monitoring()
        retail_data = self.example_5_retail_site_analysis()
        
        print("\n" + "=" * 50)
        print("All examples completed successfully!")
        print("Generated visualizations:")
        print("  - urban_heat_island_analysis.html")
        print("  - delivery_route_optimization.html")
        print("  - demographic_age_analysis.html")
        print("  - demographic_income_analysis.html")
        print("  - environmental_monitoring.html")
        print("  - pm25_heatmap.html")
        print("  - retail_site_analysis.html")
        
        return {
            'heat_island': heat_data,
            'delivery_routes': delivery_data,
            'demographics': demographic_data,
            'environment': environmental_data,
            'retail_sites': retail_data
        }

def main():
    """Main function to run examples."""
    examples = H3Examples()
    
    print("H3 Geolocation Framework - Example Applications")
    print("=" * 50)
    print("Select an example to run:")
    print("1. Urban Heat Island Analysis")
    print("2. Delivery Route Optimization")
    print("3. Demographic Analysis")
    print("4. Environmental Monitoring")
    print("5. Retail Site Analysis")
    print("6. Run All Examples")
    print("0. Exit")
    
    while True:
        try:
            choice = input("\nEnter your choice (0-6): ").strip()
            
            if choice == '0':
                print("Goodbye!")
                break
            elif choice == '1':
                examples.example_1_urban_heat_island_analysis()
            elif choice == '2':
                examples.example_2_delivery_route_optimization()
            elif choice == '3':
                examples.example_3_demographic_analysis()
            elif choice == '4':
                examples.example_4_environmental_monitoring()
            elif choice == '5':
                examples.example_5_retail_site_analysis()
            elif choice == '6':
                examples.run_all_examples()
            else:
                print("Invalid choice. Please enter a number between 0-6.")
                
        except KeyboardInterrupt:
            print("\nGoodbye!")
            break
        except Exception as e:
            print(f"Error: {e}")

if __name__ == "__main__":
    main()