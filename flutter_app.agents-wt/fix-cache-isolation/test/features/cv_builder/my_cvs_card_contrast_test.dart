import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/features/cv_builder/domain/cv_list.dart';
import 'package:sirati/features/cv_builder/presentation/my_cvs/cv_document_card.dart';
import 'package:sirati/shared/theme/app_theme.dart';

import '../../support/contrast_probe.dart';

void main() {
  for (final (mode, theme) in [
    ('light', AppTheme.lightFor(arabic: false)),
    ('dark', AppTheme.darkFor(arabic: false)),
  ]) {
    for (final draft in [false, true]) {
      testWidgets('CV card texts are AA in $mode mode (draft: $draft)',
          (tester) async {
        final item = CvListItem.fromJson({
          'id': 1,
          'title': 'Backend Developer CV',
          'badge': draft ? 'Draft' : 'ATS 92',
          'is_draft': draft,
          'updated_label': '2 days ago',
        });
        await tester.pumpWidget(MaterialApp(
          theme: theme,
          home: Scaffold(
            body: ListView(
              children: [
                CvDocumentCard(
                  item: item,
                  onEdit: () {},
                  onDelete: () {},
                  onDownload: () {},
                ),
                CreateCvCard(english: true, onTap: () {}),
              ],
            ),
          ),
        ));

        expectAllTextAa('$mode, draft: $draft');
      });
    }
  }
}
