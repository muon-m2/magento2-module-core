<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Api;

/**
 * Reduces merchant-entered appearance values to tokens that are safe inside a style declaration.
 *
 * THIS IS A SECURITY BOUNDARY, NOT A TIDINESS HELPER. A value that reaches CSS is not escapable —
 * CSS has no escaping that renders arbitrary text harmless. A value is free to terminate the
 * declaration, the rule, and the style element itself:
 *
 *     red;} body { display:none } </style><img src=x onerror=alert(1)>
 *
 * That string is one configuration field away from defacing every page of a store, and the field is
 * reachable by anyone holding the module's configuration permission.
 *
 * Every method here therefore works by ALLOW-LIST and returns `null` (or the caller's default) for
 * anything it cannot positively recognise. There is no "sanitise by removing bad characters" path,
 * because that inverts the burden of proof onto the reviewer.
 *
 * @api Implemented once in this package and consumed by every Muon module with appearance settings;
 *      an override changes what is considered safe estate-wide.
 */
interface CssValueSanitizerInterface
{
    /**
     * Reduce a colour to hex, rgb()/rgba(), hsl()/hsla() or a short keyword allow-list.
     *
     * @param string $value
     * @return string|null Null when the value is not a colour this method recognises.
     */
    public function color(string $value): ?string;

    /**
     * Reduce a length to an integer pixel value clamped into a range.
     *
     * @param string $value
     * @param int $min
     * @param int $max
     * @return string|null Null when the value is not numeric.
     */
    public function pixels(string $value, int $min, int $max): ?string;

    /**
     * Reduce a font-family list to a conservative character set.
     *
     * @param string $value
     * @return string|null Null when the value carries anything that could terminate a declaration.
     */
    public function fontFamily(string $value): ?string;

    /**
     * Reduce a font weight to one of the nine numeric weights.
     *
     * @param string $value
     * @return string|null Null when the value is not one of them.
     */
    public function fontWeight(string $value): ?string;

    /**
     * Reduce a value to one of an explicit allow-list, falling back to a caller-supplied default.
     *
     * @param string $value
     * @param string[] $allowed
     * @param string $default
     * @return string Always one of $allowed.
     */
    public function keyword(string $value, array $allowed, string $default): string;
}
