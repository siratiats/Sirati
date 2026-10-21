import 'dart:async';
import 'dart:ui';

import 'package:flutter/foundation.dart';
import 'package:share_plus/share_plus.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/cv_export/utils/export_file_namer.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/models/generated_cv.dart';

enum ExportStatus {
  idle,
  loadingPreview,
  previewReady,
  downloading,
  ready,
  error,
  premiumLocked,
}

class PdfExportState {
  final ExportStatus status;
  final double progress;
  final Uint8List? pdfBytes;
  final String filename;
  final String? errorMessage;
  final String? previewHtml;
  final bool isWatermarked;
  final String exportLanguage;
  final bool latinFallback;
  final CvTemplate template;

  const PdfExportState({
    required this.status,
    required this.progress,
    required this.pdfBytes,
    required this.filename,
    this.errorMessage,
    this.previewHtml,
    required this.isWatermarked,
    required this.exportLanguage,
    required this.latinFallback,
    required this.template,
  });

  PdfExportState copyWith({
    ExportStatus? status,
    double? progress,
    Uint8List? pdfBytes,
    String? filename,
    String? errorMessage,
    String? previewHtml,
    bool? isWatermarked,
    String? exportLanguage,
    bool? latinFallback,
    CvTemplate? template,
  }) {
    return PdfExportState(
      status: status ?? this.status,
      progress: progress ?? this.progress,
      pdfBytes: pdfBytes ?? this.pdfBytes,
      filename: filename ?? this.filename,
      errorMessage: errorMessage,
      previewHtml: previewHtml ?? this.previewHtml,
      isWatermarked: isWatermarked ?? this.isWatermarked,
      exportLanguage: exportLanguage ?? this.exportLanguage,
      latinFallback: latinFallback ?? this.latinFallback,
      template: template ?? this.template,
    );
  }
}

class PdfExportController extends ChangeNotifier {
  final ApiClient _apiClient;
  final GeneratedCv _cv;
  late PdfExportState _state;

  PdfExportController({
    required ApiClient apiClient,
    required GeneratedCv cv,
    required CvTemplate initialTemplate,
    String? initialLanguage,
    bool initialLatinFallback = false,
  })  : _apiClient = apiClient,
        _cv = cv {
    final lang = initialLanguage ?? cv.language;
    final initialFilename = ExportFileNamer.formatFilename(
      fullName: cv.fullName,
      targetJobTitle: cv.targetJobTitle,
      language: lang,
      latinFallback: initialLatinFallback,
    );

    _state = PdfExportState(
      status: ExportStatus.idle,
      progress: 0.0,
      pdfBytes: null,
      filename: initialFilename,
      isWatermarked: false,
      exportLanguage: lang,
      latinFallback: initialLatinFallback,
      template: initialTemplate,
    );
  }

  PdfExportState get state => _state;

  void setTemplate(CvTemplate template) {
    if (_state.template.id == template.id) return;
    _state = _state.copyWith(
      template: template,
      pdfBytes: null,
      status: ExportStatus.idle,
    );
    notifyListeners();
  }

  void setExportLanguage(String language) {
    if (_state.exportLanguage == language) return;
    final newFilename = ExportFileNamer.formatFilename(
      fullName: _cv.fullName,
      targetJobTitle: _cv.targetJobTitle,
      language: language,
      latinFallback: _state.latinFallback,
    );
    _state = _state.copyWith(
      exportLanguage: language,
      filename: newFilename,
      pdfBytes: null,
      status: ExportStatus.idle,
    );
    notifyListeners();
  }

  void setLatinFallback(bool value) {
    if (_state.latinFallback == value) return;
    final newFilename = ExportFileNamer.formatFilename(
      fullName: _cv.fullName,
      targetJobTitle: _cv.targetJobTitle,
      language: _state.exportLanguage,
      latinFallback: value,
    );
    _state = _state.copyWith(
      latinFallback: value,
      filename: newFilename,
    );
    notifyListeners();
  }

  Future<void> loadPreview() async {
    _state = _state.copyWith(status: ExportStatus.loadingPreview);
    notifyListeners();

    try {
      final res = await _apiClient.getJson(
        '/generated-cvs/${_cv.id}/preview?template=${_state.template.slug}&language=${_state.exportLanguage}',
      );
      final data = res['data'] as Map<String, dynamic>? ?? {};
      final html = data['html']?.toString() ?? '';
      final isWatermarked = data['is_watermarked'] == true;

      _state = _state.copyWith(
        status: ExportStatus.previewReady,
        previewHtml: html,
        isWatermarked: isWatermarked,
      );
    } catch (e) {
      _state = _state.copyWith(
        status: ExportStatus.error,
        errorMessage: e.toString(),
      );
    }
    notifyListeners();
  }

  Future<bool> exportPdf() async {
    _state = _state.copyWith(
      status: ExportStatus.downloading,
      progress: 0.15,
    );
    notifyListeners();

    try {
      _state = _state.copyWith(progress: 0.45);
      notifyListeners();

      final bytes = await _apiClient.getBytes(
        '/generated-cvs/${_cv.id}/download?template=${_state.template.slug}&language=${_state.exportLanguage}',
      );

      final uint8Bytes = Uint8List.fromList(bytes);

      _state = _state.copyWith(
        status: ExportStatus.ready,
        progress: 1.0,
        pdfBytes: uint8Bytes,
      );
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 403 || e.message.contains('premium')) {
        _state = _state.copyWith(
          status: ExportStatus.premiumLocked,
          errorMessage: e.message,
        );
      } else {
        _state = _state.copyWith(
          status: ExportStatus.error,
          errorMessage: e.message,
        );
      }
      notifyListeners();
      return false;
    } catch (e) {
      _state = _state.copyWith(
        status: ExportStatus.error,
        errorMessage: e.toString(),
      );
      notifyListeners();
      return false;
    }
  }

  Future<void> sharePdf({Rect? sharePositionOrigin}) async {
    if (_state.pdfBytes == null) {
      final success = await exportPdf();
      if (!success || _state.pdfBytes == null) return;
    }

    final file = XFile.fromData(
      _state.pdfBytes!,
      name: _state.filename,
      mimeType: 'application/pdf',
    );

    await Share.shareXFiles(
      [file],
      text: _cv.fullName,
      subject: _state.filename,
      sharePositionOrigin: sharePositionOrigin,
    );
  }
}
