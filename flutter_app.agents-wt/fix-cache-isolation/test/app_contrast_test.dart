import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/shared/theme/app_contrast.dart';
import 'package:sirati/shared/theme/sirati_colors.dart';

void main() {
  for (final entry in {
    'light': SiratiColors.light,
    'dark': SiratiColors.dark,
  }.entries) {
    group('${entry.key} semantic tokens', () {
      final c = entry.value;

      test('aliases map to role colors', () {
        expect(c.onSurface, c.textPrimary);
        expect(c.onSurfaceVariant, c.textSecondary);
        expect(c.onSurfaceMuted, c.textHint);
        expect(c.outline, c.border);
        expect(c.outlineVariant, c.borderStrong);
      });

      test('WCAG AA contrast pairs', () {
        final failures = <String>[];
        for (final pair in AppContrast.pairs(c)) {
          if (!pair.passes) {
            failures.add(
              '${pair.name}: ${pair.ratio.toStringAsFixed(2)} < ${pair.minRatio}',
            );
          }
        }
        expect(failures, isEmpty, reason: failures.join('\n'));
      });

      test('profile screen text tokens on surface pass WCAG AA (SIRATI-63)', () {
        // Name & field labels use textPrimary on surface
        final nameRatio = AppContrast.ratio(c.textPrimary, c.surface);
        expect(nameRatio, greaterThanOrEqualTo(4.5),
            reason: 'Profile name / labels on surface must be >= 4.5:1');

        // Headline & secondary text use textSecondary on surface
        final headlineRatio = AppContrast.ratio(c.textSecondary, c.surface);
        expect(headlineRatio, greaterThanOrEqualTo(4.5),
            reason: 'Profile headline / secondary text on surface must be >= 4.5:1');
      });
    });
  }
}
