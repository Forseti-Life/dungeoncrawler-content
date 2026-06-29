#!/bin/bash
# Monitor Android build progress every 10 minutes for 1 hour

echo "=========================================="
echo "Build Monitor Started: $(date)"
echo "Will check every 10 minutes for 1 hour"
echo "=========================================="

for i in {1..6}; do
    echo ""
    echo "=========================================="
    echo "Check $i/6 at $(date)"
    echo "=========================================="
    
    # Check if Gradle/Java processes are running
    PROCESS_COUNT=$(ps aux | grep -E "gradle|java.*kotlin" | grep -v grep | wc -l)
    echo "Active Gradle/Java processes: $PROCESS_COUNT"
    
    if [ $PROCESS_COUNT -gt 0 ]; then
        echo ""
        echo "Build processes running:"
        ps aux | grep -E "gradle|java.*kotlin" | grep -v grep | awk '{printf "  PID %s - CPU: %s%%, MEM: %s%%, TIME: %s\n", $2, $3, $4, $10}'
        
        # Check for APK
        APK_PATH="/home/keithaumiller/forseti.life/forseti-mobile/android/app/build/outputs/apk/debug/app-debug.apk"
        if [ -f "$APK_PATH" ]; then
            echo ""
            echo "✅ APK FOUND!"
            ls -lh "$APK_PATH"
            echo ""
            echo "Build completed successfully!"
            exit 0
        else
            echo ""
            echo "⏳ Build in progress... APK not yet created"
        fi
    else
        echo ""
        echo "No build processes running. Checking for APK..."
        
        APK_PATH="/home/keithaumiller/forseti.life/forseti-mobile/android/app/build/outputs/apk/debug/app-debug.apk"
        if [ -f "$APK_PATH" ]; then
            echo ""
            echo "✅ APK FOUND!"
            ls -lh "$APK_PATH"
            echo ""
            echo "Build completed successfully!"
            exit 0
        else
            echo ""
            echo "❌ Build appears to have stopped without creating APK"
            echo "Checking recent Gradle logs for errors..."
            echo ""
            tail -50 "/mnt/chromeos/removable/SD Card/Android/.gradle/daemon/8.0.1/daemon-"*.out.log 2>/dev/null | grep -E "BUILD|FAILURE|error:" | tail -10
            exit 1
        fi
    fi
    
    # Wait 10 minutes before next check (unless it's the last iteration)
    if [ $i -lt 6 ]; then
        echo ""
        echo "Waiting 10 minutes until next check..."
        sleep 600
    fi
done

echo ""
echo "=========================================="
echo "Monitor Complete: $(date)"
echo "=========================================="

# Final check
APK_PATH="/home/keithaumiller/forseti.life/forseti-mobile/android/app/build/outputs/apk/debug/app-debug.apk"
if [ -f "$APK_PATH" ]; then
    echo "✅ Final result: APK created successfully!"
    ls -lh "$APK_PATH"
else
    echo "❌ Final result: Build did not complete in 1 hour"
    echo "You may want to check the build manually."
fi
