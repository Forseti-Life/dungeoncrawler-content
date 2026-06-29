package com.stlouisintegration.forseti;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.location.Location;
import android.os.Build;
import android.os.Bundle;
import android.os.IBinder;
import android.util.Log;
import androidx.core.app.NotificationCompat;
import com.facebook.react.HeadlessJsTaskService;
import com.facebook.react.bridge.Arguments;
import com.facebook.react.bridge.WritableMap;
import com.facebook.react.jstasks.HeadlessJsTaskConfig;

/**
 * Foreground Service for continuous location tracking
 * Sends location updates to React Native headless task
 */
public class LocationTrackingService extends Service {
    private static final String TAG = "LocationTrackingService";
    private static final String CHANNEL_ID = "forseti_location_channel";
    private static final int NOTIFICATION_ID = 1001;
    
    private NotificationManager notificationManager;
    private boolean isRunning = false;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "LocationTrackingService created");
        notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "LocationTrackingService started");
        
        if (!isRunning) {
            isRunning = true;
            startForeground(NOTIFICATION_ID, createNotification("Forseti is monitoring your safety"));
        }
        
        return START_STICKY;
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "LocationTrackingService destroyed");
        isRunning = false;
    }

    /**
     * Create notification channel for Android O+
     */
    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "Forseti Location Service",
                NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("Monitors your location for safety alerts");
            channel.setShowBadge(false);
            notificationManager.createNotificationChannel(channel);
        }
    }

    /**
     * Create foreground service notification
     */
    private Notification createNotification(String contentText) {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this,
            0,
            notificationIntent,
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.M ? 
                PendingIntent.FLAG_IMMUTABLE : 0
        );

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Forseti Safety Monitor")
            .setContentText(contentText)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW);

        return builder.build();
    }

    /**
     * Update notification text
     */
    public void updateNotification(String text) {
        notificationManager.notify(NOTIFICATION_ID, createNotification(text));
    }

    /**
     * Trigger headless JS task with location data
     */
    public static void sendLocationToJS(Context context, Location location) {
        Intent service = new Intent(context, LocationHeadlessTaskService.class);
        Bundle bundle = new Bundle();
        
        WritableMap params = Arguments.createMap();
        params.putDouble("latitude", location.getLatitude());
        params.putDouble("longitude", location.getLongitude());
        params.putDouble("accuracy", location.getAccuracy());
        params.putDouble("timestamp", location.getTime());
        
        bundle.putBundle("location", Arguments.toBundle(params));
        service.putExtras(bundle);
        
        context.startService(service);
    }
}
