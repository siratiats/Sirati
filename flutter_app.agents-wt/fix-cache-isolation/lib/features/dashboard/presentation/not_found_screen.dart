import 'package:flutter/material.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/empty_state.dart';

class NotFoundScreen extends StatelessWidget {
  const NotFoundScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    return Scaffold(
      backgroundColor: context.sirati.background,
      appBar: AppBar(title: Text(english ? 'Sirati' : 'سيرتي')),
      body: AppEmptyState(
        icon: Icons.map_outlined,
        title: english ? 'Page not found' : 'الصفحة غير موجودة',
        subtitle: english
            ? 'This link is not valid. You can go back home.'
            : 'هذا الرابط غير صالح. يمكنك العودة للرئيسية.',
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
