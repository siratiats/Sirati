import 'package:flutter/material.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/empty_state.dart';

/// Shown when a deep link targets an entitlement-gated route without premium.
class PremiumGateScreen extends StatelessWidget {
  const PremiumGateScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    return Scaffold(
      backgroundColor: context.sirati.background,
      appBar: AppBar(title: Text(english ? 'Premium' : 'المميز')),
      body: AppEmptyState(
        icon: Icons.lock_outline_rounded,
        title: english ? 'Premium required' : 'يتطلب الاشتراك المميز',
        subtitle: english
            ? 'This screen will unlock after a premium plan is active.'
            : 'ستُفتح هذه الشاشة بعد تفعيل الاشتراك المميز.',
        actionLabel: english ? 'Go home' : 'العودة للرئيسية',
        onAction: () {
          Navigator.of(context).pushNamedAndRemoveUntil(
            AppRoutes.home,
            (route) => false,
          );
        },
      ),
    );
  }
}
