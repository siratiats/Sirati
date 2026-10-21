import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';

void main() {
  late AppLocalizations ar;
  late AppLocalizations en;

  setUpAll(() async {
    ar = await AppLocalizations.delegate.load(const Locale('ar'));
    en = await AppLocalizations.delegate.load(const Locale('en'));
  });

  test('generated catalogs cover Arabic and English', () {
    expect(AppLocalizations.supportedLocales, contains(const Locale('ar')));
    expect(AppLocalizations.supportedLocales, contains(const Locale('en')));
    expect(AppLocalizations.delegate.isSupported(const Locale('ar', 'SA')),
        isTrue);
    expect(AppLocalizations.delegate.isSupported(const Locale('en', 'US')),
        isTrue);
  });

  test('parameter interpolation substitutes arbitrary names and scores', () {
    final names = <String>[
      'سارة',
      'Nora',
      'José María',
      'محمد علي القحطاني',
      'A' * 24,
    ];
    for (final name in names) {
      expect(ar.greetingName(name), contains(name));
      expect(en.greetingName(name), contains(name));
      expect(ar.greetingName(name), isNot(contains('{')));
      expect(en.greetingName(name), isNot(contains('{')));
    }

    for (final score in <int>[0, 1, 42, 87, 100, 999]) {
      expect(ar.atsScoreLabel(score), contains('$score'));
      expect(en.atsScoreLabel(score), contains('$score'));
      expect(ar.atsScoreLabel(score), isNot(contains('{')));
    }
  });

  test(
      'Arabic six-form plurals format for arbitrary counts without leftover ICU',
      () {
    const counts = <int>[
      0,
      1,
      2,
      3,
      4,
      5,
      10,
      11,
      12,
      20,
      99,
      100,
      101,
      234,
      1000,
    ];
    for (final count in counts) {
      final cvs = ar.cvCount(count);
      final analyses = ar.analysisCount(count);
      expect(cvs, isNot(contains('{')));
      expect(analyses, isNot(contains('{')));
      expect(() => ar.cvCount(count), returnsNormally);
      expect(() => en.cvCount(count), returnsNormally);
      expect(() => ar.analysisCount(count), returnsNormally);
      expect(() => en.analysisCount(count), returnsNormally);
    }

    expect(ar.cvCount(0), contains('لا'));
    expect(ar.cvCount(1), contains('سيرة'));
    expect(ar.cvCount(2), contains('سيرتان'));
    expect(en.cvCount(1), contains('1'));
    expect(en.cvCount(7), contains('7'));
  });

  test('common chrome strings are non-empty in both catalogs', () {
    expect(ar.appTitle, isNotEmpty);
    expect(en.appTitle, isNotEmpty);
    expect(ar.somethingWentWrong, isNot(contains('Exception')));
    expect(en.safeRetryMessage, isNot(contains('null')));
  });
}
