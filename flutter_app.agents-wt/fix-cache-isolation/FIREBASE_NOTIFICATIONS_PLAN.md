# Firebase Notifications Integration Plan — Sirati Flutter App

## Current State

- **Backend**: Mostly ready — Laravel with `kreait/laravel-firebase` SDK, FCM token CRUD endpoints (`POST /api/fcm-tokens`, `DELETE /api/fcm-tokens`), `FirebaseNotificationService` that sends notification+data and data-only messages, automatic invalid token deactivation.
- **Flutter App**: Zero Firebase setup. No packages, no native config, no handlers. Existing `notifications_screen.dart` pulls from server API (poll-based).

---

## 0. Laravel Backend Changes

### Current Backend Infrastructure (Already Exists)

| Component | Status | Location |
|-----------|--------|----------|
| `kreait/laravel-firebase` package | ✅ Installed | `composer.json` |
| `UserFcmToken` model | ✅ | `app/Models/UserFcmToken.php` |
| `MobileNotification` model | ✅ | `app/Models/MobileNotification.php` |
| `FcmTokenController` (register/deactivate) | ✅ | `app/Http/Controllers/FcmTokenController.php` |
| `FirebaseNotificationService` | ✅ | `app/Services/FirebaseNotificationService.php` |
| `user_fcm_tokens` migration | ✅ | `database/migrations/` |
| `mobile_notifications` migration | ✅ | `database/migrations/` |
| User relationships (`fcmTokens`, `mobileNotifications`) | ✅ | `app/Models/User.php` |
| Notification list/read APIs | ✅ | `MobileContentController` |
| `.env.example` Firebase vars | ✅ | `FIREBASE_CREDENTIALS`, `FIREBASE_PROJECT` |

### Required Backend Changes

#### 1. Publish Firebase Config (Optional but Recommended)

```bash
php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider" --tag=config
```

This creates `config/firebase.php` allowing customization of project settings, timeouts, and multiple project support.

#### 2. Set Environment Variables

In `.env`:
```env
FIREBASE_CREDENTIALS=/path/to/service-account-key.json
FIREBASE_PROJECT=sirati-project-id
FIREBASE_HTTP_CLIENT_TIMEOUT=10
```

**To get the service account key:**
1. Firebase Console → Project Settings → Service accounts
2. Click "Generate new private key"
3. Store the JSON file securely on the server (outside web root)
4. Set `FIREBASE_CREDENTIALS` to the absolute path

#### 3. Add Queued Notification Dispatch (Recommended for Production)

Currently `FirebaseNotificationService` sends synchronously — this blocks the HTTP response when triggered from an API endpoint or event. Create a queued job:

**Create file: `app/Jobs/SendPushNotificationJob.php`**

```php
<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly int $userId,
        private readonly string $title,
        private readonly string $body,
        private readonly string $type = 'info',
        private readonly ?string $actionType = null,
        private readonly ?string $actionUrl = null,
        private readonly array $data = [],
    ) {}

    public function handle(FirebaseNotificationService $service): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('[FCM Job] User not found', ['user_id' => $this->userId]);
            return;
        }

        $service->createAndSendToUser(
            user: $user,
            title: $this->title,
            body: $this->body,
            type: $this->type,
            actionType: $this->actionType,
            actionUrl: $this->actionUrl,
            data: $this->data,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[FCM Job] Failed to send push notification', [
            'user_id' => $this->userId,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Usage:**
```php
use App\Jobs\SendPushNotificationJob;

SendPushNotificationJob::dispatch(
    userId: $user->id,
    title: 'تم تحليل سيرتك الذاتية',
    body: 'اطلع على النتائج الآن',
    type: 'cv_analysis',
    actionType: 'screen',
    actionUrl: 'analysis/' . $analysis->id,
);
```

#### 4. Add Broadcast/Bulk Send Endpoint (Optional — Admin Use)

**Create file: `app/Http/Controllers/Admin/NotificationBroadcastController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationBroadcastController extends Controller
{
    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'max:60'],
            'action_type' => ['nullable', 'string', 'max:60'],
            'action_url' => ['nullable', 'string', 'max:255'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $query = User::whereHas('fcmTokens', fn ($q) => $q->where('is_active', true));

        if (!empty($validated['user_ids'])) {
            $query->whereIn('id', $validated['user_ids']);
        }

        $userIds = $query->pluck('id');

        foreach ($userIds as $userId) {
            SendPushNotificationJob::dispatch(
                userId: $userId,
                title: $validated['title'],
                body: $validated['body'],
                type: $validated['type'] ?? 'info',
                actionType: $validated['action_type'] ?? null,
                actionUrl: $validated['action_url'] ?? null,
            );
        }

        return response()->json([
            'message' => 'Broadcast queued.',
            'recipients' => $userIds->count(),
        ]);
    }
}
```

**Route (add to `routes/api.php`):**
```php
use App\Http\Controllers\Admin\NotificationBroadcastController;

// Admin routes (protect with middleware)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('/notifications/broadcast', [NotificationBroadcastController::class, 'broadcast']);
});
```

#### 5. Add Notification Trigger Points

Integrate push notifications at key business events. Add dispatches where appropriate:

| Event | Where to Dispatch | Payload |
|-------|-------------------|---------|
| CV analysis complete | After `CvAnalysis` is saved | `action_type: "screen"`, `action_url: "analysis/{id}"` |
| Generated CV ready | After `GeneratedCv` creation | `action_type: "screen"`, `action_url: "generated-cv/{id}"` |
| New job news published | After `JobNews` import/creation | `action_type: "screen"`, `action_url: "job-news/{id}"` |
| New education content | After `EducationContent` creation | `action_type: "screen"`, `action_url: "education/{id}"` |
| Account/security alert | Auth events | `action_type: "screen"`, `action_url: "notifications"` |

**Example — After CV analysis completion:**
```php
// In the controller or service that completes analysis:
SendPushNotificationJob::dispatch(
    userId: $analysis->user_id,
    title: 'تم تحليل سيرتك الذاتية ✅',
    body: 'حصلت على ' . $analysis->ats_score . '% — اطلع على التفاصيل',
    type: 'cv_analysis',
    actionType: 'screen',
    actionUrl: 'analysis/' . $analysis->id,
);
```

#### 6. Token Cleanup Scheduled Command (Recommended)

Create an Artisan command to clean stale tokens periodically:

**Create file: `app/Console/Commands/CleanStaleTokensCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\UserFcmToken;
use Illuminate\Console\Command;

class CleanStaleTokensCommand extends Command
{
    protected $signature = 'fcm:clean-tokens {--days=90}';
    protected $description = 'Deactivate FCM tokens not seen in N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $count = UserFcmToken::where('is_active', true)
            ->where('last_seen_at', '<', now()->subDays($days))
            ->update(['is_active' => false]);

        $this->info("Deactivated {$count} stale tokens (>{$days} days).");

        return self::SUCCESS;
    }
}
```

**Schedule in `routes/console.php` or `app/Console/Kernel.php`:**
```php
Schedule::command('fcm:clean-tokens')->weekly();
```

#### 7. Add `unread_notifications_count` to Dashboard Response

If not already present, return unread count so the Flutter app can show a badge:

```php
// In MobileContentController::dashboard()
'unread_notifications_count' => $user->mobileNotifications()
    ->whereNull('read_at')
    ->count(),
```

#### 8. Token Activity Update (Recommended)

Update `last_seen_at` on every token registration so stale cleanup works correctly. **Already handled** by `FcmTokenController::store()` which sets `'last_seen_at' => now()` on every upsert.

---

### Backend Deployment Checklist

- [ ] Firebase service account JSON deployed to server (secure location, not in git)
- [ ] `FIREBASE_CREDENTIALS` env var set to path of JSON file
- [ ] `FIREBASE_PROJECT` env var set to actual Firebase project ID
- [ ] Run `php artisan migrate` (if `user_fcm_tokens` table not yet created)
- [ ] Queue worker running (`php artisan queue:work`) for async job dispatch
- [ ] `fcm:clean-tokens` scheduled command registered
- [ ] Test token registration via `POST /api/fcm-tokens` with valid auth
- [ ] Test notification send via `FirebaseNotificationService::createAndSendToUser()`
- [ ] Verify invalid token deactivation after multicast failure

---

## 1. Firebase Integration Plan

### Required Firebase Services
- **Firebase Cloud Messaging (FCM)** — push notifications
- **Firebase Core** — base SDK initialization

### Required Flutter Packages

```yaml
# pubspec.yaml additions
dependencies:
  firebase_core: ^3.13.0
  firebase_messaging: ^15.2.3
  flutter_local_notifications: ^19.0.0
  device_info_plus: ^11.3.0   # unique device_id generation
```

### Android Setup Steps

1. Create Firebase project at https://console.firebase.google.com
2. Add Android app with package name from `android/app/build.gradle` (`applicationId`)
3. Download `google-services.json` → place in `flutter_app/android/app/`
4. Add Google services plugin to `android/build.gradle` and `android/app/build.gradle`
5. Set `minSdkVersion 21` (already likely set)
6. Add notification channel and metadata to `AndroidManifest.xml`
7. Add `POST_NOTIFICATIONS` permission for Android 13+

### iOS Setup Steps

1. Add iOS app in Firebase Console with bundle ID from Xcode
2. Download `GoogleService-Info.plist` → place in `flutter_app/ios/Runner/`
3. Enable **Push Notifications** capability in Xcode (creates entitlements file)
4. Enable **Background Modes** → Remote notifications in Xcode
5. Upload APNs key (.p8) or certificate to Firebase Console → Project Settings → Cloud Messaging
6. Update `AppDelegate.swift` for Firebase initialization and APNs delegate

### Firebase Console Setup

1. Project Settings → Cloud Messaging → ensure API is enabled
2. For iOS: upload APNs Authentication Key (preferred over certificate)
3. Test via Firebase Console → Messaging → "Send your first message"

### Permission Handling

| Platform | When to Request | API |
|----------|----------------|-----|
| Android 13+ | After onboarding / first relevant moment | `Permission.notification` via `firebase_messaging` |
| Android <13 | Automatic (no runtime permission needed) | — |
| iOS | After onboarding / first relevant moment | `requestPermission()` |

### Token Registration Strategy

```
App launch → Firebase.initializeApp()
  → Get FCM token
  → If user logged in:
      → POST /api/fcm-tokens { token, device_id, platform, app_version }
  → Listen for token refresh → re-register

User logs in → register current token
User logs out → DELETE /api/fcm-tokens { token }
```

### Backend/Server Requirements (Already Met)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/fcm-tokens` | POST | Register/update token |
| `/api/fcm-tokens` | DELETE | Deactivate token on logout |
| Backend service | — | `FirebaseNotificationService` sends via `kreait/firebase-php` |

---

## 2. Notification Cases — Complete Coverage

### Case 1: App in Foreground
- `FirebaseMessaging.onMessage` stream fires
- FCM does NOT display a system notification on Android/iOS
- **We must**: show local notification via `flutter_local_notifications` OR in-app banner
- Parse `RemoteMessage` → display via local notification plugin

### Case 2: App in Background
- FCM automatically displays the system notification (if `notification` payload present)
- `onBackgroundMessage` handler fires for data processing
- No UI code runs; handler must be a **top-level function**

### Case 3: App Terminated / Cold Start
- If notification payload present → system shows it automatically
- If user taps → app launches → `FirebaseMessaging.instance.getInitialMessage()` returns the `RemoteMessage`
- Must check `getInitialMessage()` in `main.dart` after init

### Case 4: User Taps Notification
- **From background**: `FirebaseMessaging.onMessageOpenedApp` stream fires
- **From terminated**: `getInitialMessage()` returns the message
- Parse `data` payload → navigate to correct screen (deep link)

### Case 5: Data-Only Notification
- No system notification shown automatically on any platform
- `onMessage` (foreground) or `onBackgroundMessage` (background/terminated) fires
- Use for silent updates (refresh data, sync, etc.)
- On iOS: requires `content-available: 1` in APNs payload (backend already sets this)

### Case 6: Notification with Title/Body Payload
- System displays automatically when app is background/terminated
- Foreground: must handle manually (local notification or banner)

### Case 7: Token Refresh
- `FirebaseMessaging.instance.onTokenRefresh` stream
- Re-register with backend immediately
- Old token automatically becomes invalid on Firebase's side

### Case 8: User Login/Logout
- **Login**: get current token → POST to `/api/fcm-tokens`
- **Logout**: POST to `/api/fcm-tokens` (DELETE) → deactivate server-side
- Backend already handles multi-user: deactivates tokens belonging to other users on same device

### Case 9: Android 13+ Permission
- Must request `Notification` permission at runtime
- `FirebaseMessaging.instance.requestPermission()` triggers the system dialog
- If denied: token still works but notifications won't display
- Handle `denied` / `permanentlyDenied` gracefully (show settings prompt)

### Case 10: iOS Permission & Foreground Presentation
- `requestPermission(alert: true, badge: true, sound: true)`
- For foreground display: `setForegroundNotificationPresentationOptions(alert: true, badge: true, sound: true)`
- Or handle manually via `onMessage` + local notification

### Case 11: Invalid/Expired Token Cleanup
- **Backend already handles this**: `deactivateInvalidTokens()` marks tokens inactive after multicast failure
- Flutter side: on token refresh, old token auto-invalidates

### Case 12: Multiple User Accounts on Same Device
- **Backend already handles**: `FcmTokenController::store()` deactivates the same token/device_id for other users
- Flutter: always send `device_id` with token registration

### Case 13: Deep Linking from Notification Payload
- Backend sends `action_type` and `action_url` in data payload
- Flutter parses these and navigates:
  - `action_type: "screen"` + `action_url: "analysis/123"` → navigate to analysis screen
  - `action_type: "url"` + `action_url: "https://..."` → open in browser
  - `action_type: "notifications"` → open notifications screen

---

## 3. Flutter Implementation

### 3.1 `pubspec.yaml` — Additions

```yaml
dependencies:
  firebase_core: ^3.13.0
  firebase_messaging: ^15.2.3
  flutter_local_notifications: ^19.0.0
  device_info_plus: ^11.3.0
```

### 3.2 `lib/main.dart` — Modified

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'services/notification_service.dart';
// ... existing imports ...

// TOP-LEVEL — required for background messages
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Do NOT navigate or show UI here. Only process data silently.
  debugPrint('[FCM] Background message: ${message.messageId}');
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Firebase init
  await Firebase.initializeApp();

  // Register background handler
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  // Initialize notification service (local notifications, channels)
  await NotificationService.instance.initialize();

  // Handle notification that launched the app (terminated state)
  await NotificationService.instance.handleInitialMessage();

  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  runApp(const SiratiApp());
}
```

### 3.3 `lib/services/notification_service.dart` — New File

```dart
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'api_client.dart';
import 'auth_token_store.dart';

class NotificationService {
  NotificationService._();
  static final NotificationService instance = NotificationService._();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  // Navigation callback — set from UI layer
  void Function(Map<String, dynamic> data)? onNotificationTap;

  // Android notification channel
  static const _androidChannel = AndroidNotificationChannel(
    'high_importance_channel', // matches backend channel_id
    'High Importance Notifications',
    description: 'Used for important notifications',
    importance: Importance.high,
    playSound: true,
  );

  /// Call once at app startup
  Future<void> initialize() async {
    // Create Android notification channel
    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_androidChannel);

    // Initialize local notifications
    const androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings(
      requestAlertPermission: false, // We request separately
      requestBadgePermission: false,
      requestSoundPermission: false,
    );
    await _localNotifications.initialize(
      const InitializationSettings(
        android: androidSettings,
        iOS: iosSettings,
      ),
      onDidReceiveNotificationResponse: _onLocalNotificationTap,
    );

    // iOS foreground presentation
    await _messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );

    // Listen for foreground messages
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Listen for notification taps (app was in background)
    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);

    // Token refresh
    _messaging.onTokenRefresh.listen(_onTokenRefresh);
  }

  /// Check if app was opened from a terminated-state notification
  Future<void> handleInitialMessage() async {
    final message = await _messaging.getInitialMessage();
    if (message != null) {
      // Delay navigation until UI is ready
      Future.delayed(const Duration(milliseconds: 500), () {
        _handleNotificationTap(message);
      });
    }
  }

  /// Request notification permission (call after onboarding or login)
  Future<bool> requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );
    return settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional;
  }

  /// Get current FCM token
  Future<String?> getToken() async {
    return await _messaging.getToken();
  }

  /// Register token with backend (call after login or token refresh)
  Future<void> registerToken() async {
    final token = await getToken();
    if (token == null) return;

    final authToken = await AuthTokenStore().read();
    if (authToken == null) return; // Not logged in

    final deviceId = await _getDeviceId();
    final platform = Platform.isIOS ? 'ios' : 'android';

    try {
      final client = ApiClient();
      await client.postJson('/fcm-tokens', body: {
        'token': token,
        'device_id': deviceId,
        'platform': platform,
        'app_version': '1.0.0', // TODO: get from package_info_plus
      });
      debugPrint('[FCM] Token registered successfully');
    } catch (e) {
      debugPrint('[FCM] Token registration failed: $e');
    }
  }

  /// Deactivate token on backend (call on logout)
  Future<void> unregisterToken() async {
    final token = await getToken();
    if (token == null) return;

    try {
      final client = ApiClient();
      await client.deleteJson('/fcm-tokens', body: {'token': token});
      debugPrint('[FCM] Token unregistered');
    } catch (e) {
      debugPrint('[FCM] Token unregister failed: $e');
    }
  }

  // --- Private Methods ---

  void _handleForegroundMessage(RemoteMessage message) {
    debugPrint('[FCM] Foreground message: ${message.messageId}');
    final notification = message.notification;

    if (notification != null) {
      // Show as local notification so user sees it
      _localNotifications.show(
        message.hashCode,
        notification.title,
        notification.body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            _androidChannel.id,
            _androidChannel.name,
            channelDescription: _androidChannel.description,
            importance: Importance.high,
            priority: Priority.high,
            icon: '@mipmap/ic_launcher',
          ),
          iOS: const DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
          ),
        ),
        payload: _encodePayload(message.data),
      );
    }
  }

  void _handleNotificationTap(RemoteMessage message) {
    debugPrint('[FCM] Notification tapped: ${message.data}');
    _navigateFromPayload(message.data);
  }

  void _onLocalNotificationTap(NotificationResponse response) {
    if (response.payload != null) {
      final data = _decodePayload(response.payload!);
      _navigateFromPayload(data);
    }
  }

  void _navigateFromPayload(Map<String, dynamic> data) {
    if (onNotificationTap != null) {
      onNotificationTap!(data);
    }
  }

  void _onTokenRefresh(String newToken) {
    debugPrint('[FCM] Token refreshed');
    registerToken(); // Re-register with backend
  }

  Future<String> _getDeviceId() async {
    final deviceInfo = DeviceInfoPlugin();
    if (Platform.isAndroid) {
      final info = await deviceInfo.androidInfo;
      return info.id; // Unique Android ID
    } else {
      final info = await deviceInfo.iosInfo;
      return info.identifierForVendor ?? 'unknown-ios';
    }
  }

  String _encodePayload(Map<String, dynamic> data) {
    // Simple encoding for local notification payload
    return data.entries.map((e) => '${e.key}=${e.value}').join('&');
  }

  Map<String, dynamic> _decodePayload(String payload) {
    final map = <String, dynamic>{};
    for (final pair in payload.split('&')) {
      final parts = pair.split('=');
      if (parts.length == 2) {
        map[parts[0]] = parts[1];
      }
    }
    return map;
  }
}
```

### 3.4 Navigation/Deep Link Handler (integrate in app)

```dart
// In your main app widget's initState or after navigation is ready:

NotificationService.instance.onNotificationTap = (data) {
  final actionType = data['action_type'];
  final actionUrl = data['action_url'];

  switch (actionType) {
    case 'screen':
      _navigateToScreen(actionUrl);
      break;
    case 'url':
      if (actionUrl != null) launchUrl(Uri.parse(actionUrl));
      break;
    case 'notifications':
    default:
      _navigateToNotifications();
      break;
  }
};
```

### 3.5 Auth Integration (modify existing login/logout flow)

```dart
// After successful login:
await NotificationService.instance.requestPermission();
await NotificationService.instance.registerToken();

// On logout:
await NotificationService.instance.unregisterToken();
```

---

## 4. File-by-File Changes

### `flutter_app/pubspec.yaml`
Add under `dependencies:`:
```yaml
  firebase_core: ^3.13.0
  firebase_messaging: ^15.2.3
  flutter_local_notifications: ^19.0.0
  device_info_plus: ^11.3.0
```

### `flutter_app/android/build.gradle` (project-level)
Add in `buildscript.dependencies`:
```groovy
classpath 'com.google.gms:google-services:4.4.2'
```

### `flutter_app/android/app/build.gradle`
Add at bottom:
```groovy
apply plugin: 'com.google.gms.google-services'
```
Ensure `minSdkVersion 21` and `compileSdkVersion 34`.

### `flutter_app/android/app/src/main/AndroidManifest.xml`
Add inside `<manifest>`:
```xml
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
```

Add inside `<application>`:
```xml
<!-- FCM default channel -->
<meta-data
    android:name="com.google.firebase.messaging.default_notification_channel_id"
    android:value="high_importance_channel"/>

<!-- FCM default icon (optional) -->
<meta-data
    android:name="com.google.firebase.messaging.default_notification_icon"
    android:resource="@mipmap/ic_launcher"/>
```

### `flutter_app/android/app/google-services.json`
Download from Firebase Console → place here.

### `flutter_app/ios/Runner/GoogleService-Info.plist`
Download from Firebase Console → place here.

### `flutter_app/ios/Runner/AppDelegate.swift`
```swift
import Flutter
import UIKit
import FirebaseCore
import FirebaseMessaging

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    FirebaseApp.configure()
    GeneratedPluginRegistrant.register(with: self)

    // Register for remote notifications
    UNUserNotificationCenter.current().delegate = self
    application.registerForRemoteNotifications()

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  // Forward APNs token to Firebase
  override func application(
    _ application: UIApplication,
    didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
  ) {
    Messaging.messaging().apnsToken = deviceToken
    super.application(application, didRegisterForRemoteNotificationsWithDeviceToken: deviceToken)
  }
}
```

### `flutter_app/ios/Runner/Runner.entitlements` (create or verify in Xcode)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>aps-environment</key>
    <string>development</string>
</dict>
</plist>
```

### `flutter_app/ios/Runner/Info.plist`
Add:
```xml
<key>UIBackgroundModes</key>
<array>
    <string>fetch</string>
    <string>remote-notification</string>
</array>
<key>FirebaseAppDelegateProxyEnabled</key>
<false/>
```

### `flutter_app/lib/main.dart`
See Section 3.2 above.

### `flutter_app/lib/services/notification_service.dart`
New file — see Section 3.3 above.

---

## 5. Best Practices & Edge Cases

### Error Handling
- Wrap all FCM operations in try/catch — never crash the app due to notification failure
- If token registration fails, retry on next app launch
- If permission denied, still initialize service (for data-only messages on Android <13)
- Backend already handles messaging exceptions and logs them

### Logging/Debugging Tips
- Use `debugPrint('[FCM] ...')` prefix for all notification logs
- Firebase Console → "Send test message" with FCM token for quick testing
- Android: `adb logcat | grep FCM` for native-level logs
- iOS: Console.app → filter by process name
- Check `FirebaseMessaging.instance.getNotificationSettings()` to verify permission state

### Testing Checklist
- [ ] Fresh install → permission dialog appears at correct moment
- [ ] Foreground: notification banner appears
- [ ] Background: system notification appears, tap navigates correctly
- [ ] Terminated: tap launches app and navigates to correct screen
- [ ] Token refresh: new token is sent to backend
- [ ] Login: token registered
- [ ] Logout: token deactivated
- [ ] Second account login on same device: old user stops receiving
- [ ] Deny permission → app doesn't crash, graceful fallback
- [ ] Data-only message: silent processing without crash
- [ ] Invalid payload: no crash, logged and ignored
- [ ] Network offline: token registration queued/retried on next launch

### Firebase Payload Examples

**Notification + Data (sent by backend):**
```json
{
  "message": {
    "notification": {
      "title": "تم تحليل سيرتك الذاتية",
      "body": "اطلع على النتائج الآن"
    },
    "data": {
      "type": "cv_analysis",
      "notification_id": "42",
      "action_type": "screen",
      "action_url": "analysis/15"
    },
    "android": {
      "priority": "high",
      "notification": {
        "channel_id": "high_importance_channel",
        "sound": "default",
        "click_action": "FLUTTER_NOTIFICATION_CLICK"
      }
    },
    "apns": {
      "headers": { "apns-priority": "10" },
      "payload": { "aps": { "sound": "default" } }
    }
  }
}
```

**Data-Only (silent):**
```json
{
  "message": {
    "data": {
      "type": "sync",
      "action": "refresh_notifications"
    },
    "android": { "priority": "high" },
    "apns": {
      "headers": { "apns-priority": "5" },
      "payload": { "aps": { "content-available": 1 } }
    }
  }
}
```

### Security Considerations
- Never log the full FCM token in production
- FCM tokens are per-device secrets — transmit only over HTTPS (already enforced by API client)
- Backend validates auth before accepting token registration
- Backend deactivates tokens of other users on same device (prevents token hijacking)
- Use `flutter_secure_storage` if caching token locally (don't store in SharedPreferences)
- `device_id` should not be user-guessable

### Backend vs Flutter Responsibilities

| Responsibility | Owner |
|---------------|-------|
| Decide WHEN to send notification | Backend |
| Compose notification content | Backend |
| Send via FCM API | Backend |
| Invalid token cleanup | Backend |
| Multi-user token deactivation | Backend |
| Request permission | Flutter |
| Register/unregister token | Flutter |
| Display foreground notifications | Flutter |
| Handle tap → navigate | Flutter |
| Parse deep link payload | Flutter |

---

## 6. FlutterFire CLI Alternative (Recommended)

Instead of manual `google-services.json` setup, use FlutterFire CLI:

```bash
dart pub global activate flutterfire_cli
flutterfire configure --project=YOUR_FIREBASE_PROJECT_ID
```

This auto-generates `firebase_options.dart` and configures both platforms. Then in `main.dart`:

```dart
import 'firebase_options.dart';

await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
```

This is the recommended approach for new Firebase integration.
