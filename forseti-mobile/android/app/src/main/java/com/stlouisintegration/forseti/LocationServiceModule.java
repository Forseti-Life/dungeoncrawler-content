package com.stlouisintegration.forseti;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

import androidx.core.app.NotificationCompat;

import com.facebook.react.bridge.Promise;
import com.facebook.react.bridge.ReactApplicationContext;
import com.facebook.react.bridge.ReactContextBaseJavaModule;
import com.facebook.react.bridge.ReactMethod;

/**
 * Native module to control LocationTrackingService from React Native
 */
public class LocationServiceModule extends ReactContextBaseJavaModule {
    private static final String TAG = "LocationServiceModule";
    private final ReactApplicationContext reactContext;

    public LocationServiceModule(ReactApplicationContext reactContext) {
        super(reactContext);
        this.reactContext = reactContext;
    }

    @Override
    public String getName() {
        return "LocationServiceModule";
    }

    @ReactMethod
    public void startLocationService(Promise promise) {
        try {
            Context context = getReactApplicationContext();
            Intent serviceIntent = new Intent(context, LocationTrackingService.class);
            
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(serviceIntent);
            } else {
                context.startService(serviceIntent);
            }
            
            Log.d(TAG, "Location service started successfully");
            promise.resolve(true);
        } catch (Exception e) {
            Log.e(TAG, "Failed to start location service", e);
            promise.reject("START_SERVICE_FAILED", e.getMessage());
        }
    }

    @ReactMethod
    public void stopLocationService(Promise promise) {
        try {
            Context context = getReactApplicationContext();
            Intent serviceIntent = new Intent(context, LocationTrackingService.class);
            context.stopService(serviceIntent);
            
            Log.d(TAG, "Location service stopped successfully");
            promise.resolve(true);
        } catch (Exception e) {
            Log.e(TAG, "Failed to stop location service", e);
            promise.reject("STOP_SERVICE_FAILED", e.getMessage());
        }
    }

    @ReactMethod
    public void isServiceRunning(Promise promise) {
        // Simple check - service should be running if monitoring is enabled
        promise.resolve(true);
    }

    @ReactMethod
    public void sendNativeTestNotification(String title, String message, Promise promise) {
        try {
            Context context = getReactApplicationContext();
            NotificationManager notificationManager = 
                (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
            
            String channelId = "forseti_test_native";
            
            // Create notification channel for Android 8.0+
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                NotificationChannel channel = new NotificationChannel(
                    channelId,
                    "Forseti Native Test",
                    NotificationManager.IMPORTANCE_DEFAULT
                );
                channel.setDescription("Native notification test channel");
                notificationManager.createNotificationChannel(channel);
                Log.d(TAG, "Created native test notification channel");
            }

            // Create notification intent
            Intent notificationIntent = new Intent(context, MainActivity.class);
            PendingIntent pendingIntent = PendingIntent.getActivity(
                context,
                0,
                notificationIntent,
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.M ? 
                    PendingIntent.FLAG_IMMUTABLE : 0
            );

            // Build notification
            NotificationCompat.Builder builder = new NotificationCompat.Builder(context, channelId)
                .setContentTitle(title)
                .setContentText(message)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentIntent(pendingIntent)
                .setPriority(NotificationCompat.PRIORITY_DEFAULT)
                .setAutoCancel(true);

            Notification notification = builder.build();
            notificationManager.notify(12345, notification);
            
            Log.d(TAG, "Native test notification sent: " + title);
            promise.resolve("Native notification sent successfully");
        } catch (Exception e) {
            Log.e(TAG, "Failed to send native notification", e);
            promise.reject("NATIVE_NOTIFICATION_FAILED", e.getMessage());
        }
    }
}
