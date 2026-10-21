import 'dart:async';

import 'package:flutter/material.dart';

import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Semi-opaque veil over a text field while an AI operation runs.
///
/// When [isLoading] is false, returns [child] unchanged.
/// While loading, cycles [statusMessages] (or [statusStream]) with a smooth
/// fade every [messageInterval] so the user sees an "Active Co-Pilot" status
/// instead of a stagnant skeleton.
///
/// Rebuild scope is limited to the status line; [child] is not rebuilt by
/// the timer (held in a [RepaintBoundary] + stable [child] slot).
class AiFieldLoadingOverlay extends StatelessWidget {
  final bool isLoading;
  final Widget child;
  final String? semanticsLabel;

  /// Sequential micro-copy. Ignored when [statusStream] is non-null.
  final List<String>? statusMessages;

  /// Live status source. Takes precedence over [statusMessages].
  final Stream<String>? statusStream;

  /// Time between sequential messages (list mode). Default 2.5s.
  final Duration messageInterval;

  const AiFieldLoadingOverlay({
    super.key,
    required this.isLoading,
    required this.child,
    this.semanticsLabel,
    this.statusMessages,
    this.statusStream,
    this.messageInterval = const Duration(milliseconds: 2500),
  });

  /// Default English / Arabic micro-copy for CV AI work.
  static List<String> defaultStatusMessages({required bool english}) {
    if (english) {
      return const [
        'Parsing formatting structure…',
        'Extracting technical keywords…',
        'Benchmarking against market trends…',
        'Scoring ATS compatibility…',
      ];
    }
    return const [
      'جارٍ تحليل بنية التنسيق…',
      'جارٍ استخراج الكلمات المفتاحية التقنية…',
      'جارٍ المقارنة مع اتجاهات السوق…',
      'جارٍ تقييم التوافق مع أنظمة ATS…',
    ];
  }

  @override
  Widget build(BuildContext context) {
    if (!isLoading) return child;

    final english = Localizations.localeOf(context).languageCode != 'ar';
    final messages = statusMessages ??
        (statusStream == null
            ? defaultStatusMessages(english: english)
            : const <String>[]);
    final label = semanticsLabel ??
        (english
            ? 'AI is working on this field'
            : 'الذكاء الاصطناعي يعمل على هذا الحقل');

    return Semantics(
      container: true,
      liveRegion: true,
      label: label,
      child: Stack(
        children: [
          // Stable child: not rebuilt when status text changes.
          IgnorePointer(
            child: RepaintBoundary(child: child),
          ),
          Positioned.fill(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppFormStyles.radius),
              child: _AiCoPilotVeil(
                messages: messages,
                statusStream: statusStream,
                messageInterval: messageInterval,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Overlay veil + status line. Isolates setState to this subtree only.
class _AiCoPilotVeil extends StatefulWidget {
  final List<String> messages;
  final Stream<String>? statusStream;
  final Duration messageInterval;

  const _AiCoPilotVeil({
    required this.messages,
    required this.statusStream,
    required this.messageInterval,
  });

  @override
  State<_AiCoPilotVeil> createState() => _AiCoPilotVeilState();
}

class _AiCoPilotVeilState extends State<_AiCoPilotVeil> {
  int _index = 0;
  String _current = '';
  Timer? _timer;
  StreamSubscription<String>? _sub;

  @override
  void initState() {
    super.initState();
    _bind();
  }

  @override
  void didUpdateWidget(covariant _AiCoPilotVeil oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.statusStream != widget.statusStream ||
        oldWidget.messages != widget.messages ||
        oldWidget.messageInterval != widget.messageInterval) {
      _unbind();
      _index = 0;
      _bind();
    }
  }

  @override
  void dispose() {
    _unbind();
    super.dispose();
  }

  void _unbind() {
    _timer?.cancel();
    _timer = null;
    _sub?.cancel();
    _sub = null;
  }

  void _bind() {
    final stream = widget.statusStream;
    if (stream != null) {
      _current = '';
      _sub = stream.listen((msg) {
        if (!mounted || msg == _current) return;
        setState(() => _current = msg);
      });
      return;
    }

    final list = widget.messages;
    if (list.isEmpty) {
      _current = '';
      return;
    }
    _current = list.first;
    if (list.length == 1) return;

    _timer = Timer.periodic(widget.messageInterval, (_) {
      if (!mounted) return;
      setState(() {
        _index = (_index + 1) % list.length;
        _current = list[_index];
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final reduce = MotionSettings.reduce(context);

    return ColoredBox(
      color: c.surface.withValues(alpha: .82),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.2,
                  color: c.primary,
                ),
              ),
              if (_current.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sm - 2),
                // Only this node animates on message change.
                AnimatedSwitcher(
                  duration: reduce ? Duration.zero : MotionDurations.slow,
                  switchInCurve: MotionCurves.enter,
                  switchOutCurve: MotionCurves.exit,
                  layoutBuilder: (current, previous) => Stack(
                    alignment: Alignment.center,
                    children: [
                      ...previous,
                      if (current != null) current,
                    ],
                  ),
                  transitionBuilder: (child, animation) {
                    if (reduce) return child;
                    return FadeTransition(
                      opacity: animation,
                      child: SlideTransition(
                        position: Tween<Offset>(
                          begin: const Offset(0, 0.08),
                          end: Offset.zero,
                        ).animate(animation),
                        child: child,
                      ),
                    );
                  },
                  child: Text(
                    _current,
                    key: ValueKey<String>(_current),
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 12.5,
                      height: 1.4,
                      fontWeight: FontWeight.w600,
                      color: c.textSecondary,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
