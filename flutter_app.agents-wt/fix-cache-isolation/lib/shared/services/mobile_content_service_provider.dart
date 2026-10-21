import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/shared/services/mobile_content_service.dart';

/// Single injection point for [MobileContentService]; override in tests.
final mobileContentServiceProvider =
    Provider<MobileContentService>((ref) => MobileContentService());
