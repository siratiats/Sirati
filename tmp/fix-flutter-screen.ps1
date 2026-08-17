$path = Resolve-Path 'flutter_app\lib\screens\cv_generator_screen.dart'
$text = [System.IO.File]::ReadAllText($path)
$text = $text.Replace(
    "  final GeneratedCv? initialCv;`r`n`r`n  const CvGeneratorScreen({super.key, this.initialCv});",
    "  final GeneratedCv? initialCv;`r`n  final CvApiService? apiService;`r`n`r`n  const CvGeneratorScreen({super.key, this.initialCv, this.apiService});"
)
$text = $text.Replace(
    "    for (final c in _allControllers) {`r`n      c.addListener(_onAnyFieldChanged);`r`n    }",
    "    for (final c in _allControllers) {`r`n      c.addListener(_onAnyFieldChanged);`r`n    }`r`n    _experienceFocusNode.addListener(_onExperienceFocusChanged);"
)
$text = $text.Replace(
    "    for (final c in _allControllers) {`r`n      c.removeListener(_onAnyFieldChanged);`r`n      c.dispose();`r`n    }`r`n    super.dispose();",
    "    for (final c in _allControllers) {`r`n      c.removeListener(_onAnyFieldChanged);`r`n      c.dispose();`r`n    }`r`n    _experienceFocusNode.removeListener(_onExperienceFocusChanged);`r`n    _experienceFocusNode.dispose();`r`n    super.dispose();"
)
$text = [regex]::Replace(
    $text,
    '(?s)\nclass _HelperText extends StatelessWidget \{.*\z',
    "`r`n"
)
[System.IO.File]::WriteAllText($path, $text, [System.Text.UTF8Encoding]::new($false))
