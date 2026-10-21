import 'package:flutter/material.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/motion.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Signed-out landing: brand, value proposition, register / sign-in.
class WelcomeBody extends StatelessWidget {
  final VoidCallback onRegister;
  final VoidCallback onLogin;
  final VoidCallback onPrivacy;

  const WelcomeBody({
    super.key,
    required this.onRegister,
    required this.onLogin,
    required this.onPrivacy,
  });

  @override
  Widget build(BuildContext context) {
    final en = AppLocale.isEnglish(context);
    final cardTitle =
        en ? 'Build your CV professionally' : 'اصنع سيرتك الذاتية باحترافية';
    final cardBody = en
        ? 'Create an ATS-ready CV that reaches employers with world-class design, in minutes.'
        : 'أنشئ سيرة ذاتية متوافقة مع أنظمة ATS وتصل لأصحاب العمل خلال دقائق.';

    return SafeArea(
      child: LayoutBuilder(
        builder: (context, constraints) {
          return SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.xl + 4,
                  AppSpacing.lg - 2,
                  AppSpacing.xl + 4,
                  AppSpacing.xl,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const MotionReveal(
                      order: 0,
                      child: Align(
                        alignment: AlignmentDirectional.topStart,
                        child: LanguageToggle(),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xxl + 4),
                    const MotionReveal(
                      order: 1,
                      offset: Offset(0, 0.04),
                      duration: MotionDurations.slow,
                      child: Center(child: _SplashLogo()),
                    ),
                    const SizedBox(height: AppSpacing.lg - 2),
                    MotionReveal(
                      order: 2,
                      child: Text(
                        en ? 'Sirati' : 'سيرتي',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w800,
                          color: context.sirati.primaryInk,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm - 2),
                    MotionReveal(
                      order: 2,
                      child: Text(
                        en
                            ? 'Your first step towards a better professional future'
                            : 'خطوتك الأولى نحو مستقبل مهني أفضل',
                        textAlign: TextAlign.center,
                        style: AppTextStyles.bodyMd(context.sirati).copyWith(
                          height: 1.7,
                          color: context.sirati.textSecondary,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl + 4),
                    MotionReveal(
                      order: 3,
                      child: _ValueCard(
                        title: cardTitle,
                        body: cardBody,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl + 4),
                    MotionReveal(
                      order: 4,
                      child: SubmitButton(
                        label: en ? 'Create New Account' : 'إنشاء حساب جديد',
                        icon: Icons.arrow_forward,
                        onPressed: onRegister,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    MotionReveal(
                      order: 4,
                      child: SubmitButton(
                        label: en ? 'Sign In' : 'تسجيل الدخول',
                        outlined: true,
                        onPressed: onLogin,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg - 2),
                    MotionReveal(
                      order: 5,
                      child: TextButton(
                        onPressed: onPrivacy,
                        child: Text(
                          en ? 'Privacy Policy' : 'سياسة الخصوصية',
                          style: AppTextStyles.labelMd(context.sirati).copyWith(
                            fontWeight: FontWeight.w700,
                            color: context.sirati.textHint,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Elevated hero card — surface + border + soft shadow so it reads as content.
class _ValueCard extends StatelessWidget {
  final String title;
  final String body;

  const _ValueCard({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.xl - 2),
      decoration: BoxDecoration(
        color: context.sirati.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.sirati.border),
        boxShadow: context.sirati.softShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            textAlign: TextAlign.start,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: context.sirati.textPrimary,
              height: 1.3,
            ),
          ),
          const SizedBox(height: AppSpacing.sm - 2),
          Text(
            body,
            textAlign: TextAlign.start,
            style: AppTextStyles.bodySm(context.sirati).copyWith(
              height: 1.75,
              color: context.sirati.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}

class _SplashLogo extends StatelessWidget {
  const _SplashLogo();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 112,
      height: 112,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: context.sirati.surface,
        border: Border.all(color: context.sirati.primaryLight, width: 2),
        boxShadow: [
          BoxShadow(
            color: context.sirati.primary.withValues(alpha: 0.12),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: const Center(child: SiratiMark(size: 64, elevated: true)),
    );
  }
}
