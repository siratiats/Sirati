import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/routing/app_routes.dart';
import 'package:sirati/core/utils/ai_error_message.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/application/improved_cv_controller.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result/recommendations_tab.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result/score_tab.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/app_snack_bar.dart';
import 'package:sirati/shared/widgets/language_toggle.dart';
import 'package:sirati/shared/widgets/loading/ai_progress_overlay.dart';
import 'package:sirati/shared/widgets/submit_button.dart';

/// Analysis result: score + criteria tab, recommendations tab, and a
/// "generate improved CV" action driven by [ImprovedCvController].
class AnalysisResultScreen extends ConsumerWidget {
  const AnalysisResultScreen({super.key, required this.analysis});

  final CvAnalysis analysis;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final english = AppLocale.isEnglish(context);
    final isGenerating = ref.watch(improvedCvControllerProvider(analysis.id));

    return Directionality(
      textDirection: AppLocale.direction(context),
      child: DefaultTabController(
        length: 2,
        child: Scaffold(
          backgroundColor: context.sirati.background,
          appBar: AppBar(
            title: Text(english ? 'Analysis Results' : 'نتائج التحليل'),
            actions: const [LanguageToggle()],
            bottom: TabBar(
              tabs: [
                Tab(text: english ? 'Score & Criteria' : 'الدرجة والمعايير'),
                Tab(text: english ? 'Recommendations' : 'التوصيات'),
              ],
            ),
          ),
          bottomNavigationBar: SafeArea(
            child: Container(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
              decoration: BoxDecoration(
                color: context.sirati.surface,
                border: Border(top: BorderSide(color: context.sirati.border)),
                boxShadow: context.sirati.softShadow,
              ),
              child: SubmitButton(
                label: english ? 'Generate Improved CV' : 'توليد سيرة محسّنة',
                loadingLabel: english ? 'Generating...' : 'جارٍ التوليد...',
                isLoading: isGenerating,
                icon: Icons.auto_awesome_rounded,
                onPressed: isGenerating ? null : () => _generate(context, ref),
              ),
            ),
          ),
          body: TabBarView(
            children: [
              ScoreTab(analysis: analysis, english: english),
              RecommendationsTab(
                strengths: analysis.strengths,
                quickWins: analysis.quickWins,
                english: english,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _generate(BuildContext context, WidgetRef ref) async {
    if (!context.mounted) return;
    final english = AppLocale.isEnglish(context);
    final controller =
        ref.read(improvedCvControllerProvider(analysis.id).notifier);

    final progress = await AiProgressOverlay.show(
      context,
      kind: AiProgressKind.generation,
      english: english,
      onCancelled: controller.cancel,
    );
    final outcome =
        await controller.generate(isCancelled: () => progress.isCancelled);
    await progress.dismiss();
    if (!context.mounted) return;

    switch (outcome) {
      case ImprovedCvCancelled():
        return;
      case ImprovedCvFailed(:final error):
        if (error is ApiException) {
          AppSnackBar.fromException(context, error);
        } else {
          AppSnackBar.error(
            context,
            english
                ? 'An unexpected error occurred while generating the CV.'
                : 'حدث خطأ غير متوقع أثناء توليد السيرة.',
          );
        }
      case ImprovedCvReady(
          :final generatedCvId,
          :final timedOut,
          :final aiFailed,
          :final aiError,
        ):
        if (timedOut) {
          AppSnackBar.warning(
            context,
            english
                ? 'AI is still working. A usable local CV is ready, and you can retry generation later.'
                : 'لا يزال الذكاء الاصطناعي يعمل. نسخة محلية من سيرتك جاهزة، ويمكنك إعادة محاولة التوليد لاحقاً.',
          );
        } else if (aiFailed) {
          AppSnackBar.error(
            context,
            AiErrorMessage.generationFailed(aiError, english: english),
            actionLabel: english ? 'Retry' : 'إعادة المحاولة',
            onAction: () => _generate(context, ref),
          );
        }
        unawaited(Navigator.of(context).pushNamed(AppRoutes.cv(generatedCvId)));
    }
  }
}
