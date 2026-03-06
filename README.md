# Malay (ms-MY) Language Pack for Joomla! 6

[![Build Joomla Language Pack](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/build.yml/badge.svg)](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/build.yml)
[![GitHub release](https://img.shields.io/github/v/release/zakiabdullah/joomla-ms-MY?color=green&include_prereleases&label=release&style=for-the-badge)](https://github.com/zakiabdullah/joomla-ms-MY/releases)

Official Malay (Bahasa Melayu) translation pack for Joomla! 6 CMS.

## Information

| | |
|---|---|
| **Language** | Malay (ms-MY) |
| **Language Tag** | `ms-MY` / `ms_MY` |
| **Joomla Version** | 6.0.0 |
| **Package Version** | 6.0.0.1 |
| **Minimum PHP** | 8.2.0 |

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
├── build/                           # Build scripts (J!German-style)
│   ├── build.php                    # Package builder (CLI)
│   └── bump.php                     # Version bumper (CLI)
├── .github/workflows/               # GitHub Actions CI/CD
├── pkg_ms-MY.xml                    # Package manifest
└── script.php                       # Install/update script
```

## Building

### Version Bump
```bash
php build/bump.php -v 5.4.4 -l 1
```

### Build Language Pack
```bash
php build/build.php --lpackages --v
```

### Build from Specific Tag
```bash
php build/build.php --lpackages --v --tagversion "5.4.4v1"
```

Output: `build/tmp/packages/ms-MY_joomla_lang_full_<version>.zip`

## CI/CD Workflow

### GitHub Actions
- **Push to `j5`/`j6`**: Automatically builds the package and uploads as artifact
- **New tag**: Builds the package and creates a GitHub Release automatically

### Creating a New Release
```bash
# 1. Bump version
php build/bump.php -v 5.4.4 -l 1

# 2. Commit changes
git add -A
git commit -m "release 5.4.4v1"

# 3. Tag and push
git tag -a 5.4.4v1 -m "Release 5.4.4v1"
git push origin j5
git push origin 5.4.4v1
```

### Crowdin
The `crowdin.yml` file enables translation synchronization with [Crowdin](https://crowdin.com):
1. Create a Crowdin project
2. Set source language: English (en-GB)
3. Add target language: Malay (ms-MY)
4. Connect the GitHub repository via Crowdin integration

## Contributing

Contributions are welcome! Please open an [Issue](https://github.com/zakiabdullah/joomla-ms-MY/issues) or submit a Pull Request.

## License

GNU General Public License version 2 or later; see [LICENSE.txt](https://www.gnu.org/licenses/gpl-2.0.html)