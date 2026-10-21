/// One row of the in-app notification inbox.
class NotificationItem {
  const NotificationItem({
    required this.id,
    required this.title,
    required this.body,
    required this.isRead,
    required this.createdAt,
    required this.createdLabel,
    required this.actionType,
    required this.actionUrl,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    final raw = json['created_at'] ?? json['createdAt'];
    final id = json['id'];
    return NotificationItem(
      id: id is int ? id : int.tryParse(id?.toString() ?? ''),
      title: _text(json['title']),
      body: _text(json['body']),
      isRead: json['is_read'] == true,
      createdAt: raw == null ? null : DateTime.tryParse(raw.toString()),
      createdLabel: _text(json['created_label']),
      actionType: _text(json['action_type']),
      actionUrl: _text(json['action_url']),
    );
  }

  final int? id;
  final String title;
  final String body;
  final bool isRead;
  final DateTime? createdAt;
  final String createdLabel;
  final String actionType;
  final String actionUrl;

  /// Whether tapping should navigate: only `url` and `screen` actions lead
  /// somewhere. Anything else would reopen the inbox the user is already in.
  bool get opensSomething =>
      (actionType == 'url' || actionType == 'screen') && actionUrl.isNotEmpty;

  /// Payload in the push-notification shape, so taps reuse the push router.
  Map<String, dynamic> get routePayload => {
        'action_type': actionType,
        'action_url': actionUrl,
      };

  NotificationItem asRead() => NotificationItem(
        id: id,
        title: title,
        body: body,
        isRead: true,
        createdAt: createdAt,
        createdLabel: createdLabel,
        actionType: actionType,
        actionUrl: actionUrl,
      );

  /// Rows from an inbox payload; non-object rows are dropped.
  static List<NotificationItem> listFrom(Map<String, dynamic> payload) {
    final raw = payload['items'];
    if (raw is! List) return const [];
    return [
      for (final item in raw)
        if (item is Map<String, dynamic>) NotificationItem.fromJson(item),
    ];
  }
}

String _text(Object? value) => value?.toString().trim() ?? '';
