class AuthSession {
  final String token;
  final String tokenType;
  final AuthUser user;

  const AuthSession(
      {required this.token, required this.tokenType, required this.user});

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? json;

    return AuthSession(
      token: data['token']?.toString() ?? '',
      tokenType: data['token_type']?.toString() ?? 'Bearer',
      user:
          AuthUser.fromJson(data['user'] as Map<String, dynamic>? ?? const {}),
    );
  }
}

class AuthUser {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? location;
  final int? jobTitleId;
  final String? jobTitleOther;
  final Map<String, dynamic>? jobTitle;
  final bool emailVerified;

  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.location,
    this.jobTitleId,
    this.jobTitleOther,
    this.jobTitle,
    this.emailVerified = false,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final rawTitle = json['job_title'];
    return AuthUser(
      id: _asInt(json['id']),
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phone: json['phone']?.toString(),
      location: json['location']?.toString(),
      jobTitleId: _asNullableInt(json['job_title_id']),
      jobTitleOther: _nullable(json['job_title_other']),
      jobTitle: rawTitle is Map ? Map<String, dynamic>.from(rawTitle) : null,
      emailVerified:
          _asBool(json['email_verified'] ?? json['email_verified_at']),
    );
  }

  AuthUser copyWith({
    String? name,
    String? phone,
    String? location,
    int? jobTitleId,
    String? jobTitleOther,
    Map<String, dynamic>? jobTitle,
    bool? emailVerified,
  }) {
    return AuthUser(
      id: id,
      name: name ?? this.name,
      email: email,
      phone: phone ?? this.phone,
      location: location ?? this.location,
      jobTitleId: jobTitleId ?? this.jobTitleId,
      jobTitleOther: jobTitleOther ?? this.jobTitleOther,
      jobTitle: jobTitle ?? this.jobTitle,
      emailVerified: emailVerified ?? this.emailVerified,
    );
  }

  String? displayJobTitle({required bool english}) {
    if (jobTitleOther != null && jobTitleOther!.trim().isNotEmpty) {
      return jobTitleOther!.trim();
    }
    if (jobTitle != null) {
      final ar = jobTitle!['name_ar']?.toString().trim();
      final en = jobTitle!['name_en']?.toString().trim();
      if (english && en != null && en.isNotEmpty) return en;
      if (ar != null && ar.isNotEmpty) return ar;
      if (en != null && en.isNotEmpty) return en;
    }
    return null;
  }
}

int _asInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

int? _asNullableInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse(value.toString());
}

String? _nullable(dynamic value) {
  final str = value?.toString();
  if (str == null || str.isEmpty) return null;
  return str;
}

bool _asBool(dynamic value) {
  if (value is bool) return value;
  if (value is String) {
    final lower = value.toLowerCase();
    if (lower == 'true' || lower == '1') return true;
    // Non-empty ISO datetime from email_verified_at means verified.
    if (value.isNotEmpty && lower != 'false' && lower != 'null') return true;
  }
  if (value is num) return value != 0;
  return false;
}
