import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthTokenStore {
  const AuthTokenStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'sirati_auth_token';
  final FlutterSecureStorage _storage;

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);
}

/// In-memory token store for unit tests.
class MemoryAuthTokenStore extends AuthTokenStore {
  MemoryAuthTokenStore([this.value]);

  String? value;

  @override
  Future<String?> readToken() async => value;

  @override
  Future<void> saveToken(String token) async {
    value = token;
  }

  @override
  Future<void> clearToken() async {
    value = null;
  }
}
