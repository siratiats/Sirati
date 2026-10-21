import 'package:flutter/material.dart';

import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Subtle notice when list/dashboard content was served from disk (offline).
///
/// [surface] is one of `dashboard` | `cvs` | `news` (analytics only).
class OfflineCacheBanner extends StatefulWidget {
  final bool english;

  /// Analytics surface key — never free text.
  final String surface;

  const OfflineCacheBanner({
    super.key,
    required this.english,
    required this.surface,
  });

  @override
  State<OfflineCacheBanner> createState() => _OfflineCacheBannerState();
}

class _OfflineCacheBannerState extends State<OfflineCacheBanner> {
  @override
  void initState() {
    super.initState();
    AnalyticsService.logOfflineFallbackShown(surface: widget.surface);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Icon(Icons.cloud_off_rounded, size: 16, color: c.textHint),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              widget.english
                  ? 'Offline — showing saved data'
                  : 'غير متصل — عرض بيانات محفوظة',
              style: TextStyle(
                fontSize: 12,
                height: 1.3,
                fontWeight: FontWeight.w500,
                color: c.textHint,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
