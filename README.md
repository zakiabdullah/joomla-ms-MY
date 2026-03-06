# Pakej Bahasa Melayu (ms-MY) untuk Joomla! 5

[![Build Joomla Language Pack](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/build.yml/badge.svg)](https://github.com/zakiabdullah/joomla-ms-MY/actions/workflows/build.yml)
[![GitHub release](https://img.shields.io/github/v/release/zakiabdullah/joomla-ms-MY?color=green&include_prereleases&label=release&style=for-the-badge)](https://github.com/zakiabdullah/joomla-ms-MY/releases)

Pakej terjemahan Bahasa Melayu rasmi untuk Joomla! 5 CMS.

## Maklumat

| | |
|---|---|
| **Bahasa** | Bahasa Melayu (ms-MY) |
| **Tag Bahasa** | `ms-MY` / `ms_MY` |
| **Versi Joomla** | 5.4.4 |
| **Versi Pakej** | 5.4.4.1 |
| **PHP Minimum** | 8.1.0 |

## Pemasangan

### Melalui Pentadbir Joomla
1. Log masuk ke Pentadbir Joomla
2. Pergi ke **Sistem → Pasang → Bahasa**
3. Cari "Malay" dan pasang

### Muat Turun Manual
1. Muat turun fail ZIP terkini dari [Releases](https://github.com/zakiabdullah/joomla-ms-MY/releases)
2. Pergi ke **Sistem → Pasang → Sambungan**
3. Muat naik fail ZIP dan pasang
4. Aktifkan bahasa di **Sistem → Urus → Bahasa**

## Struktur Repositori

```
├── language/ms-MY/                  # Fail bahasa laman (site)
├── administrator/language/ms-MY/    # Fail bahasa pentadbir (admin)
├── api/language/ms-MY/              # Fail bahasa API
├── installation/language/ms-MY/     # Fail bahasa pemasangan
├── .github/workflows/               # GitHub Actions CI/CD
├── pkg_ms-MY.xml                    # Manifes pakej
├── script.php                       # Skrip pemasangan/kemaskini
├── update_ms-MY.xml                 # Pelayan kemaskini Joomla
├── crowdin.yml                      # Konfigurasi Crowdin
├── build.sh                         # Skrip pembinaan (Linux/macOS/CI)
└── build.ps1                        # Skrip pembinaan (Windows PowerShell)
```

## Pembinaan

### Windows (PowerShell)
```powershell
.\build.ps1
```

### Linux / macOS / CI
```bash
chmod +x build.sh
./build.sh
```

Fail output: `dist/ms-MY_joomla_lang_full_<version>.zip`

## Aliran Kerja CI/CD

### GitHub Actions
- **Push ke `main`**: Bina pakej secara automatik dan muat naik sebagai artifact
- **Tag baharu**: Bina pakej dan cipta GitHub Release secara automatik

### Cara Keluarkan Versi Baharu
```bash
git tag -a 5.4.4v1 -m "Release 5.4.4v1"
git push origin 5.4.4v1
```

### Crowdin
Fail `crowdin.yml` membolehkan penyegerakan terjemahan dengan [Crowdin](https://crowdin.com):
1. Cipta projek Crowdin
2. Tetapkan bahasa sumber: English (en-GB)
3. Tambah bahasa sasaran: Malay (ms-MY)
4. Sambungkan repositori GitHub melalui integrasi Crowdin

## Sumbangan

Sumbangan dialu-alukan! Sila buka [Issue](https://github.com/zakiabdullah/joomla-ms-MY/issues) atau hantar Pull Request.

## Lesen

GNU General Public License versi 2 atau lebih baharu; lihat [LICENSE.txt](https://www.gnu.org/licenses/gpl-2.0.html)