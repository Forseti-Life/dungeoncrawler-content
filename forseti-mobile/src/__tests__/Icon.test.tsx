import React from 'react';
import { render } from '@testing-library/react-native';
import Icon from '../components/Icon';

describe('Icon Component', () => {
  it('renders without crashing', () => {
    const { getByTestId } = render(<Icon name="home" size={24} color="#000" testID="icon" />);
    expect(getByTestId('icon')).toBeTruthy();
  });

  it('applies the correct size', () => {
    const { getByTestId } = render(<Icon name="home" size={32} color="#000" testID="icon" />);
    const icon = getByTestId('icon');
    expect(icon.props.style).toMatchObject(
      expect.objectContaining({
        fontSize: 32,
      })
    );
  });

  it('applies the correct color', () => {
    const { getByTestId } = render(<Icon name="home" size={24} color="#FF0000" testID="icon" />);
    const icon = getByTestId('icon');
    expect(icon.props.style).toMatchObject(
      expect.objectContaining({
        color: '#FF0000',
      })
    );
  });

  it('handles various icon names', () => {
    const iconNames = ['home', 'map', 'search', 'user', 'settings'];

    iconNames.forEach(name => {
      const { getByTestId } = render(
        <Icon name={name} size={24} color="#000" testID={`icon-${name}`} />
      );
      expect(getByTestId(`icon-${name}`)).toBeTruthy();
    });
  });
});
