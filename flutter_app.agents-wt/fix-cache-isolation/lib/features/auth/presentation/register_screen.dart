import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/analytics_service.dart';
import 'package:sirati/core/routing/app_router.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/core/utils/root_navigation.dart';
import 'package:sirati/features/auth/application/register_controller.dart';
import 'package:sirati/features/auth/domain/registration_rules.dart';
import 'package:sirati/features/auth/presentation/email_verification_screen.dart';
import 'package:sirati/features/auth/presentation/login_screen.dart';
import 'package:sirati/features/auth/presentation/register/register_widgets.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/services/job_titles_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/auth_form_constraint.dart';
import 'package:sirati/shared/widgets/directional_icon.dart';
import 'package:sirati/shared/widgets/form_fields.dart';
import 'package:sirati/shared/widgets/job_title_picker_field.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/password_strength_meter.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _locationController = TextEditingController();
  final _passwordController = TextEditingController();
  final _jobTitleOtherController = TextEditingController();

  bool _obscurePassword = true;
  bool _agreedToTerms = false;
  bool _submitted = false;
  bool _showFormBanner = false;
  JobTitle? _selectedJobTitle;

  bool get _isOther => _selectedJobTitle?.isOther == true;

  @override
  void initState() {
    super.initState();
    if (!kIsWeb && Firebase.apps.isNotEmpty) {
      unawaited(FirebaseCrashlytics.instance.log('RegisterScreen opened'));
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _locationController.dispose();
    _passwordController.dispose();
    _jobTitleOtherController.dispose();
    super.dispose();
  }

  Future<void> _register() async {
    if (ref.read(registerControllerProvider)) return;
    final en = AppLocale.isEnglish(context);
    setState(() {
      _submitted = true;
      _showFormBanner = false;
    });

    final valid = _formKey.currentState!.validate();
    final otherError = RegistrationRules.jobTitleOther(
      _jobTitleOtherController.text,
      isOther: _isOther,
    );
    if (!valid || !_agreedToTerms || otherError != null) {
      setState(() => _showFormBanner = true);
      unawaited(HapticFeedback.selectionClick());
      if (!_agreedToTerms) {
        AppSnackBar.warning(
          context,
          en
              ? 'Please agree to the Terms & Conditions'
              : 'يرجى الموافقة على الشروط والأحكام',
        );
      }
      return;
    }

    final outcome = await ref.read(registerControllerProvider.notifier).submit(
          RegistrationInput(
            name: _nameController.text,
            email: _emailController.text,
            password: _passwordController.text,
            phone: _phoneController.text,
            location: _locationController.text,
            jobTitleId: _selectedJobTitle?.id,
            jobTitleIsOther: _isOther,
            jobTitleOther: _jobTitleOtherController.text,
          ),
        );
    if (!mounted) return;

    switch (outcome) {
      case RegisterSucceeded(:final email):
        unawaited(AnalyticsService.logRegisterSuccess());
        unawaited(HapticFeedback.lightImpact());
        replaceRoot(context, EmailVerificationScreen(email: email));
      case RegisterRejected(:final error):
        AppSnackBar.fromException(
          context,
          error,
          retryLabel: en ? 'Retry' : 'إعادة',
          onRetry: _register,
        );
      case RegisterFailedUnexpectedly():
        AppSnackBar.error(
          context,
          en
              ? 'An unexpected error occurred while creating the account.'
              : 'حدث خطأ غير متوقع أثناء إنشاء الحساب.',
        );
      case RegisterIgnored():
        break;
    }
  }

  void _handleBack() {
    final navigator = Navigator.of(context);
    if (navigator.canPop()) {
      navigator.pop();
    } else {
      unawaited(navigator.pushNamedAndRemoveUntil(
        AppRoutes.splash,
        (route) => false,
      ));
    }
  }

  void _goToLogin() {
    unawaited(Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    ));
  }

  AutovalidateMode get _autovalidate => _submitted
      ? AutovalidateMode.onUserInteraction
      : AutovalidateMode.disabled;

  String? Function(String?) _validator(
    FieldError? Function(String?) rule, {
    required String requiredEn,
    required String requiredAr,
    int? max,
  }) {
    return (value) {
      final error = rule(value);
      if (error == null) return null;
      return fieldErrorText(
        error,
        english: AppLocale.isEnglish(context),
        requiredEn: requiredEn,
        requiredAr: requiredAr,
        max: max,
      );
    };
  }

  @override
  Widget build(BuildContext context) {
    final en = AppLocale.isEnglish(context);
    final isLoading = ref.watch(registerControllerProvider);
    final jobTitles = ref.watch(jobTitlesProvider);
    final looksGood = en ? 'Looks good' : 'يبدو جيداً';
    void next(String _) => FocusScope.of(context).nextFocus();

    final otherError = _submitted
        ? RegistrationRules.jobTitleOther(
            _jobTitleOtherController.text,
            isOther: _isOther,
          )
        : null;
    const otherRequiredEn = 'Please enter your job title';
    const otherRequiredAr = 'يرجى كتابة المسمى الوظيفي عند اختيار أخرى.';

    return Scaffold(
      backgroundColor: context.sirati.background,
      body: SafeArea(
        child: AuthFormConstraint(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 14, 24, 28),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      IconButton(
                        onPressed: _handleBack,
                        tooltip:
                            MaterialLocalizations.of(context).backButtonTooltip,
                        icon: DirectionalIcon(
                          Icons.arrow_back_ios_new_rounded,
                          color: context.sirati.textPrimary,
                          size: 18,
                        ),
                      ),
                      const Spacer(),
                      const LanguageToggle(),
                    ],
                  ),
                  const SizedBox(height: 8),
                  RegisterHero(english: en),
                  const SizedBox(height: 22),
                  if (_showFormBanner)
                    AppFormErrorBanner(
                      message: en
                          ? 'Please fix the highlighted fields to continue.'
                          : 'يرجى تصحيح الحقول المحددة للمتابعة.',
                      onDismiss: () => setState(() => _showFormBanner = false),
                    ),
                  FieldLabel(text: en ? 'Full Name' : 'الاسم الكامل'),
                  AppTextFormField(
                    controller: _nameController,
                    showSuccessWhenValid: true,
                    successMessage: looksGood,
                    autovalidateMode: _autovalidate,
                    textAlign: TextAlign.start,
                    textCapitalization: TextCapitalization.words,
                    textInputAction: TextInputAction.next,
                    onFieldSubmitted: next,
                    hintText: en ? 'Enter your full name' : 'اكتب اسمك الكامل',
                    prefixIcon: const Icon(Icons.person_outline_rounded),
                    validator: _validator(
                      RegistrationRules.name,
                      requiredEn: 'Please enter your full name',
                      requiredAr: 'يرجى إدخال اسمك الكامل',
                      max: RegistrationRules.nameMax,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  FieldLabel(text: en ? 'Email Address' : 'البريد الإلكتروني'),
                  AppTextFormField(
                    controller: _emailController,
                    showSuccessWhenValid: true,
                    successMessage: looksGood,
                    autovalidateMode: _autovalidate,
                    keyboardType: TextInputType.emailAddress,
                    textDirection: TextDirection.ltr,
                    textAlign: TextAlign.start,
                    textInputAction: TextInputAction.next,
                    onFieldSubmitted: next,
                    hintText: 'example@mail.com',
                    prefixIcon: const Icon(Icons.mail_outline_rounded),
                    validator: _validator(
                      RegistrationRules.email,
                      requiredEn: 'Please enter your email',
                      requiredAr: 'يرجى إدخال البريد الإلكتروني',
                      max: RegistrationRules.emailMax,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  FieldLabel(text: en ? 'Phone Number' : 'رقم الجوال'),
                  AppTextFormField(
                    controller: _phoneController,
                    showSuccessWhenValid: true,
                    successMessage: looksGood,
                    autovalidateMode: _autovalidate,
                    keyboardType: TextInputType.phone,
                    textDirection: TextDirection.ltr,
                    textAlign: TextAlign.start,
                    textInputAction: TextInputAction.next,
                    onFieldSubmitted: next,
                    hintText: '05XXXXXXXX',
                    prefixIcon: const Icon(Icons.phone_iphone_outlined),
                    validator: _validator(
                      RegistrationRules.phone,
                      requiredEn: 'Please enter your phone number',
                      requiredAr: 'يرجى إدخال رقم الجوال',
                      max: RegistrationRules.phoneMax,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  FieldLabel(text: en ? 'City/Country' : 'المدينة/الدولة'),
                  AppTextFormField(
                    controller: _locationController,
                    showSuccessWhenValid: true,
                    successMessage: looksGood,
                    autovalidateMode: _autovalidate,
                    textAlign: TextAlign.start,
                    textInputAction: TextInputAction.next,
                    onFieldSubmitted: next,
                    hintText: en ? 'Riyadh, Saudi Arabia' : 'الرياض، السعودية',
                    prefixIcon: const Icon(Icons.place_outlined),
                    validator: _validator(
                      RegistrationRules.location,
                      requiredEn: 'Please enter your city or country',
                      requiredAr: 'يرجى إدخال المدينة أو الدولة',
                      max: RegistrationRules.locationMax,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  FieldLabel(
                    text: en
                        ? 'Job title (optional)'
                        : 'المسمى الوظيفي (اختياري)',
                  ),
                  JobTitlePickerField(
                    titles: jobTitles.value ?? const [],
                    value: _selectedJobTitle,
                    english: en,
                    loading: jobTitles.isLoading && !jobTitles.hasValue,
                    submitted: _submitted,
                    errorText: otherError == null
                        ? null
                        : fieldErrorText(
                            otherError,
                            english: en,
                            requiredEn: otherRequiredEn,
                            requiredAr: otherRequiredAr,
                            max: RegistrationRules.jobTitleOtherMax,
                          ),
                    onChanged: (title) => setState(() {
                      _selectedJobTitle = title;
                      if (title?.isOther != true) {
                        _jobTitleOtherController.clear();
                      }
                    }),
                  ),
                  if (_isOther) ...[
                    const SizedBox(height: AppSpacing.md),
                    FieldLabel(
                      text: en ? 'Your job title' : 'المسمى الوظيفي الخاص بك',
                    ),
                    AppTextFormField(
                      controller: _jobTitleOtherController,
                      showSuccessWhenValid: true,
                      successMessage: looksGood,
                      autovalidateMode: _autovalidate,
                      textAlign: TextAlign.start,
                      textInputAction: TextInputAction.next,
                      onFieldSubmitted: next,
                      maxLength: RegistrationRules.jobTitleOtherMax,
                      hintText: en
                          ? 'e.g. Logistics Consultant'
                          : 'مثال: مستشار لوجستي',
                      prefixIcon: const Icon(Icons.edit_outlined),
                      validator: _validator(
                        (v) => RegistrationRules.jobTitleOther(
                          v,
                          isOther: _isOther,
                        ),
                        requiredEn: otherRequiredEn,
                        requiredAr: otherRequiredAr,
                        max: RegistrationRules.jobTitleOtherMax,
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.md),
                  FieldLabel(text: en ? 'Password' : 'كلمة المرور'),
                  AppTextFormField(
                    controller: _passwordController,
                    showSuccessWhenValid: true,
                    autovalidateMode: _autovalidate,
                    obscureText: _obscurePassword,
                    textDirection: TextDirection.ltr,
                    textAlign: TextAlign.start,
                    textInputAction: TextInputAction.done,
                    onFieldSubmitted: (_) => unawaited(_register()),
                    hintText: '••••••••',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                        color: context.sirati.textHint,
                      ),
                      onPressed: () =>
                          setState(() => _obscurePassword = !_obscurePassword),
                    ),
                    onChanged: (_) => setState(() {}),
                    validator: _validator(
                      RegistrationRules.password,
                      requiredEn: 'Please enter your password',
                      requiredAr: 'يرجى إدخال كلمة المرور',
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  PasswordStrengthMeter(
                    password: _passwordController.text,
                    english: en,
                  ),
                  const SizedBox(height: 14),
                  TermsAgreement(
                    english: en,
                    agreed: _agreedToTerms,
                    onChanged: (value) =>
                        setState(() => _agreedToTerms = value),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  SubmitButton(
                    label: en ? 'Create Account' : 'إنشاء الحساب',
                    loadingLabel:
                        en ? 'Creating account...' : 'جارٍ إنشاء الحساب...',
                    isLoading: isLoading,
                    icon: Icons.arrow_forward_rounded,
                    onPressed: () => unawaited(_register()),
                  ),
                  const SizedBox(height: 6),
                  SignInPrompt(english: en, onTap: _goToLogin),
                  const SizedBox(height: 8),
                  TextButton(
                    // Preview mode: Home becomes the root route so Back
                    // doesn't return to the auth screens.
                    onPressed: () => AppRouter.openAfterAuth(context),
                    child: Text(
                      en ? 'Continue without account' : 'المتابعة بدون حساب',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: context.sirati.textHint,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
