<?php

namespace App\Services\Cv;

use App\Support\ArabicPdfText;
use Mpdf\Mpdf;
use Mpdf\Otl;
use Mpdf\Writer\BaseWriter;
use ReflectionProperty;

/**
 * Keep mPDF's visual glyph layout, but publish logical Unicode via PDF ActualText.
 *
 * mPDF 8.x has no ActualText hook: its bidi line boundary and content writer are
 * private services. Keep that integration here, covered by real PDF parse-back
 * tests (including wrapped lines and page breaks), rather than patching vendor.
 */
final class LogicalTextMpdf extends Mpdf
{
    private LogicalTextWriter $logicalWriter;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $writerProperty = new ReflectionProperty(Mpdf::class, 'writer');
        $this->logicalWriter = new LogicalTextWriter($writerProperty->getValue($this));
        $writerProperty->setValue($this, $this->logicalWriter);

        $fontCache = (new ReflectionProperty(Mpdf::class, 'fontCache'))->getValue($this);
        (new ReflectionProperty(Mpdf::class, 'otl'))->setValue($this, new LogicalTextOtl($this, $fontCache));
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0, $lcpaddingL = 0, $lcpaddingR = 0, $valign = 'M', $spanfill = 0, $exactWidth = false, $OTLdata = false, $textvar = 0, $lineBox = false)
    {
        $previous = $this->logicalWriter->actualText;
        $this->logicalWriter->actualText = $OTLdata['logicalText'] ?? ArabicPdfText::unshape($txt);

        try {
            return parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $currentx, $lcpaddingL, $lcpaddingR, $valign, $spanfill, $exactWidth, $OTLdata, $textvar, $lineBox);
        } finally {
            $this->logicalWriter->actualText = $previous;
        }
    }
}

/** @internal mPDF's line content is still logical here, before bidiReorder. */
final class LogicalTextOtl extends Otl
{
    public function bidiReorder(&$chunkorder, &$content, &$cOTLdata, $blockdir)
    {
        $logical = implode('', array_filter($content, fn ($part) => ! str_starts_with($part, Mpdf::OBJECT_IDENTIFIER)));
        // Decode the font's presentation glyphs while their order is known.
        // bidiPrepare has consumed embeddings; omit its remaining display marks
        // from ActualText. This is renderer metadata, never a stored-field edit.
        $logical = str_replace(["\u{200E}", "\u{200F}", "\u{061C}"], '', ArabicPdfText::unshape($logical));

        parent::bidiReorder($chunkorder, $content, $cOTLdata, $blockdir);

        // A line can consist of several font/style chunks painted in visual
        // order. Publish its logical text once; the other chunks contribute no
        // replacement text. This also keeps each marked sequence on its page.
        $pending = $logical;
        foreach ($content as $index => $part) {
            if ($part === '' || str_starts_with($part, Mpdf::OBJECT_IDENTIFIER)) {
                continue;
            }
            $cOTLdata[$index]['logicalText'] = $pending;
            $pending = '';
        }
    }
}

/** @internal Delegate positioning/buffering to mPDF, including table replays. */
final class LogicalTextWriter
{
    public ?string $actualText = null;

    public function __construct(private readonly BaseWriter $writer) {}

    public function write($content, $newline = true)
    {
        if ($this->actualText !== null && (str_contains($content, ' Tj') || str_contains($content, ' TJ'))) {
            // UTF-16BE with BOM is a PDF text string, independent of the glyph
            // font's ToUnicode map. Hex encoding cannot escape the dictionary.
            $hex = bin2hex("\xFE\xFF".mb_convert_encoding($this->actualText, 'UTF-16BE', 'UTF-8'));
            $content = '/Span << /ActualText <'.$hex.'> >> BDC '.$content.' EMC';
        }

        return $this->writer->write($content, $newline);
    }

    public function __call(string $method, array $arguments)
    {
        return $this->writer->$method(...$arguments);
    }
}
