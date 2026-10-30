<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework;

defined('_JEXEC') or die;

/**
 * Static helper for positional HTML tag manipulation.
 * 
 * Provides methods to insert content before or after the Nth occurrence
 * of any given HTML tag in the passed HTML fragment.
 */
class DOMHelper
{
    /**
     * HTML void elements that never have a closing tag.
     *
     * @var array
     */
    private static $voidElements = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img',
        'input', 'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * Regex pattern to match any HTML opening or closing tag.
     *
     * @var string
     */
    private static $tagPattern = '/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*\/?>/';

    /**
     * Find the byte-offset and length of every opening tag for the given
     * element name at any nesting depth.
     *
     * Matches <tag>, <tag ...>, <tag/> but NOT tags that merely start with
     * the same letters (e.g. searching for "p" will not match <pre> or <progress>).
     *
     * @param  string  $html  The HTML string to search
     * @param  string  $tag   The tag name (e.g. "p", "div", "section")
     *
     * @return array   Array of arrays with keys "offset" and "length"
     */
    public static function findOpeningTags(string $html, string $tag): array
    {
        $escapedTag = preg_quote(trim($tag), '/');

        preg_match_all('/<' . $escapedTag . '\b[^>]*\/?>/i', $html, $matches, PREG_OFFSET_CAPTURE);

        return array_map(function ($m)
        {
            return [
                'offset' => $m[1],
                'length' => strlen($m[0]),
            ];
        }, $matches[0]);
    }

    /**
     * Insert content immediately before the Nth opening tag
     * of the given element.
     *
     * Falls back to prepending the content at the start of the HTML when the
     * requested occurrence exceeds the total count.
     *
     * @param  string  $html        The HTML string
     * @param  string  $content     The content to insert
     * @param  string  $tag         The tag name (e.g. "p", "div")
     * @param  int     $occurrence  The 1-based occurrence index
     *
     * @return string
     */
    public static function insertBeforeTag(string $html, string $content, string $tag = 'p', int $occurrence = 1): string
    {
        $tags = self::findOpeningTags($html, $tag);

        if (empty($tags) || $occurrence > count($tags))
        {
            return $content . $html;
        }

        $pos = $tags[$occurrence - 1]['offset'];

        return substr($html, 0, $pos) . $content . substr($html, $pos);
    }

    /**
     * Insert content immediately after the Nth closing tag
     * of the given element.
     *
     * Properly handles nested same-name tags by tracking depth to find the
     * matching closer. For void / self-closing tags the content is inserted
     * right after the tag. Falls back to appending at the end when the
     * requested occurrence exceeds the total count.
     *
     * @param  string  $html        The HTML string
     * @param  string  $content     The content to insert
     * @param  string  $tag         The tag name (e.g. "p", "div")
     * @param  int     $occurrence  The 1-based occurrence index
     *
     * @return string
     */
    public static function insertAfterTag(string $html, string $content, string $tag = 'p', int $occurrence = 1): string
    {
        $tags = self::findOpeningTags($html, $tag);

        if (empty($tags) || $occurrence > count($tags))
        {
            return $html . $content;
        }

        $match    = $tags[$occurrence - 1];
        $closePos = self::findMatchingClosePosition($html, $tag, $match['offset'], $match['length']);

        // For void / self-closing tags or when no closer is found, insert after the opening tag
        $insertAt = $closePos ?? ($match['offset'] + $match['length']);

        return substr($html, 0, $insertAt) . $content . substr($html, $insertAt);
    }

    /**
     * Find the byte-offset immediately after the matching closing tag
     * for a given opening tag.
     *
     * Tracks nested occurrences of the same tag name so that e.g.
     * <div><div>…</div></div> resolves to the correct outer closer.
     * Returns null for void / self-closing tags or when no closing tag is found.
     *
     * @param  string  $html        The full HTML string
     * @param  string  $tag         The tag name
     * @param  int     $openOffset  Byte-offset of the opening tag
     * @param  int     $openLength  Length of the opening tag string
     *
     * @return int|null  Byte-offset right after </tag>, or null
     */
    private static function findMatchingClosePosition(string $html, string $tag, int $openOffset, int $openLength): ?int
    {
        $targetTag = strtolower(trim($tag));

        if (in_array($targetTag, self::$voidElements))
        {
            return null;
        }

        $searchFrom = $openOffset + $openLength;
        $depth      = 1;

        if (!preg_match_all(self::$tagPattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $searchFrom))
        {
            return null;
        }

        foreach ($matches as $match)
        {
            $fullTag = $match[0][0];
            $tagName = strtolower($match[1][0]);

            if ($tagName !== $targetTag)
            {
                continue;
            }

            $isClosing = ($fullTag[1] === '/');

            if ($isClosing)
            {
                $depth--;

                if ($depth === 0)
                {
                    return $match[0][1] + strlen($fullTag);
                }

                continue;
            }

            $isSelfClosing = substr($fullTag, -2) === '/>';

            if (!$isSelfClosing)
            {
                $depth++;
            }
        }

        return null;
    }
}
