module.exports = {
  dependencies: {
    'react-native-maps': {
      platforms: {
        android: {
          packageImportPath: 'import com.rnmaps.maps.MapsPackage;',
          packageInstance: 'new MapsPackage()',
        },
      },
    },
  },
};
