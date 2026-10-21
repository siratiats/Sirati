import 'dart:convert';
import 'dart:io';

import 'package:analyzer/dart/analysis/features.dart';
import 'package:analyzer/dart/analysis/utilities.dart';
import 'package:analyzer/dart/ast/ast.dart';
import 'package:analyzer/dart/ast/visitor.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sirati/features/cv_builder/controllers/cv_builder_controller.dart';
import 'package:sirati/features/cv_builder/data/cv_repository.dart';
import 'package:sirati/features/cv_builder/data/skill_storage_keys.dart';
import 'package:sirati/features/cv_builder/presentation/experience_education_editor.dart';
import 'package:sirati/features/cv_builder/presentation/personal_details_editor.dart';
import 'package:sirati/features/cv_builder/presentation/skills_languages_editor.dart';
import 'package:sirati/l10n/generated/app_localizations.dart';
import 'package:sirati/shared/models/cv_document.dart';
import 'package:sirati/shared/theme/app_theme.dart';

const _arabicScript = r'[\u0600-\u06FF]';

void main() {
  late AppLocalizations en;
  late AppLocalizations ar;

  setUpAll(() async {
    en = await AppLocalizations.delegate.load(const Locale('en'));
    ar = await AppLocalizations.delegate.load(const Locale('ar'));
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  Widget wrap({
    required Locale locale,
    required Widget child,
  }) {
    return MaterialApp(
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      theme: AppTheme.light,
      home: Scaffold(
        body: SingleChildScrollView(
          child: child,
        ),
      ),
    );
  }

  testWidgets('personal details validation messages follow the ambient locale',
      (tester) async {
    final invalidNumbers = <String>[
      '+966401234567',
      '+971401234567',
      '+96511111111',
      'not-a-phone',
    ];

    Future<void> pumpLocale(Locale locale) async {
      final controller = CvBuilderController(
        initialDocument: CvDocument.createEmpty(id: 'i18n'),
        repository: LocalCvRepository(engine: MemoryCvStorageEngine()),
      );
      await tester.pumpWidget(
        wrap(
          locale: locale,
          child: PersonalDetailsEditor(controller: controller),
        ),
      );
      await tester.pump();
    }

    Finder phoneField() => find.byWidgetPredicate((widget) {
          return widget is TextField &&
              widget.keyboardType == TextInputType.phone;
        });

    await pumpLocale(const Locale('en'));
    for (final number in invalidNumbers) {
      await tester.enterText(phoneField(), number);
      await tester.pump();
      final field = tester.widget<TextField>(phoneField());
      expect(field.decoration?.errorText, isNotNull);
      expect(
        field.decoration!.errorText,
        isNot(contains(RegExp(_arabicScript))),
      );
    }

    await pumpLocale(const Locale('ar'));
    await tester.enterText(phoneField(), invalidNumbers.first);
    await tester.pump();
    final arabicField = tester.widget<TextField>(phoneField());
    expect(arabicField.decoration?.errorText, isNotNull);
    expect(
      arabicField.decoration!.errorText,
      contains(RegExp(_arabicScript)),
    );

    await tester.pump(const Duration(seconds: 1));
  });

  test(
      'sampled production screens call AppLocalizations rather than inlining copy',
      () {
    const screens = [
      'lib/features/settings/presentation/settings_screen.dart',
      'lib/features/cv_builder/presentation/cv_builder_screen.dart',
      'lib/features/cv_builder/presentation/personal_details_editor.dart',
      'lib/features/cv_builder/presentation/experience_education_editor.dart',
      'lib/features/cv_builder/presentation/skills_languages_editor.dart',
      'lib/features/cv_builder/presentation/section_editor_framework.dart',
      'lib/features/cv_builder/presentation/my_cvs_screen.dart',
      'lib/features/dashboard/presentation/history_screen.dart',
      'lib/shared/widgets/language_toggle.dart',
    ];
    for (final path in screens) {
      final file = File(path);
      expect(file.existsSync(), isTrue, reason: path);
      final parsed = parseFile(
        path: file.absolute.path.replaceAll('/', Platform.pathSeparator),
        featureSet: FeatureSet.latestLanguageVersion(),
      );
      final visitor = _L10nCallVisitor();
      parsed.unit.accept(visitor);
      expect(
        visitor.calledAppLocalizations,
        isTrue,
        reason: '$path must look up AppLocalizations (production call site)',
      );
    }
  });

  test('settings and personal-details UI chrome has no Arabic string literals',
      () {
    const screens = [
      'lib/features/settings/presentation/settings_screen.dart',
      'lib/features/cv_builder/presentation/personal_details_editor.dart',
      'lib/features/cv_builder/presentation/experience_education_editor.dart',
      'lib/features/cv_builder/presentation/section_editor_framework.dart',
    ];
    for (final path in screens) {
      final file = File(path);
      final parsed = parseFile(
        path: file.absolute.path.replaceAll('/', Platform.pathSeparator),
        featureSet: FeatureSet.latestLanguageVersion(),
      );
      final visitor = _ArabicLiteralVisitor();
      parsed.unit.accept(visitor);
      expect(
        visitor.hits,
        isEmpty,
        reason:
            '$path still inlines Arabic UI copy:\n${visitor.hits.join('\n')}',
      );
    }
  });

  test('English catalog strings contain no Arabic script', () {
    final catalog = jsonDecode(
      File('lib/l10n/app_en.arb').readAsStringSync(),
    ) as Map<String, dynamic>;
    for (final entry in catalog.entries) {
      if (entry.key.startsWith('@')) continue;
      final value = entry.value;
      if (value is! String) continue;
      expect(
        value,
        isNot(contains(RegExp(_arabicScript))),
        reason: 'app_en.arb ${entry.key} contains Arabic script',
      );
    }
    expect(ar.invalidSaudiPhone, contains(RegExp(_arabicScript)));
    expect(ar.settings, contains(RegExp(_arabicScript)));
    expect(ar.workExperienceTitle, contains(RegExp(_arabicScript)));
    expect(ar.endDateBeforeStart, contains(RegExp(_arabicScript)));
  });

  test('every storage key has an English export counterpart with no Arabic',
      () {
    expect(SkillStorageKeys.english.keys.toSet(), SkillStorageKeys.all);
    expect(LanguageLevelStorageKeys.english.keys.toSet(),
        LanguageLevelStorageKeys.all);

    expect(SkillStorageKeys.englishFor(SkillStorageKeys.beginner),
        en.skillLevelBeginner);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.intermediate),
        en.skillLevelIntermediate);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.advanced),
        en.skillLevelAdvanced);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.expert),
        en.skillLevelExpert);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.technical),
        en.skillCategoryTechnical);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.soft),
        en.skillCategorySoft);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.tools),
        en.skillCategoryTools);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.management),
        en.skillCategoryManagement);
    expect(SkillStorageKeys.englishFor(SkillStorageKeys.general),
        en.skillCategoryGeneral);
    expect(LanguageLevelStorageKeys.englishFor(LanguageLevelStorageKeys.native),
        en.languageLevelNative);
    expect(LanguageLevelStorageKeys.englishFor(LanguageLevelStorageKeys.fluent),
        en.languageLevelFluent);
    expect(LanguageLevelStorageKeys.englishFor(LanguageLevelStorageKeys.c1),
        en.languageLevelC1);
    expect(LanguageLevelStorageKeys.englishFor(LanguageLevelStorageKeys.b2),
        en.languageLevelB2);
    expect(LanguageLevelStorageKeys.englishFor(LanguageLevelStorageKeys.a2),
        en.languageLevelA2);

    for (final key in SkillStorageKeys.all) {
      final english = SkillStorageKeys.englishFor(key);
      expect(english, isNotEmpty, reason: key);
      expect(english, isNot(contains(RegExp(_arabicScript))), reason: key);
      final text = LocalizedText(ar: key, en: english);
      expect(text.resolve('en'), english);
      expect(text.resolve('ar'), key);
    }
    for (final key in LanguageLevelStorageKeys.all) {
      final english = LanguageLevelStorageKeys.englishFor(key);
      expect(english, isNotEmpty, reason: key);
      expect(english, isNot(contains(RegExp(_arabicScript))), reason: key);
      final text = LocalizedText(ar: key, en: english);
      expect(text.resolve('en'), english);
      expect(text.resolve('ar'), key);
    }
    expect(SkillStorageKeys.englishFor('not-a-storage-key'), isEmpty);
    expect(LanguageLevelStorageKeys.englishFor('not-a-storage-key'), isEmpty);
  });

  test('skills editor Arabic literals are persisted storage keys only', () {
    const path =
        'lib/features/cv_builder/presentation/skills_languages_editor.dart';
    final parsed = parseFile(
      path: File(path).absolute.path.replaceAll('/', Platform.pathSeparator),
      featureSet: FeatureSet.latestLanguageVersion(),
    );
    final visitor = _ArabicLiteralVisitor();
    parsed.unit.accept(visitor);
    final allowed = {
      ...SkillStorageKeys.all,
      ...LanguageLevelStorageKeys.all,
    };
    final unexpected = visitor.hits.where((hit) => !allowed.contains(hit));
    expect(
      unexpected,
      isEmpty,
      reason: '$path inlines Arabic UI copy:\n${unexpected.join('\n')}',
    );
  });

  testWidgets('experience and skills editor chrome follows the ambient locale',
      (tester) async {
    Future<CvBuilderController> pumpEditor({
      required Locale locale,
      required Widget Function(CvBuilderController c) builder,
    }) async {
      final controller = CvBuilderController(
        initialDocument: CvDocument.createEmpty(id: 'wizard'),
        repository: LocalCvRepository(engine: MemoryCvStorageEngine()),
      );
      await tester.pumpWidget(
        wrap(
          locale: locale,
          child: ListenableBuilder(
            listenable: controller,
            builder: (context, _) => builder(controller),
          ),
        ),
      );
      await tester.pump();
      return controller;
    }

    await pumpEditor(
      locale: const Locale('en'),
      builder: (c) => ExperienceEducationEditor(controller: c),
    );
    expect(find.text(en.workExperienceTitle), findsOneWidget);
    expect(find.text(en.educationQualificationsTitle), findsOneWidget);
    expect(find.text(ar.workExperienceTitle), findsNothing);

    await pumpEditor(
      locale: const Locale('ar'),
      builder: (c) => ExperienceEducationEditor(controller: c),
    );
    expect(find.text(ar.workExperienceTitle), findsOneWidget);
    expect(find.text(en.workExperienceTitle), findsNothing);

    await pumpEditor(
      locale: const Locale('en'),
      builder: (c) => SkillsLanguagesEditor(controller: c),
    );
    expect(find.text(en.skillsTitle), findsOneWidget);
    expect(find.text(en.skillLevelAdvanced), findsOneWidget);
    expect(find.text(en.skillCategoryTechnical), findsOneWidget);
    expect(find.text(ar.skillsTitle), findsNothing);

    await tester.pump(const Duration(seconds: 1));
  });

  testWidgets(
      'skill proficiency is stored as the schema key regardless of locale',
      (tester) async {
    final names = <String>['Flutter', 'إدارة المشاريع', 'SQL'];
    for (final name in names) {
      final controller = CvBuilderController(
        initialDocument: CvDocument.createEmpty(id: 'skill-$name'),
        repository: LocalCvRepository(engine: MemoryCvStorageEngine()),
      );
      await tester.pumpWidget(
        wrap(
          locale: const Locale('en'),
          child: ListenableBuilder(
            listenable: controller,
            builder: (context, _) =>
                SkillsLanguagesEditor(controller: controller),
          ),
        ),
      );
      await tester.pump();

      await tester.enterText(find.byType(TextField).first, name);
      await tester.pump();
      await tester.tap(find.text(en.addItem).first);
      await tester.pump();

      final skill = controller.document.skills!.single;
      expect(skill.name.ar, name);
      expect(skill.level.ar, SkillStorageKeys.advanced);
      expect(skill.category.ar, SkillStorageKeys.technical);
      expect(skill.level.en,
          SkillStorageKeys.englishFor(SkillStorageKeys.advanced));
      expect(skill.category.en,
          SkillStorageKeys.englishFor(SkillStorageKeys.technical));
      expect(skill.level.resolve('en'), isNot(contains(RegExp(_arabicScript))));
      expect(
          skill.category.resolve('en'), isNot(contains(RegExp(_arabicScript))));
      expect(find.text(en.skillLevelAdvanced), findsWidgets);
    }

    await tester.pump(const Duration(seconds: 1));
  });

  testWidgets(
      'language proficiency is stored bilingually so English resolve has no Arabic',
      (tester) async {
    final names = <String>['English', 'الفرنسية', 'Spanish'];
    for (final name in names) {
      final controller = CvBuilderController(
        initialDocument: CvDocument.createEmpty(id: 'lang-$name'),
        repository: LocalCvRepository(engine: MemoryCvStorageEngine()),
      );
      await tester.pumpWidget(
        wrap(
          locale: const Locale('en'),
          child: ListenableBuilder(
            listenable: controller,
            builder: (context, _) =>
                SkillsLanguagesEditor(controller: controller),
          ),
        ),
      );
      await tester.pump();

      final languageField = find.byType(TextField).at(1);
      await tester.enterText(languageField, name);
      await tester.pump();
      await tester.tap(find.text(en.addItem).last);
      await tester.pump();

      final language = controller.document.languages!.single;
      expect(language.name.ar, name);
      expect(language.level.ar, LanguageLevelStorageKeys.fluent);
      expect(
        language.level.en,
        LanguageLevelStorageKeys.englishFor(LanguageLevelStorageKeys.fluent),
      );
      expect(
        language.level.resolve('en'),
        isNot(contains(RegExp(_arabicScript))),
      );
    }

    await tester.pump(const Duration(seconds: 1));
  });

  testWidgets('new achievement bullets persist empty text, not a locale string',
      (tester) async {
    final controller = CvBuilderController(
      initialDocument: CvDocument.createEmpty(id: 'bullets'),
      repository: LocalCvRepository(engine: MemoryCvStorageEngine()),
    );
    await tester.pumpWidget(
      wrap(
        locale: const Locale('ar'),
        child: ListenableBuilder(
          listenable: controller,
          builder: (context, _) =>
              ExperienceEducationEditor(controller: controller),
        ),
      ),
    );
    await tester.pump();

    await tester.tap(find.text(ar.addExperience));
    await tester.pump();
    await tester.ensureVisible(find.text(ar.addAchievement));
    await tester.tap(find.text(ar.addAchievement));
    await tester.pump();

    final bullets = controller.document.experience!.single.bullets;
    expect(bullets, isNotEmpty);
    expect(bullets.single.ar, isEmpty);
    expect(bullets.single.en, isEmpty);
    expect(bullets.single.ar, isNot(contains(RegExp(_arabicScript))));

    await tester.pump(const Duration(seconds: 1));
  });
}

class _L10nCallVisitor extends RecursiveAstVisitor<void> {
  bool calledAppLocalizations = false;

  @override
  void visitPrefixedIdentifier(PrefixedIdentifier node) {
    if (node.prefix.name == 'AppLocalizations') {
      calledAppLocalizations = true;
    }
    super.visitPrefixedIdentifier(node);
  }

  @override
  void visitMethodInvocation(MethodInvocation node) {
    final target = node.target;
    if (target is SimpleIdentifier && target.name == 'AppLocalizations') {
      calledAppLocalizations = true;
    }
    super.visitMethodInvocation(node);
  }
}

class _ArabicLiteralVisitor extends RecursiveAstVisitor<void> {
  final hits = <String>[];

  @override
  void visitSimpleStringLiteral(SimpleStringLiteral node) {
    if (RegExp(_arabicScript).hasMatch(node.value)) {
      hits.add(node.value);
    }
    super.visitSimpleStringLiteral(node);
  }

  @override
  void visitStringInterpolation(StringInterpolation node) {
    for (final element in node.elements) {
      if (element is InterpolationString &&
          RegExp(_arabicScript).hasMatch(element.value)) {
        hits.add(element.value);
      }
    }
    super.visitStringInterpolation(node);
  }
}
