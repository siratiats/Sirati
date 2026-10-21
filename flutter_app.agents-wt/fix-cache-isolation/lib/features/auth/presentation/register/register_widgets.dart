import 'package:flutter/material.dart';
import 'package:sirati/features/auth/domain/registration_rules.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Localised message for a rejected registration field.
String fieldErrorText(
  FieldError error, {
  required bool english,
  required String requiredEn,
  required String requiredAr,
  int? max,
}) =>
    switch (error) {
      FieldError.required => english ? requiredEn : requiredAr,
      FieldError.tooLong => english
          ? 'Must be at most $max characters'
          : 'يجب ألا يتجاوز $max حرفاً',
      FieldError.tooShort => english
          ? 'Password must be at least ${RegistrationRules.passwordMin} characters'
          : 'يجب أن تكون كلمة المرور ${RegistrationRules.passwordMin} أحرف على الأقل',
      FieldError.invalidEmail =>
        english ? 'Email address is invalid' : 'البريد الإلكتروني غير صحيح',
      FieldError.invalidPhone =>
        english ? 'Enter a valid phone number' : 'أدخل رقم جوال صحيح',
    };

class FieldLabel extends StatelessWidget {
  const FieldLabel({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(start: 4, bottom: 6),
      child: Text(
        text,
        textAlign: TextAlign.start,
        style: AppTextStyles.labelMd(context.sirati).copyWith(
          fontWeight: FontWeight.w700,
          color: context.sirati.textSecondary,
        ),
      ),
    );
  }
}

class RegisterHero extends StatelessWidget {
  const RegisterHero({super.key, required this.english});

  final bool english;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SiratiMark(size: 52),
        const SizedBox(height: 10),
        Text(
          english ? 'Join Us Today' : 'انضم إلينا اليوم',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w800,
            color: context.sirati.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          english
              ? 'Start your career journey and build a professional CV'
              : 'ابدأ رحلتك المهنية وابنِ سيرة ذاتية احترافية',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 13,
            height: 1.6,
            color: context.sirati.textSecondary,
          ),
        ),
      ],
    );
  }
}

/// Checkbox row for accepting the terms; the whole row toggles.
class TermsAgreement extends StatelessWidget {
  const TermsAgreement({
    super.key,
    required this.english,
    required this.agreed,
    required this.onChanged,
  });

  final bool english;
  final bool agreed;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final colors = context.sirati;
    return PressScale(
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          onTap: () => onChanged(!agreed),
          borderRadius: BorderRadius.circular(10),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 48),
            child: Row(
              children: [
                Checkbox(
                  value: agreed,
                  activeColor: colors.primary,
                  checkColor: colors.onPrimary,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(5),
                  ),
                  side: BorderSide(color: colors.borderStrong, width: 1.4),
                  onChanged: (value) => onChanged(value ?? false),
                ),
                Expanded(
                  child: Text.rich(
                    TextSpan(
                      style: TextStyle(
                        fontSize: 12.5,
                        height: 1.55,
                        color: colors.textSecondary,
                      ),
                      children: [
                        TextSpan(text: english ? 'I agree to ' : 'أوافق على '),
                        TextSpan(
                          text: english
                              ? 'Terms & Conditions'
                              : 'الشروط والأحكام',
                          style: TextStyle(
                            color: colors.primaryInk,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// "Already have an account? Sign In" with a 48dp tap target on the link.
class SignInPrompt extends StatelessWidget {
  const SignInPrompt({super.key, required this.english, required this.onTap});

  final bool english;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.sirati;
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(
          english ? 'Already have an account?' : 'لديك حساب بالفعل؟',
          style: TextStyle(fontSize: 13, color: colors.textSecondary),
        ),
        TextButton(
          onPressed: onTap,
          style: TextButton.styleFrom(
            foregroundColor: colors.primaryInk,
            minimumSize: const Size(48, 48),
            padding: const EdgeInsets.symmetric(horizontal: 6),
            textStyle:
                const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
          ),
          child: Text(english ? 'Sign In' : 'تسجيل الدخول'),
        ),
      ],
    );
  }
}
