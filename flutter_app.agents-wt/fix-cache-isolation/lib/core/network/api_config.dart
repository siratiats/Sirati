class ApiConfig {
  static const baseUrl = String.fromEnvironment(
    'SIRATI_API_BASE_URL',
    defaultValue: 'https://siratie.com/api',
  );

  /// Bump with each store release (no package_info_plus dependency).
  static const appVersion = String.fromEnvironment(
    'SIRATI_APP_VERSION',
    defaultValue: '1.0.0',
  );

  /// Official support email, used across store listing, privacy policy, and Contact Us.
  static const supportEmail = String.fromEnvironment(
    'SIRATI_SUPPORT_EMAIL',
    defaultValue: 'support@siratie.com',
  );

  static Uri uri(String path) {
    final normalizedPath = path.startsWith('/') ? path.substring(1) : path;
    return Uri.parse(
        '${baseUrl.replaceAll(RegExp(r'/$'), '')}/$normalizedPath');
  }
}
