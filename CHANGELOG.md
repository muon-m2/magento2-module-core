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

### Changed
- `PROVENANCE.md` no longer describes the duplication across `Muon_TopMenu` / `Muon_HeaderMenu` as
  deliberate. That rationale predated this package: both consumers now load the single copy here.

No release: this is a CI and documentation change with no effect on the installed package.

## [1.0.0] - 2026-08-03

### Added
- `Api\CssValueSanitizerInterface` and its allow-list implementation, extracted from
  `Muon_HeaderMenu` and `Muon_FooterMenu`, which each carried a copy that had already drifted.
- Vendored `accessible-menu` 4.4.0 `TopLinkDisclosureMenu` IIFE build, with licence and provenance
  record, extracted from `Muon_HeaderMenu` and `Muon_TopMenu`.

### Notes
- Consumers should depend on `CssValueSanitizerInterface`, not the concrete class. The interface is
  `@api`; overriding it changes what is considered safe estate-wide.
