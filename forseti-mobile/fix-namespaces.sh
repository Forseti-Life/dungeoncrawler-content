#!/bin/bash

# Fix namespace issues in React Native libraries for Android Gradle Plugin 8+

declare -A packages=(
  ["node_modules/@react-native-async-storage/async-storage/android/build.gradle"]="com.reactnativecommunity.asyncstorage"
  ["node_modules/react-native-vector-icons/android/build.gradle"]="com.oblador.vectoricons"
  ["node_modules/react-native-geolocation-service/android/build.gradle"]="com.agontuk.RNFusedLocation"
  ["node_modules/react-native-gesture-handler/android/build.gradle"]="com.swmansion.gesturehandler"
  ["node_modules/react-native-maps/android/build.gradle"]="com.rnmaps.maps"
  ["node_modules/react-native-permissions/android/build.gradle"]="com.zoontek.rnpermissions"
  ["node_modules/react-native-push-notification/android/build.gradle"]="com.dieam.reactnativepushnotification"
  ["node_modules/react-native-safe-area-context/android/build.gradle"]="com.th3rdwave.safeareacontext"
  ["node_modules/react-native-screens/android/build.gradle"]="com.swmansion.rnscreens"
  ["node_modules/react-native-svg/android/build.gradle"]="com.horcrux.svg"
)

for gradle_file in "${!packages[@]}"; do
  package="${packages[$gradle_file]}"
  
  if [ -f "$gradle_file" ]; then
    # Check if namespace is already set
    if ! grep -q "namespace" "$gradle_file"; then
      echo "Adding namespace to $gradle_file"
      
      # Add namespace after 'android {' line
      sed -i "/^android {/a\    namespace \"$package\"" "$gradle_file"
    else
      echo "Namespace already exists in $gradle_file"
    fi
  else
    echo "Warning: $gradle_file not found"
  fi
done

echo "Namespace fixes complete!"
