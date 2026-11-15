# Malay (ms-MY) Language Pack for Joomla! 5 and 6

[![Build Joomla Language Pack](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/build.yml/badge.svg)](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/build.yml)
[![Translation QA](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/lint-ini.yml/badge.svg)](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/lint-ini.yml)
[![Sync Check](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/compare-translation.yml/badge.svg)](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/compare-translation.yml)
[![GitHub release](https://img.shields.io/github/v/release/zakiabdullah/joomla-ms-MY?color=green&include_prereleases&label=release&style=for-the-badge)](https://github.com/zakiabdullah/joomla-ms-MY/releases)

Official Malay (Bahasa Melayu) translation pack for Joomla! CMS.

## Information

| | |
|---|---|
| **Language** | Malay (ms-MY) |
| **Language Tag** | `ms-MY` / `ms_MY` |
| **Joomla Version** | 5.x and 6.x |
| **Package Version** | Config-driven (see `build/build.config.json`) |
| **Minimum PHP** | 8.1.0 |

## Active Target Versions

Current enabled targets in `build/build.config.json`:

| Target | Joomla Major | Package Version | Final Artifact |
|---|---:|---:|---|
| `j5` | 5 | 5.4.10.1 | `dist/pkg_ms-MY_j5.zip` |
| `j6` | 6 | 6.2.0.1 | `dist/pkg_ms-MY_j6.zip` |

One translation tree builds both packages. `j5` is the source branch, and
`j6` follows it. Strings whose value must differ between Joomla 5 and 6
(for example a placeholder that only exists in Joomla 6) live in
`overrides/j6/`, and `build.ps1` applies them to the Joomla 6 package only.

## Quality

Every change is checked automatically (see [CONTRIBUTING.md](CONTRIBUTING.md)):

- **Format:** UTF-8 without BOM, strict `KEY="value"` lines, upper-case keys,
  no duplicate keys, escaped quotes.
- **Parity:** every en-GB key from Joomla `5.4-dev` and `6.2-dev` is
  translated, and every `%s` / `%1$s` / `{token}` placeholder matches the source.
- **Terminology:** shared glossary and style guide in [GLOSSARY.md](GLOSSARY.md).

```bash
php build/check-translation.php                                   # format
php build/check-translation.php --ref=../joomla-cms --target=j6   # parity
```

## Installation

### Via Joomla Administrator
1. Log in to Joomla Administrator
2. Go to **System → Install → Languages**
3. Search for "Malay" and install

### Manual Download
1. Download the latest ZIP file from [Releases](https://github.com/zakiabdullah/joomla-ms-MY/releases)
2. Go to **System → Install → Extensions**
3. Upload the ZIP file and install
4. Activate the language at **System → Manage → Languages**

## Repository Structure

```
├── language/ms-MY/                  # Site frontend language files
├── administrator/language/ms-MY/    # Administrator language files
├── api/language/ms-MY/              # API language files
├── installation/language/ms-MY/     # Installation language files
├── overrides/j6/                    # Joomla 6 only values applied at build time
├── build/                           # Build config, QA tool and legacy PHP scripts
│   ├── build.config.json            # Dual-target build configuration
│   ├── check-translation.php        # Translation QA (format + parity)
│   ├── build.php                    # Package builder (CLI)
│   └── bump.php                     # Version bumper (CLI)
├── build.ps1                        # PowerShell dual-target build pipeline
├── .github/workflows/               # GitHub Actions CI/CD
├── CHANGELOG.md                     # Release history
├── CONTRIBUTING.md                  # How to contribute (Bahasa Melayu)
├── GLOSSARY.md                      # Terminology & style guide
├── pkg_ms-MY.xml                    # Package manifest
└── script.php                       # Install/update script
```

## Building

### PowerShell (Windows)
```powershell
# Build all enabled targets from build/build.config.json
./build.ps1

# Use a custom config file
./build.ps1 -ConfigPath build/build.config.json

# Clean generated folders only
./build.ps1 -CleanOnly

# Build without XML/zip validation checks
./build.ps1 -SkipValidation
```

Optional flags:
- `-ConfigPath` points to a JSON config with target versions and metadata
- `-CleanOnly` removes `build/joomla5`, `build/joomla6`, and `dist` then exits
- `-SkipValidation` skips XML parse and non-empty zip checks

Output:
- `build/joomla5` (staging + sub-packages)
- `build/joomla6` (staging + sub-packages)
- `dist/pkg_ms-MY_j5.zip`
- `dist/pkg_ms-MY_j6.zip`

### Legacy PHP Helpers
These scripts are still available if you need the old flow.

#### Version Bump
```bash
php build/bump.php -v 5.4.10 -l 1
```

#### Build Language Pack
```bash
php build/build.php --lpackages --v
```

#### Build from Specific Tag
```bash
php build/build.php --lpackages --v --tagversion "5.4.10v1"
```

Output: `build/tmp/packages/ms-MY_joomla_lang_full_<version>.zip`

## CI/CD Workflow

### GitHub Actions
- **Push / PR to `j5`/`j6`**: runs Translation QA, builds both packages and uploads them as artifacts.
- **New tag**: builds both packages and creates a GitHub Release with `SHA256SUMS.txt`.
- **Weekly sync check**: compares the translation with Joomla `5.4-dev`, `6.2-dev`
  and the next minor branch, and reports new strings to translate.

### Creating a New Release
```bash
# 1. Bump version
php build/bump.php -v 5.4.10 -l 1

# 2. Update CHANGELOG.md, run ./build.ps1, then commit
git add -A
git commit -m "release 5.4.10v1"

# 3. Tag and push
git tag -a 5.4.10v1 -m "Release 5.4.10v1"
git push origin j5
git push origin 5.4.10v1

```

## Release Notes Template

Use [RELEASE_NOTES_TEMPLATE.md](RELEASE_NOTES_TEMPLATE.md) to prepare release notes for both `j5` and `j6` artifacts.

## Contributing

Contributions are welcome! Read [CONTRIBUTING.md](CONTRIBUTING.md) and the
[glossary](GLOSSARY.md), then open an [Issue](https://github.com/zakiabdullah/joomla-ms-MY/issues)
or submit a Pull Request. Report security issues privately as described in [SECURITY.md](SECURITY.md).

## License

GNU General Public License version 2 or later; see [LICENSE.txt](https://www.gnu.org/licenses/gpl-2.0.html)
