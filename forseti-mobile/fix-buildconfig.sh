#!/bin/bash

# Fix buildConfig feature issues in React Native libraries for Android Gradle Plugin 8+

# Libraries that need buildConfig enabled
libs=(
  "node_modules/react-native-safe-area-context/android/build.gradle"
  "node_modules/react-native-screens/android/build.gradle"
  "node_modules/react-native-gesture-handler/android/build.gradle"
)

for gradle_file in "${libs[@]}"; do
  if [ -f "$gradle_file" ]; then
    # Check if buildConfig is already set
    if ! grep -q "buildConfig true" "$gradle_file"; then
      echo "Adding buildConfig to $gradle_file"
      
      # Add buildFeatures { buildConfig true } after namespace or after 'android {' line
      if grep -q "namespace" "$gradle_file"; then
        sed -i "/namespace/a\    buildFeatures {\n        buildConfig true\n    }" "$gradle_file"
      else
        sed -i "/^android {/a\    buildFeatures {\n        buildConfig true\n    }" "$gradle_file"
      fi
    else
      echo "buildConfig already exists in $gradle_file"
    fi
  else
    echo "Warning: $gradle_file not found"
  fi
done

echo "buildConfig fixes complete!"
