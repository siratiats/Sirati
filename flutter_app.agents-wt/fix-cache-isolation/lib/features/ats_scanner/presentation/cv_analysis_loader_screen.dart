import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/ats_scanner/presentation/analysis_result_screen.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/services/cv_api_service_provider.dart';
import 'package:sirati/shared/theme/app_theme.dart';
import 'package:sirati/shared/widgets/empty_state.dart';
import 'package:sirati/shared/widgets/loading/branded_loader.dart';

/// Deep-link entry for `/analysis/:id` — loads the analysis then shows the real screen.
class CvAnalysisLoaderScreen extends ConsumerStatefulWidget {
  final int analysisId;

  const CvAnalysisLoaderScreen({
    super.key,
    required this.analysisId,
  });

  @override
  ConsumerState<CvAnalysisLoaderScreen> createState() =>
      _CvAnalysisLoaderScreenState();
}

class _CvAnalysisLoaderScreenState
    extends ConsumerState<CvAnalysisLoaderScreen> {
  late Future<CvAnalysis> _future;

  @override
  void initState() {
    super.initState();
    _future = ref.read(cvApiServiceProvider).getAnalysis(widget.analysisId);
  }

  @override
  Widget build(BuildContext context) {
    final english = AppLocale.isEnglish(context);
    return FutureBuilder<CvAnalysis>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasData) {
          return AnalysisResultScreen(analysis: snapshot.data!);
        }
        if (snapshot.hasError) {
          return Scaffold(
            backgroundColor: context.sirati.background,
            appBar: AppBar(),
            body: AppErrorState(
              english: english,
              message: snapshot.error is ApiException
                  ? (snapshot.error as ApiException).displayMessage
                  : (english
                      ? 'Could not open this analysis.'
                      : 'تعذر فتح هذا التحليل.'),
              onRetry: () {
                setState(() {
                  _future = ref
                      .read(cvApiServiceProvider)
                      .getAnalysis(widget.analysisId);
                });
              },
            ),
          );
        }
        return Scaffold(
          backgroundColor: context.sirati.background,
          appBar: AppBar(
            // Same as the page: a transparent bar over `background`.
            backgroundColor: context.sirati.background,
            elevation: 0,
          ),
          body: const Center(child: BrandedLoader()),
        );
      },
    );
  }
}
