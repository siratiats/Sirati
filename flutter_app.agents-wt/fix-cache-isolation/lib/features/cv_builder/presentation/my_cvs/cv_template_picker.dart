import 'package:flutter/material.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/template_preview.dart';

/// Bottom sheet listing CV designs; pops with the chosen [CvTemplate].
class CvTemplatePicker extends StatelessWidget {
  const CvTemplatePicker({
    super.key,
    required this.templates,
    required this.english,
  });

  final List<CvTemplate> templates;
  final bool english;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(18, 6, 18, 18),
        shrinkWrap: true,
        itemCount: templates.length + 1,
        itemBuilder: (context, index) {
          if (index == 0) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                english ? 'Choose CV design' : 'اختر تصميم السيرة',
                textAlign: TextAlign.start,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: context.sirati.textPrimary,
                ),
              ),
            );
          }
          final template = templates[index - 1];
          return Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              onTap: () => Navigator.pop(context, template),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: BorderSide(color: context.sirati.border),
              ),
              leading: TemplatePreview(
                imageUrl: template.previewImageUrl,
                errorFallback: const SiratiMark(size: 22),
              ),
              title: Text(
                template.name,
                textAlign: TextAlign.start,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                template.isDefault
                    ? (english ? 'Default template' : 'القالب الافتراضي')
                    : template.slug,
                textAlign: TextAlign.start,
              ),
              trailing: const Icon(Icons.download_rounded),
            ),
          );
        },
      ),
    );
  }
}
