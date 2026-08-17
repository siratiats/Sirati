$path = Resolve-Path 'flutter_app\lib\screens\cv_generator_screen.dart'
$text = [System.IO.File]::ReadAllText($path)
$text = [regex]::Replace(
    $text,
    '  final GeneratedCv\? initialCv;\s+const CvGeneratorScreen\(\{super\.key, this\.initialCv\}\);',
    "  final GeneratedCv? initialCv;`r`n  final CvApiService? apiService;`r`n`r`n  const CvGeneratorScreen({super.key, this.initialCv, this.apiService});"
)
$text = [regex]::Replace(
    $text,
    '(for \(final c in _allControllers\) \{\s+c\.addListener\(_onAnyFieldChanged\);\s+\})',
    "`$1`r`n    _experienceFocusNode.addListener(_onExperienceFocusChanged);",
    1
)
$text = [regex]::Replace(
    $text,
    '(for \(final c in _allControllers\) \{\s+c\.removeListener\(_onAnyFieldChanged\);\s+c\.dispose\(\);\s+\})',
    "`$1`r`n    _experienceFocusNode.removeListener(_onExperienceFocusChanged);`r`n    _experienceFocusNode.dispose();",
    1
)

$original = [System.IO.File]::ReadAllText((Resolve-Path 'tmp\orig\screens\cv_generator_screen.dart'))
$langStart = $original.IndexOf('class _LangOption')
$langOption = $original.Substring($langStart)
$text = $text.TrimEnd() + "`r`n`r`nenum _ExitAction { cancel, discard, saveDraft }`r`n`r`n" + $langOption
[System.IO.File]::WriteAllText($path, $text, [System.Text.UTF8Encoding]::new($false))
