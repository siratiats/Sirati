import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/shared/services/auth_api_service.dart';

/// Single injection point for [AuthApiService]; tests override it.
final authApiServiceProvider =
    Provider<AuthApiService>((ref) => AuthApiService());
