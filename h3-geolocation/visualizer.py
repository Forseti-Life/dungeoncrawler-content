"""
Visualization Module for H3 Framework

Provides advanced visualization capabilities including heatmaps, 
interactive maps, statistical plots, and 3D visualizations.
"""

import matplotlib.pyplot as plt
import matplotlib.patches as patches
import numpy as np
import h3
import folium
from folium import plugins
import plotly.graph_objects as go
import plotly.express as px
from plotly.subplots import make_subplots
import seaborn as sns
from typing import List, Dict, Tuple, Optional, Union, Any
import pandas as pd
from matplotlib.colors import LinearSegmentedColormap
import warnings
warnings.filterwarnings('ignore')

class H3Visualizer:
    """Advanced visualization utilities for H3 framework."""
    
    def __init__(self, default_style: str = 'seaborn-v0_8'):
        """
        Initialize visualizer.
        
        Args:
            default_style (str): Default matplotlib style
        """
        try:
            plt.style.use(default_style)
        except:
            plt.style.use('default')
        
        # Color palettes
        self.color_palettes = {
            'viridis': plt.cm.viridis,
            'plasma': plt.cm.plasma,
            'inferno': plt.cm.inferno,
            'magma': plt.cm.magma,
            'cool': plt.cm.cool,
            'hot': plt.cm.hot,
            'custom_blue': LinearSegmentedColormap.from_list(
                'custom_blue', ['lightblue', 'darkblue']
            ),
            'custom_red': LinearSegmentedColormap.from_list(
                'custom_red', ['lightcoral', 'darkred']
            )
        }
    
    def create_folium_map(self, center: Tuple[float, float], zoom: int = 10, 
                         tiles: str = 'OpenStreetMap') -> folium.Map:
        """
        Create base Folium map.
        
        Args:
            center (Tuple[float, float]): Map center (lat, lng)
            zoom (int): Initial zoom level
            tiles (str): Tile style
            
        Returns:
            folium.Map: Base map object
        """
        return folium.Map(
            location=center,
            zoom_start=zoom,
            tiles=tiles,
            control_scale=True
        )
    
    def add_h3_hexagons_to_map(self, folium_map: folium.Map, hexagons: List[str], 
                              values: Optional[List[float]] = None,
                              color_palette: str = 'viridis',
                              opacity: float = 0.6) -> folium.Map:
        """
        Add H3 hexagons to Folium map.
        
        Args:
            folium_map (folium.Map): Base map
            hexagons (List[str]): H3 hexagon indices
            values (Optional[List[float]]): Values for color coding
            color_palette (str): Color palette name
            opacity (float): Hexagon opacity
            
        Returns:
            folium.Map: Map with hexagons
        """
        if values and len(values) == len(hexagons):
            # Normalize values for color mapping
            min_val, max_val = min(values), max(values)
            if max_val > min_val:
                normalized_values = [(v - min_val) / (max_val - min_val) for v in values]
            else:
                normalized_values = [0.5] * len(values)
            
            # Get colormap
            colormap = self.color_palettes.get(color_palette, plt.cm.viridis)
        
        for i, hexagon in enumerate(hexagons):
            try:
                # Get hexagon boundary
                boundary = h3.cell_to_boundary(hexagon)
                coordinates = [[lat, lng] for lat, lng in boundary]
                
                # Determine color
                if values and len(values) == len(hexagons):
                    color_intensity = normalized_values[i]
                    rgba = colormap(color_intensity)
                    color = f'#{int(rgba[0]*255):02x}{int(rgba[1]*255):02x}{int(rgba[2]*255):02x}'
                    popup_text = f"H3: {hexagon}<br>Value: {values[i]:.2f}"
                else:
                    color = '#3388ff'
                    popup_text = f"H3: {hexagon}"
                
                # Add polygon to map
                folium.Polygon(
                    locations=coordinates,
                    color=color,
                    weight=2,
                    opacity=0.8,
                    fillColor=color,
                    fillOpacity=opacity,
                    popup=folium.Popup(popup_text, max_width=200),
                    tooltip=popup_text
                ).add_to(folium_map)
                
            except Exception as e:
                print(f"Error adding hexagon {hexagon}: {e}")
                continue
        
        return folium_map
    
    def create_heatmap(self, coordinates: List[Tuple[float, float]], 
                      values: Optional[List[float]] = None,
                      center: Optional[Tuple[float, float]] = None,
                      zoom: int = 10, radius: int = 15) -> folium.Map:
        """
        Create heatmap visualization.
        
        Args:
            coordinates (List[Tuple[float, float]]): Point coordinates
            values (Optional[List[float]]): Intensity values
            center (Optional[Tuple[float, float]]): Map center
            zoom (int): Zoom level
            radius (int): Heat point radius
            
        Returns:
            folium.Map: Heatmap
        """
        if not center:
            # Calculate center from coordinates
            if coordinates:
                center_lat = sum(coord[0] for coord in coordinates) / len(coordinates)
                center_lng = sum(coord[1] for coord in coordinates) / len(coordinates)
                center = (center_lat, center_lng)
            else:
                center = (0, 0)
        
        # Create base map
        heatmap = folium.Map(location=center, zoom_start=zoom)
        
        # Prepare heat data
        if values and len(values) == len(coordinates):
            heat_data = [[coord[0], coord[1], val] for coord, val in zip(coordinates, values)]
        else:
            heat_data = [[coord[0], coord[1]] for coord in coordinates]
        
        # Add heatmap layer
        plugins.HeatMap(
            heat_data,
            radius=radius,
            blur=15,
            max_zoom=18
        ).add_to(heatmap)
        
        return heatmap
    
    def create_cluster_map(self, coordinates: List[Tuple[float, float]], 
                          labels: Optional[List[str]] = None,
                          center: Optional[Tuple[float, float]] = None,
                          zoom: int = 10) -> folium.Map:
        """
        Create clustered marker map.
        
        Args:
            coordinates (List[Tuple[float, float]]): Point coordinates
            labels (Optional[List[str]]): Point labels
            center (Optional[Tuple[float, float]]): Map center
            zoom (int): Zoom level
            
        Returns:
            folium.Map: Cluster map
        """
        if not center:
            if coordinates:
                center_lat = sum(coord[0] for coord in coordinates) / len(coordinates)
                center_lng = sum(coord[1] for coord in coordinates) / len(coordinates)
                center = (center_lat, center_lng)
            else:
                center = (0, 0)
        
        # Create base map
        cluster_map = folium.Map(location=center, zoom_start=zoom)
        
        # Create marker cluster
        marker_cluster = plugins.MarkerCluster().add_to(cluster_map)
        
        # Add markers
        for i, coord in enumerate(coordinates):
            label = labels[i] if labels and i < len(labels) else f"Point {i+1}"
            
            folium.Marker(
                location=coord,
                popup=folium.Popup(label, max_width=200),
                tooltip=label
            ).add_to(marker_cluster)
        
        return cluster_map
    
    def plot_h3_resolution_comparison(self, data: Dict[int, int], 
                                    title: str = "H3 Resolution Distribution"):
        """
        Plot H3 resolution distribution.
        
        Args:
            data (Dict[int, int]): Resolution counts
            title (str): Plot title
        """
        resolutions = sorted(data.keys())
        counts = [data[res] for res in resolutions]
        
        plt.figure(figsize=(12, 6))
        
        # Bar plot
        plt.subplot(1, 2, 1)
        bars = plt.bar(resolutions, counts, color='skyblue', edgecolor='navy', alpha=0.7)
        plt.xlabel('H3 Resolution')
        plt.ylabel('Count')
        plt.title(f'{title} - Distribution')
        plt.grid(True, alpha=0.3)
        
        # Add value labels on bars
        for bar, count in zip(bars, counts):
            plt.text(bar.get_x() + bar.get_width()/2, bar.get_height() + max(counts)*0.01,
                    str(count), ha='center', va='bottom')
        
        # Pie chart
        plt.subplot(1, 2, 2)
        plt.pie(counts, labels=[f'Res {r}' for r in resolutions], autopct='%1.1f%%',
                startangle=90, colors=plt.cm.Set3(np.linspace(0, 1, len(resolutions))))
        plt.title(f'{title} - Proportion')
        
        plt.tight_layout()
        plt.show()
    
    def plot_spatial_distribution(self, coordinates: List[Tuple[float, float]], 
                                 values: Optional[List[float]] = None,
                                 title: str = "Spatial Distribution"):
        """
        Plot spatial distribution of points.
        
        Args:
            coordinates (List[Tuple[float, float]]): Point coordinates
            values (Optional[List[float]]): Color values
            title (str): Plot title
        """
        if not coordinates:
            return
        
        lats = [coord[0] for coord in coordinates]
        lngs = [coord[1] for coord in coordinates]
        
        plt.figure(figsize=(15, 10))
        
        if values and len(values) == len(coordinates):
            # Colored scatter plot
            scatter = plt.scatter(lngs, lats, c=values, cmap='viridis', 
                                alpha=0.6, s=50, edgecolors='black', linewidth=0.5)
            plt.colorbar(scatter, label='Value')
        else:
            # Simple scatter plot
            plt.scatter(lngs, lats, color='blue', alpha=0.6, s=50, 
                       edgecolors='black', linewidth=0.5)
        
        plt.xlabel('Longitude')
        plt.ylabel('Latitude')
        plt.title(title)
        plt.grid(True, alpha=0.3)
        
        # Add statistics
        plt.text(0.02, 0.98, f'Points: {len(coordinates)}', 
                transform=plt.gca().transAxes, verticalalignment='top',
                bbox=dict(boxstyle='round', facecolor='white', alpha=0.8))
        
        plt.tight_layout()
        plt.show()
    
    def create_3d_visualization(self, hexagons: List[str], values: List[float], 
                               title: str = "H3 3D Visualization"):
        """
        Create 3D visualization of H3 data.
        
        Args:
            hexagons (List[str]): H3 hexagon indices
            values (List[float]): Height/color values
            title (str): Plot title
        """
        if not hexagons or not values:
            return
        
        # Get coordinates for each hexagon
        coordinates = []
        for hexagon in hexagons:
            lat, lng = h3.cell_to_latlng(hexagon)
            coordinates.append((lat, lng))
        
        lats = [coord[0] for coord in coordinates]
        lngs = [coord[1] for coord in coordinates]
        
        # Create 3D plot
        fig = go.Figure(data=[go.Scatter3d(
            x=lngs,
            y=lats,
            z=values,
            mode='markers',
            marker=dict(
                size=8,
                color=values,
                colorscale='Viridis',
                opacity=0.8,
                colorbar=dict(title="Value"),
                showscale=True
            ),
            text=[f'H3: {h}<br>Value: {v:.2f}' for h, v in zip(hexagons, values)],
            hovertemplate='%{text}<br>Lat: %{y:.4f}<br>Lng: %{x:.4f}<extra></extra>'
        )])
        
        fig.update_layout(
            title=title,
            scene=dict(
                xaxis_title='Longitude',
                yaxis_title='Latitude',
                zaxis_title='Value',
                camera=dict(
                    eye=dict(x=1.2, y=1.2, z=0.6)
                )
            ),
            width=900,
            height=700
        )
        
        fig.show()
    
    def plot_hexagon_neighbors(self, center_hexagon: str, k_rings: int = 2):
        """
        Visualize hexagon with its neighbors.
        
        Args:
            center_hexagon (str): Central H3 hexagon
            k_rings (int): Number of neighbor rings
        """
        # Get all hexagons within k rings
        all_hexagons = h3.grid_disk(center_hexagon, k_rings)
        
        plt.figure(figsize=(12, 10))
        
        # Color scheme
        colors = ['red', 'orange', 'yellow', 'lightgreen', 'lightblue']
        
        for ring in range(k_rings + 1):
            if ring == 0:
                hexagons = [center_hexagon]
                color = 'red'
                label = 'Center'
            else:
                hexagons = list(h3.grid_ring(center_hexagon, ring))
                color = colors[ring % len(colors)]
                label = f'Ring {ring}'
            
            for hexagon in hexagons:
                # Get hexagon boundary
                boundary = h3.cell_to_boundary(hexagon)
                
                # Create polygon
                hex_x = [coord[1] for coord in boundary] + [boundary[0][1]]
                hex_y = [coord[0] for coord in boundary] + [boundary[0][0]]
                
                plt.fill(hex_x, hex_y, color=color, alpha=0.6, 
                        edgecolor='black', linewidth=1)
                
                # Add hexagon ID as text
                center_lat, center_lng = h3.cell_to_latlng(hexagon)
                plt.text(center_lng, center_lat, hexagon[-3:], 
                        ha='center', va='center', fontsize=8, weight='bold')
        
        plt.xlabel('Longitude')
        plt.ylabel('Latitude')
        plt.title(f'H3 Hexagon {center_hexagon} with {k_rings} Neighbor Rings')
        plt.grid(True, alpha=0.3)
        plt.axis('equal')
        
        # Create legend
        handles = []
        for ring in range(k_rings + 1):
            if ring == 0:
                label = 'Center'
                color = 'red'
            else:
                label = f'Ring {ring}'
                color = colors[ring % len(colors)]
            
            handles.append(plt.Rectangle((0, 0), 1, 1, facecolor=color, alpha=0.6))
        
        plt.legend(handles, [f'Ring {i}' if i > 0 else 'Center' for i in range(k_rings + 1)])
        plt.tight_layout()
        plt.show()
    
    def create_interactive_dashboard(self, data: List[Dict], 
                                   center: Tuple[float, float] = (38.6270, -90.1994)):
        """
        Create interactive dashboard with multiple visualizations.
        
        Args:
            data (List[Dict]): H3 data with coordinates and values
            center (Tuple[float, float]): Map center
        """
        if not data:
            print("No data provided for dashboard")
            return
        
        # Extract coordinates and values
        coordinates = []
        values = []
        hexagons = []
        
        for record in data:
            if 'lat' in record and 'lng' in record:
                coordinates.append((record['lat'], record['lng']))
                
                # Extract numeric value for visualization
                value = 1  # Default value
                if 'data' in record and isinstance(record['data'], dict):
                    for key, val in record['data'].items():
                        try:
                            value = float(val)
                            break
                        except (ValueError, TypeError):
                            continue
                
                values.append(value)
                
                if 'h3_index' in record:
                    hexagons.append(record['h3_index'])
        
        # Create subplots
        fig = make_subplots(
            rows=2, cols=2,
            subplot_titles=('Spatial Distribution', 'Value Distribution', 
                          'H3 Resolution Analysis', 'Statistical Summary'),
            specs=[[{"type": "scatter"}, {"type": "histogram"}],
                   [{"type": "bar"}, {"type": "table"}]]
        )
        
        # Spatial distribution
        if coordinates and values:
            lats = [coord[0] for coord in coordinates]
            lngs = [coord[1] for coord in coordinates]
            
            fig.add_trace(
                go.Scatter(
                    x=lngs, y=lats, mode='markers',
                    marker=dict(
                        color=values,
                        colorscale='Viridis',
                        size=8,
                        opacity=0.7,
                        colorbar=dict(title="Value", x=0.45)
                    ),
                    name='Data Points'
                ),
                row=1, col=1
            )
        
        # Value distribution
        if values:
            fig.add_trace(
                go.Histogram(x=values, nbinsx=20, name='Value Distribution'),
                row=1, col=2
            )
        
        # H3 Resolution analysis
        if hexagons:
            resolutions = [h3.cell_to_res(h) for h in hexagons]
            resolution_counts = {}
            for res in resolutions:
                resolution_counts[res] = resolution_counts.get(res, 0) + 1
            
            fig.add_trace(
                go.Bar(
                    x=list(resolution_counts.keys()),
                    y=list(resolution_counts.values()),
                    name='Resolution Count'
                ),
                row=2, col=1
            )
        
        # Statistical summary table
        if values:
            stats_data = [
                ['Count', len(values)],
                ['Mean', f'{np.mean(values):.2f}'],
                ['Median', f'{np.median(values):.2f}'],
                ['Std Dev', f'{np.std(values):.2f}'],
                ['Min', f'{min(values):.2f}'],
                ['Max', f'{max(values):.2f}']
            ]
            
            fig.add_trace(
                go.Table(
                    header=dict(values=['Statistic', 'Value'],
                               fill_color='paleturquoise',
                               align='left'),
                    cells=dict(values=list(zip(*stats_data)),
                              fill_color='lavender',
                              align='left')
                ),
                row=2, col=2
            )
        
        # Update layout
        fig.update_layout(
            title_text="H3 Geolocation Data Dashboard",
            showlegend=False,
            height=800,
            width=1200
        )
        
        fig.update_xaxes(title_text="Longitude", row=1, col=1)
        fig.update_yaxes(title_text="Latitude", row=1, col=1)
        fig.update_xaxes(title_text="Value", row=1, col=2)
        fig.update_yaxes(title_text="Frequency", row=1, col=2)
        fig.update_xaxes(title_text="H3 Resolution", row=2, col=1)
        fig.update_yaxes(title_text="Count", row=2, col=1)
        
        fig.show()
    
    def save_visualization(self, fig, filename: str, format: str = 'png', 
                          dpi: int = 300, bbox_inches: str = 'tight'):
        """
        Save matplotlib visualization to file.
        
        Args:
            fig: Matplotlib figure object
            filename (str): Output filename
            format (str): File format
            dpi (int): Resolution
            bbox_inches (str): Bounding box setting
        """
        if hasattr(fig, 'savefig'):
            fig.savefig(filename, format=format, dpi=dpi, bbox_inches=bbox_inches)
        else:
            plt.savefig(filename, format=format, dpi=dpi, bbox_inches=bbox_inches)
        
        print(f"Visualization saved to {filename}")

# Utility functions for quick visualizations
def quick_hexagon_map(hexagons: List[str], values: Optional[List[float]] = None,
                     center: Optional[Tuple[float, float]] = None) -> folium.Map:
    """Quick function to create hexagon map."""
    visualizer = H3Visualizer()
    
    if not center and hexagons:
        # Calculate center from hexagons
        lats, lngs = [], []
        for h in hexagons[:10]:  # Use first 10 for center calculation
            try:
                lat, lng = h3.cell_to_latlng(h)
                lats.append(lat)
                lngs.append(lng)
            except:
                continue
        
        if lats and lngs:
            center = (sum(lats)/len(lats), sum(lngs)/len(lngs))
        else:
            center = (38.6270, -90.1994)  # Default to St. Louis
    
    folium_map = visualizer.create_folium_map(center, zoom=10)
    return visualizer.add_h3_hexagons_to_map(folium_map, hexagons, values)

def quick_heatmap(coordinates: List[Tuple[float, float]], 
                 values: Optional[List[float]] = None) -> folium.Map:
    """Quick function to create heatmap."""
    visualizer = H3Visualizer()
    return visualizer.create_heatmap(coordinates, values)