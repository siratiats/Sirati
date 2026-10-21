import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/network/api_exception.dart';
import 'package:sirati/features/auth/application/register_controller.dart';
import 'package:sirati/features/auth/domain/registration_rules.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';

class _FakeAuth extends AuthApiService {
  final calls = <Map<String, Object?>>[];
  Completer<void>? gate;
  Object? error;
  String serverEmail = 'server@x.io';

  @override
  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
    String? location,
    int? jobTitleId,
    String? jobTitleOther,
  }) async {
    calls.add({
      'name': name,
      'email': email,
      'password': password,
      'passwordConfirmation': passwordConfirmation,
      'phone': phone,
      'location': location,
      'jobTitleId': jobTitleId,
      'jobTitleOther': jobTitleOther,
    });
    await gate?.future;
    final e = error;
    if (e != null) throw e;
    return AuthSession(
      token: 't',
      tokenType: 'Bearer',
      user: AuthUser(id: 1, name: name, email: serverEmail),
    );
  }
}

RegistrationInput _input({String email = 'typed@x.io'}) => RegistrationInput(
      name: 'Sara',
      email: email,
      password: 'secret123',
      phone: '٠٥٠١٢٣٤٥٦٧',
      location: 'Riyadh',
      jobTitleId: 3,
      jobTitleIsOther: true,
      jobTitleOther: ' Chef ',
    );

void main() {
  late _FakeAuth auth;
  late ProviderContainer container;

  setUp(() {
    auth = _FakeAuth();
    container = ProviderContainer(
      overrides: [authApiServiceProvider.overrideWithValue(auth)],
    );
    container.listen(registerControllerProvider, (_, __) {});
  });

  tearDown(() => container.dispose());

  RegisterController controller() =>
      container.read(registerControllerProvider.notifier);

  test('sends normalised input with a matching confirmation', () async {
    await controller().submit(_input());
    final call = auth.calls.single;
    expect(call['phone'], '0501234567');
    expect(call['jobTitleOther'], 'Chef');
    expect(call['passwordConfirmation'], call['password']);
  });

  test('success carries the server email, falling back to the typed one',
      () async {
    expect(
        await controller().submit(_input()),
        isA<RegisterSucceeded>()
            .having((s) => s.email, 'email', 'server@x.io'));
    for (final blank in ['', '   ']) {
      auth.serverEmail = blank;
      expect(
        await controller().submit(_input()),
        isA<RegisterSucceeded>().having((s) => s.email, 'email', 'typed@x.io'),
      );
    }
  });

  test('only one request runs at a time; state tracks it', () async {
    auth.gate = Completer<void>();
    final first = controller().submit(_input());
    expect(container.read(registerControllerProvider), isTrue);
    final extra = [for (var i = 0; i < 3; i++) controller().submit(_input())];
    for (final outcome in await Future.wait(extra)) {
      expect(outcome, isA<RegisterIgnored>());
    }
    auth.gate!.complete();
    expect(await first, isA<RegisterSucceeded>());
    expect(auth.calls, hasLength(1));
    expect(container.read(registerControllerProvider), isFalse);
  });

  test('failures are classified and always release the in-flight flag',
      () async {
    final cases = <(Object, Type)>[
      (const ApiException('taken', statusCode: 422), RegisterRejected),
      (
        const ApiException('down', type: ApiErrorType.network),
        RegisterRejected
      ),
      (StateError('boom'), RegisterFailedUnexpectedly),
      (const FormatException('bad json'), RegisterFailedUnexpectedly),
    ];
    for (final (error, expected) in cases) {
      auth.error = error;
      final outcome = await controller().submit(_input());
      expect(outcome.runtimeType, expected, reason: '$error');
      if (outcome is RegisterRejected) expect(outcome.error, same(error));
      expect(container.read(registerControllerProvider), isFalse,
          reason: 'flag released after $error');
    }
    expect(auth.calls, hasLength(cases.length));
  });
}
