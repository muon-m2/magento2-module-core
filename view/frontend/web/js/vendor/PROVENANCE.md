# Vendored third-party assets

Provenance record for everything in this directory, so a reviewer can prove the committed blob is the
upstream build rather than a locally modified one.

## `accessible-menu-top-link-disclosure.iife.js`

| | |
|---|---|
| Package | [`accessible-menu`](https://www.npmjs.com/package/accessible-menu) |
| Version | **4.4.0** |
| Upstream | https://github.com/NickDJM/accessible-menu |
| Build | `TopLinkDisclosureMenu` + `Treeview`, IIFE bundle |
| Licence | ISC — © 2024 Nick Milton, full text in `accessible-menu-LICENSE.txt` |
| Size | 33,330 bytes |
| SHA-256 | `c2193ce3708c5a15eb4039b17e30d32792a34eca814a81e793e3b58dcf5f1aed` |

**The file is committed byte-for-byte as upstream ships it — no banner, no reformatting.** The moment
a local edit is applied the checksum stops being comparable against the published artifact and
provenance becomes an assertion rather than something you can check.

Verify with:

```bash
sha256sum view/frontend/web/js/vendor/accessible-menu-top-link-disclosure.iife.js
```

## Why this module carries its own copy

`Muon_TopMenu` vendors the identical build. That duplication is deliberate, not an oversight: the two
modules are **mutually exclusive alternatives** — a store view runs one or the other — and sharing the
asset would make `Muon_HeaderMenu` depend on a module it exists to replace. A store running only this
module would then have to install the other to get its navigation JavaScript.

The cost is one 33 KB file duplicated on disk in an install that has both modules present, and only
one of the two is ever loaded into a page. The alternative — extracting a third `Muon_*` package
purely to hold one vendored blob — buys a shared 33 KB at the price of a third module to version,
publish and keep in sync across two consumers. Revisit if a third module ever needs the same library.

## Why it is loaded as a plain head script

The bundle is a zero-dependency IIFE, declared in `muon_headermenu_enabled.xml` with `defer`. It is
deliberately **not** registered as a Breeze bundle component: Breeze's `Block\Js` only *adds* assets —
it strips nothing beyond its own explicit remove list — so a plain head script loads unchanged on both
a Luma-shaped and a Breeze storefront. That is what lets `Muon_HeaderMenuBreeze` stay PHP-free and
carry styling only.

`defer` matters: parsing must not block on ~33 KB of script before first paint. It is safe here
because `headermenu.js` does its own work on `DOMContentLoaded` and nothing inline consumes the
library. `defer` also preserves execution order, so the vendored bundle still runs before the
initialiser.
