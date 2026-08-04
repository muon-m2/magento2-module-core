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

## Why this module owns the only copy

`Muon_TopMenu` and `Muon_HeaderMenu` both need this build, and both used to carry their own copy.
That duplication was deliberate at the time: the two modules are **mutually exclusive alternatives**
— a store view runs one or the other — so sharing the asset through either would have made one
depend on the module it exists to replace, and a store running only one of them would have had to
install its rival to get its navigation JavaScript.

`Muon_Core` removes that objection. It is a leaf: it depends on no other Muon package, so both
consumers can require it without depending on each other. As of `Muon_TopMenu` 2.2.0 this package
holds the only copy, and both modules load it as `Muon_Core::js/vendor/…`.

The gain is not the 33 KB. It is that a security bump to `accessible-menu` is now applied in one
place and verified in one place — the checksum step in this repository's CI is the guard, and it
moved here with the blob.

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
