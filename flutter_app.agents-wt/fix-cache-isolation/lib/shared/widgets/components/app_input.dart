import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:sirati/core/utils/bidi_text_utils.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Token-driven text field (SIRATI-21). Uses [AppFormStyles] / theme inputs.
class AppInput extends StatelessWidget {
  final TextEditingController? controller;
  final String? label;
  final String? hint;
  final String? semanticLabel;
  final bool obscureText;
  final TextInputType? keyboardType;
  final int maxLines;
  final ValueChanged<String>? onChanged;
  final TextDirection? textDirection;
  final List<TextInputFormatter>? inputFormatters;
  final String? errorText;
  final String? helperText;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final FocusNode? focusNode;
  final bool enabled;
  final bool readOnly;
  final VoidCallback? onTap;

  const AppInput({
    super.key,
    this.controller,
    this.label,
    this.hint,
    this.semanticLabel,
    this.obscureText = false,
    this.keyboardType,
    this.maxLines = 1,
    this.onChanged,
    this.textDirection,
    this.inputFormatters,
    this.errorText,
    this.helperText,
    this.prefixIcon,
    this.suffixIcon,
    this.focusNode,
    this.enabled = true,
    this.readOnly = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    Widget buildField(TextDirection effectiveDirection) {
      return Semantics(
        textField: true,
        label: semanticLabel ?? label ?? hint,
        excludeSemantics: true,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: AppTouchTarget.min),
          child: TextField(
            controller: controller,
            obscureText: obscureText,
            keyboardType: keyboardType,
            maxLines: maxLines,
            onChanged: onChanged,
            textDirection: effectiveDirection,
            inputFormatters: inputFormatters,
            focusNode: focusNode,
            enabled: enabled,
            readOnly: readOnly,
            onTap: onTap,
            style: AppTypography.of(context, AppTypography.bodyMd),
            decoration: InputDecoration(
              labelText: label,
              hintText: hint,
              errorText: errorText,
              helperText: helperText,
              prefixIcon: prefixIcon,
              suffixIcon: suffixIcon,
            ),
          ),
        ),
      );
    }

    if (textDirection != null) {
      return buildField(textDirection!);
    }

    if (controller != null) {
      return ListenableBuilder(
        listenable: controller!,
        builder: (context, _) {
          final dir = BidiTextUtils.detectBaseDirection(
            controller!.text,
            fallback: Directionality.of(context),
          );
          return buildField(dir);
        },
      );
    }

    return buildField(Directionality.of(context));
  }
}
