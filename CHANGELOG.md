# Changelog

All notable changes to `Muon_Core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this package follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.5.0] - 2026-08-07

### Added

- **Audience and schedule filtering**, shared by every Muon module whose entities can be shown to
  some visitors and not others. Three new `@api` contracts:
  - `Api\Data\FilterableInterface` — the four filters (login state, customer group, device,
    schedule window) plus their allowed values. Implemented by an entity that wants the shared
    resolver.
  - `Api\VisitorContextInterface` — store, customer group and login state for the current request,
    plus a cache-key suffix. Read from `App\Http\Context` and never from the customer session: the
    session is emptied by `DepersonalizePlugin` on cacheable pages, and only HTTP-context values
    reach `Context::getVaryString()`, which keys the full-page cache.
  - `Api\VisibilityResolverInterface` — one decision, one visitor, one instant.
- `Model\VisibilityResolver` — the default implementation. Filters compose with AND and **every rule
  fails closed**: an unrecognised visibility value, an unparseable schedule bound, or a
  never-loaded group allow-list all hide the subject. Something that should have appeared and did
  not is a support ticket; something that should have been hidden and appeared is a disclosure.
- `Model\VisitorContext` — the default implementation, memoised per request and reset between them.
- `Model\Validator\ScheduleWindow` — `validate()` for save-time messages and `contains()` for
  render-time decisions. The two halves disagree on an unparseable bound on purpose: the first
  reports it, the second treats it as closed.
- `Model\Clock` — `nowUtc()`, the single place the store's timezone is converted away.

### Changed

- **`magento/module-customer` is now a hard dependency.** `VisitorContext` reads the group and
  login-state keys that `Magento\Customer\Model\Context` defines. It is base product, present in
  every Magento install, so this does not narrow where the package can be used — but it is a real
  widening of a shared leaf module's footprint and is recorded here rather than left to be
  discovered.

### Notes

- `Muon_HeaderMenu` carries its own copies of these classes and is unaffected by this release. It
  converges onto these contracts in a later version; until then the two sets coexist in different
  namespaces and resolve independently.

## [1.4.0] - 2026-08-04

### Added

- **Per-store-view caption overrides**, shared by every Muon module whose entities render a
  merchant-authored caption. Three new `@api` contracts:
  - `Api\Data\ScopedCaptionInterface` — one store view's caption, serialisable into a REST payload.
  - `Api\CaptionStorageInterface` — bulk read and diffing write of an entity's overrides. No table
    name appears in any signature; an implementation is bound per entity through a virtual type
    carrying its table and columns, the same way `ScopedCacheTags` is bound with its base tag.
    Deliberately has **no default preference**, so a missing virtual type is a wiring error rather
    than a query-time failure with empty identifiers.
  - `Api\CaptionResolverInterface` — chooses the override when present, otherwise returns the
    entity's own caption **verbatim**. The default is never passed through the translator: a menu
    caption is merchant content, and `__()` would let an unrelated i18n entry silently rewrite a
    caption whose text collides with a translation key.
- `Model\Caption\CaptionValidator` — store view must exist and be non-zero, one caption per store
  view, 255-character limit matching the column. Errors are accumulated and returned rather than
  thrown, because the two consuming modules disagree on style and the list satisfies both.
- `Model\Caption\CaptionListConverter` — converts between the DTO list the API speaks and the plain
  map storage writes, in both directions, preserving the `null` that means "not supplied".

### Fixed

- **Declared `magento/module-store` and `magento/module-backend`, which the code has always used
  but `composer.json` never listed.** `Model\Cache\Tag\ScopedCacheTags` imports
  `Magento\Store\Model\StoreManagerInterface` and the adminhtml button blocks import
  `Magento\Backend`; both were undeclared, so a consumer installing this package alone could resolve
  a dependency set that cannot autoload it. Found while adding the caption machinery.

## [1.3.0] - 2026-08-04

### Changed

- **Relicensed from proprietary to MIT, so the repository can be made public.** This package is generic
  infrastructure — a CSS allow-list sanitiser, adminhtml button blocks, a scope-qualified cache-tag
  helper and a vendored ISC bundle — with no business logic in it.

  The practical driver is dependency resolution: consumers' CI could not install a private package
  without a credential, which had already forced `Muon_TopMenu` to strip this dependency before
  running its unit tests. That workaround stopped being viable the moment a consumer referenced a
  class from here rather than only an asset.

  Matches the other public `muon/*` repositories, which are MIT. The vendored `accessible-menu`
  bundle remains ISC and is unaffected — its notice ships alongside it and is declared in
  `extra.third-party-licenses`.

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
