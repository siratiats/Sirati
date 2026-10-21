import 'package:http/http.dart' as http;
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/models/cv_template.dart';
import 'package:sirati/shared/models/generated_cv.dart';

class CvApiService {
  CvApiService({
    ApiClient? apiClient,
    Future<void> Function(Duration)? pollingDelay,
    // Must stay in sync with App\Services\Ai\AiTimeouts::CLIENT_POLL_SECONDS
    // and remain strictly greater than JOB_SECONDS (180).
    this.pollingTimeout = const Duration(seconds: 210),
  })  : _pollingDelay = pollingDelay ?? Future<void>.delayed,
        _apiClient = apiClient ??
            ApiClient(tokenProvider: const AuthTokenStore().readToken);

  final ApiClient _apiClient;
  final Future<void> Function(Duration) _pollingDelay;
  final Duration pollingTimeout;

  Future<CvAnalysis> submitAnalysis({
    required String targetJobTitle,
    required String resumeText,
    String? jobDescription,
    http.MultipartFile? resumeFile,
    String? idempotencyKey,
  }) async {
    final trimmedJd = jobDescription?.trim() ?? '';
    final response = await _apiClient.postMultipart(
      '/cv-analyses',
      fields: {
        'target_job_title': targetJobTitle,
        if (resumeText.trim().isNotEmpty) 'resume_text': resumeText.trim(),
        if (trimmedJd.isNotEmpty) 'job_description': trimmedJd,
      },
      file: resumeFile,
      extraHeaders: {
        ..._idempotencyHeaders(idempotencyKey),
        ...asyncAiHeaders,
      },
    );

    return CvAnalysis.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<CvAnalysis> getAnalysis(int id) async {
    final response = await _apiClient.getJson('/cv-analyses/$id');
    return CvAnalysis.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<AiPollResult<CvAnalysis>> pollAnalysis(
    CvAnalysis initial, {
    bool Function()? isCancelled,
    bool Function()? isPaused,
    void Function(CvAnalysis value)? onProgress,
  }) {
    return _poll<CvAnalysis>(
      initial: initial,
      statusOf: (value) => value.aiStatus,
      refresh: () => getAnalysis(initial.id),
      isCancelled: isCancelled,
      isPaused: isPaused,
      onProgress: onProgress,
    );
  }

  Future<List<CvAnalysis>> listAnalyses() async {
    final page = await listAnalysesPage();
    return page.items;
  }

  Future<PagedList<CvAnalysis>> listAnalysesPage({int page = 1}) async {
    final response =
        await _apiClient.getJson('/cv-analyses?page=$page&per_page=20');
    return PagedList(
      items: (response['data'] as List? ?? [])
          .whereType<Map<String, dynamic>>()
          .map(CvAnalysis.fromJson)
          .toList(),
      currentPage: _intMeta(response, 'current_page', page),
      lastPage: _intMeta(response, 'last_page', 1),
    );
  }

  /// Creates a CV and returns immediately with `ai_status: queued`.
  ///
  /// `X-Sirati-Async` is what makes the server queue the OpenAI call instead of
  /// running it inside the request. Without it the POST blocks for the whole
  /// generation (15-30s, longer in Arabic, up to ~75s if the provider fallback
  /// kicks in) while holding a PHP worker. Callers must follow this with
  /// [pollGeneratedCv].
  Future<GeneratedCv> generateCv(
    Map<String, dynamic> payload, {
    String? idempotencyKey,
  }) async {
    final response = await _apiClient.postJson(
      '/generated-cvs',
      payload,
      extraHeaders: {
        ..._idempotencyHeaders(idempotencyKey),
        ...asyncAiHeaders,
      },
    );
    return GeneratedCv.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> enhanceCvField({
    required String field,
    required String draft,
    required String jobTitle,
    required String language,
  }) async {
    final response = await _apiClient.postJson(
      '/generated-cvs/enhance-field',
      {
        'field': field,
        'draft': draft,
        'job_title': jobTitle,
        'language': language,
      },
    );

    final data = response['data'];
    if (data is! Map<String, dynamic>) return const {};

    return {
      'enhanced_text': data['enhanced_text']?.toString() ?? '',
      'changes_made': _stringList(data['changes_made']),
      'missing_facts': _stringList(data['missing_facts']),
      'ats_keywords_added': _stringList(data['ats_keywords_added']),
      'unverified_claims': _unverifiedClaims(data['unverified_claims']),
    };
  }

  Future<Map<String, dynamic>> enhanceJobDescription({
    required String targetJobTitle,
    required String jobDescription,
    required String language,
  }) async {
    final response = await _apiClient.postJson(
      '/generated-cvs/enhance-job-description',
      {
        'target_job_title': targetJobTitle,
        'job_description': jobDescription,
        'language': language,
      },
    );

    final data = response['data'];
    return data is Map<String, dynamic> ? data : const {};
  }

  List<String> _stringList(dynamic value) => value is List
      ? value
          .map((item) => item.toString().trim())
          .where((item) => item.isNotEmpty)
          .toList()
      : const [];

  List<Map<String, String>> _unverifiedClaims(dynamic value) {
    if (value is! List) return const [];

    return value
        .whereType<Map>()
        .map((claim) => {
              'text': claim['text']?.toString().trim() ?? '',
              'kind': claim['kind']?.toString() ?? '',
            })
        .where((claim) =>
            claim['text']!.isNotEmpty &&
            (claim['kind'] == 'date' || claim['kind'] == 'employer'))
        .toList();
  }

  Future<GeneratedCv> updateGeneratedCv(
    int id,
    Map<String, dynamic> payload,
  ) async {
    final response = await _apiClient.putJson(
      '/generated-cvs/$id',
      payload,
      extraHeaders: asyncAiHeaders,
    );
    return GeneratedCv.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> deleteGeneratedCv(int id) async {
    await _apiClient.deleteJson('/generated-cvs/$id');
  }

  Future<GeneratedCv> generateCvFromAnalysis({
    required int analysisId,
    required Map<String, dynamic> overrides,
    String? idempotencyKey,
  }) async {
    final response = await _apiClient.postJson(
      '/cv-analyses/$analysisId/generated-cv',
      overrides,
      extraHeaders: {
        ..._idempotencyHeaders(idempotencyKey),
        ...asyncAiHeaders,
      },
    );

    return GeneratedCv.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<GeneratedCv> getGeneratedCv(int id) async {
    final response = await _apiClient.getJson('/generated-cvs/$id');
    return GeneratedCv.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<AiPollResult<GeneratedCv>> pollGeneratedCv(
    GeneratedCv initial, {
    bool Function()? isCancelled,
    bool Function()? isPaused,
    void Function(GeneratedCv value)? onProgress,
  }) {
    return _poll<GeneratedCv>(
      initial: initial,
      statusOf: (value) => value.aiStatus,
      refresh: () => getGeneratedCv(initial.id),
      isCancelled: isCancelled,
      isPaused: isPaused,
      onProgress: onProgress,
    );
  }

  Future<List<GeneratedCv>> listGeneratedCvs() async {
    final page = await listGeneratedCvsPage();
    return page.items;
  }

  Future<PagedList<GeneratedCv>> listGeneratedCvsPage({int page = 1}) async {
    final response =
        await _apiClient.getJson('/generated-cvs?page=$page&per_page=20');
    return PagedList(
      items: (response['data'] as List? ?? [])
          .whereType<Map<String, dynamic>>()
          .map(GeneratedCv.fromJson)
          .toList(),
      currentPage: _intMeta(response, 'current_page', page),
      lastPage: _intMeta(response, 'last_page', 1),
    );
  }

  /// Opt-in header that makes the API queue AI work and return immediately.
  static const Map<String, String> asyncAiHeaders = {'X-Sirati-Async': '1'};

  Map<String, String> _idempotencyHeaders(String? key) {
    if (key == null || key.isEmpty) return const {};
    return {'Idempotency-Key': key};
  }

  int _intMeta(Map<String, dynamic> response, String key, int fallback) {
    final meta = response['meta'];
    if (meta is! Map) return fallback;
    final value = meta[key];
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  Future<List<CvTemplate>> listCvTemplates({required bool english}) async {
    final response = await _apiClient.getJson(
      '/mobile/cv-templates?lang=${english ? 'en' : 'ar'}',
    );
    final data = response['data'];
    final items = data is Map<String, dynamic> ? data['items'] : null;

    return (items as List? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(CvTemplate.fromJson)
        .where((template) => template.slug.isNotEmpty)
        .toList();
  }

  String pdfUrlForTemplate(GeneratedCv cv, String? templateSlug) {
    final baseUrl = (cv.templatePdfUrl?.isNotEmpty ?? false)
        ? cv.templatePdfUrl!
        : (cv.pdfUrl ?? '');
    if (baseUrl.isEmpty) return '';

    final uri = Uri.parse(baseUrl);
    if (templateSlug == null || templateSlug.isEmpty) {
      return uri.toString();
    }

    return uri.replace(queryParameters: {
      ...uri.queryParameters,
      'template': templateSlug,
    }).toString();
  }

  /// Polls until [statusOf] leaves the pending states.
  ///
  /// [onProgress] fires on every refreshed value so the caller can surface real
  /// job state (queued vs processing) instead of a timer-driven guess.
  Future<AiPollResult<T>> _poll<T>({
    required T initial,
    required String Function(T value) statusOf,
    required Future<T> Function() refresh,
    bool Function()? isCancelled,
    bool Function()? isPaused,
    void Function(T value)? onProgress,
  }) async {
    var current = initial;
    var elapsed = Duration.zero;

    while (AiStatus.isPending(statusOf(current))) {
      if (isCancelled?.call() ?? false) {
        return AiPollResult(value: current, cancelled: true);
      }

      if (isPaused?.call() ?? false) {
        await _pollingDelay(const Duration(milliseconds: 250));
        continue;
      }

      if (elapsed >= pollingTimeout) {
        return AiPollResult(value: current, timedOut: true);
      }

      final delay = elapsed < const Duration(seconds: 30)
          ? const Duration(seconds: 2)
          : const Duration(seconds: 5);
      await _pollingDelay(delay);
      elapsed += delay;

      if (isCancelled?.call() ?? false) {
        return AiPollResult(value: current, cancelled: true);
      }
      if (isPaused?.call() ?? false) {
        continue;
      }

      current = await refresh();
      onProgress?.call(current);
    }

    return AiPollResult(value: current);
  }
}

class PagedList<T> {
  final List<T> items;
  final int currentPage;
  final int lastPage;

  const PagedList({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  bool get hasMore => currentPage < lastPage;
}

class AiPollResult<T> {
  final T value;
  final bool timedOut;
  final bool cancelled;

  const AiPollResult({
    required this.value,
    this.timedOut = false,
    this.cancelled = false,
  });
}
