$path = Resolve-Path 'tests\Feature\CvPdfRenderingTest.php'
$lines = [System.Collections.Generic.List[string]]::new()
$lines.AddRange([string[]][System.IO.File]::ReadAllLines($path))
$lines[27] = "        `$this->assertStringNotContainsString('candidate@example.com ·', `$html);"
[System.IO.File]::WriteAllLines($path, $lines, [System.Text.UTF8Encoding]::new($false))
