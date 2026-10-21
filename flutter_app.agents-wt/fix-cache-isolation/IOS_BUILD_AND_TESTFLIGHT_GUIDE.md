# 🍎 Sirati iOS — Mac Device → TestFlight

> You already have the `Runner.app.zip` from Codemagic. This guide covers: install on a Mac-connected iPhone or simulator, and publish to TestFlight.

---

## 📋 Prerequisites

| Tool | Version | Download |
|------|---------|----------|
| macOS | 13+ (Ventura or newer) | — |
| Xcode | 15+ (latest recommended) | [Mac App Store](https://apps.apple.com/us/app/xcode/id497799835) |
| Apple Developer Account | Paid ($99/year) | [developer.apple.com](https://developer.apple.com) |
| iPhone (for device testing) | iOS 16+ | — |
| `Runner.app.zip` | Downloaded from Codemagic | — |

> No Flutter SDK, CocoaPods, or Git needed on the Mac — Codemagic handles the entire build.

---

## Step 1: Install on Mac Simulator (from Runner.app.zip)

1. Extract the ZIP:
   ```bash
   unzip /path/to/Runner.app.zip -d /path/to/extracted/
   ```

2. List available simulators:
   ```bash
   xcrun simctl list devices available
   ```

3. Boot a simulator:
   ```bash
   open -a Simulator
   ```

4. Install the app:
   ```bash
   xcrun simctl install booted /path/to/extracted/Runner.app
   ```

---

## Step 2: Install IPA on iPhone via Mac

### 2a. Connect iPhone to Mac

1. Plug iPhone into Mac via USB cable
2. Unlock iPhone → tap **"Trust This Computer"**
3. On iPhone: **Settings → Privacy & Security → Developer Mode** → Enable → Restart

### 2b. Install using Apple Configurator (Easiest)

```bash
# Install Apple Configurator from Mac App Store (free)
# Then drag the .ipa file onto your iPhone in Apple Configurator
```

Or via command line:

```bash
# Install using xcrun
xcrun devicectl device install app --device <DEVICE_ID> /path/to/Runner.ipa

# Find your device ID:
xcrun devicectl list devices
```

### 2c. Install using Xcode

1. Open Xcode → **Window → Devices and Simulators**
2. Select your connected iPhone
3. Drag the `.ipa` file onto the **Installed Apps** section
4. The app appears on your iPhone home screen

### 2d. Trust the Developer Certificate

After install, if the app won't open:

1. On iPhone: **Settings → General → VPN & Device Management**
2. Tap the developer profile under "Enterprise App"
3. Tap **Trust**

---

## Step 3: Upload to TestFlight via Codemagic (Automated)

### 3a. One-Time Setup: App Store Connect API Key

1. Go to [App Store Connect → Users and Access → Keys](https://appstoreconnect.apple.com/access/api)
2. Click **+** → Create an API Key with **Developer** role
3. Download the `.p8` file — **save it securely, you can't re-download**

### 3b. Add Secrets to Codemagic

In Codemagic → Your App → **Environment Variables**, add:

| Variable | Value |
|----------|-------|
| `APP_STORE_CONNECT_API_KEY` | Content of the `.p8` file |
| `APP_STORE_CONNECT_KEY_ID` | The Key ID (e.g., `ABC1234567`) |
| `APP_STORE_CONNECT_ISSUER_ID` | Your Issuer ID (from the Keys page top) |

### 3c. Enable iOS Code Signing

In Codemagic → **App Settings** → **iOS Code Signing** → Select **Automatic**

### 3d. Trigger TestFlight Build

Now every `ios-testflight` workflow run will:
1. Build the IPA
2. Upload directly to App Store Connect
3. Appear in TestFlight automatically

---

## Step 4: Configure TestFlight in App Store Connect

### First Time Only — Create the App

1. Go to [appstoreconnect.apple.com](https://appstoreconnect.apple.com)
2. **My Apps** → **+** → **New App**

| Field | Value |
|-------|-------|
| Platform | iOS |
| Name | Sirati |
| Primary Language | Arabic |
| Bundle ID | `com.sirati.ats` |
| SKU | `SIRATI_ATS_001` |
| User Access | Full Access |

3. Click **Create**

### Required App Information

Fill in before TestFlight works:

- **App Information**: Privacy Policy URL, Category (Business/Productivity)
- **Pricing and Availability**: Free
- **App Privacy**: Complete the privacy questionnaire
- **App Review Information**: Contact info, demo account credentials

### Add TestFlight Testers

1. In your app → **TestFlight** tab
2. **Internal Testing** (up to 100 team members):
   - Add Apple Developer team members → builds appear automatically
3. **External Testing** (up to 10,000 testers):
   - Create a **New Group** → Add tester emails
   - First build requires **Beta App Review** by Apple

---

## Step 5: Processing & Distribution

After Codemagic uploads:

1. Apple processes the build (5-30 min) — you'll get an email
2. App Store Connect → TestFlight → Select the build
3. Add **What to Test** notes
4. Click **Start Testing** (internal) or **Submit for Review** (external)

Testers receive an email → install **TestFlight** from App Store → redeem invite.

---

## 📱 Quick Reference: Commands Cheat Sheet

```bash
# === FIND DEVICE ===
xcrun devicectl list devices                        # List connected iPhones
xcrun simctl list devices available                 # List simulators

# === INSTALL ON DEVICE ===
xcrun devicectl device install app --device <ID> Runner.ipa   # Install IPA on iPhone

# === INSTALL ON SIMULATOR ===
open -a Simulator                                   # Launch simulator
xcrun simctl install booted Runner.app              # Install app on booted simulator

# === CODEMAGIC CLI (optional) ===
# Install: brew install codemagic/tools/cli-tools
codemagic-cli build get <build-id>                  # Get build status
```

---

## 🔄 Codemagic Workflow Reference

Your `codemagic.yaml` workflows:

| Workflow | What it does | Output |
|----------|-------------|--------|
| `android-preview` | Builds Android debug APK | `app-debug.apk` |
| `ios-preview` | Builds iOS simulator `.app` | `Runner.app` |
| `ios-testflight` | Builds + uploads to TestFlight | IPA auto-uploaded |

### `ios-testflight` workflow (in `codemagic.yaml`):

```yaml
  ios-testflight:
    name: iOS TestFlight Release
    instance_type: mac_mini_m2
    max_build_duration: 60
    environment:
      flutter: stable
      xcode: latest
      cocoapods: default
      vars:
        SIRATI_API_BASE_URL: "https://sirati-main-shokc5.laravel.cloud/api"
    scripts:
      - name: Get Flutter packages
        script: flutter pub get
      - name: Install CocoaPods
        script: cd ios && pod install && cd ..
      - name: Analyze
        script: flutter analyze
      - name: Run tests
        script: flutter test --exclude-tags golden
      - name: Build iOS IPA
        script: |
          flutter build ipa --release \
            --dart-define=SIRATI_API_BASE_URL=$SIRATI_API_BASE_URL
    artifacts:
      - build/ios/ipa/*.ipa
    publishing:
      app_store_connect:
        api_key: $APP_STORE_CONNECT_API_KEY
        key_id: $APP_STORE_CONNECT_KEY_ID
        issuer_id: $APP_STORE_CONNECT_ISSUER_ID
```

---

## ⚠️ Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| App won't open on iPhone (untrusted developer) | Settings → General → VPN & Device Management → Trust |
| `xcrun devicectl` command not found | Install Xcode 15+ from Mac App Store |
| Device not showing in `devicectl list devices` | Unlock iPhone, trust the Mac, try a different USB cable |
| IPA won't install (provisioning profile mismatch) | Make sure the device UDID is in your Apple Developer account provisioning profile |
| Codemagic build fails on code signing | In Codemagic → App Settings → iOS Code Signing → **Automatic** |
| `ITMS-90078: Missing Push Notification Entitlement` | Enable Push Notifications capability in Xcode project (committed to repo) |
| Build stuck on "Processing" in App Store Connect | Wait up to 30 min; if longer, check email for Apple compliance issues |

---

## 🔗 Important Links

| Resource | URL |
|----------|-----|
| Codemagic Dashboard | [codemagic.io](https://codemagic.io) |
| App Store Connect | [appstoreconnect.apple.com](https://appstoreconnect.apple.com) |
| Apple Developer Portal | [developer.apple.com](https://developer.apple.com) |
| TestFlight Guide | [developer.apple.com/testflight](https://developer.apple.com/testflight) |
| GitHub Repo | [github.com/siratiats/Sirati-Mobile](https://github.com/siratiats/Sirati-Mobile) |

---

> **Next Step**: After TestFlight testing is stable, submit for **App Store Review** from App Store Connect to publish to the public App Store.
