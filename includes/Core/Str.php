<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class Str
{
    /**
     * Slug for naming a record. Never empty: a record needs an address, so an
     * unusable title falls back to "item".
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $slug = self::slugify($value, $separator);
        return $slug === '' ? 'item' : $slug;
    }

    /**
     * Slug for a value arriving in a query string.
     *
     * Empty in, empty out — the opposite of slug(), and the difference matters:
     * running a blank ?category= through slug() yields "item", which then filters
     * a listing down to the nothing that matches it.
     */
    public static function slugFilter(string $value, string $separator = '-'): string
    {
        return self::slugify($value, $separator);
    }

    /** The shared transliterate-and-clean step. May return an empty string. */
    private static function slugify(string $value, string $separator = '-'): string
    {
        $value = trim($value);
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
            if (is_string($t)) {
                $value = $t;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', $separator, $value) ?? '';
        return trim($value, $separator);
    }

    public static function excerpt(string $html, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $length * 0.6) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return rtrim($cut, " ,.;:-") . '…';
    }

    public static function readingTime(string $html): int
    {
        $words = str_word_count(strip_tags($html));
        return max(1, (int) ceil($words / 200));
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $out   = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $out === '' ? '?' : $out;
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Whitelist-based HTML sanitiser for admin-authored rich text. Admins are
     * trusted users, but a compromised or lower-privileged account must not be
     * able to inject script into public pages.
     */
    public static function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $allowed = [
            'p' => ['class'], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
            'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => ['cite'],
            'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => [], 'h6' => [],
            'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading'],
            'figure' => ['class'], 'figcaption' => [], 'hr' => [], 'code' => [], 'pre' => [],
            'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'],
            'td' => ['colspan', 'rowspan'], 'span' => ['class'], 'div' => ['class'],
        ];

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="tb-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('tb-root');
        if ($root === null) {
            return '';
        }

        $walk = static function (\DOMNode $node) use (&$walk, $allowed): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMElement) {
                    $tag = strtolower($child->nodeName);
                    if (!isset($allowed[$tag])) {
                        // Unwrap unknown tags but keep their text content.
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }
                    foreach (iterator_to_array($child->attributes) as $attr) {
                        $name = strtolower($attr->nodeName);
                        if (!in_array($name, $allowed[$tag], true)) {
                            $child->removeAttribute($attr->nodeName);
                            continue;
                        }
                        if (in_array($name, ['href', 'src'], true)) {
                            $val = trim($attr->nodeValue ?? '');
                            $ok  = preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $val) === 1;
                            if (!$ok) {
                                $child->removeAttribute($attr->nodeName);
                            }
                        }
                    }
                    if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                    $walk($child);
                } elseif ($child instanceof \DOMComment) {
                    $node->removeChild($child);
                } elseif (!($child instanceof \DOMText)) {
                    $node->removeChild($child);
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

}
