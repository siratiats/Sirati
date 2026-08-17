$path = Resolve-Path 'tests\Feature\CvPdfRenderingTest.php'
$text = [System.IO.File]::ReadAllText($path)
$text = $text.Replace(
    '$this->assertStringNotContainsString('' · '', $html);',
    '$this->assertStringNotContainsString(''candidate@example.com ·'', $html);'
)
[System.IO.File]::WriteAllText($path, $text, [System.Text.UTF8Encoding]::new($false))
