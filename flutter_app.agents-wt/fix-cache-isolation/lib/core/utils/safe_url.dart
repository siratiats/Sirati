import 'package:url_launcher/url_launcher.dart';

/// Returns a launchable https URI, or null when [raw] is unsafe/malformed.
Uri? parseSafeExternalUrl(String? raw) {
  if (raw == null) return null;
  final trimmed = raw.trim();
  if (trimmed.isEmpty) return null;

  final uri = Uri.tryParse(trimmed);
  if (uri == null || !uri.hasScheme || !uri.hasAuthority) return null;
  final scheme = uri.scheme.toLowerCase();
  if (scheme == 'https') return uri;
  if (scheme == 'http' &&
      (uri.host == 'localhost' || uri.host == '127.0.0.1')) {
    return uri;
  }
  return null;
}

Future<bool> launchSafeExternalUrl(String? raw) async {
  final uri = parseSafeExternalUrl(raw);
  if (uri == null) return false;
  return launchUrl(uri, mode: LaunchMode.externalApplication);
}
