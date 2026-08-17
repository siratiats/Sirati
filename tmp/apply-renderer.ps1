$path = 'app\Services\CvTemplateRenderer.php'
$text = [System.IO.File]::ReadAllText((Resolve-Path $path))
$text = $text.Replace('use ArPHP\I18N\Arabic;', 'use App\Services\Cv\CvMarkdownRenderer;')
$text = $text.Replace("class CvTemplateRenderer`r`n{", "class CvTemplateRenderer`r`n{`r`n    public function __construct(`r`n        private readonly CvMarkdownRenderer `$markdownRenderer,`r`n    ) {}`r`n")
$old = @'
            'content' => $this->formatPdfText($generatedCv->generated_markdown, $language),
'@
$new = @'
            'contacts' => array_values(array_filter([
                $this->formatPdfText($generatedCv->email, $language),
                $this->formatPdfText($generatedCv->phone, $language),
                $this->formatPdfText($generatedCv->linkedin, $language),
                $this->formatPdfText($generatedCv->location, $language),
            ], fn (string $value): bool => trim($value) !== '')),
            'contentHtml' => $this->markdownRenderer->render(
                (string) $generatedCv->generated_markdown,
                $language,
            ),
'@
$text = $text.Replace($old, $new)
$oldMethod = @'
        $text = (string) $text;
        if ($language !== 'ar') {
            return $text;
        }

        $arabic = new Arabic();

        return collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line) => $arabic->utf8Glyphs($line, 90, false, true))
            ->implode("\n");
'@
$text = $text.Replace($oldMethod, '        return $this->markdownRenderer->shapeText($text, $language);')
[System.IO.File]::WriteAllText((Resolve-Path $path), $text, [System.Text.UTF8Encoding]::new($false))
Copy-Item -LiteralPath 'tmp\pdf.blade.php' -Destination 'resources\views\generated-cvs\pdf.blade.php' -Force
Copy-Item -LiteralPath 'tmp\modern-rtl.blade.php' -Destination 'resources\views\generated-cvs\templates\modern-rtl.blade.php' -Force
