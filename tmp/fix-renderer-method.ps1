$path = Resolve-Path 'app\Services\CvTemplateRenderer.php'
$text = [System.IO.File]::ReadAllText($path)
$pattern = '(?s)    private function formatPdfText\(\?string \$text, string \$language\): string\s*\{.*?\n    \}\s*\n\}'
$replacement = @'
    private function formatPdfText(?string $text, string $language): string
    {
        return $this->markdownRenderer->shapeText($text, $language);
    }
}
'@
$text = [regex]::Replace($text, $pattern, $replacement)
[System.IO.File]::WriteAllText($path, $text, [System.Text.UTF8Encoding]::new($false))
