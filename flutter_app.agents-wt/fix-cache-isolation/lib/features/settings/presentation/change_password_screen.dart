import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/password_strength_meter.dart';
import 'package:sirati/shared/widgets/password_visibility_toggle.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() =>
      _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentCtrl = TextEditingController();
  final _newCtrl = TextEditingController();
  final _confirmCtrl = TextEditingController();
  final _confirmKey = GlobalKey<FormFieldState<String>>();

  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;
  bool _submitted = false;
  bool _showBanner = false;
  bool _loading = false;
  String? _currentFieldError;

  @override
  void dispose() {
    _currentCtrl.dispose();
    _newCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    // One request at a time (keyboard Done + button).
    if (_loading) return;
    final english = AppLocale.isEnglish(context);
    setState(() {
      _submitted = true;
      _showBanner = false;
      _currentFieldError = null;
    });
    if (!_formKey.currentState!.validate()) {
      setState(() => _showBanner = true);
      unawaited(HapticFeedback.selectionClick());
      return;
    }

    setState(() => _loading = true);
    try {
      final message = await ref.read(authApiServiceProvider).changePassword(
            currentPassword: _currentCtrl.text,
            newPassword: _newCtrl.text,
            confirmation: _confirmCtrl.text,
          );
      if (!mounted) return;
      unawaited(HapticFeedback.lightImpact());
      AppSnackBar.success(
        context,
        english
            ? 'Password changed successfully.'
            : (message.isNotEmpty ? message : 'تم تغيير كلمة المرور بنجاح.'),
      );
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      final fieldErrors = e.errors['current_password'];
      if (fieldErrors != null && fieldErrors.isNotEmpty) {
        setState(() {
          _currentFieldError =
              english ? 'Current password is incorrect.' : fieldErrors.first;
          _showBanner = true;
        });
        _formKey.currentState?.validate();
      } else {
        AppSnackBar.fromException(context, e);
      }
    } catch (_) {
      if (!mounted) return;
      AppSnackBar.error(
        context,
        english
            ? 'Could not change password. Try again.'
            : 'تعذر تغيير كلمة المرور. حاول مجدداً.',
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final en = AppLocale.isEnglish(context);
    final auto = _submitted
        ? AutovalidateMode.onUserInteraction
        : AutovalidateMode.disabled;

    return Scaffold(
      backgroundColor: context.sirati.background,
      appBar: AppBar(
        title: Text(en ? 'Change password' : 'تغيير كلمة المرور'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg, AppSpacing.md, AppSpacing.lg, AppSpacing.xxl),
          children: [
            if (_showBanner)
              AppFormErrorBanner(
                message: en
                    ? 'Please fix the highlighted fields to continue.'
                    : 'يرجى تصحيح الحقول المحددة للمتابعة.',
                onDismiss: () => setState(() => _showBanner = false),
              ),
            _Label(en ? 'Current password' : 'كلمة المرور الحالية'),
            const SizedBox(height: 6),
            AppTextFormField(
              controller: _currentCtrl,
              autovalidateMode: auto,
              obscureText: _obscureCurrent,
              textDirection: TextDirection.ltr,
              textAlign: TextAlign.start,
              textInputAction: TextInputAction.next,
              onFieldSubmitted: (_) => FocusScope.of(context).nextFocus(),
              hintText: '••••••••',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: PasswordVisibilityToggle(
                obscured: _obscureCurrent,
                onToggle: () =>
                    setState(() => _obscureCurrent = !_obscureCurrent),
              ),
              validator: (v) {
                if (_currentFieldError != null) return _currentFieldError;
                if (v == null || v.isEmpty) {
                  return en
                      ? 'Enter your current password'
                      : 'أدخل كلمة المرور الحالية';
                }
                return null;
              },
              onChanged: (_) {
                if (_currentFieldError != null) {
                  setState(() => _currentFieldError = null);
                }
              },
            ),
            const SizedBox(height: AppSpacing.md),
            _Label(en ? 'New password' : 'كلمة المرور الجديدة'),
            const SizedBox(height: 6),
            AppTextFormField(
              controller: _newCtrl,
              autovalidateMode: auto,
              obscureText: _obscureNew,
              textDirection: TextDirection.ltr,
              textAlign: TextAlign.start,
              textInputAction: TextInputAction.next,
              onFieldSubmitted: (_) => FocusScope.of(context).nextFocus(),
              hintText: '••••••••',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: PasswordVisibilityToggle(
                obscured: _obscureNew,
                onToggle: () => setState(() => _obscureNew = !_obscureNew),
              ),
              onChanged: (_) {
                setState(() {});
                // A stale "do not match" must clear when the new one changes.
                if (_submitted) _confirmKey.currentState?.validate();
              },
              validator: (v) {
                if (v == null || v.isEmpty) {
                  return en ? 'Enter a new password' : 'أدخل كلمة مرور جديدة';
                }
                if (v.length < 8) {
                  return en
                      ? 'Password must be at least 8 characters'
                      : 'يجب أن تكون كلمة المرور 8 أحرف على الأقل';
                }
                if (v == _currentCtrl.text) {
                  return en
                      ? 'New password must differ from current'
                      : 'يجب أن تختلف عن كلمة المرور الحالية';
                }
                return null;
              },
            ),
            const SizedBox(height: AppSpacing.xs),
            PasswordStrengthMeter(password: _newCtrl.text, english: en),
            const SizedBox(height: AppSpacing.md),
            _Label(en ? 'Confirm new password' : 'تأكيد كلمة المرور الجديدة'),
            const SizedBox(height: 6),
            AppTextFormField(
              key: _confirmKey,
              controller: _confirmCtrl,
              autovalidateMode: auto,
              obscureText: _obscureConfirm,
              textDirection: TextDirection.ltr,
              textAlign: TextAlign.start,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => unawaited(_submit()),
              hintText: '••••••••',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: PasswordVisibilityToggle(
                obscured: _obscureConfirm,
                onToggle: () =>
                    setState(() => _obscureConfirm = !_obscureConfirm),
              ),
              validator: (v) {
                if (v == null || v.isEmpty) {
                  return en
                      ? 'Confirm your new password'
                      : 'أكد كلمة المرور الجديدة';
                }
                if (v != _newCtrl.text) {
                  return en
                      ? 'Passwords do not match'
                      : 'كلمتا المرور غير متطابقتين';
                }
                return null;
              },
            ),
            const SizedBox(height: AppSpacing.xl),
            SubmitButton(
              label: en ? 'Update password' : 'تحديث كلمة المرور',
              loadingLabel: en ? 'Updating...' : 'جارٍ التحديث...',
              isLoading: _loading,
              icon: Icons.check_rounded,
              onPressed: () => unawaited(_submit()),
            ),
          ],
        ),
      ),
    );
  }
}

class _Label extends StatelessWidget {
  final String text;
  const _Label(this.text);

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      textAlign: TextAlign.start,
      style: AppTextStyles.titleSm(context.sirati)
          .copyWith(color: context.sirati.textSecondary),
    );
  }
}
