import React from 'react';
import { render, screen } from '@testing-library/react-native';
import HomeScreen from '../screens/HomeScreen';

// Mock the navigation prop
const mockNavigation = {
  navigate: jest.fn(),
  goBack: jest.fn(),
  setOptions: jest.fn(),
};

describe('HomeScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders correctly', () => {
    const { getByText } = render(<HomeScreen navigation={mockNavigation} />);

    // Check if key elements are rendered
    expect(getByText(/safety/i)).toBeTruthy();
  });

  it('displays the location information', () => {
    const { getByText } = render(<HomeScreen navigation={mockNavigation} />);

    // Should show location-related content
    expect(getByText(/Current Location/i) || getByText(/location/i)).toBeTruthy();
  });

  it('should have safety-related content', () => {
    const { getByText } = render(<HomeScreen navigation={mockNavigation} />);

    // Check for safety score or safety-related text
    const safetyElements = screen.queryAllByText(/safety/i);
    expect(safetyElements.length).toBeGreaterThan(0);
  });
});
