import 'package:flutter/material.dart';
import 'package:sirati/features/cv_builder/domain/cv_markdown.dart';
import 'package:sirati/shared/theme/app_theme.dart';

/// Renders parsed CV blocks in the ambient direction.
class CvDocumentBlocks extends StatelessWidget {
  const CvDocumentBlocks({super.key, required this.blocks});

  final List<CvBlock> blocks;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    final body = TextStyle(fontSize: 12, color: c.textPrimary, height: 1.6);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final block in blocks)
          switch (block) {
            CvHeading(:final text) => Padding(
                padding: const EdgeInsets.only(top: 14, bottom: 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Semantics(
                      header: true,
                      child: Text(
                        text,
                        textAlign: TextAlign.start,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: c.textPrimary,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Divider(height: 1, color: c.border),
                  ],
                ),
              ),
            CvStrongLine(:final text) => Padding(
                padding: const EdgeInsets.only(bottom: 3),
                child: Text(
                  text,
                  textAlign: TextAlign.start,
                  style: body.copyWith(fontWeight: FontWeight.w600),
                ),
              ),
            CvBullet(:final text) => Padding(
                padding: const EdgeInsets.only(bottom: 3),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ExcludeSemantics(
                      child: Text(
                        '•  ',
                        style: body.copyWith(color: c.primaryInk),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        text,
                        textAlign: TextAlign.start,
                        style: body.copyWith(height: 1.5),
                      ),
                    ),
                  ],
                ),
              ),
            CvParagraph(:final text, :final isContact) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  text,
                  textDirection: isContact ? TextDirection.ltr : null,
                  textAlign: TextAlign.start,
                  style: body,
                ),
              ),
          },
      ],
    );
  }
}

/// Coloured status strip above the CV.
class CvStatusBanner extends StatelessWidget {
  const CvStatusBanner.success({
    super.key,
    required this.message,
    this.trailing,
  })  : _success = true,
        icon = Icons.auto_awesome;

  const CvStatusBanner.warning({super.key, required this.message})
      : _success = false,
        trailing = null,
        icon = Icons.info_outline;

  final bool _success;
  final String message;
  final IconData icon;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final c = context.sirati;
    // Gated container pairs (AA in both themes).
    final background = _success ? c.successLight : c.warningLight;
    final ink = _success ? c.onSuccessContainer : c.onWarningContainer;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: ink, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              textAlign: TextAlign.start,
              style: TextStyle(
                fontSize: 13,
                height: 1.4,
                color: ink,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: 8),
            DefaultTextStyle.merge(
              style: TextStyle(color: ink, fontWeight: FontWeight.w700),
              child: trailing!,
            ),
          ],
        ],
      ),
    );
  }
}
