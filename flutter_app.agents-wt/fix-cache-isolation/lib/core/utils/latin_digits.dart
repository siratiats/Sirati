import 'package:flutter/services.dart';

/// Maps Arabic-Indic (٠–٩) and Extended Arabic-Indic (۰–۹) digits to
/// ASCII so numbers typed on Arabic, Persian, or Urdu keyboards validate and
/// are stored in one form. Everything else is returned unchanged.
String latinDigits(String input) {
  final out = StringBuffer();
  for (final rune in input.runes) {
    if (rune >= 0x0660 && rune <= 0x0669) {
      out.writeCharCode(0x30 + rune - 0x0660);
    } else if (rune >= 0x06F0 && rune <= 0x06F9) {
      out.writeCharCode(0x30 + rune - 0x06F0);
    } else {
      out.writeCharCode(rune);
    }
  }
  return out.toString();
}

/// Numeric-code input (OTPs): converts Arabic digits to ASCII, drops
/// everything else, and caps the length. Unlike
/// `FilteringTextInputFormatter.digitsOnly`, it accepts a code typed on an
/// Arabic keyboard instead of silently rejecting every keystroke.
class LatinDigitsInputFormatter extends TextInputFormatter {
  const LatinDigitsInputFormatter({this.maxLength});

  final int? maxLength;

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = latinDigits(newValue.text).replaceAll(RegExp(r'[^0-9]'), '');
    final max = maxLength;
    final text =
        max != null && digits.length > max ? digits.substring(0, max) : digits;
    if (text == newValue.text) return newValue;
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
