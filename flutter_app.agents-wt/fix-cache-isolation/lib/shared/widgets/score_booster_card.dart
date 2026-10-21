import 'package:flutter/material.dart';

import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/animated_ats_score_bar.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Context-driven tip shown on [ScoreBoosterCard].
@immutable
class ScoreBoosterTip {
  /// Full sentence, e.g. "Adding your field of study will boost… +15 points!"
  final String message;

  /// Points advertised in the tip (for badge chrome).
  final int boostPoints;

  final IconData icon;

  const ScoreBoosterTip({
    required this.message,
    this.boostPoints = 0,
    this.icon = Icons.auto_awesome_rounded,
  });
}

/// Lean tracker for form-section completion → progress 0–1 + active tip.
///
/// Screens register weighted fields and call [setFieldComplete] when a field
/// becomes valid (e.g. via [AppTextFormField.onBecameValid]). Only notifies
/// when progress or tip actually changes.
class ScoreBoosterController extends ChangeNotifier {
  ScoreBoosterController({
    Map<String, int>? fieldWeights,
    this.tipForIncomplete,
  }) : _weights = Map<String, int>.from(fieldWeights ?? const {}) {
    for (final id in _weights.keys) {
      _complete[id] = false;
    }
  }

  final Map<String, int> _weights;
  final Map<String, bool> _complete = {};

  /// Builds a tip from the set of incomplete field ids (ordered by priority).
  final ScoreBoosterTip? Function(List<String> incompleteIds)? tipForIncomplete;

  double _progress = 0;
  ScoreBoosterTip? _tip;

  /// Last field id we already logged for [score_booster_tip_shown].
  String? _lastLoggedTipFieldId;
  bool _completedLogged = false;

  double get progress => _progress;
  ScoreBoosterTip? get tip => _tip;
  int get totalWeight => _weights.values.fold<int>(0, (sum, w) => sum + w);
  int get completedWeight {
    var sum = 0;
    _weights.forEach((id, w) {
      if (_complete[id] == true) sum += w;
    });
    return sum;
  }

  /// Register or update a field weight (idempotent).
  void registerField(String id, {int weight = 10}) {
    if (_weights[id] == weight && _complete.containsKey(id)) return;
    _weights[id] = weight;
    _complete.putIfAbsent(id, () => false);
    _recompute();
  }

  /// Mark a field complete/incomplete. No-op if unchanged.
  void setFieldComplete(String id, bool complete) {
    if (!_weights.containsKey(id)) {
      registerField(id);
    }
    if (_complete[id] == complete) return;
    _complete[id] = complete;
    _recompute();
  }

  /// Replace completion map in one shot (e.g. after loading profile).
  void setAll(Map<String, bool> completed) {
    var changed = false;
    completed.forEach((id, value) {
      if (!_weights.containsKey(id)) {
        _weights[id] = 10;
        changed = true;
      }
      if (_complete[id] != value) {
        _complete[id] = value;
        changed = true;
      }
    });
    if (changed) _recompute();
  }

  /// Force tip refresh without changing completion (e.g. language toggle).
  void refreshTip() => _recompute(forceNotify: true);

  void _recompute({bool forceNotify = false}) {
    final total = totalWeight;
    final nextProgress = total == 0 ? 0.0 : completedWeight / total;

    final incomplete = _weights.keys
        .where((id) => _complete[id] != true)
        .toList(growable: false);
    final nextTip = tipForIncomplete?.call(incomplete);

    final progressChanged = (nextProgress - _progress).abs() > 0.001;
    final tipChanged = nextTip?.message != _tip?.message ||
        nextTip?.boostPoints != _tip?.boostPoints;

    if (!forceNotify && !progressChanged && !tipChanged) return;

    _progress = nextProgress.clamp(0.0, 1.0);
    _tip = nextTip;

    // Analytics: field id only (never tip free text). Deduped per field.
    final tipFieldId = incomplete.isNotEmpty ? incomplete.first : null;
    if (tipFieldId != null &&
        tipFieldId != _lastLoggedTipFieldId &&
        nextProgress < 1.0) {
      _lastLoggedTipFieldId = tipFieldId;
      AnalyticsService.logScoreBoosterTipShown(fieldId: tipFieldId);
    }
    if (nextProgress >= 1.0 && !_completedLogged) {
      _completedLogged = true;
      AnalyticsService.logScoreBoosterCompleted();
    } else if (nextProgress < 1.0) {
      _completedLogged = false;
    }

    notifyListeners();
  }
}

/// Sticky bottom micro-card: readiness % + contextual tip.
///
/// Anchor with [ScoreBoosterScaffold] or place in a [Stack] / column footer.
class ScoreBoosterCard extends StatelessWidget {
  final ScoreBoosterController controller;
  final EdgeInsetsGeometry margin;
  final bool showWhenComplete;

  const ScoreBoosterCard({
    super.key,
    required this.controller,
    this.margin = const EdgeInsets.fromLTRB(16, 0, 16, 12),
    this.showWhenComplete = true,
  });

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) {
        final progress = controller.progress;
        final tip = controller.tip;
        if (!showWhenComplete && progress >= 1.0) {
          return const SizedBox.shrink();
        }

        final c = context.sirati;
        final english = Localizations.localeOf(context).languageCode != 'ar';
        final pct = (progress * 100).round();

        return Padding(
          padding: margin,
          child: Material(
            color: c.surface,
            elevation: 0,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: c.border),
                boxShadow: c.softShadow,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 32,
                        height: 32,
                        decoration: BoxDecoration(
                          color: c.primaryLight,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Icon(
                          tip?.icon ?? Icons.trending_up_rounded,
                          size: 18,
                          color: c.primary,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          english ? 'Career readiness' : 'جاهزية المسار المهني',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: c.textPrimary,
                          ),
                        ),
                      ),
                      Text(
                        '$pct%',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: c.primary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  // Smooth fill when completion changes (controller notify).
                  AnimatedAtsScoreBar(
                    key: ValueKey('booster-$pct'),
                    value: progress,
                    color: c.primary,
                    height: 6,
                    borderRadius: 999,
                    semanticLabel: english
                        ? 'Career readiness $pct percent'
                        : 'جاهزية المسار $pct بالمئة',
                  ),
                  if (tip != null) ...[
                    const SizedBox(height: 10),
                    AnimatedSwitcher(
                      duration: MotionSettings.reduce(context)
                          ? Duration.zero
                          : MotionDurations.medium,
                      switchInCurve: MotionCurves.enter,
                      switchOutCurve: MotionCurves.exit,
                      child: Row(
                        key: ValueKey(tip.message),
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (tip.boostPoints > 0) ...[
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: c.amberLight,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                '+${tip.boostPoints}',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: c.amber,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                          ],
                          Expanded(
                            child: Text(
                              tip.message,
                              textAlign: TextAlign.start,
                              style: TextStyle(
                                fontSize: 12.5,
                                height: 1.4,
                                fontWeight: FontWeight.w600,
                                color: c.textSecondary,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Wraps [body] with a sticky bottom [ScoreBoosterCard] above the keyboard / nav.
class ScoreBoosterScaffold extends StatelessWidget {
  final Widget body;
  final ScoreBoosterController controller;
  final double bottomInset;

  const ScoreBoosterScaffold({
    super.key,
    required this.body,
    required this.controller,
    this.bottomInset = 0,
  });

  @override
  Widget build(BuildContext context) {
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;
    return Stack(
      children: [
        // Extra bottom padding so list content clears the sticky card.
        Positioned.fill(
          child: MediaQuery(
            data: MediaQuery.of(context).copyWith(
              padding: MediaQuery.paddingOf(context).copyWith(
                bottom:
                    MediaQuery.paddingOf(context).bottom + 108 + bottomInset,
              ),
            ),
            child: body,
          ),
        ),
        Positioned(
          left: 0,
          right: 0,
          bottom: keyboard > 0 ? keyboard : bottomInset,
          child: SafeArea(
            top: false,
            child: ScoreBoosterCard(controller: controller),
          ),
        ),
      ],
    );
  }
}
