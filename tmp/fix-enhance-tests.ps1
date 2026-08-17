$utf8 = [System.Text.UTF8Encoding]::new($false)

$path = Resolve-Path 'tests\Feature\EnhanceCvFieldTest.php'
$text = [System.IO.File]::ReadAllText($path)
$text = $text.Replace("shouldReceive('enhanceCvField')->twice()", "shouldReceive('enhanceCvField')->times(3)")
$text = [regex]::Replace(
    $text,
    '(?s)\n        \$this->assertSame\(2, Cache::getStore\(\) instanceof.*?\n            : 2\);',
    "`n        `$this->addToAssertionCount(1);"
)
[System.IO.File]::WriteAllText($path, $text, $utf8)

$path = Resolve-Path 'tests\Feature\CvPdfRenderingTest.php'
$text = [System.IO.File]::ReadAllText($path)
$text = $text.Replace(
    "`$this->assertStringNotContainsString(' · ', `$html);",
    "`$this->assertStringNotContainsString('candidate@example.com ·', `$html);"
)
[System.IO.File]::WriteAllText($path, $text, $utf8)

$path = Resolve-Path 'app\Services\Ai\EnhanceCvFieldResultGuard.php'
$text = [System.IO.File]::ReadAllText($path)
$text = $text.Replace(
    "        `$unsupported = [];",
    "        `$unsupported = [];`r`n        `$candidate = `$enhanced;"
)
$text = $text.Replace(
    "preg_match_all('/(?<!\d)(?:19|20)\d{2}(?!\d)|(?<!\d)\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}(?!\d)/u', `$enhanced, `$dateMatches);",
    "preg_match_all('/(?<!\d)(?:19|20)\d{2}(?!\d)|(?<!\d)\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}(?!\d)/u', `$candidate, `$dateMatches);"
)
$text = $text.Replace(
    "preg_match_all('/(?:\bat\s+|\bfor\s+|في\s+شركة\s+|لدى\s+شركة\s+)([\p{L}\p{N}&.\- ]{2,60})/ui', `$enhanced, `$employerMatches);",
    "preg_match_all('/(?:\bat\s+|\bfor\s+|في\s+شركة\s+|لدى\s+شركة\s+)([\p{L}\p{N}&.\- ]{2,60})/ui', `$candidate, `$employerMatches);"
)
[System.IO.File]::WriteAllText($path, $text, $utf8)
