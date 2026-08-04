# Changelog

All notable changes to `Muon_Core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this package follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.0] - 2026-08-04

### Added

- **`Model\Cache\Tag\ScopedCacheTags`** — derives an estate-wide cache tag and its scope-qualified
  variants from one base tag, supplied as a DI argument.

  A module that declares a single scope-less tag on every cacheable render, and purges by that same
  tag whatever scope was saved, evicts the full-page cache for the whole estate on any config save:
  PageCache folds block identities into `X-Magento-Tags`, so a `showInStore="1"` field changed on one
  store view purges all of them. `Muon_HeaderMenu` and `Muon_TopMenu` hit that independently and
  fixed it the same way — which is why the algorithm belongs here rather than in either of them.

  Consumed through a virtual type per module, each supplying its own base tag. The superset of the
  two implementations is kept, including `estateWide()`, which only one of them had.

  Note the **cache type itself is deliberately not shared**: its entire per-module difference is the
  `TYPE_IDENTIFIER` and `CACHE_TAG` constants, which are `@api` and identify the type in Cache
  Management. Magento's `TagScope` is already the shared base, and a further one would add a
  dependency without removing a line.

## [1.1.0] - 2026-08-04

### Added

- **Shared adminhtml edit-form buttons** — `Block\Adminhtml\Button\BackButton`, `SaveButton`
  and `SaveAndContinueButton`, plus the `AbstractButton` base holding the URL builder and JS escaper
  they share. Every Muon module with an admin form carried its own copies: five `BackButton`s across
  three modules, byte-identical apart from the namespace.

  `SaveButton` and `SaveAndContinueButton` take the form namespace (and, for save, a label) as DI
  arguments, so a consumer declares a virtual type rather than a subclass. Both are `@api`: consuming
  `ui_component` form XML references them by FQCN.

  Two behaviours were unified rather than carried forward as-is:

  - **The URL is escaped.** Four of the five `BackButton` copies interpolated `getUrl()` straight
    into a `location.href = '…'` literal; one escaped it. The escaping version is the one kept.
  - **Plain Save passes `params[0] = false`.** One copy passed `true` with no accompanying `back`
    value — a redirect flag with no destination. Magento core's own plain Save passes `false` and
    lets the controller decide (`Magento\Cms\Block\Adminhtml\Page\Edit\SaveButton`); `true` belongs
    to the save-and-continue family, which pairs it with `['back' => 'edit']`.

  These buttons are for forms on the `buttonAdapter` mechanism. A form still wired with the legacy
  `['button' => ['event' => 'save']]` + `form-role` attributes needs its own — that is different
  wiring, not a different label.

- CI now verifies the vendored `accessible-menu` bundle against the SHA-256 recorded in
  `PROVENANCE.md`, so a swapped blob is distinguishable from a legitimate bump. The package is in no
  manifest, so no SCA or Dependabot advisory would ever reach it. Ported from `Muon_TopMenu`, which
  dropped its own copy of the bundle in 2.2.0 — the guard moved here with the blob it protects.

- `extra.third-party-licenses` declaring the vendored `accessible-menu` build, moved here from
  `Muon_TopMenu`, whose copy of the blob it described is removed in 2.2.0. This package had no such
  entry, so without the move the licence metadata would have been lost rather than merely dangling.

### Changed
- `PROVENANCE.md` now explains that the duplication across `Muon_TopMenu` / `Muon_HeaderMenu` was
  deliberate **only until this package existed** — a leaf both can require without depending on each
  other — rather than presenting it as the standing arrangement.


## [1.0.0] - 2026-08-03

### Added
- `Api\CssValueSanitizerInterface` and its allow-list implementation, extracted from
  `Muon_HeaderMenu` and `Muon_FooterMenu`, which each carried a copy that had already drifted.
- Vendored `accessible-menu` 4.4.0 `TopLinkDisclosureMenu` IIFE build, with licence and provenance
  record, extracted from `Muon_HeaderMenu` and `Muon_TopMenu`.

### Notes
- Consumers should depend on `CssValueSanitizerInterface`, not the concrete class. The interface is
  `@api`; overriding it changes what is considered safe estate-wide.
