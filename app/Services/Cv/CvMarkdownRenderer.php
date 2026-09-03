<?php

namespace App\Services\Cv;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;
use RuntimeException;

final class CvMarkdownRenderer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'br', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const LRM = "\u{200E}";

    private const LRE = "\u{202A}";

    private const PDF = "\u{202C}";

    private readonly CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    public function render(string $markdown, string $language, ?int $generatedCvId = null): string
    {
        $html = (string) $this->converter->convert($markdown);
        $document = $this->loadFragment($html);
        $root = $this->rootOrFail($document, $generatedCvId, mb_strlen($markdown, 'UTF-8'));

        $this->rewriteLinks($root);
        $this->sanitizeChildren($root);
        if ($language === 'ar') {
            $this->shapeTextNodes($root);
        }

        return $this->innerHtml($root);
    }

    public function shapeText(?string $text, string $language): string
    {
        $text = (string) $text;

        if ($language !== 'ar' || ! $this->containsArabic($text)) {
            return $text;
        }

        return $this->protectLtrRuns($text);
    }

    private function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * Keep emails, URLs, phones, Latin tech terms, and numerals as LTR runs
     * inside Arabic text. Uses embedding marks so escaped Blade fields stay
     * stable without HTML, and PDF engines can still extract logical Unicode.
     */
    private function protectLtrRuns(string $text): string
    {
        $pattern = '/(?:[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})'
            .'|(?:https?:\/\/[^\s<]+)'
            .'|(?:\+\d[\d\s\-()]{6,}\d)'
            .'|(?:[A-Za-z][A-Za-z0-9+.#\/]*(?:\s+[A-Za-z][A-Za-z0-9+.#\/]*)*)'
            .'|(?:\d+(?:[.,]\d+)?%?)/u';

        $protected = preg_replace_callback(
            $pattern,
            function (array $match): string {
                $run = $match[0];

                if (str_contains($run, self::LRM) || str_contains($run, self::LRE)) {
                    return $run;
                }

                return $this->isolateLtr($run);
            },
            $text,
        );

        return $protected ?? $text;
    }

    private function isolateLtr(string $text): string
    {
        return self::LRM.self::LRE.$text.self::PDF;
    }

    private function loadFragment(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
                .'<div id="cv-markdown-root">'.$html.'</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function rootOrFail(
        DOMDocument $document,
        ?int $generatedCvId,
        int $markdownLength,
    ): DOMElement {
        $root = $document->getElementById('cv-markdown-root');
        if ($root instanceof DOMElement) {
            return $root;
        }

        $fallback = $document->getElementsByTagName('div')->item(0);
        if ($fallback instanceof DOMElement) {
            return $fallback;
        }

        Log::warning('CV markdown wrapper lookup failed', [
            'generated_cv_id' => $generatedCvId,
            'markdown_length' => $markdownLength,
        ]);

        throw new RuntimeException('CV markdown wrapper lookup failed.');
    }

    private function rewriteLinks(DOMElement $root): void
    {
        $anchors = [];
        foreach ($root->getElementsByTagName('a') as $anchor) {
            if ($anchor instanceof DOMElement) {
                $anchors[] = $anchor;
            }
        }

        foreach ($anchors as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            $text = trim($anchor->textContent);

            if (! $this->isAllowedLink($href) || $href === $text) {
                continue;
            }

            $anchor->appendChild(
                $anchor->ownerDocument->createTextNode(" ({$href})"),
            );
        }
    }

    private function isAllowedLink(string $href): bool
    {
        return (bool) preg_match('/\A(?:https?:\/\/|mailto:)/iD', $href);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
            } elseif ($node instanceof DOMElement) {
                $this->sanitizeChildren($node);
                if (! in_array(strtolower($node->tagName), self::ALLOWED_TAGS, true)) {
                    while ($node->firstChild !== null) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                } else {
                    while ($node->attributes->length > 0) {
                        $attribute = $node->attributes->item(0);
                        if ($attribute === null) {
                            break;
                        }
                        $node->removeAttributeNode($attribute);
                    }
                }
            } elseif ($node->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($node);
            }

            $node = $next;
        }
    }

    private function shapeTextNodes(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $child->nodeValue = $this->shapeText((string) $child->nodeValue, 'ar');
            } else {
                $this->shapeTextNodes($child);
            }
        }
    }

    private function innerHtml(DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
