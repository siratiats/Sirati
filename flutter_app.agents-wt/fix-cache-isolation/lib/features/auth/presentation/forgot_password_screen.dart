import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/latin_digits.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/auth_form_constraint.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/password_strength_meter.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Two-step forgot password: request OTP → enter code + new password.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _confirmKey = GlobalKey<FormFieldState<String>>();

  bool _isLoading = false;
  bool _isResending = false;
  bool _submitted = false;
  bool _codeSent = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  int _resendCooldown = 0;
  Timer? _cooldownTimer;

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    _emailController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  void _startCooldown(int seconds) {
    _cooldownTimer?.cancel();
    setState(() => _resendCooldown = seconds);
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_resendCooldown <= 1) {
        timer.cancel();
        setState(() => _resendCooldown = 0);
      } else {
        setState(() => _resendCooldown -= 1);
      }
    });
  }

  Future<void> _sendCode({bool isResend = false}) async {
    // One request at a time: keyboard Done, the button, Resend and the
    // snackbar Retry can all fire while one is running.
    if (_isLoading || _isResending) return;
    final english = AppLocale.isEnglish(context);
    setState(() {
      _submitted = true;
    });

    if (!_codeSent) {
      if (!_formKey.currentState!.validate()) {
        unawaited(HapticFeedback.selectionClick());
        return;
      }
    } else if (_emailController.text.trim().isEmpty ||
        !_emailController.text.contains('@')) {
      AppSnackBar.error(
        context,
        english
            ? 'Please enter a valid email'
            : 'أدخل بريداً إلكترونياً صحيحاً',
      );
      return;
    }

    setState(() {
      if (isResend) {
        _isResending = true;
      } else {
        _isLoading = true;
      }
    });

    try {
      final message = await ref.read(authApiServiceProvider).forgotPassword(
            email: _emailController.text.trim(),
          );
      if (!mounted) return;
      setState(() {
        _codeSent = true;
        _submitted = false;
      });
      _startCooldown(60);
      unawaited(HapticFeedback.lightImpact());
      AppSnackBar.success(context, message);
    } on ApiException catch (exception) {
      if (mounted) {
        AppSnackBar.fromException(
          context,
          exception,
          retryLabel: english ? 'Retry' : 'إعادة',
          onRetry: () => _sendCode(isResend: isResend),
        );
      }
    } catch (_) {
      if (mounted) {
        AppSnackBar.error(
          context,
          english
              ? 'Could not send the code. Try again later.'
              : 'تعذر إرسال الرمز. حاول لاحقاً.',
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isResending = false;
        });
      }
    }
  }

  Future<void> _resetPassword() async {
    if (_isLoading) return;
    final english = AppLocale.isEnglish(context);
    setState(() => _submitted = true);
    if (!_formKey.currentState!.validate()) {
      unawaited(HapticFeedback.selectionClick());
      return;
    }

    setState(() => _isLoading = true);
    try {
      final message = await ref.read(authApiServiceProvider).resetPassword(
            email: _emailController.text.trim(),
            code: _codeController.text.trim(),
            password: _passwordController.text,
            passwordConfirmation: _confirmController.text,
          );
      if (!mounted) return;
      unawaited(HapticFeedback.lightImpact());
      AppSnackBar.success(context, message);
      Navigator.of(context).pop();
    } on ApiException catch (exception) {
      if (mounted) {
        AppSnackBar.fromException(
          context,
          exception,
          retryLabel: english ? 'Retry' : 'إعادة',
          onRetry: _resetPassword,
        );
      }
    } catch (_) {
      if (mounted) {
        AppSnackBar.error(
          context,
          english
              ? 'Could not reset the password. Try again.'
              : 'تعذر تعيين كلمة المرور. حاول مرة أخرى.',
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(english ? 'Reset Password' : 'استعادة كلمة المرور'),
        actions: const [
          Padding(
            padding: EdgeInsetsDirectional.only(end: 12),
            child: LanguageToggle(),
          ),
        ],
      ),
      body: AuthFormConstraint(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 28, 20, 32),
            children: [
              Text(
                _codeSent
                    ? (english
                        ? 'Enter the 6-digit code we sent and choose a new password.'
                        : 'أدخل الرمز المكون من 6 أرقام الذي أرسلناه واختر كلمة مرور جديدة.')
                    : (english
                        ? 'Enter your email and we will send a 6-digit reset code.'
                        : 'أدخل بريدك الإلكتروني وسنرسل لك رمزاً من 6 أرقام لاستعادة كلمة المرور.'),
                textAlign: TextAlign.start,
                style: AppTextStyles.titleMd(context.sirati)
                    .copyWith(fontSize: 17, height: 1.55),
              ),
              const SizedBox(height: AppSpacing.xl),
              Text(
                english ? 'Email Address' : 'البريد الإلكتروني',
                textAlign: TextAlign.start,
                style: AppTextStyles.labelMd(context.sirati)
                    .copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              AppTextFormField(
                controller: _emailController,
                enabled: !_codeSent,
                autovalidateMode: _submitted
                    ? AutovalidateMode.onUserInteraction
                    : AutovalidateMode.disabled,
                keyboardType: TextInputType.emailAddress,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.start,
                textInputAction:
                    _codeSent ? TextInputAction.next : TextInputAction.done,
                onFieldSubmitted: (_) {
                  if (!_codeSent) unawaited(_sendCode());
                },
                hintText: 'name@example.com',
                prefixIcon: const Icon(Icons.email_outlined),
                validator: (value) {
                  if (_codeSent) return null;
                  final email = value?.trim() ?? '';
                  if (email.isEmpty) {
                    return english
                        ? 'Please enter your email'
                        : 'يرجى إدخال البريد الإلكتروني';
                  }
                  if (!email.contains('@')) {
                    return english
                        ? 'Enter a valid email address'
                        : 'أدخل بريداً إلكترونياً صحيحاً';
                  }
                  return null;
                },
              ),
              if (!_codeSent) ...[
                const SizedBox(height: AppSpacing.md),
                SubmitButton(
                  label: english ? 'Send code' : 'إرسال الرمز',
                  loadingLabel: english ? 'Sending...' : 'جارٍ الإرسال...',
                  isLoading: _isLoading,
                  icon: Icons.send_outlined,
                  onPressed: _sendCode,
                ),
              ] else ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  english ? 'Verification code' : 'رمز التحقق',
                  textAlign: TextAlign.start,
                  style: AppTextStyles.labelMd(context.sirati)
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                AppTextFormField(
                  controller: _codeController,
                  autovalidateMode: _submitted
                      ? AutovalidateMode.onUserInteraction
                      : AutovalidateMode.disabled,
                  keyboardType: TextInputType.number,
                  textDirection: TextDirection.ltr,
                  textAlign: TextAlign.center,
                  textInputAction: TextInputAction.next,
                  maxLength: 6,
                  hintText: '••••••',
                  autofillHints: const [AutofillHints.oneTimeCode],
                  // Accepts codes typed on Arabic keyboards (٠-٩), unlike digitsOnly.
                  inputFormatters: const [
                    LatinDigitsInputFormatter(maxLength: 6)
                  ],
                  prefixIcon: const Icon(Icons.pin_outlined),
                  validator: (value) {
                    final code = value?.trim() ?? '';
                    if (code.length != 6) {
                      return english
                          ? 'Enter the 6-digit code'
                          : 'أدخل الرمز المكون من 6 أرقام';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.md),
                Text(
                  english ? 'New password' : 'كلمة المرور الجديدة',
                  textAlign: TextAlign.start,
                  style: AppTextStyles.labelMd(context.sirati)
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                AppTextFormField(
                  controller: _passwordController,
                  autovalidateMode: _submitted
                      ? AutovalidateMode.onUserInteraction
                      : AutovalidateMode.disabled,
                  obscureText: _obscurePassword,
                  textDirection: TextDirection.ltr,
                  textAlign: TextAlign.start,
                  textInputAction: TextInputAction.next,
                  hintText: '••••••••',
                  prefixIcon: const Icon(Icons.lock_outline),
                  suffixIcon: IconButton(
                    tooltip: _obscurePassword
                        ? (english ? 'Show password' : 'إظهار كلمة المرور')
                        : (english ? 'Hide password' : 'إخفاء كلمة المرور'),
                    icon: Icon(_obscurePassword
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined),
                    onPressed: () =>
                        setState(() => _obscurePassword = !_obscurePassword),
                  ),
                  onChanged: (_) {
                    setState(() {});
                    if (_submitted) _confirmKey.currentState?.validate();
                  },
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return english
                          ? 'Please enter a new password'
                          : 'يرجى إدخال كلمة مرور جديدة';
                    }
                    if (value.length < 8) {
                      return english
                          ? 'Password must be at least 8 characters'
                          : 'يجب أن تكون كلمة المرور 8 أحرف على الأقل';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.xs),
                PasswordStrengthMeter(
                  password: _passwordController.text,
                  english: english,
                ),
                const SizedBox(height: AppSpacing.md),
                Text(
                  english ? 'Confirm password' : 'تأكيد كلمة المرور',
                  textAlign: TextAlign.start,
                  style: AppTextStyles.labelMd(context.sirati)
                      .copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                AppTextFormField(
                  key: _confirmKey,
                  controller: _confirmController,
                  autovalidateMode: _submitted
                      ? AutovalidateMode.onUserInteraction
                      : AutovalidateMode.disabled,
                  obscureText: _obscureConfirm,
                  textDirection: TextDirection.ltr,
                  textAlign: TextAlign.start,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => unawaited(_resetPassword()),
                  hintText: '••••••••',
                  prefixIcon: const Icon(Icons.lock_outline),
                  suffixIcon: IconButton(
                    tooltip: _obscureConfirm
                        ? (english ? 'Show password' : 'إظهار كلمة المرور')
                        : (english ? 'Hide password' : 'إخفاء كلمة المرور'),
                    icon: Icon(_obscureConfirm
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined),
                    onPressed: () =>
                        setState(() => _obscureConfirm = !_obscureConfirm),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return english
                          ? 'Please confirm your password'
                          : 'يرجى تأكيد كلمة المرور';
                    }
                    if (value != _passwordController.text) {
                      return english
                          ? 'Passwords do not match'
                          : 'كلمتا المرور غير متطابقتين';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.md),
                SubmitButton(
                  label: english ? 'Reset password' : 'تعيين كلمة المرور',
                  loadingLabel: english ? 'Saving...' : 'جارٍ الحفظ...',
                  isLoading: _isLoading,
                  icon: Icons.check_circle_outline,
                  onPressed: _resetPassword,
                ),
                const SizedBox(height: AppSpacing.sm),
                Center(
                  child: TextButton(
                    // The countdown is information, not a dead control: keep it readable
                    // while the button is disabled.
                    style: TextButton.styleFrom(
                      disabledForegroundColor: context.sirati.textSecondary,
                    ),
                    onPressed: (_resendCooldown > 0 || _isResending)
                        ? null
                        : () => _sendCode(isResend: true),
                    child: Text(
                      _isResending
                          ? (english ? 'Sending...' : 'جارٍ الإرسال...')
                          : _resendCooldown > 0
                              ? (english
                                  ? 'Resend code in $_resendCooldown s'
                                  : 'إعادة الإرسال بعد $_resendCooldown ث')
                              : (english ? 'Resend code' : 'إعادة إرسال الرمز'),
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _isLoading
                      ? null
                      : () {
                          setState(() {
                            _codeSent = false;
                            _submitted = false;
                            _codeController.clear();
                            _passwordController.clear();
                            _confirmController.clear();
                          });
                        },
                  child: Text(
                    english ? 'Use a different email' : 'استخدام بريد آخر',
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
