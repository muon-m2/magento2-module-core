<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Style;

use Muon\Core\Api\CssValueSanitizerInterface;

/**
 * Allow-list implementation of the CSS value sanitiser.
 *
 * The rationale for every rule — and why this is a security boundary rather than a formatting
 * helper — lives on {@see CssValueSanitizerInterface}. Read that before relaxing anything here.
 */
class CssValueSanitizer implements CssValueSanitizerInterface
{
    /**
     * Colour keywords worth accepting by name.
     *
     * Deliberately short: the named-colour list is long, and every extra keyword is surface for no
     * real merchant benefit. No `none` — it is not a valid <color>, so `color: none` would invalidate
     * the whole declaration; `transparent` expresses the same intent and is valid everywhere.
     */
    private const COLOR_KEYWORDS = ['transparent', 'inherit', 'currentColor'];

    /**
     * Font-family names that need no quoting, plus the generic families.
     */
    private const FONT_PATTERN = '/^[A-Za-z0-9 ,\'"\-]{1,120}$/';

    /**
     * The nine numeric font weights.
     */
    private const FONT_WEIGHTS = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];

    /**
     * Sanitise a colour.
     *
     * Accepts #rgb, #rrggbb, #rrggbbaa, rgb()/rgba(), hsl()/hsla() and the short keyword list.
     *
     * @param string $value
     * @return string|null Null when the value is not a colour this module will emit.
     */
    public function color(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (self::COLOR_KEYWORDS as $keyword) {
            if (strcasecmp($value, $keyword) === 0) {
                return $keyword;
            }
        }

        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1) {
            return strtolower($value);
        }

        // Function form: only digits, dots, percent, commas, spaces and slashes may appear inside,
        // which excludes every character needed to break out of the declaration — and excludes
        // url() and expression() entirely, since neither can be spelled with that character set.
        if (preg_match('/^(rgba?|hsla?)\(\s*[0-9.,%\s\/]+\)$/i', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * Sanitise a pixel length, clamped to a range.
     *
     * @param string $value
     * @param int $min
     * @param int $max
     * @return string|null
     */
    public function pixels(string $value, int $min, int $max): ?string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^\d{1,4}$/', $value) !== 1) {
            return null;
        }

        return max($min, min($max, (int) $value)) . 'px';
    }

    /**
     * Sanitise a font-family list.
     *
     * Quotes are permitted because family names legitimately carry them, but nothing else that could
     * terminate a declaration is.
     *
     * @param string $value
     * @return string|null
     */
    public function fontFamily(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || preg_match(self::FONT_PATTERN, $value) !== 1) {
            return null;
        }

        // A stray semicolon or brace cannot reach here, but a lone quote would produce invalid CSS
        // that swallows the rest of the block, so reject unbalanced quoting too.
        if (substr_count($value, '"') % 2 !== 0 || substr_count($value, "'") % 2 !== 0) {
            return null;
        }

        return $value;
    }

    /**
     * Sanitise a numeric font weight.
     *
     * @param string $value
     * @return string|null
     */
    public function fontWeight(string $value): ?string
    {
        return in_array(trim($value), self::FONT_WEIGHTS, true) ? trim($value) : null;
    }

    /**
     * Sanitise a value drawn from a fixed set of keywords.
     *
     * @param string $value
     * @param string[] $allowed
     * @param string $default
     * @return string
     */
    public function keyword(string $value, array $allowed, string $default): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
