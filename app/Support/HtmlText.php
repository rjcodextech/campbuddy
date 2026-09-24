<?php

namespace App\Support;

/**
 * Plain text out of third-party HTML (a WordCamp session description, a
 * speaker bio): tags dropped along with the contents of <script> and
 * <style>, entities decoded, one line per block element, blank lines
 * collapsed. The result is only ever set as text — never markup.
 */
class HtmlText
{
    /** Elements whose end starts a new line when HTML is turned into text. */
    private const BLOCK_TAGS = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'br', 'blockquote', 'figure', 'section', 'article', 'tr'];

    /**
     * @return array<int, string> the non-empty, trimmed lines of text
     */
    public static function lines(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        // The <?xml> line makes libxml read the fragment as UTF-8; LIBXML_NONET
        // keeps it from fetching anything.
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (['script', 'style'] as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Blocks end a line, so "<h2>Ada</h2><p>Bio…</p>" reads as two lines, not "AdaBio…".
        foreach (self::BLOCK_TAGS as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                if ($tag === 'br') {
                    $node->parentNode?->replaceChild($document->createTextNode("\n"), $node);
                } else {
                    $node->appendChild($document->createTextNode("\n"));
                }
            }
        }

        $text = $document->getElementsByTagName('body')->item(0)?->textContent ?? '';

        return array_values(array_filter(
            array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $text))),
            fn (string $line) => $line !== ''
        ));
    }

    /**
     * The whole text, at most $limit characters (cut at a word, with an
     * ellipsis) — or null when there's no text at all.
     */
    public static function plain(?string $html, ?int $limit = null): ?string
    {
        $lines = self::lines($html);

        if ($lines === []) {
            return null;
        }

        $text = implode("\n", $lines);

        if ($limit !== null && mb_strlen($text) > $limit) {
            $cut = mb_substr($text, 0, $limit);
            $space = mb_strrpos($cut, ' ');
            $text = rtrim($space > $limit * 0.6 ? mb_substr($cut, 0, $space) : $cut, " \n.,;:").'…';
        }

        return $text;
    }
}
