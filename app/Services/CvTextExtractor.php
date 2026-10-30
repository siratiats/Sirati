<?php

namespace App\Services;

use App\Services\I18n\ServerText;
use App\Support\ArabicPdfText;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

class CvTextExtractor
{
    private const MIN_LENGTH = 80;

    public function extract(Request $request): array
    {
        $textParts = [];
        $filename = null;
        $inputMethod = 'paste';

        if ($request->filled('resume_text')) {
            $textParts[] = (string) $request->input('resume_text');
        }

        if ($request->hasFile('resume_file')) {
            $file = $request->file('resume_file');
            $filename = Utf8Sanitizer::sanitize($file->getClientOriginalName());
            $inputMethod = $request->filled('resume_text') ? 'mixed' : 'upload';
            $extension = mb_strtolower($file->getClientOriginalExtension());

            $textParts[] = match ($extension) {
                'pdf' => $this->extractPdf($file->getRealPath()),
                'txt' => Utf8Sanitizer::sanitize((string) file_get_contents($file->getRealPath())),
                default => throw ValidationException::withMessages([
                    'resume_file' => ServerText::t('serverCvFileTypeUnsupported'),
                ]),
            };
        }

        $text = Utf8Sanitizer::sanitize(trim(implode("\n\n", array_filter($textParts))));

        if (mb_strlen($text) < self::MIN_LENGTH) {
            throw ValidationException::withMessages([
                'resume_text' => ServerText::t('serverCvTextTooShort', ['min' => self::MIN_LENGTH]),
            ]);
        }

        return [
            'text' => $text,
            'filename' => $filename,
            'input_method' => $inputMethod,
        ];
    }

    private function extractPdf(string $path): string
    {
        $raw = (new Parser)->parseFile($path)->getText();
        $text = trim(Utf8Sanitizer::sanitize(ArabicPdfText::normalizeExtracted($raw)));

        if ($text === '') {
            throw ValidationException::withMessages([
                'resume_file' => ServerText::t('serverCvPdfUnreadable'),
            ]);
        }

        return $text;
    }
}
