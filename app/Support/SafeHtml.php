<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Keeps the HTML an admin writes in the legal-page editor (Admin → Legal) to
 * what that editor can make: text formatting, headings, lists, links, quotes,
 * code, tables and the callout box. Anything else — scripts, styles, frames,
 * forms, event-handler attributes, javascript: links — is dropped, so the
 * public Privacy and Terms pages can never carry script, whoever saved them.
 */
class SafeHtml
{
    /** Tags kept, with the attributes each may keep (plus the global ones). */
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [], 'section' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'strike' => [], 'del' => [], 'ins' => [],
        'mark' => [], 'small' => [], 'sub' => [], 'sup' => [], 'code' => [], 'pre' => [], 'blockquote' => [],
        'ul' => [], 'ol' => ['start', 'type'], 'li' => [],
        'a' => ['href', 'target', 'rel'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'caption' => [],
        'th' => ['colspan', 'rowspan', 'scope', 'data-label'], 'td' => ['colspan', 'rowspan', 'data-label'],
    ];

    private const GLOBAL_ATTRIBUTES = ['class', 'id', 'title', 'lang', 'dir'];

    /** Tags removed together with everything inside them. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'button', 'input', 'link', 'meta', 'base', 'frame', 'frameset', 'applet'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="safe-html-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('safe-html-root');
        if (! $root) {
            return e(strip_tags($html));
        }
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! array_key_exists($tag, self::TAGS)) {
                // Unknown wrapper: keep its (already cleaned) contents, lose the tag.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::TAGS[$tag]);
        foreach (iterator_to_array($el->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a') {
            $href = trim((string) $el->getAttribute('href'));
            if ($href !== '' && ! self::safeUrl($href)) {
                $el->removeAttribute('href');
            }
            if ($el->getAttribute('target') !== '') {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /** Links without a scheme (relative, #in-page) or with http(s), mailto or tel only. */
    private static function safeUrl(string $url): bool
    {
        // Browsers ignore control characters and whitespace inside a scheme ("java\tscript:").
        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) ?? '';

        if (! preg_match('#^[a-z][a-z0-9+.\-]*:#i', $compact)) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:)#i', $compact);
    }
}
