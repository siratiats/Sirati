abstract final class AiStatus {
  static const notConfigured = 'not_configured';
  static const queued = 'queued';
  static const processing = 'processing';
  static const completed = 'completed';
  static const failed = 'failed';

  static bool isPending(String value) => value == queued || value == processing;
}
