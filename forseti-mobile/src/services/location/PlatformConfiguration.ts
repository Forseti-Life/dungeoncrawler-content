/**
 * Boot Receiver Configuration for Android
 *
 * This file documents the Android configuration needed for auto-start on boot.
 * Manual Android configuration is required in AndroidManifest.xml and Java/Kotlin code.
 */

/*
==============================================================================
ANDROID CONFIGURATION STEPS
==============================================================================

1. ADD PERMISSIONS TO AndroidManifest.xml:
   
   <uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
   <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
   <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
   <uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
   <uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
   <uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
   <uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
   <uses-permission android:name="android.permission.VIBRATE" />

2. ADD BOOT RECEIVER TO AndroidManifest.xml:

   <application>
     ...
     
     <receiver 
       android:name=".BootReceiver"
       android:enabled="true"
       android:exported="true"
       android:permission="android.permission.RECEIVE_BOOT_COMPLETED">
       <intent-filter>
         <action android:name="android.intent.action.BOOT_COMPLETED" />
         <action android:name="android.intent.action.QUICKBOOT_POWERON" />
         <category android:name="android.intent.category.DEFAULT" />
       </intent-filter>
     </receiver>

     <service
       android:name=".LocationMonitoringService"
       android:enabled="true"
       android:exported="false"
       android:foregroundServiceType="location" />

   </application>

3. CREATE BootReceiver.java (or .kt) in android/app/src/main/java/com/forseti/:

   package com.forseti;

   import android.content.BroadcastReceiver;
   import android.content.Context;
   import android.content.Intent;
   import android.util.Log;

   public class BootReceiver extends BroadcastReceiver {
       @Override
       public void onReceive(Context context, Intent intent) {
           if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
               Log.d("Forseti", "Boot completed - checking monitoring state");
               
               // Check if user had monitoring enabled
               // Start LocationMonitoringService if it was enabled
               Intent serviceIntent = new Intent(context, LocationMonitoringService.class);
               context.startForegroundService(serviceIntent);
           }
       }
   }

4. CREATE LocationMonitoringService.java in android/app/src/main/java/com/forseti/:

   package com.forseti;

   import android.app.Notification;
   import android.app.NotificationChannel;
   import android.app.NotificationManager;
   import android.app.PendingIntent;
   import android.app.Service;
   import android.content.Intent;
   import android.os.Build;
   import android.os.IBinder;
   import androidx.core.app.NotificationCompat;

   public class LocationMonitoringService extends Service {
       private static final String CHANNEL_ID = "location_monitoring";
       private static final int NOTIFICATION_ID = 1001;

       @Override
       public void onCreate() {
           super.onCreate();
           createNotificationChannel();
       }

       @Override
       public int onStartCommand(Intent intent, int flags, int startId) {
           // Create foreground notification
           Notification notification = createNotification();
           startForeground(NOTIFICATION_ID, notification);
           
           return START_STICKY; // Restart service if killed
       }

       @Override
       public IBinder onBind(Intent intent) {
           return null;
       }

       private void createNotificationChannel() {
           if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
               NotificationChannel channel = new NotificationChannel(
                   CHANNEL_ID,
                   "Location Monitoring",
                   NotificationManager.IMPORTANCE_LOW
               );
               channel.setDescription("Forseti background location monitoring");
               
               NotificationManager manager = getSystemService(NotificationManager.class);
               manager.createNotificationChannel(channel);
           }
       }

       private Notification createNotification() {
           Intent notificationIntent = new Intent(this, MainActivity.class);
           PendingIntent pendingIntent = PendingIntent.getActivity(
               this, 0, notificationIntent, PendingIntent.FLAG_IMMUTABLE
           );

           return new NotificationCompat.Builder(this, CHANNEL_ID)
               .setContentTitle("Forseti Protection Active")
               .setContentText("Monitoring your location for safety alerts")
               .setSmallIcon(R.drawable.ic_notification)
               .setContentIntent(pendingIntent)
               .setOngoing(true)
               .build();
       }
   }

5. GRADLE CONFIGURATION (android/app/build.gradle):

   android {
       ...
       defaultConfig {
           ...
           minSdkVersion 23  // Minimum for background location
           targetSdkVersion 33
       }
   }

6. iOS CONFIGURATION (Info.plist):

   <key>UIBackgroundModes</key>
   <array>
       <string>location</string>
       <string>fetch</string>
       <string>remote-notification</string>
   </array>
   
   <key>NSLocationWhenInUseUsageDescription</key>
   <string>Forseti needs your location to alert you about dangerous areas</string>
   
   <key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
   <string>Forseti monitors your location in the background to send safety alerts when you enter high-crime areas</string>
   
   <key>NSLocationAlwaysUsageDescription</key>
   <string>Forseti uses your location in the background to provide real-time safety alerts</string>

==============================================================================
*/

export const ANDROID_CONFIG = {
  permissions: [
    'RECEIVE_BOOT_COMPLETED',
    'ACCESS_FINE_LOCATION',
    'ACCESS_COARSE_LOCATION',
    'ACCESS_BACKGROUND_LOCATION',
    'FOREGROUND_SERVICE',
    'FOREGROUND_SERVICE_LOCATION',
    'POST_NOTIFICATIONS',
    'VIBRATE',
  ],

  services: ['BootReceiver', 'LocationMonitoringService'],

  notificationChannels: [
    {
      id: 'location_monitoring',
      name: 'Location Monitoring',
      importance: 'low',
      description: 'Background location monitoring',
    },
    {
      id: 'danger_alerts',
      name: 'Danger Alerts',
      importance: 'high',
      description: 'High-priority safety alerts',
    },
  ],
};

export const IOS_CONFIG = {
  backgroundModes: ['location', 'fetch', 'remote-notification'],

  locationPermissions: [
    'NSLocationWhenInUseUsageDescription',
    'NSLocationAlwaysAndWhenInUseUsageDescription',
    'NSLocationAlwaysUsageDescription',
  ],
};
