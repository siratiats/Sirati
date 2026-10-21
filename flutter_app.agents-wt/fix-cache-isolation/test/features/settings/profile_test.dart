import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sirati/core/utils/app_locale.dart';
import 'package:sirati/features/settings/application/settings_controllers.dart';
import 'package:sirati/features/settings/domain/profile_update.dart';
import 'package:sirati/features/settings/presentation/profile_screen.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/auth_session.dart';
import 'package:sirati/shared/models/job_title.dart';
import 'package:sirati/shared/services/auth_api_service.dart';
import 'package:sirati/shared/services/auth_api_service_provider.dart';
import 'package:sirati/shared/services/job_titles_provider.dart';
import 'package:sirati/shared/services/session_cache.dart';
import 'package:sirati/shared/theme/app_theme.dart';

class _Call {
  _Call(this.name, this.jobTitleId, this.jobTitleOther, this.clear);
  final String name;
  final int? jobTitleId;
  final String? jobTitleOther;
  final bool clear;

  /// Whether the request body mentions the job title at all — mirrors
  /// AuthApiService.updateProfile's body construction.
  bool get touchesJobTitle =>
      clear || jobTitleId != null || jobTitleOther != null;
}

class _FakeAuth extends AuthApiService {
  _FakeAuth({required this.user});

  AuthUser user;
  Completer<void>? meGate;
  Completer<void>? saveGate;
  final calls = <_Call>[];

  @override
  Future<AuthUser?> me() async {
    await meGate?.future;
    return user;
  }

  @override
  Future<AuthUser> updateProfile({
    required String name,
    int? jobTitleId,
    String? jobTitleOther,
    bool clearJobTitle = false,
  }) async {
    calls.add(_Call(name, jobTitleId, jobTitleOther, clearJobTitle));
    await saveGate?.future;
    return user;
  }
}

JobTitle _title(int id, String slug) => JobTitle.fromJson(
    {'id': id, 'slug': slug, 'name_en': slug, 'name_ar': slug});

const _user = AuthUser(
  id: 1,
  name: 'Sara',
  email: 'sara@x.io',
  jobTitleId: 5,
);

void main() {
  group('ProfileRules', () {
    test('name and other title: required, trimmed, bounded', () {
      for (final (rule, max) in <(ProfileFieldError? Function(String?), int)>[
        (ProfileRules.name, 255),
        ((v) => ProfileRules.jobTitleOther(v, isOther: true), 120),
      ]) {
        for (final blank in [null, '', '   ']) {
          expect(rule(blank), ProfileFieldError.required);
        }
        expect(rule('  ${'a' * max}  '), isNull);
        expect(rule('a' * (max + 1)), ProfileFieldError.tooLong);
      }
      expect(ProfileRules.jobTitleOther('', isOther: false), isNull);
    });
  });

  group('ProfileSaveController', () {
    late _FakeAuth auth;
    late ProviderContainer container;
    setUp(() {
      auth = _FakeAuth(user: _user);
      container = ProviderContainer(
        overrides: [authApiServiceProvider.overrideWithValue(auth)],
      );
      container.listen(profileSaveProvider, (_, __) {});
    });
    tearDown(() => container.dispose());

    Future<ProfileSaveOutcome> save(JobTitleChange change) => container
        .read(profileSaveProvider.notifier)
        .save(ProfileUpdate(name: '  Sara  ', jobTitle: change));

    test('each job-title change maps to exactly its request shape', () async {
      await save(const JobTitleUnchanged());
      await save(const JobTitleCleared());
      await save(const JobTitleSelected(3));
      await save(const JobTitleSelected(9, otherText: ' Chef '));

      expect(auth.calls.map((c) => c.name).toSet(), {'Sara'});
      expect(auth.calls[0].touchesJobTitle, isFalse,
          reason: 'unchanged must not mention the job title');
      expect(auth.calls[1].clear, isTrue);
      expect(auth.calls[2].jobTitleId, 3);
      expect(auth.calls[2].jobTitleOther, isNull);
      expect(auth.calls[3].jobTitleId, 9);
      expect(auth.calls[3].jobTitleOther, 'Chef');
    });

    test('a second save while one is running is ignored', () async {
      auth.saveGate = Completer<void>();
      final first = save(const JobTitleUnchanged());
      expect(await save(const JobTitleUnchanged()), isA<ProfileSaveIgnored>());
      auth.saveGate!.complete();
      expect(await first, isA<ProfileSaved>());
      expect(auth.calls, hasLength(1));
      expect(container.read(profileSaveProvider), isFalse);
    });
  });

  group('ProfileScreen', () {
    setUp(() {
      AppLocale.languageCode.value = 'en';
      SessionCache.instance.clear();
    });

    Future<void> pump(
      WidgetTester tester,
      _FakeAuth auth, {
      required Future<List<JobTitle>> Function() titles,
    }) async {
      tester.view.physicalSize = const Size(1080, 3000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(ProviderScope(
        overrides: [
          authApiServiceProvider.overrideWithValue(auth),
          jobTitlesProvider.overrideWith((ref) => titles()),
        ],
        child: MaterialApp(
          theme: AppTheme.lightFor(arabic: false),
          locale: const Locale('en'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: const ProfileScreen(),
        ),
      ));
      await tester.pumpAndSettle();
    }

    for (final (label, titles) in <(String, Future<List<JobTitle>> Function())>[
      ('catalogue failed to load', () async => throw StateError('offline')),
      ('title was retired', () async => [_title(1, 'dev'), _title(2, 'other')]),
      ('title is listed', () async => [_title(5, 'pm'), _title(2, 'other')]),
    ]) {
      testWidgets('saving a name change keeps the job title ($label)',
          (tester) async {
        final auth = _FakeAuth(user: _user);
        await pump(tester, auth, titles: titles);

        await tester.enterText(find.byType(TextField).first, 'Sara Ahmed');
        await tester.tap(find.text('Save changes'));
        await tester.pumpAndSettle();

        expect(auth.calls, hasLength(1));
        expect(auth.calls.single.name, 'Sara Ahmed');
        expect(auth.calls.single.touchesJobTitle, isFalse,
            reason: 'the saved job title must survive ($label)');
      });
    }

    testWidgets('a late profile response does not overwrite typed edits',
        (tester) async {
      SessionCache.instance.setUser(_user);
      final auth = _FakeAuth(user: _user)..meGate = Completer<void>();
      await pump(tester, auth, titles: () async => [_title(5, 'pm')]);

      await tester.enterText(find.byType(TextField).first, 'Typed Name');
      auth.user = const AuthUser(id: 1, name: 'Server Name', email: 'a@b.c');
      auth.meGate!.complete();
      await tester.pumpAndSettle();

      final field = tester.widget<TextField>(find.byType(TextField).first);
      expect(field.controller!.text, 'Typed Name');
    });
  });
}
