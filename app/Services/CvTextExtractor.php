<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

class CvTextExtractor
{
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
            $filename = $file->getClientOriginalName();
            $inputMethod = $request->filled('resume_text') ? 'mixed' : 'upload';
            $extension = mb_strtolower($file->getClientOriginalExtension());

            $textParts[] = match ($extension) {
                'pdf' => $this->extractPdf($file->getRealPath()),
                'txt' => (string) file_get_contents($file->getRealPath()),
                default => throw ValidationException::withMessages([
                    'resume_file' => 'يدعم Sirati حالياً ملفات PDF و TXT فقط.',
                ]),
            };
        }

        $text = trim(implode("\n\n", array_filter($textParts)));

        if (mb_strlen($text) < 80) {
            throw ValidationException::withMessages([
                'resume_text' => 'أدخل أو ارفع سيرة ذاتية تحتوي على 80 حرفاً على الأقل.',
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
        $text = trim((new Parser)->parseFile($path)->getText());

        if ($text === '') {
            throw ValidationException::withMessages([
                'resume_file' => 'لم نتمكن من قراءة النص من ملف PDF. جرّب نسخ النص يدوياً أو رفع ملف PDF نصي.',
            ]);
        }

        return $text;
    }
}
