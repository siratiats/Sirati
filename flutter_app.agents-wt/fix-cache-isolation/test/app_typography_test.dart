import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/theme/app_typography.dart';

void main() {
  test('scale covers display through label', () {
    expect(AppTypography.all, hasLength(13));
    expect(AppTypography.displayLg.size, 32);
    expect(AppTypography.headlineLg.size, 24);
    expect(AppTypography.titleLg.size, 18);
    expect(AppTypography.bodyMd.size, 14);
    // 12, not 11: 11px Arabic sits at the readability floor (SIRATI-92).
    expect(AppTypography.labelSm.size, 12);
  });

  test('Arabic line-height is taller than Latin at every token', () {
    for (final token in AppTypography.all) {
      expect(
        token.heightArabic,
        greaterThan(token.heightLatin),
        reason: 'size ${token.size}',
      );
    }
  });

  test('resolve applies script-specific height and IBM Plex', () {
    final latin = AppTypography.bodyMd.resolve(arabic: false);
    final arabic = AppTypography.bodyMd.resolve(arabic: true);

    expect(latin.height, AppTypography.bodyMd.heightLatin);
    expect(arabic.height, AppTypography.bodyMd.heightArabic);
    expect(latin.fontFamily, 'IBM Plex Sans Arabic');
    expect(arabic.fontFamily, 'IBM Plex Sans Arabic');
    expect(latin.fontSize, arabic.fontSize);
  });

  testWidgets('AppTypography.of follows Directionality', (tester) async {
    late TextStyle ltrStyle;
    late TextStyle rtlStyle;

    await tester.pumpWidget(
      MaterialApp(
        home: Column(
          children: [
            Directionality(
              textDirection: TextDirection.ltr,
              child: Builder(
                builder: (context) {
                  ltrStyle = AppTypography.of(context, AppTypography.bodyLg);
                  return const SizedBox.shrink();
                },
              ),
            ),
            Directionality(
              textDirection: TextDirection.rtl,
              child: Builder(
                builder: (context) {
                  rtlStyle = AppTypography.of(context, AppTypography.bodyLg);
                  return const SizedBox.shrink();
                },
              ),
            ),
          ],
        ),
      ),
    );

    expect(ltrStyle.height, AppTypography.bodyLg.heightLatin);
    expect(rtlStyle.height, AppTypography.bodyLg.heightArabic);
  });
}
