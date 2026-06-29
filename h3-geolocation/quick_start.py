#!/usr/bin/env python3
"""
Quick Start Script for H3 Geolocation Framework

This script provides a simple interface to get started with the H3 framework
and demonstrates basic functionality with real St. Louis data.
"""

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from h3_framework import H3GeolocationFramework
import h3

def quick_demo():
    """Quick demonstration of H3 framework capabilities."""
    print("H3 Geolocation Framework - Quick Demo")
    print("=" * 40)
    
    # Initialize framework
    framework = H3GeolocationFramework()
    
    # St. Louis landmarks
    landmarks = {
        'Gateway Arch': (38.6247, -90.1848),
        'Busch Stadium': (38.6226, -90.1928),
        'Forest Park': (38.6355, -90.2732),
        'Saint Louis University': (38.6370, -90.2307),
        'Washington University': (38.6488, -90.3108),
        'St. Louis Zoo': (38.6356, -90.2931),
        'City Museum': (38.6338, -90.2006),
        'Anheuser-Busch Brewery': (38.6168, -90.2141)
    }
    
    print("Converting St. Louis landmarks to H3 indices:")
    print("-" * 40)
    
    h3_landmarks = {}
    for name, (lat, lng) in landmarks.items():
        # Convert to H3 at resolution 9 (neighborhood level)
        h3_index = framework.coords_to_h3(lat, lng, 9)
        h3_landmarks[name] = h3_index
        
        print(f"{name:25} -> {h3_index}")
        print(f"  Coordinates: {lat:.4f}, {lng:.4f}")
        print(f"  Resolution:  {h3.cell_to_res(h3_index)}")
        print()
    
    # Demonstrate neighbor analysis
    print("Neighbor Analysis - Gateway Arch area:")
    print("-" * 40)
    
    gateway_arch_h3 = h3_landmarks['Gateway Arch']
    neighbors = framework.get_neighbors(gateway_arch_h3, k=1)
    
    print(f"Gateway Arch H3: {gateway_arch_h3}")
    print(f"Direct neighbors ({len(neighbors)} hexagons):")
    
    for i, neighbor in enumerate(neighbors, 1):
        neighbor_center = h3.cell_to_latlng(neighbor)
        distance = framework.calculate_distance(
            landmarks['Gateway Arch'], neighbor_center
        )
        print(f"  {i}. {neighbor} (distance: {distance:.0f}m)")
    
    # Create simple visualization
    print("\nCreating visualization...")
    print("-" * 40)
    
    try:
        # Convert landmarks to format for visualization
        landmark_data = []
        for name, (lat, lng) in landmarks.items():
            landmark_data.append({
                'lat': lat,
                'lng': lng,
                'name': name,
                'h3_index': h3_landmarks[name]
            })
        
        # Create map
        st_louis_center = (38.6270, -90.1994)
        visualization_map = framework.create_interactive_map(
            landmark_data, 
            center=st_louis_center,
            zoom=11
        )
        
        # Save map
        visualization_map.save('st_louis_landmarks.html')
        print("✓ Interactive map saved as 'st_louis_landmarks.html'")
        
        # Create hexagon visualization
        hexagon_indices = list(h3_landmarks.values())
        hex_map = framework.visualize_hexagons(
            hexagon_indices,
            center=st_louis_center,
            zoom=11
        )
        hex_map.save('st_louis_hexagons.html')
        print("✓ Hexagon map saved as 'st_louis_hexagons.html'")
        
    except Exception as e:
        print(f"Visualization error: {e}")
        print("Note: Make sure all dependencies are installed")
    
    # Demonstrate spatial analysis
    print("\nSpatial Analysis:")
    print("-" * 40)
    
    # Find hexagons within 2km of Gateway Arch
    arch_location = landmarks['Gateway Arch']
    nearby_hexagons = framework.get_hexagons_in_radius(
        arch_location[0], arch_location[1], 2000, resolution=9
    )
    
    print(f"Hexagons within 2km of Gateway Arch: {len(nearby_hexagons)}")
    
    # Calculate area coverage
    total_area = sum(framework.calculate_hexagon_area(h) for h in nearby_hexagons)
    print(f"Total area covered: {total_area/1000000:.2f} km²")
    
    # Distance matrix between landmarks
    print("\nDistance Matrix (top 5 pairs):")
    print("-" * 40)
    
    distances = []
    landmark_list = list(landmarks.items())
    
    for i, (name1, coord1) in enumerate(landmark_list):
        for j, (name2, coord2) in enumerate(landmark_list[i+1:], i+1):
            distance = framework.calculate_distance(coord1, coord2)
            distances.append((name1, name2, distance))
    
    # Sort by distance and show top 5
    distances.sort(key=lambda x: x[2])
    for name1, name2, distance in distances[:5]:
        print(f"{name1} <-> {name2}: {distance:.0f}m")
    
    print("\n" + "=" * 40)
    print("Quick demo completed!")
    print("Generated files:")
    print("  - st_louis_landmarks.html (interactive landmark map)")
    print("  - st_louis_hexagons.html (H3 hexagon visualization)")

def interactive_demo():
    """Interactive demo allowing user input."""
    print("\nH3 Interactive Demo")
    print("=" * 40)
    
    framework = H3GeolocationFramework()
    
    while True:
        print("\nOptions:")
        print("1. Convert coordinates to H3")
        print("2. Convert H3 to coordinates")
        print("3. Find neighbors")
        print("4. Calculate distance")
        print("5. Get hexagons in radius")
        print("0. Exit")
        
        try:
            choice = input("\nEnter your choice (0-5): ").strip()
            
            if choice == '0':
                break
            
            elif choice == '1':
                lat = float(input("Enter latitude: "))
                lng = float(input("Enter longitude: "))
                resolution = int(input("Enter resolution (0-15, default 9): ") or "9")
                
                h3_index = framework.coords_to_h3(lat, lng, resolution)
                print(f"H3 index: {h3_index}")
                
            elif choice == '2':
                h3_index = input("Enter H3 index: ").strip()
                try:
                    lat, lng = framework.h3_to_coords(h3_index)
                    resolution = h3.cell_to_res(h3_index)
                    print(f"Coordinates: {lat:.6f}, {lng:.6f}")
                    print(f"Resolution: {resolution}")
                except Exception as e:
                    print(f"Error: {e}")
                    
            elif choice == '3':
                h3_index = input("Enter H3 index: ").strip()
                k = int(input("Enter k-ring distance (default 1): ") or "1")
                
                try:
                    neighbors = framework.get_neighbors(h3_index, k)
                    print(f"Found {len(neighbors)} neighbors:")
                    for i, neighbor in enumerate(neighbors[:10], 1):  # Show first 10
                        print(f"  {i}. {neighbor}")
                    if len(neighbors) > 10:
                        print(f"  ... and {len(neighbors) - 10} more")
                except Exception as e:
                    print(f"Error: {e}")
                    
            elif choice == '4':
                print("Enter first coordinate:")
                lat1 = float(input("  Latitude: "))
                lng1 = float(input("  Longitude: "))
                
                print("Enter second coordinate:")
                lat2 = float(input("  Latitude: "))
                lng2 = float(input("  Longitude: "))
                
                distance = framework.calculate_distance((lat1, lng1), (lat2, lng2))
                print(f"Distance: {distance:.2f} meters ({distance/1000:.2f} km)")
                
            elif choice == '5':
                lat = float(input("Enter center latitude: "))
                lng = float(input("Enter center longitude: "))
                radius = float(input("Enter radius in meters: "))
                resolution = int(input("Enter resolution (0-15, default 9): ") or "9")
                
                hexagons = framework.get_hexagons_in_radius(lat, lng, radius, resolution)
                print(f"Found {len(hexagons)} hexagons in {radius}m radius")
                
                if len(hexagons) <= 20:
                    for i, h in enumerate(hexagons, 1):
                        print(f"  {i}. {h}")
                else:
                    print("First 10 hexagons:")
                    for i, h in enumerate(hexagons[:10], 1):
                        print(f"  {i}. {h}")
                    print(f"  ... and {len(hexagons) - 10} more")
            
            else:
                print("Invalid choice.")
                
        except ValueError as e:
            print(f"Invalid input: {e}")
        except KeyboardInterrupt:
            print("\nGoodbye!")
            break
        except Exception as e:
            print(f"Error: {e}")

def main():
    """Main function."""
    print("H3 Geolocation Framework")
    print("Quick Start & Demo")
    print("=" * 40)
    print("1. Run Quick Demo")
    print("2. Interactive Demo")
    print("0. Exit")
    
    try:
        choice = input("\nEnter your choice (0-2): ").strip()
        
        if choice == '1':
            quick_demo()
        elif choice == '2':
            interactive_demo()
        elif choice == '0':
            print("Goodbye!")
        else:
            print("Invalid choice.")
            
    except KeyboardInterrupt:
        print("\nGoodbye!")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    main()