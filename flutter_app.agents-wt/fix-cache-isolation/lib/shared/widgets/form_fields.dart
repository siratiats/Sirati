import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:sirati/core/utils/bidi_text_utils.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/motion.dart';

/// Shared form chrome — soft error surfaces, professional borders, RTL-safe copy.
class AppFormStyles {
  AppFormStyles._();

  static const radius = 14.0;

  /// Inline field error text. `error` on surface/background is a gated AA
  /// pair in both themes (the old static style was light-only).
  static TextStyle errorTextStyle(SiratiColors c) => TextStyle(
        fontSize: 12.5,
        height: 1.35,
        fontWeight: FontWeight.w600,
        color: c.error,
      );

  static TextStyle successTextStyle(SiratiColors c) => TextStyle(
        fontSize: 12.5,
        height: 1.35,
        fontWeight: FontWeight.w600,
        color: c.onSuccessContainer,
      );

  static InputDecorationTheme inputThemeFor(SiratiColors c) {
    OutlineInputBorder o(Color color, double width) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(radius),
          borderSide: BorderSide(color: color, width: width),
        );

    return InputDecorationTheme(
      filled: true,
      fillColor: c.surface,
      hoverColor: c.surfaceLow,
      border: o(c.border, 1),
      enabledBorder: o(c.border, 1),
      focusedBorder: o(c.primary, 1.8),
      errorBorder: o(c.red.withValues(alpha: .55), 1.4),
      focusedErrorBorder: o(c.red, 1.8),
      disabledBorder: o(c.border, 1),
      labelStyle: TextStyle(
        color: c.textSecondary,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      floatingLabelStyle: TextStyle(
        color: c.primary,
        fontSize: 13,
        fontWeight: FontWeight.w700,
      ),
      hintStyle: TextStyle(
        color: c.textHint,
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
      errorStyle: TextStyle(
        fontSize: 12.5,
        height: 1.35,
        fontWeight: FontWeight.w600,
        color: c.red,
      ),
      errorMaxLines: 2,
      prefixIconColor: c.textSecondary,
      suffixIconColor: c.textSecondary,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
    );
  }

  static InputDecoration decoration({
    String? hintText,
    String? labelText,
    Widget? prefixIcon,
    Widget? suffixIcon,
    bool dense = false,
  }) {
    return InputDecoration(
      hintText: hintText,
      labelText: labelText,
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      isDense: dense,
    );
  }
}

/// Compact inline validation message with icon — modern SaaS style.
///
/// Animates in with a small fade + slide when it appears (respects
/// reduced-motion via [MotionSettings]).
class AppFieldError extends StatelessWidget {
  final String message;

  const AppFieldError({super.key, required this.message});

  @override
  Widget build(BuildContext context) {
    final row = Padding(
      padding: const EdgeInsetsDirectional.only(top: 8, start: 2, end: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 18,
            height: 18,
            margin: const EdgeInsetsDirectional.only(top: 1),
            decoration: BoxDecoration(
              color: context.sirati.redLight,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Icon(
              Icons.error_outline_rounded,
              size: 13,
              color: context.sirati.red,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              textAlign: TextAlign.start,
              style: AppFormStyles.errorTextStyle(context.sirati),
            ),
          ),
        ],
      ),
    );

    if (MotionSettings.reduce(context)) return row;

    // Fade + slight upward slide so the error feels like it *appears*,
    // instead of just expanding into space via the parent AnimatedSize.
    return TweenAnimationBuilder<double>(
      key: ValueKey(message),
      tween: Tween(begin: 0, end: 1),
      duration: MotionDurations.medium,
      curve: MotionCurves.enter,
      builder: (context, t, child) => Opacity(
        opacity: t,
        child: Transform.translate(
          offset: Offset(0, (1 - t) * -4),
          child: child,
        ),
      ),
      child: row,
    );
  }
}

/// Soft banner above a form when submit is blocked by validation.
class AppFormErrorBanner extends StatelessWidget {
  final String message;
  final VoidCallback? onDismiss;

  const AppFormErrorBanner({
    super.key,
    required this.message,
    this.onDismiss,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedSize(
      duration: MotionDurations.fast,
      curve: MotionCurves.state,
      child: Container(
        width: double.infinity,
        margin: const EdgeInsets.only(bottom: AppSpacing.md),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: context.sirati.errorLight,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: context.sirati.red.withValues(alpha: .22)),
        ),
        child: Row(
          children: [
            Icon(Icons.error_outline_rounded,
                size: 20, color: context.sirati.red),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                textAlign: TextAlign.start,
                // Banner sits on errorLight: use the gated container pair.
                style: AppFormStyles.errorTextStyle(context.sirati).copyWith(
                  height: 1.4,
                  color: context.sirati.onErrorContainer,
                ),
              ),
            ),
            if (onDismiss != null)
              IconButton(
                onPressed: onDismiss,
                visualDensity: VisualDensity.compact,
                icon: Icon(Icons.close_rounded,
                    size: 18, color: context.sirati.red),
              ),
          ],
        ),
      ),
    );
  }
}

/// Soft green banner mirroring [AppFormErrorBanner] — use for inline
/// confirmations (e.g. "Reset link sent") without opening a snackbar.
class AppFormSuccessBanner extends StatelessWidget {
  final String message;
  final VoidCallback? onDismiss;
  final IconData icon;

  const AppFormSuccessBanner({
    super.key,
    required this.message,
    this.onDismiss,
    this.icon = Icons.check_circle_outline_rounded,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedSize(
      duration: MotionDurations.fast,
      curve: MotionCurves.state,
      child: TweenAnimationBuilder<double>(
        key: ValueKey(message),
        tween: Tween(begin: 0, end: 1),
        duration: MotionDurations.medium,
        curve: MotionCurves.enter,
        builder: (context, t, child) => Opacity(
          opacity: MotionSettings.reduce(context) ? 1 : t,
          child: Transform.translate(
            offset:
                Offset(0, MotionSettings.reduce(context) ? 0 : (1 - t) * -4),
            child: child,
          ),
        ),
        child: Container(
          width: double.infinity,
          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: context.sirati.successLight,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
                color: context.sirati.success.withValues(alpha: .35)),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 22, color: context.sirati.success),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  message,
                  textAlign: TextAlign.start,
                  style: TextStyle(
                    fontSize: 13.5,
                    height: 1.45,
                    color: context.sirati.success,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (onDismiss != null)
                IconButton(
                  onPressed: onDismiss,
                  visualDensity: VisualDensity.compact,
                  icon: Icon(Icons.close_rounded,
                      size: 18, color: context.sirati.success),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Modern text field with soft error fill, custom error row, and press-safe focus.
///
/// **Validation policy:** by default the field stays quiet while typing. Errors
/// appear only after **focus is lost** (blur), then re-validate on each change
/// ([AutovalidateMode.onUserInteraction] after first blur). Pass an explicit
/// [autovalidateMode] to override (e.g. after form submit).
class AppTextFormField extends FormField<String> {
  AppTextFormField({
    super.key,
    this.controller,
    String? initialValue,
    super.validator,
    super.onSaved,
    AutovalidateMode? autovalidateMode,
    String? hintText,
    String? labelText,
    Widget? prefixIcon,
    Widget? suffixIcon,
    TextInputType? keyboardType,
    TextInputAction? textInputAction,
    TextCapitalization textCapitalization = TextCapitalization.none,
    List<TextInputFormatter>? inputFormatters,
    bool obscureText = false,
    bool enabled = true,
    // Nullable so callers can pass `maxLines: null` for a fully auto-growing
    // multiline field (e.g. CV paste). Defaults to 1 (single-line).
    int? maxLines = 1,
    int? minLines,
    int? maxLength,
    bool readOnly = false,
    ValueChanged<String>? onChanged,
    ValueChanged<String>? onFieldSubmitted,

    /// Called when the field loses focus after the user interacted with it.
    this.onFocusLost,

    /// Called when the field becomes valid after blur / post-blur typing.
    this.onBecameValid,
    TextAlign textAlign = TextAlign.start,
    TextDirection? textDirection,
    this.focusNode,

    /// Platform autofill tokens (wrap the form in [AutofillGroup] for best results).
    this.autofillHints,
    bool filled = true,
    EdgeInsetsGeometry? contentPadding,

    /// When true, draws a soft red wash behind the field on error.
    bool softErrorFill = true,

    /// Soft green chrome + check when the field is non-empty and valid
    /// (after first blur, or when [autovalidateMode] forces validation).
    this.showSuccessWhenValid = false,
    this.successMessage,
  })  : _autovalidateMode = autovalidateMode,
        super(
          initialValue:
              controller != null ? controller.text : (initialValue ?? ''),
          // Always disabled at FormField level — blur / manual validate control
          // when errors appear (avoids premature flashing while typing).
          autovalidateMode: AutovalidateMode.disabled,
          builder: (FormFieldState<String> field) {
            final state = field as _AppTextFormFieldState;
            return state.buildField(
              hintText: hintText,
              labelText: labelText,
              prefixIcon: prefixIcon,
              suffixIcon: suffixIcon,
              keyboardType: keyboardType,
              textInputAction: textInputAction,
              textCapitalization: textCapitalization,
              inputFormatters: inputFormatters,
              obscureText: obscureText,
              enabled: enabled,
              maxLines: maxLines,
              minLines: minLines,
              maxLength: maxLength,
              readOnly: readOnly,
              onChanged: onChanged,
              onFieldSubmitted: onFieldSubmitted,
              textAlign: textAlign,
              textDirection: textDirection,
              filled: filled,
              contentPadding: contentPadding,
              softErrorFill: softErrorFill,
            );
          },
        );

  final TextEditingController? controller;
  final FocusNode? focusNode;
  final bool showSuccessWhenValid;
  final String? successMessage;
  final List<String>? autofillHints;
  final ValueChanged<String>? onFocusLost;
  final ValueChanged<String>? onBecameValid;

  /// Explicit mode from the caller, if any. `null` → blur-first policy.
  final AutovalidateMode? _autovalidateMode;

  AutovalidateMode get fieldAutovalidateMode =>
      _autovalidateMode ?? AutovalidateMode.disabled;

  @override
  FormFieldState<String> createState() => _AppTextFormFieldState();
}

class _AppTextFormFieldState extends FormFieldState<String> {
  TextEditingController? _localController;
  FocusNode? _ownedFocusNode;
  bool _hasFocus = false;

  /// After first unfocus, re-validate on each keystroke (onUserInteraction).
  bool _blurredOnce = false;
  bool _wasValid = false;

  TextEditingController get _effectiveController =>
      widget.controller ?? _localController!;

  FocusNode get _focusNode => widget.focusNode ?? _ownedFocusNode!;

  @override
  AppTextFormField get widget => super.widget as AppTextFormField;

  @override
  void initState() {
    super.initState();
    if (widget.controller == null) {
      _localController = TextEditingController(text: widget.initialValue);
    } else {
      widget.controller!.addListener(_handleControllerChanged);
    }
    if (widget.focusNode == null) {
      _ownedFocusNode = FocusNode(debugLabel: 'AppTextFormField');
    }
    _focusNode.addListener(_onFocusChange);
  }

  @override
  void didUpdateWidget(covariant AppTextFormField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.controller != oldWidget.controller) {
      oldWidget.controller?.removeListener(_handleControllerChanged);
      widget.controller?.addListener(_handleControllerChanged);
      if (oldWidget.controller != null && widget.controller == null) {
        _localController =
            TextEditingController.fromValue(oldWidget.controller!.value);
      }
      if (widget.controller != null) {
        setValue(widget.controller!.text);
        if (oldWidget.controller == null) {
          _localController?.dispose();
          _localController = null;
        }
      }
    }
    if (widget.focusNode != oldWidget.focusNode) {
      (oldWidget.focusNode ?? _ownedFocusNode)?.removeListener(_onFocusChange);
      if (widget.focusNode == null && _ownedFocusNode == null) {
        _ownedFocusNode = FocusNode(debugLabel: 'AppTextFormField');
      }
      if (widget.focusNode != null && oldWidget.focusNode == null) {
        _ownedFocusNode?.dispose();
        _ownedFocusNode = null;
      }
      _focusNode.addListener(_onFocusChange);
    }
  }

  @override
  void dispose() {
    _focusNode.removeListener(_onFocusChange);
    widget.controller?.removeListener(_handleControllerChanged);
    _localController?.dispose();
    _ownedFocusNode?.dispose();
    super.dispose();
  }

  void _onFocusChange() {
    final hasFocus = _focusNode.hasFocus;
    if (_hasFocus == hasFocus) return;

    setState(() => _hasFocus = hasFocus);

    // Strict policy: validate only after focus is lost.
    if (!hasFocus) {
      final text = _effectiveController.text;
      _blurredOnce = true;
      final ok = validate();
      widget.onFocusLost?.call(text);
      if (ok && !_wasValid) {
        _wasValid = true;
        widget.onBecameValid?.call(text);
      } else if (!ok) {
        _wasValid = false;
      }
    }
  }

  @override
  void didChange(String? value) {
    super.didChange(value);
    if (_effectiveController.text != value) {
      _effectiveController.value = TextEditingValue(
        text: value ?? '',
        selection: TextSelection.collapsed(offset: (value ?? '').length),
      );
    }
    // After first blur: re-validate as the user types (onUserInteraction).
    // Before first blur: stay quiet — no error flash while typing.
    final forceAlways = widget._autovalidateMode == AutovalidateMode.always;
    if (_blurredOnce || forceAlways) {
      final ok = validate();
      if (ok && !_wasValid) {
        _wasValid = true;
        widget.onBecameValid?.call(value ?? '');
      } else if (!ok) {
        _wasValid = false;
      }
    }
  }

  @override
  void reset() {
    super.reset();
    _blurredOnce = false;
    _wasValid = false;
    _effectiveController.text = widget.initialValue ?? '';
  }

  void _handleControllerChanged() {
    if (_effectiveController.text != value) {
      didChange(_effectiveController.text);
    }
  }

  bool _isShowingSuccess() {
    if (!widget.showSuccessWhenValid) return false;
    // Never show success chrome before the first blur (unless always mode).
    final forceAlways = widget._autovalidateMode == AutovalidateMode.always;
    if (!_blurredOnce && !forceAlways) return false;
    final text = value?.trim() ?? '';
    if (text.isEmpty) return false;
    if (hasError && (errorText?.isNotEmpty ?? false)) return false;
    final validator = widget.validator;
    if (validator != null && validator(value) != null) return false;
    return true;
  }

  Widget buildField({
    String? hintText,
    String? labelText,
    Widget? prefixIcon,
    Widget? suffixIcon,
    TextInputType? keyboardType,
    TextInputAction? textInputAction,
    TextCapitalization textCapitalization = TextCapitalization.none,
    List<TextInputFormatter>? inputFormatters,
    bool obscureText = false,
    bool enabled = true,
    int? maxLines = 1,
    int? minLines,
    int? maxLength,
    bool readOnly = false,
    ValueChanged<String>? onChanged,
    ValueChanged<String>? onFieldSubmitted,
    TextAlign textAlign = TextAlign.start,
    TextDirection? textDirection,
    bool filled = true,
    EdgeInsetsGeometry? contentPadding,
    bool softErrorFill = true,
  }) {
    final showError = hasError && (errorText?.isNotEmpty ?? false);
    final showSuccess = !showError && _isShowingSuccess();

    final fill = !filled
        ? null
        : showError && softErrorFill
            ? context.sirati.redLight.withValues(alpha: .35)
            : showSuccess
                ? context.sirati.successLight.withValues(alpha: .35)
                : context.sirati.surface;

    final borderColor = showError
        ? context.sirati.red.withValues(alpha: .5)
        : showSuccess
            ? context.sirati.success.withValues(alpha: .55)
            : _hasFocus
                ? context.sirati.primary
                : context.sirati.border;

    final resolvedSuffix = showSuccess
        ? Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (suffixIcon != null) suffixIcon,
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 10),
                child: Icon(
                  Icons.check_circle_rounded,
                  size: 20,
                  color: context.sirati.success,
                ),
              ),
            ],
          )
        : suffixIcon;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AnimatedContainer(
          duration: MotionSettings.reduce(context)
              ? Duration.zero
              : MotionDurations.fast,
          curve: MotionCurves.state,
          decoration: BoxDecoration(
            color: fill,
            borderRadius: BorderRadius.circular(AppFormStyles.radius),
            border: Border.all(
              color: borderColor,
              width: (showError || showSuccess || _hasFocus) ? 1.4 : 1,
            ),
            boxShadow: showError
                ? [
                    BoxShadow(
                      color: context.sirati.red.withValues(alpha: .06),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ]
                : showSuccess
                    ? [
                        BoxShadow(
                          color: context.sirati.success.withValues(alpha: .06),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ]
                    : _hasFocus
                        ? [
                            BoxShadow(
                              color:
                                  context.sirati.primary.withValues(alpha: .08),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ]
                        : null,
          ),
          child: TextField(
            controller: _effectiveController,
            focusNode: _focusNode,
            enabled: enabled,
            readOnly: readOnly,
            obscureText: obscureText,
            keyboardType: keyboardType,
            textInputAction: textInputAction,
            textCapitalization: textCapitalization,
            inputFormatters: inputFormatters,
            autofillHints: widget.autofillHints,
            maxLines: obscureText ? 1 : maxLines,
            minLines: minLines,
            maxLength: maxLength,
            textAlign: textAlign,
            textDirection: textDirection ??
                BidiTextUtils.detectBaseDirection(
                  _effectiveController.text,
                  fallback: Directionality.of(context),
                ),
            style: TextStyle(
              fontSize: 15,
              height: 1.35,
              fontWeight: FontWeight.w500,
              color: context.sirati.textPrimary,
            ),
            cursorColor: context.sirati.primary,
            onChanged: (value) {
              didChange(value);
              onChanged?.call(value);
            },
            onSubmitted: onFieldSubmitted,
            decoration: InputDecoration(
              hintText: hintText,
              labelText: labelText,
              prefixIcon: prefixIcon == null
                  ? null
                  : IconTheme(
                      data: IconThemeData(
                        color: showError
                            ? context.sirati.red.withValues(alpha: .85)
                            : showSuccess
                                ? context.sirati.success
                                : context.sirati.textSecondary,
                        size: 20,
                      ),
                      child: prefixIcon,
                    ),
              suffixIcon: resolvedSuffix,
              filled: false,
              border: InputBorder.none,
              enabledBorder: InputBorder.none,
              focusedBorder: InputBorder.none,
              errorBorder: InputBorder.none,
              focusedErrorBorder: InputBorder.none,
              disabledBorder: InputBorder.none,
              contentPadding: contentPadding ??
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              errorStyle: const TextStyle(height: 0, fontSize: 0),
              errorText: null,
              counterText: '',
            ),
          ),
        ),
        AnimatedSize(
          duration: MotionDurations.fast,
          curve: MotionCurves.state,
          alignment: Alignment.topCenter,
          child: showError
              ? AppFieldError(message: errorText!)
              : showSuccess && (widget.successMessage?.isNotEmpty ?? false)
                  ? Padding(
                      padding: const EdgeInsetsDirectional.only(
                          top: 8, start: 2, end: 2),
                      child: Text(
                        widget.successMessage!,
                        textAlign: TextAlign.start,
                        style: AppFormStyles.successTextStyle(context.sirati),
                      ),
                    )
                  : const SizedBox(width: double.infinity),
        ),
      ],
    );
  }
}
