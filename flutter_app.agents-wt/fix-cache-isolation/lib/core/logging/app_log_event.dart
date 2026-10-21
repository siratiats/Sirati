/// Structured domain event tokens for [AppLog].
///
/// Prevents free-text CV bodies from being passed as log messages.
/// Dynamic context must always be provided via the key-filtered `data:` map.
enum AppLogEvent {
  // App lifecycle & navigation
  appStartup('app_startup'),
  appCrash('app_crash'),
  navigation('navigation'),

  // Auth
  authLoginSuccess('auth_login_success'),
  authLoginFailed('auth_login_failed'),
  authLogout('auth_logout'),
  authSessionExpired('auth_session_expired'),

  // CV Builder & Management
  cvCreated('cv_created'),
  cvUpdated('cv_updated'),
  cvAutosaved('cv_autosaved'),
  cvAutosaveFailed('cv_autosave_failed'),
  cvDraftRecovered('cv_draft_recovered'),
  cvDraftBackupFailed('cv_draft_backup_failed'),
  cvLoaded('cv_loaded'),
  cvLoadFailed('cv_load_failed'),
  cvParsed('cv_parsed'),
  cvParseFailed('cv_parse_failed'),
  cvDeleted('cv_deleted'),
  cvDeleteFailed('cv_delete_failed'),
  cvMigrated('cv_migrated'),
  cvMigrationFailed('cv_migration_failed'),

  // CV Export & Templates
  cvExportRequested('cv_export_requested'),
  cvExportCompleted('cv_export_completed'),
  cvExportFailed('cv_export_failed'),
  templateSelected('template_selected'),

  // ATS & AI
  atsScanStarted('ats_scan_started'),
  atsScanCompleted('ats_scan_completed'),
  atsScanFailed('ats_scan_failed'),
  aiGenerationStarted('ai_generation_started'),
  aiGenerationCompleted('ai_generation_completed'),
  aiGenerationFailed('ai_generation_failed'),

  // Network & System
  networkRequestFailed('network_request_failed'),
  platformError('platform_error'),
  uncaughtWidgetError('uncaught_widget_error'),
  diagnostic('diagnostic');

  final String eventId;
  const AppLogEvent(this.eventId);

  @override
  String toString() => eventId;
}
