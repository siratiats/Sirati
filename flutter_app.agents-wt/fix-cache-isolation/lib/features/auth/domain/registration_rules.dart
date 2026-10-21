import 'package:sirati/core/utils/latin_digits.dart';

export 'package:sirati/core/utils/latin_digits.dart' show latinDigits;

/// Why a registration field was rejected. Localised in the presentation layer.
enum FieldError { required, tooLong, tooShort, invalidEmail, invalidPhone }

/// Client-side registration rules, kept in line with
/// `MobileAuthController::register` so users see limits before the server
/// rejects the request. The server remains the authority.
abstract final class RegistrationRules {
  static const nameMax = 255;
  static const emailMax = 255;
  static const phoneMax = 30;
  static const locationMax = 120;
  static const jobTitleOtherMax = 120;
  static const passwordMin = 8;

  /// E.164 allows at most 15 digits; shorter than 8 is never a real number.
  static const phoneMinDigits = 8;
  static const phoneMaxDigits = 15;

  static final _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');

  /// Digits with an optional leading `+` and common separators.
  static final _phoneChars = RegExp(r'^\+?[0-9\s\-().]+$');

  static FieldError? name(String? value) => _requiredWithin(value, nameMax);

  static FieldError? email(String? value) {
    final base = _requiredWithin(value, emailMax);
    if (base != null) return base;
    return _email.hasMatch(value!.trim()) ? null : FieldError.invalidEmail;
  }

  static FieldError? phone(String? value) {
    final base = _requiredWithin(value, phoneMax);
    if (base != null) return base;
    final phone = latinDigits(value!.trim());
    if (!_phoneChars.hasMatch(phone)) return FieldError.invalidPhone;
    final digits = phone.codeUnits.where((c) => c >= 48 && c <= 57).length;
    return digits < phoneMinDigits || digits > phoneMaxDigits
        ? FieldError.invalidPhone
        : null;
  }

  static FieldError? location(String? value) =>
      _requiredWithin(value, locationMax);

  /// Passwords are not trimmed: spaces are legitimate characters.
  static FieldError? password(String? value) {
    if (value == null || value.isEmpty) return FieldError.required;
    return value.length < passwordMin ? FieldError.tooShort : null;
  }

  /// Required only when the "Other" job title is selected.
  static FieldError? jobTitleOther(String? value, {required bool isOther}) =>
      isOther ? _requiredWithin(value, jobTitleOtherMax) : null;

  static FieldError? _requiredWithin(String? value, int max) {
    final text = value?.trim() ?? '';
    if (text.isEmpty) return FieldError.required;
    return text.length > max ? FieldError.tooLong : null;
  }
}

/// The values sent to the register endpoint, already normalised.
class RegistrationInput {
  RegistrationInput({
    required String name,
    required String email,
    required this.password,
    required String phone,
    required String location,
    required this.jobTitleId,
    required bool jobTitleIsOther,
    required String jobTitleOther,
  })  : name = name.trim(),
        email = email.trim(),
        phone = latinDigits(phone.trim()),
        location = location.trim(),
        // Free text is only meaningful with "Other"; never send a stale value.
        jobTitleOther = jobTitleIsOther ? jobTitleOther.trim() : null;

  final String name;
  final String email;
  final String password;
  final String phone;
  final String location;
  final int? jobTitleId;
  final String? jobTitleOther;
}
