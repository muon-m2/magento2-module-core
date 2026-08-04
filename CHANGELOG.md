# Changelog

All notable changes to `Muon_Core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this package follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-08-03

### Added
- `Api\CssValueSanitizerInterface` and its allow-list implementation, extracted from
  `Muon_HeaderMenu` and `Muon_FooterMenu`, which each carried a copy that had already drifted.
- Vendored `accessible-menu` 4.4.0 `TopLinkDisclosureMenu` IIFE build, with licence and provenance
  record, extracted from `Muon_HeaderMenu` and `Muon_TopMenu`.

### Notes
- Consumers should depend on `CssValueSanitizerInterface`, not the concrete class. The interface is
  `@api`; overriding it changes what is considered safe estate-wide.
