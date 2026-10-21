import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/auth/domain/registration_rules.dart';

void main() {
  group('required + max-length fields match the server limits', () {
    final cases = <(String, FieldError? Function(String?), int)>[
      ('name', RegistrationRules.name, 255),
      ('email', RegistrationRules.email, 255),
      ('phone', RegistrationRules.phone, 30),
      ('location', RegistrationRules.location, 120),
      (
        'jobTitleOther',
        (v) => RegistrationRules.jobTitleOther(v, isOther: true),
        120,
      ),
    ];

    test('blank is required for every field', () {
      for (final (name, rule, _) in cases) {
        for (final blank in [null, '', ' ', '\t\n ']) {
          expect(rule(blank), FieldError.required, reason: '$name "$blank"');
        }
      }
    });

    test('one character over the limit is rejected; trimming applies first',
        () {
      for (final (name, rule, max) in cases) {
        final over = name == 'email'
            ? '${'a' * (max - 4)}@x.io'
            : name == 'phone'
                ? '1' * (max + 1)
                : 'a' * (max + 1);
        expect(rule(over), FieldError.tooLong, reason: name);
        if (name == 'name' || name == 'location' || name == 'jobTitleOther') {
          expect(rule('  ${'a' * max}  '), isNull, reason: '$name at max');
        }
      }
    });

    test('the server limits are the ones in MobileAuthController', () {
      expect(RegistrationRules.nameMax, 255);
      expect(RegistrationRules.emailMax, 255);
      expect(RegistrationRules.phoneMax, 30);
      expect(RegistrationRules.locationMax, 120);
      expect(RegistrationRules.jobTitleOtherMax, 120);
      expect(RegistrationRules.passwordMin, 8);
    });
  });

  group('email', () {
    test('accepts local@domain.tld shapes', () {
      for (final email in [
        'a@b.co',
        'first.last+tag@sub.example.com',
        ' spaced@trim.io ',
        'عربي@مثال.السعودية',
      ]) {
        expect(RegistrationRules.email(email), isNull, reason: email);
      }
    });

    test('rejects anything without exactly one @ and a dotted domain', () {
      for (final email in [
        'plain',
        '@no-local.io',
        'no-domain@',
        'no-dot@domain',
        'two@@at.io',
        'a@b@c.io',
        'has space@x.io',
        'a@x.',
      ]) {
        expect(RegistrationRules.email(email), FieldError.invalidEmail,
            reason: email);
      }
    });
  });

  group('phone', () {
    test('valid for 8–15 digits with common separators and optional +', () {
      for (var digits = 8; digits <= 15; digits++) {
        final n = '5' * digits;
        for (final form in [
          n,
          '+$n',
          '(${n.substring(0, 3)}) ${n.substring(3)}',
          '${n.substring(0, 2)}-${n.substring(2)}'
        ]) {
          expect(RegistrationRules.phone(form), isNull, reason: form);
        }
      }
    });

    test('invalid below 8 or above 15 digits', () {
      for (final digits in [1, 7, 16, 20]) {
        expect(RegistrationRules.phone('5' * digits), FieldError.invalidPhone,
            reason: '$digits digits');
      }
    });

    test('letters or a misplaced + are rejected even with enough digits', () {
      for (final phone in [
        'abc12345678',
        '0501234567x',
        '05+01234567',
        '++966501234567'
      ]) {
        expect(RegistrationRules.phone(phone), FieldError.invalidPhone,
            reason: phone);
      }
    });

    test('Arabic-Indic and Extended Arabic-Indic digits are accepted', () {
      for (final phone in ['٠٥٠١٢٣٤٥٦٧', '۰۵۰۱۲۳۴۵۶۷', '+٩٦٦٥٠١٢٣٤٥٦٧']) {
        expect(RegistrationRules.phone(phone), isNull, reason: phone);
      }
    });
  });

  group('latinDigits', () {
    test('maps every Arabic digit form to ASCII and leaves the rest alone', () {
      for (var d = 0; d <= 9; d++) {
        expect(latinDigits(String.fromCharCode(0x0660 + d)), '$d');
        expect(latinDigits(String.fromCharCode(0x06F0 + d)), '$d');
      }
      for (final s in ['', 'abc', '+966 (50) 123-4567', 'سيرتي']) {
        expect(latinDigits(s), s);
      }
    });
  });

  group('password', () {
    test('length is measured untrimmed and the boundary is 8', () {
      expect(RegistrationRules.password(null), FieldError.required);
      expect(RegistrationRules.password(''), FieldError.required);
      for (var n = 1; n < 8; n++) {
        expect(RegistrationRules.password('x' * n), FieldError.tooShort);
      }
      for (var n = 8; n < 20; n++) {
        expect(RegistrationRules.password('x' * n), isNull);
      }
      // Spaces count: eight spaces is a (weak) valid password server-side.
      expect(RegistrationRules.password(' ' * 8), isNull);
    });
  });

  group('jobTitleOther', () {
    test('ignored unless "Other" is selected', () {
      for (final v in [null, '', 'x' * 500]) {
        expect(RegistrationRules.jobTitleOther(v, isOther: false), isNull);
      }
    });
  });

  group('RegistrationInput', () {
    RegistrationInput build({bool isOther = true, String other = ' Chef '}) =>
        RegistrationInput(
          name: '  Sara  ',
          email: ' s@x.io ',
          password: ' pass word ',
          phone: ' ٠٥٠١٢٣٤٥٦٧ ',
          location: ' Riyadh ',
          jobTitleId: 3,
          jobTitleIsOther: isOther,
          jobTitleOther: other,
        );

    test('trims text, normalises digits, keeps the password verbatim', () {
      final input = build();
      expect(input.name, 'Sara');
      expect(input.email, 's@x.io');
      expect(input.phone, '0501234567');
      expect(input.location, 'Riyadh');
      expect(input.password, ' pass word ');
      expect(input.jobTitleOther, 'Chef');
    });

    test('free-text title is dropped unless "Other" is selected', () {
      expect(build(isOther: false).jobTitleOther, isNull);
    });
  });
}
