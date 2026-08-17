# Smart Daily Notifications — Implementation

Status: **MVP implemented** (2026-07-17). Feature flag default **off**.

## Enable in production

```env
SMART_NOTIFICATIONS_ENABLED=true
```

Also ensure:

```bash
php artisan migrate --force
php artisan schedule:work   # or system cron: * * * * * php artisan schedule:run
php artisan queue:work
```

## What shipped

### Backend
| Piece | Path |
|-------|------|
| Preferences table | `database/migrations/2026_07_16_100000_create_notification_preferences_table.php` |
| Decisions table | `database/migrations/2026_07_16_100001_create_notification_decisions_table.php` |
| Config | `config/smart_notifications.php` |
| Candidate rules (priority 1–7) | `app/Services/Notifications/DailyNotificationCandidateService.php` |
| Policy (opt-out, quiet hours, caps, cooldowns, idempotency) | `app/Services/Notifications/NotificationPolicyService.php` |
| AR/EN templates | `app/Services/Notifications/NotificationTemplateService.php` |
| Planner | `app/Services/Notifications/DailyNotificationPlanner.php` |
| Command | `php artisan notifications:plan-daily` (every 15 min) |
| Send job (tries=1, no duplicate rows) | `app/Jobs/SendPlannedNotificationJob.php` |
| APIs | preferences GET/PUT, activity POST, conversion POST, opened POST |
| FCM register metadata | language, timezone, app_version, notifications_enabled |

### Rules (first match wins)
1. Never analyzed → CV analysis  
2. Latest ATS &lt; 70 → analysis result tip  
3. Analysis without later CV → create CV  
4. Generated CV ≥ 7 days old → My CVs  
5. Matching job news → Job News  
6. Relevant education → Education  
7. Fallback tip → Home  

### Flutter
| Piece | Path |
|-------|------|
| Engagement client | `lib/services/notification_engagement_service.dart` |
| Token metadata + open tracking | `lib/services/notification_service.dart` |
| Settings server sync + opt_out | `lib/screens/settings_screen.dart` |
| Deep links (cv-analysis, analysis/{id}, create-cv, my-cvs, education/{id}, job-news, home, settings) | `lib/screens/home_screen.dart` |
| Conversion events | analysis completed, CV generated |

## Tests

```bash
php artisan test --filter=SmartNotifications
# 7 passed
```

## Safeguards
- Max 1 automated send / 24h (`minimum_gap_hours`)
- Max 4 / week (per preference)
- Skip if active in last 3 hours
- Quiet hours + preferred-time delivery window
- Idempotency key `daily:{user}:{rule}:{local-date}`
- Settings opt-out persists server-side (not only local secure storage)
- Logout deactivates token **without** permanent opt-out
