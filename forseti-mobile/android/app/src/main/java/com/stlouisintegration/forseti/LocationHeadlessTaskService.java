package com.stlouisintegration.forseti;

import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import com.facebook.react.HeadlessJsTaskService;
import com.facebook.react.bridge.Arguments;
import com.facebook.react.jstasks.HeadlessJsTaskConfig;
import javax.annotation.Nullable;

/**
 * Headless JS Task Service - Handles location updates when app is backgrounded
 */
public class LocationHeadlessTaskService extends HeadlessJsTaskService {
    private static final String TAG = "LocationHeadlessTask";

    @Override
    protected @Nullable HeadlessJsTaskConfig getTaskConfig(Intent intent) {
        Bundle extras = intent.getExtras();
        if (extras != null) {
            Log.d(TAG, "Starting headless task with location data");
            return new HeadlessJsTaskConfig(
                "LocationBackgroundTask",
                Arguments.fromBundle(extras),
                60000, // Timeout: 60 seconds
                true   // Allow task in foreground
            );
        }
        return null;
    }
}
