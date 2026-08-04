# Changelog

All notable changes to `Muon_Core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this package follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
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

No release: this is CI and metadata only, with no change to any shipped PHP, template or asset.

## [1.0.0] - 2026-08-03

### Added
- `Api\CssValueSanitizerInterface` and its allow-list implementation, extracted from
  `Muon_HeaderMenu` and `Muon_FooterMenu`, which each carried a copy that had already drifted.
- Vendored `accessible-menu` 4.4.0 `TopLinkDisclosureMenu` IIFE build, with licence and provenance
  record, extracted from `Muon_HeaderMenu` and `Muon_TopMenu`.

### Notes
- Consumers should depend on `CssValueSanitizerInterface`, not the concrete class. The interface is
  `@api`; overriding it changes what is considered safe estate-wide.
