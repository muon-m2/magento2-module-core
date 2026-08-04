# Muon_Core — Developer Guide

[Back to README](../README.md)

A practical guide to building on top of this module. Code snippets are derived from the
module's real public signatures; every snippet cites its source file.

## Overview

`Muon_Core` holds the pieces more than one Muon module needs, so that neither has to depend on the
other. It ships exactly one PHP contract — `CssValueSanitizerInterface` — plus a vendored
`accessible-menu` bundle under `view/frontend/web/js/vendor/`.

The sanitiser exists because **a value that reaches CSS is not escapable**. CSS has no escaping that
renders arbitrary text harmless; a merchant-entered value is free to terminate the declaration, the
rule, and the `<style>` element itself. The interface documents this as a security boundary rather
than a formatting helper (`Api/CssValueSanitizerInterface.php:14-25`).

Consequently every method works by **allow-list**, and returns `null` — or, for `keyword()`, the
caller-supplied default — for anything it cannot positively recognise. There is no
"strip the bad characters" path (`Api/CssValueSanitizerInterface.php:27-28`).

## API Usage

Type-hint the interface; DI supplies `Muon\Core\Model\Style\CssValueSanitizer` through the preference
in `etc/di.xml`.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Muon\Core\Api\CssValueSanitizerInterface;

class Appearance
{
    public function __construct(
        private readonly CssValueSanitizerInterface $sanitizer
    ) {
    }

    public function backgroundColor(string $configured): string
    {
        // Null means "not a colour I recognise" — always supply your own fallback.
        return $this->sanitizer->color($configured) ?? '#ffffff';
    }
}
```

Source: `Api/CssValueSanitizerInterface.php:30`, `etc/di.xml`

### Method reference

| Method | Signature | Accepts | Returns on rejection |
|--------|-----------|---------|---------------------|
| `color` | `color(string $value): ?string` | `#rgb`, `#rrggbb`, `#rrggbbaa`, `rgb()`/`rgba()`, `hsl()`/`hsla()`, and the keywords `transparent`, `inherit`, `currentColor` | `null` |
| `pixels` | `pixels(string $value, int $min, int $max): ?string` | 1–4 digits, clamped into `[$min, $max]`, returned with a `px` suffix | `null` |
| `fontFamily` | `fontFamily(string $value): ?string` | Letters, digits, spaces, commas, hyphens and balanced quotes, up to 120 characters | `null` |
| `fontWeight` | `fontWeight(string $value): ?string` | One of `100`–`900` in hundreds | `null` |
| `keyword` | `keyword(string $value, array $allowed, string $default): string` | Any member of `$allowed` | `$default` (never `null`) |

Sources: `Api/CssValueSanitizerInterface.php:38,48,56,64,74`;
`Model/Style/CssValueSanitizer.php:28,33,38`

Two behaviours worth knowing before you rely on them:

- `color()` accepts the function forms only when the parentheses contain nothing but digits, dots,
  percent signs, commas, spaces and slashes. That character set cannot spell `url(` or `expression(`,
  which is why those are excluded without a separate rule (`Model/Style/CssValueSanitizer.php:66-71`).
- `fontFamily()` rejects unbalanced quoting even though a stray quote cannot terminate a declaration,
  because a lone quote produces invalid CSS that swallows the rest of the block
  (`Model/Style/CssValueSanitizer.php:112-116`).

## Extension Points

### Preference

| For | Bound to | Area | Source |
|-----|----------|------|--------|
| `Muon\Core\Api\CssValueSanitizerInterface` | `Muon\Core\Model\Style\CssValueSanitizer` | global | `etc/di.xml` |

To change what counts as a safe value, override the preference in your own module's `etc/di.xml`:

```xml
<preference for="Muon\Core\Api\CssValueSanitizerInterface"
            type="Vendor\Module\Model\Style\MyCssValueSanitizer"/>
```

Because the interface is bound once and consumed by every Muon module with appearance settings, an
override changes what is considered safe **estate-wide**, not just for your module
(`Api/CssValueSanitizerInterface.php:27-28`). Widening an allow-list here re-opens the injection
surface the interface exists to close.

### Front-end asset

`view/frontend/web/js/vendor/accessible-menu-top-link-disclosure.iife.js` is a zero-dependency IIFE
that consuming modules reference directly from a layout file as `Muon_Core::js/vendor/…`. It is
deliberately not registered as a Breeze bundle component, so it loads unchanged on both Luma-shaped
and Breeze storefronts (`view/frontend/web/js/vendor/PROVENANCE.md`).

The committed file is byte-for-byte upstream. Verify before trusting it:

```bash
sha256sum view/frontend/web/js/vendor/accessible-menu-top-link-disclosure.iife.js
# c2193ce3708c5a15eb4039b17e30d32792a34eca814a81e793e3b58dcf5f1aed
```

Applying a local edit makes the checksum incomparable against the published artifact, at which point
provenance becomes an assertion rather than something a reviewer can check.
