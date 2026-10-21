import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sirati/core/network/api_client.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/core/storage/auth_token_store.dart';
import 'package:sirati/shared/models/ai_status.dart';
import 'package:sirati/shared/models/cv_analysis.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/cv_api_service.dart';

void main() {
  group('CvApiService', () {
    test('lists CV analyses from Laravel resource collection JSON', () async {
      final service = CvApiService(
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            expect(request.method, 'GET');
            expect(request.url.path, '/api/cv-analyses');
            expect(request.headers['x-sirati-async'], '1');

            return _jsonResponse({
              'data': [
                _analysisJson(id: 7, targetJobTitle: 'Laravel Developer'),
              ],
            });
          }),
        ),
      );

      final analyses = await service.listAnalyses();

      expect(analyses, hasLength(1));
      expect(analyses.single.id, 7);
      expect(analyses.single.targetJobTitle, 'Laravel Developer');
      expect(analyses.single.criteria.single.label, 'Keywords');
      expect(analyses.single.inputMethodLabel, 'لصق نص');
    });

    test('submits analysis as multipart request', () async {
      final service = CvApiService(
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            expect(request.method, 'POST');
            expect(request.url.path, '/api/cv-analyses');
            expect(request.headers['content-type'],
                contains('multipart/form-data'));
            expect(request.headers['x-sirati-async'], '1');

            final body = request.body;
            expect(body, contains('name="target_job_title"'));
            expect(body, contains('Data Analyst'));
            expect(body, contains('name="resume_text"'));
            expect(body, contains('Resume text'));

            return _jsonResponse(
                {'data': _analysisJson(targetJobTitle: 'Data Analyst')},
                statusCode: 201);
          }),
        ),
      );

      final analysis = await service.submitAnalysis(
        targetJobTitle: 'Data Analyst',
        resumeText: '  Resume text  ',
      );

      expect(analysis.targetJobTitle, 'Data Analyst');
      expect(analysis.scoreTotal, 82);
    });

    test('generates CV from JSON payload', () async {
      final service = CvApiService(
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            expect(request.method, 'POST');
            expect(request.url.path, '/api/generated-cvs');
            expect(request.headers['x-sirati-async'], '1');
            expect(
                request.headers['content-type'], contains('application/json'));

            final payload = jsonDecode(request.body) as Map<String, dynamic>;
            expect(payload['full_name'], 'Salem Sayer');
            expect(payload['language'], 'en');

            return _jsonResponse({'data': _generatedCvJson()}, statusCode: 201);
          }),
        ),
      );

      final generatedCv = await service.generateCv({
        'full_name': 'Salem Sayer',
        'target_job_title': 'Backend Developer',
        'language': 'en',
        'skills_input': 'PHP, Laravel, APIs',
        'experience_input':
            'Built backend APIs for internal teams with measurable outcomes.',
        'education_input': 'BSc Computer Science',
      });

      expect(generatedCv.id, 9);
      expect(generatedCv.fullName, 'Salem Sayer');
      expect(
          generatedCv.pdfUrl, 'http://localhost:8000/api/generated-cvs/9/pdf');
    });

    test('throws displayable Laravel validation errors', () async {
      final service = CvApiService(
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            return _jsonResponse({
              'message': 'The resume text field is required.',
              'errors': {
                'resume_text': ['Paste resume text or upload a file.'],
              },
            }, statusCode: 422);
          }),
        ),
      );

      expect(
        () => service.submitAnalysis(
            targetJobTitle: 'Backend Developer', resumeText: ''),
        throwsA(
          isA<ApiException>().having(
            (exception) => exception.displayMessage,
            'displayMessage',
            'Paste resume text or upload a file.',
          ),
        ),
      );
    });

    test('polls every two seconds until queued work completes', () async {
      var refreshes = 0;
      final delays = <Duration>[];
      final service = CvApiService(
        pollingDelay: (delay) async => delays.add(delay),
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            refreshes++;
            final status =
                refreshes == 1 ? AiStatus.processing : AiStatus.completed;
            return _jsonResponse({
              'data': {
                ..._analysisJson(),
                'ai_status': status,
              },
            });
          }),
        ),
      );
      final initial = CvAnalysis.fromJson({
        ..._analysisJson(),
        'ai_status': AiStatus.queued,
      });

      final result = await service.pollAnalysis(initial);

      expect(result.value.aiStatus, AiStatus.completed);
      expect(result.timedOut, isFalse);
      expect(refreshes, 2);
      expect(delays, const [Duration(seconds: 2), Duration(seconds: 2)]);
    });

    test('polling gives up at the configured timeout', () async {
      var refreshes = 0;
      final service = CvApiService(
        pollingTimeout: const Duration(seconds: 4),
        pollingDelay: (_) async {},
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            refreshes++;
            return _jsonResponse({
              'data': {
                ..._analysisJson(),
                'ai_status': AiStatus.processing,
              },
            });
          }),
        ),
      );
      final initial = CvAnalysis.fromJson({
        ..._analysisJson(),
        'ai_status': AiStatus.queued,
      });

      final result = await service.pollAnalysis(initial);

      expect(result.timedOut, isTrue);
      expect(result.value.scoreTotal, 82);
      expect(refreshes, 2);
    });
  });

  test('preserves the Laravel message for HTTP 429 responses', () async {
    const message =
        'لقد وصلت إلى الحد المؤقت للطلبات. يرجى المحاولة مرة أخرى بعد قليل.';
    final apiClient = ApiClient(
      httpClient: MockClient((request) async {
        return _jsonResponse({
          'message': message,
          'code': 'ai_rate_limit_short',
        }, statusCode: 429);
      }),
    );

    expect(
      () => apiClient.postJson('/cv-analyses', const {}),
      throwsA(
        isA<ApiException>()
            .having(
              (exception) => exception.type,
              'type',
              ApiErrorType.rateLimited,
            )
            .having(
              (exception) => exception.displayMessage,
              'displayMessage',
              message,
            ),
      ),
    );
  });

  group('AuthApiService', () {
    test('logs in and stores the returned token', () async {
      final tokenStore = _MemoryAuthTokenStore();
      final service = AuthApiService(
        tokenStore: tokenStore,
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            expect(request.method, 'POST');
            expect(request.url.path, '/api/auth/login');

            final payload = jsonDecode(request.body) as Map<String, dynamic>;
            expect(payload['email'], 'salem@example.com');
            expect(payload['password'], 'password123');
            expect(payload['device_name'], 'sirati-mobile');

            return _jsonResponse({'data': _authSessionJson()});
          }),
        ),
      );

      final session = await service.login(
        email: 'salem@example.com',
        password: 'password123',
      );

      expect(session.token, 'plain-text-token');
      expect(session.user.email, 'salem@example.com');
      expect(await tokenStore.readToken(), 'plain-text-token');
    });

    test('registers and stores the returned token', () async {
      final tokenStore = _MemoryAuthTokenStore();
      final service = AuthApiService(
        tokenStore: tokenStore,
        apiClient: ApiClient(
          httpClient: MockClient((request) async {
            expect(request.method, 'POST');
            expect(request.url.path, '/api/auth/register');

            final payload = jsonDecode(request.body) as Map<String, dynamic>;
            expect(payload['name'], 'Salem Sayer');
            expect(payload['password_confirmation'], 'password123');

            return _jsonResponse({'data': _authSessionJson()}, statusCode: 201);
          }),
        ),
      );

      await service.register(
        name: 'Salem Sayer',
        email: 'salem@example.com',
        password: 'password123',
        passwordConfirmation: 'password123',
      );

      expect(await tokenStore.readToken(), 'plain-text-token');
    });

    test('sends bearer token when token provider returns one', () async {
      final apiClient = ApiClient(
        tokenProvider: () async => 'stored-token',
        httpClient: MockClient((request) async {
          expect(request.headers['authorization'], 'Bearer stored-token');
          return _jsonResponse({'data': _analysisJson()});
        }),
      );

      await apiClient.getJson('/cv-analyses/1');
    });
  });
}

http.Response _jsonResponse(Map<String, dynamic> body, {int statusCode = 200}) {
  return http.Response(
    jsonEncode(body),
    statusCode,
    headers: const {'content-type': 'application/json; charset=utf-8'},
  );
}

Map<String, dynamic> _analysisJson({
  int id = 1,
  String targetJobTitle = 'Backend Developer',
}) {
  return {
    'id': id,
    'target_job_title': targetJobTitle,
    'original_filename': null,
    'input_method': 'paste',
    'score_total': 82,
    'grade': 'B+',
    'job_match': 85,
    'criteria': [
      {'label': 'Keywords', 'score': 25, 'max': 30},
    ],
    'strengths': ['Strong keyword coverage'],
    'weaknesses': [
      {'priority': 'high', 'issue': 'Missing metrics', 'fix': 'Add numbers'},
    ],
    'keywords_found': ['Laravel', 'API'],
    'keywords_missing': ['Docker'],
    'quick_wins': ['Add Docker'],
    'ai_status': 'not_configured',
    'ai_feedback': null,
    'ai_error': null,
    'created_at': '2026-06-18T08:00:00.000000Z',
    'updated_at': '2026-06-18T08:00:00.000000Z',
  };
}

Map<String, dynamic> _generatedCvJson() {
  return {
    'id': 9,
    'full_name': 'Salem Sayer',
    'email': 'salem@example.com',
    'phone': '+966500000000',
    'linkedin': 'linkedin.com/in/salem',
    'location': 'Riyadh',
    'target_job_title': 'Backend Developer',
    'language': 'en',
    'generated_markdown': '# Salem Sayer\n\n## Experience\nBuilt APIs.',
    'ai_status': 'not_configured',
    'ai_output': null,
    'ai_error': null,
    'score_total': 88,
    'grade': 'A',
    'criteria': [
      {'label': 'Keywords', 'score': 28, 'max': 30},
    ],
    'pdf_url': 'http://localhost:8000/api/generated-cvs/9/pdf',
    'created_at': '2026-06-18T08:00:00.000000Z',
    'updated_at': '2026-06-18T08:00:00.000000Z',
  };
}

Map<String, dynamic> _authSessionJson() {
  return {
    'token': 'plain-text-token',
    'token_type': 'Bearer',
    'user': {
      'id': 5,
      'name': 'Salem Sayer',
      'email': 'salem@example.com',
      'created_at': '2026-06-18T08:00:00.000000Z',
    },
  };
}

class _MemoryAuthTokenStore extends AuthTokenStore {
  String? _token;

  @override
  Future<String?> readToken() async => _token;

  @override
  Future<void> saveToken(String token) async {
    _token = token;
  }

  @override
  Future<void> clearToken() async {
    _token = null;
  }
}
