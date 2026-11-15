# Changelog

All notable changes to the Malay (ms-MY) language pack are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Tags use `<Joomla 5 version>v<n>`; every release ships both the Joomla 5
and the Joomla 6 package.

## [5.4.10v1] - 2026-11-15

Packages: `pkg_ms-MY_j5.zip` 5.4.10.1 (Joomla 5.4.10) and `pkg_ms-MY_j6.zip` 6.2.0.1 (Joomla 6.2.0).

### Added
- All new strings for Joomla 5.4.10 and 6.2.0. The pack is at 100 % key coverage against `5.4-dev` and `6.2-dev`.
- Early translations for strings coming in Joomla 6.2.1: Smart Search ARIA label,
  Cassiopeia Extended, scheduler orphan filter, keyboard-shortcut timeout and the TOTP QR code alt text.
- `title` attributes on links in user action log, privacy and consent messages (Joomla 6.2 accessibility change).
- Joomla 6 specific overrides (`overrides/j6/`) for strings whose placeholders differ from Joomla 5,
  so the uninstall messages show the extension type and name, and accessible media labels show the media type.

### Fixed
- `JACTION_ADMIN` now reads "Konfigurasi ACL & Pilihan" (it was truncated to "Konfigurasi").
- Installation password message uses `%d` like en-GB.
- Consistent terminology across the pack: "pengatasan" for *override*, "penerangan" for *description*,
  "didaftar masuk" for *checked in*, "Daftar Masuk" for *Check-in*.
- Labels that added words not in the source (e.g. "Fail Audio" → "Audio", "Hari Relatif" → "Tarikh Relatif").

### Tooling
- New `build/check-translation.php` QA tool. It checks UTF-8/BOM, the strict `KEY="value"` format,
  duplicate keys, unescaped quotes and placeholder parity, and compares with official en-GB files.
- CI runs the QA tool on every change and compares with Joomla `5.4-dev` and `6.2-dev`. The weekly sync also covers the next minor branch.
- Releases include `SHA256SUMS.txt`.
- Added `CONTRIBUTING.md`, `GLOSSARY.md`, issue/PR templates, `.editorconfig` and `.gitattributes`.

## [5.4.9v2] - 2026-10-11
- Translation fixes for Joomla 5.4.9 and 6.1.4.

## [5.4.9v1] - 2026-10-11
- Malay language pack for Joomla 5.4.9 and 6.1.4.

## [5.4.8v1] - 2026-08-21
- Malay language pack for Joomla 5.4.8 and 6.1.3.

[5.4.10v1]: https://github.com/zakiabdullah/joomla-ms-MY/compare/5.4.9v2...5.4.10v1
[5.4.9v2]: https://github.com/zakiabdullah/joomla-ms-MY/compare/5.4.9v1...5.4.9v2
[5.4.9v1]: https://github.com/zakiabdullah/joomla-ms-MY/compare/5.4.8v1...5.4.9v1
[5.4.8v1]: https://github.com/zakiabdullah/joomla-ms-MY/releases/tag/5.4.8v1
