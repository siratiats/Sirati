import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_locale.dart';

/// Eye button for a password field, labelled for screen readers in both
/// states.
class PasswordVisibilityToggle extends StatelessWidget {
  const PasswordVisibilityToggle({
    super.key,
    required this.obscured,
    required this.onToggle,
  });

  final bool obscured;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    return IconButton(
      tooltip: obscured
          ? (english ? 'Show password' : 'إظهار كلمة المرور')
          : (english ? 'Hide password' : 'إخفاء كلمة المرور'),
      icon: Icon(
        obscured ? Icons.visibility_outlined : Icons.visibility_off_outlined,
      ),
      onPressed: onToggle,
    );
  }
}
