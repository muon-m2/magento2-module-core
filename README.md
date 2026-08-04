# Muon_Core

Shared utilities and vendored front-end assets used by more than one Muon module. It declares no
`<sequence>` and depends on no other Muon package — it is a leaf, so anything may require it without
creating a dependency cycle (`etc/module.xml`).

## Requirements

- Magento Open Source or Adobe Commerce (see `composer.json` for version constraints)
- PHP (see `composer.json` for version constraints)

## Installation

Enable the module and run setup upgrade:

    bin/magento module:enable Muon_Core
    bin/magento setup:upgrade
    bin/magento cache:flush

## Dependencies

| Package | Constraint |
|---------|-----------|
| `php` | `~8.3.0 \|\| ~8.4.0 \|\| ~8.5.0` |
| `magento/framework` | `^103.0.9` |

Source: `composer.json`

## Features

- **CSS value sanitisation** — `Muon\Core\Api\CssValueSanitizerInterface` reduces merchant-entered
  appearance values (colours, pixel lengths, font families, font weights, keyword choices) to tokens
  that are safe to emit inside a style declaration. Every method works by allow-list and returns
  `null` for anything it cannot positively recognise (`Api/CssValueSanitizerInterface.php`).
- **Default binding** — the interface is bound to `Muon\Core\Model\Style\CssValueSanitizer` via a DI
  preference, so consumers type-hint the interface and receive the allow-list implementation
  (`etc/di.xml`).
- **Vendored `accessible-menu` bundle** — an IIFE build of `accessible-menu` 4.4.0
  (`TopLinkDisclosureMenu` + `Treeview`) shipped byte-for-byte as upstream publishes it, with a
  checksum and licence record in `view/frontend/web/js/vendor/PROVENANCE.md`.

## Public API

| Interface | Description |
|-----------|-------------|
| `Muon\Core\Api\CssValueSanitizerInterface` | Reduces merchant-entered appearance values to tokens that are safe inside a style declaration. Documented as a security boundary: an override changes what is considered safe estate-wide. |

Source: `Api/CssValueSanitizerInterface.php`

## Documentation

- [Technical reference](docs/technical-reference.md) — full surface inventory with source citations
- [Developer guide](docs/developer-guide.md) — how to consume and extend the sanitiser
